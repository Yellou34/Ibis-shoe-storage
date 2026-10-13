<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('This site needs PHP 8.0 or newer.');
}

date_default_timezone_set(TIMEZONE);
error_reporting(E_ALL);
ini_set('display_errors', SHOW_ERRORS ? '1' : '0');
ini_set('log_errors', '1');

const UPLOAD_DIR = __DIR__ . '/../uploads';
const ROLE_RANK  = ['viewer' => 1, 'staff' => 2, 'admin' => 3];

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ---------- small helpers ----------

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function get_str(string $key): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

function post_str(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function pull_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(400);
        exit('Your session expired. Go back, reload the page and try again.');
    }
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }
    csrf_check();
}

// ---------- database ----------

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = DB_DSN !== ''
        ? DB_DSN
        : 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Cannot connect to the database. Check the settings in includes/config.php.');
    }
    return $pdo;
}

// ---------- people and roles ----------

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    if (!empty($_SESSION['uid'])) {
        $st = db()->prepare('SELECT id, name, email, role, active FROM users WHERE id = ?');
        $st->execute([(int)$_SESSION['uid']]);
        $row = $st->fetch();
        if ($row && (int)$row['active'] === 1) {
            $user = $row;
        } else {
            unset($_SESSION['uid']);
        }
    }
    return $user;
}

function has_role(string $min): bool
{
    $u = current_user();
    return $u !== null && ROLE_RANK[$u['role']] >= ROLE_RANK[$min];
}

function require_login(string $min = 'viewer'): array
{
    $u = current_user();
    if ($u === null) {
        redirect('login.php');
    }
    if (ROLE_RANK[$u['role']] < ROLE_RANK[$min]) {
        http_response_code(403);
        page_header('Not allowed');
        echo '<p class="note bad">Your account does not have permission to do that.</p>';
        echo '<p><a class="btn" href="index.php">Back to stock</a></p>';
        page_footer();
        exit;
    }
    return $u;
}

// ---------- shoe helpers ----------

function fmt_size($v): string
{
    return rtrim(rtrim(number_format((float)$v, 1, '.', ''), '0'), '.');
}

function parse_size(string $s): ?float
{
    $s = str_replace(',', '.', trim($s));
    if (!preg_match('/^\d{1,2}(\.\d)?$/', $s)) {
        return null;
    }
    $f = round((float)$s, 1);
    return ($f > 0 && $f < 100) ? $f : null;
}

function size_param(float $size): string
{
    return number_format($size, 1, '.', '');
}

function nm(array $s): string
{
    $colour = (string)($s['colour'] ?? '');
    return $s['brand'] . ($colour !== '' ? ' (' . $colour . ')' : '');
}

function get_shoe(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM shoes WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function get_sizes(int $shoeId): array
{
    $st = db()->prepare('SELECT id, size, quantity FROM shoe_sizes WHERE shoe_id = ? ORDER BY size');
    $st->execute([$shoeId]);
    return $st->fetchAll();
}

function total_pairs(array $sizes): int
{
    $t = 0;
    foreach ($sizes as $z) {
        $t += (int)$z['quantity'];
    }
    return $t;
}

/** Add pairs to a size, creating the size row when it is new. */
function add_pairs(int $shoeId, float $size, int $qty): void
{
    $pdo = db();
    $sz  = size_param($size);
    $up  = $pdo->prepare('UPDATE shoe_sizes SET quantity = quantity + ? WHERE shoe_id = ? AND size = ?');
    $up->execute([$qty, $shoeId, $sz]);
    if ($up->rowCount() === 0) {
        try {
            $pdo->prepare('INSERT INTO shoe_sizes (shoe_id, size, quantity) VALUES (?, ?, ?)')
                ->execute([$shoeId, $sz, $qty]);
        } catch (PDOException $e) {
            // Someone else created the size a moment ago: add to theirs.
            $up->execute([$qty, $shoeId, $sz]);
        }
    }
}

function log_stock(?int $shoeId, string $label, ?float $size, int $change, ?string $note = null): void
{
    $u = current_user();
    db()->prepare('INSERT INTO stock_log (shoe_id, label, size, change_qty, note, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            $shoeId,
            mb_substr($label, 0, 140),
            $size === null ? null : size_param($size),
            $change,
            $note,
            $u ? (int)$u['id'] : null,
            now(),
        ]);
}

function find_shoe_by_serial(string $serial, int $exceptId = 0): ?array
{
    if ($serial === '') {
        return null;
    }
    $st = db()->prepare('SELECT * FROM shoes WHERE LOWER(serial_code) = LOWER(?) AND id <> ?');
    $st->execute([$serial, $exceptId]);
    $row = $st->fetch();
    return $row ?: null;
}

function find_shoe_by_brand_colour(string $brand, string $colour, int $exceptId = 0): ?array
{
    $st = db()->prepare('SELECT * FROM shoes WHERE LOWER(brand) = LOWER(?) AND LOWER(colour) = LOWER(?) AND id <> ?');
    $st->execute([$brand, $colour, $exceptId]);
    $row = $st->fetch();
    return $row ?: null;
}

function all_brands(): array
{
    return db()->query('SELECT DISTINCT brand FROM shoes ORDER BY brand')->fetchAll(PDO::FETCH_COLUMN);
}

/** Keep only a safe query string, used to return to the same filtered page. */
function safe_back(string $q): string
{
    $q = substr($q, 0, 200);
    return preg_match('/^[A-Za-z0-9=&%_.+\-]*$/', $q) ? $q : '';
}

require __DIR__ . '/photos.php';
require __DIR__ . '/layout.php';
