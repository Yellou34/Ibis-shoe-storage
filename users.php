<?php
// Administrators add people, choose what they may do, and switch accounts off.
require __DIR__ . '/includes/bootstrap.php';
$me  = require_login('admin');
$pdo = db();
$errors = [];
$form = ['name' => '', 'email' => '', 'role' => 'staff'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $do = post_str('do');

    if ($do === 'create') {
        $form['name']  = post_str('name');
        $form['email'] = strtolower(post_str('email'));
        $form['role']  = post_str('role');
        $pass = (string)($_POST['password'] ?? '');

        if ($form['name'] === '' || mb_strlen($form['name']) > 100) $errors[] = 'Enter the person\'s name.';
        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 190) $errors[] = 'Enter a valid email address.';
        if (!isset(ROLE_RANK[$form['role']])) $errors[] = 'Choose a role.';
        if (strlen($pass) < 8) $errors[] = 'The password needs at least 8 characters.';
        if (!$errors) {
            $st = $pdo->prepare('SELECT 1 FROM users WHERE LOWER(email) = ?');
            $st->execute([$form['email']]);
            if ($st->fetch()) $errors[] = 'That email already has an account.';
        }
        if (!$errors) {
            $pdo->prepare('INSERT INTO users (name, email, password_hash, role, active, created_at) VALUES (?, ?, ?, ?, 1, ?)')
                ->execute([$form['name'], $form['email'], password_hash($pass, PASSWORD_DEFAULT), $form['role'], now()]);
            flash('Account created for ' . $form['name'] . '. Give them their email and the password you set.');
            redirect('users.php');
        }
    } elseif ($do === 'update' || $do === 'password') {
        $uid = (int)($_POST['uid'] ?? 0);
        $st = $pdo->prepare('SELECT id, name FROM users WHERE id = ?');
        $st->execute([$uid]);
        $target = $st->fetch();
        if (!$target) {
            $errors[] = 'That person no longer exists.';
        } elseif ($do === 'update') {
            if ($uid === (int)$me['id']) {
                $errors[] = 'You cannot change your own role or switch yourself off.';
            } else {
                $role = post_str('role');
                $active = post_str('active') === '1' ? 1 : 0;
                if (!isset(ROLE_RANK[$role])) {
                    $errors[] = 'Choose a role.';
                } else {
                    $pdo->prepare('UPDATE users SET role = ?, active = ? WHERE id = ?')->execute([$role, $active, $uid]);
                    flash('Updated ' . $target['name'] . '.');
                    redirect('users.php');
                }
            }
        } else {
            $pass = (string)($_POST['password'] ?? '');
            if (strlen($pass) < 8) {
                $errors[] = 'The new password needs at least 8 characters.';
            } else {
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), $uid]);
                flash('New password set for ' . $target['name'] . '.');
                redirect('users.php');
            }
        }
    }
}

$people = $pdo->query('SELECT id, name, email, role, active, created_at FROM users ORDER BY name')->fetchAll();
page_header('People', 'users');
?>
<div class="head"><div>
    <h1 class="title">People</h1>
    <p class="lede">Only people listed here can sign in.</p>
</div></div>
<?php foreach ($errors as $m): ?><p class="note bad" role="alert"><?= e($m) ?></p><?php endforeach; ?>

<section class="panel stack">
    <h2>Add a person</h2>
    <form method="post" class="stack">
        <?= csrf_field() ?><input type="hidden" name="do" value="create">
        <div class="fields">
            <div class="field"><label for="n-name">Name</label>
                <input id="n-name" name="name" required maxlength="100" value="<?= e($form['name']) ?>"></div>
            <div class="field"><label for="n-email">Email</label>
                <input id="n-email" name="email" type="email" required value="<?= e($form['email']) ?>"></div>
            <div class="field"><label for="n-role">Can</label>
                <select id="n-role" name="role">
                    <option value="viewer"<?= $form['role'] === 'viewer' ? ' selected' : '' ?>>Look only (viewer)</option>
                    <option value="staff"<?= $form['role'] === 'staff' ? ' selected' : '' ?>>Add and change stock (staff)</option>
                    <option value="admin"<?= $form['role'] === 'admin' ? ' selected' : '' ?>>Do everything (admin)</option>
                </select></div>
            <div class="field"><label for="n-pass">Starting password</label>
                <input id="n-pass" name="password" type="text" required minlength="8" autocomplete="off"></div>
        </div>
        <p class="help">Tell them the password in person. They can change it under My account.</p>
        <div class="actions-row"><button class="btn primary" type="submit">Add person</button></div>
    </form>
</section>

<section class="stack">
<?php foreach ($people as $p): $self = (int)$p['id'] === (int)$me['id']; ?>
    <article class="panel person">
        <div class="who">
            <strong><?= e($p['name']) ?></strong><?= $self ? ' <small>(you)</small>' : '' ?>
            <div class="colour"><?= e($p['email']) ?></div>
            <?php if (!(int)$p['active']): ?><span class="flag out">Switched off</span><?php endif; ?>
        </div>
        <form method="post" class="inline">
            <?= csrf_field() ?><input type="hidden" name="do" value="update"><input type="hidden" name="uid" value="<?= (int)$p['id'] ?>">
            <label class="sr" for="r<?= (int)$p['id'] ?>">Role for <?= e($p['name']) ?></label>
            <select id="r<?= (int)$p['id'] ?>" name="role"<?= $self ? ' disabled' : '' ?>>
                <?php foreach (['viewer', 'staff', 'admin'] as $r): ?>
                    <option value="<?= $r ?>"<?= $p['role'] === $r ? ' selected' : '' ?>><?= ucfirst($r) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="sr" for="a<?= (int)$p['id'] ?>">Status for <?= e($p['name']) ?></label>
            <select id="a<?= (int)$p['id'] ?>" name="active"<?= $self ? ' disabled' : '' ?>>
                <option value="1"<?= (int)$p['active'] ? ' selected' : '' ?>>Can sign in</option>
                <option value="0"<?= !(int)$p['active'] ? ' selected' : '' ?>>Switched off</option>
            </select>
            <?php if (!$self): ?><button class="btn small" type="submit">Save</button><?php endif; ?>
        </form>
        <form method="post" class="inline">
            <?= csrf_field() ?><input type="hidden" name="do" value="password"><input type="hidden" name="uid" value="<?= (int)$p['id'] ?>">
            <label class="sr" for="p<?= (int)$p['id'] ?>">New password for <?= e($p['name']) ?></label>
            <input id="p<?= (int)$p['id'] ?>" name="password" type="text" minlength="8" required autocomplete="off" placeholder="New password">
            <button class="btn small" type="submit">Set password</button>
        </form>
    </article>
<?php endforeach; ?>
</section>
<?php page_footer();
