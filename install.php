<?php
// One-time setup: creates the first administrator.
// It stops working as soon as any account exists. Delete this file after setup.
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
if ($count > 0) {
    redirect('login.php');
}

$errors = [];
$f = ['name' => '', 'email' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $f['name']  = post_str('name');
    $f['email'] = strtolower(post_str('email'));
    $pass  = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');

    if ($f['name'] === '' || mb_strlen($f['name']) > 100) $errors[] = 'Enter your name.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || strlen($f['email']) > 190) $errors[] = 'Enter a valid email address.';
    if (strlen($pass) < 8) $errors[] = 'The password needs at least 8 characters.';
    if ($pass !== $pass2) $errors[] = 'The two passwords do not match.';

    if (!$errors) {
        $pdo->prepare('INSERT INTO users (name, email, password_hash, role, active, created_at) VALUES (?, ?, ?, ?, 1, ?)')
            ->execute([$f['name'], $f['email'], password_hash($pass, PASSWORD_DEFAULT), 'admin', now()]);
        flash('Administrator account created. Sign in below, then delete install.php from the server.');
        redirect('login.php');
    }
}

page_header('First-time setup', '', true);
?>
<h1 class="title">First-time setup</h1>
<p class="lede">Create the administrator account. You can add other people afterwards.</p>
<?php foreach ($errors as $m): ?><p class="note bad" role="alert"><?= e($m) ?></p><?php endforeach; ?>
<form method="post" class="panel stack">
    <?= csrf_field() ?>
    <div class="field"><label for="name">Your name</label>
        <input id="name" name="name" required maxlength="100" value="<?= e($f['name']) ?>"></div>
    <div class="field"><label for="email">Email</label>
        <input id="email" name="email" type="email" required value="<?= e($f['email']) ?>"></div>
    <div class="field"><label for="password">Password (8 or more characters)</label>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
    <div class="field"><label for="password2">Repeat password</label>
        <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password"></div>
    <button class="btn primary" type="submit">Create administrator</button>
</form>
<?php page_footer();
