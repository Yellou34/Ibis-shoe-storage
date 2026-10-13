<?php
require __DIR__ . '/includes/bootstrap.php';

$pdo = db();
try {
    $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
} catch (PDOException $e) {
    page_header('Setup needed', '', true);
    echo '<h1 class="title">Database not set up</h1>';
    echo '<p class="note bad">The tables are missing. Import <code>sql/database.sql</code> first, then reload this page.</p>';
    page_footer();
    exit;
}
if ($count === 0) {
    redirect('install.php');
}
if (current_user()) {
    redirect('index.php');
}

$error = '';
$email = '';
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $email = strtolower(post_str('email'));
    $pass  = (string)($_POST['password'] ?? '');

    $pdo->prepare('DELETE FROM login_attempts WHERE created_at < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?');
    $st->execute([$ip, date('Y-m-d H:i:s', time() - 600)]);

    if ((int)$st->fetchColumn() >= 8) {
        $error = 'Too many failed attempts. Wait 10 minutes and try again.';
    } else {
        $st = $pdo->prepare('SELECT id, password_hash, active FROM users WHERE LOWER(email) = ?');
        $st->execute([$email]);
        $row = $st->fetch();
        // Check a dummy hash when the email is unknown so both cases take similar time.
        $hash = $row['password_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuK1oPz3dK4Oe5v5oO5p7mJ0YyV9c1uRa';
        $ok = password_verify($pass, $hash) && $row && (int)$row['active'] === 1;

        if ($ok) {
            $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$row['id'];
            redirect('index.php');
        }
        $pdo->prepare('INSERT INTO login_attempts (ip, created_at) VALUES (?, ?)')->execute([$ip, now()]);
        $error = 'That email and password do not match an active account.';
    }
}

page_header('Sign in', '', true);
?>
<h1 class="title"><?= e(APP_NAME) ?></h1>
<p class="lede">Sign in to see and update the stock.</p>
<?php if ($error): ?><p class="note bad" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="panel stack">
    <?= csrf_field() ?>
    <div class="field"><label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="username" required value="<?= e($email) ?>"></div>
    <div class="field"><label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required></div>
    <button class="btn primary" type="submit">Sign in</button>
</form>
<p class="help">Accounts are created by an administrator. Ask them if you need access.</p>
<?php page_footer();
