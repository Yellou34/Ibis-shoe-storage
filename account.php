<?php
// Anyone signed in can change their own password.
require __DIR__ . '/includes/bootstrap.php';
$me  = require_login();
$pdo = db();
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $cur  = (string)($_POST['current'] ?? '');
    $new  = (string)($_POST['password'] ?? '');
    $new2 = (string)($_POST['password2'] ?? '');

    $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([(int)$me['id']]);
    $hash = (string)$st->fetchColumn();

    if (!password_verify($cur, $hash)) $errors[] = 'Your current password is not right.';
    if (strlen($new) < 8) $errors[] = 'The new password needs at least 8 characters.';
    if ($new !== $new2) $errors[] = 'The two new passwords do not match.';
    if (!$errors) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), (int)$me['id']]);
        session_regenerate_id(true);
        flash('Your password was changed.');
        redirect('account.php');
    }
}

page_header('My account', 'account');
?>
<div class="head"><div>
    <h1 class="title">My account</h1>
    <p class="lede"><?= e($me['name']) ?> &middot; <?= e($me['email']) ?> &middot; <?= e($me['role']) ?></p>
</div></div>
<?php foreach ($errors as $m): ?><p class="note bad" role="alert"><?= e($m) ?></p><?php endforeach; ?>
<form method="post" class="panel stack">
    <?= csrf_field() ?>
    <h2>Change password</h2>
    <div class="field"><label for="current">Current password</label>
        <input id="current" name="current" type="password" required autocomplete="current-password"></div>
    <div class="field"><label for="password">New password (8 or more characters)</label>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
    <div class="field"><label for="password2">Repeat new password</label>
        <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password"></div>
    <div class="actions-row"><button class="btn primary" type="submit">Change password</button></div>
</form>
<?php page_footer();
