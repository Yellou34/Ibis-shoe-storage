<?php
function page_header(string $title, string $active = '', bool $bare = false): void
{
    $u = current_user();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<meta name="robots" content="noindex,nofollow">';
    echo '<title>' . e($title . ' | ' . APP_NAME) . '</title>';
    echo '<link rel="stylesheet" href="assets/style.css"></head><body>';

    if (!$bare && $u) {
        $links = ['index.php' => ['stock', 'Stock']];
        if (has_role('staff')) {
            $links['add.php'] = ['add', 'Add shoes'];
        }
        $links['history.php'] = ['history', 'History'];
        if (has_role('admin')) {
            $links['users.php'] = ['users', 'People'];
        }
        $links['account.php'] = ['account', 'My account'];

        echo '<header class="bar"><div class="bar-in">';
        echo '<a class="brandmark" href="index.php">' . e(APP_NAME) . '</a><nav aria-label="Main">';
        foreach ($links as $href => [$key, $label]) {
            $cur = $active === $key ? ' aria-current="page"' : '';
            echo '<a href="' . e($href) . '"' . $cur . '>' . e($label) . '</a>';
        }
        echo '</nav><form method="post" action="logout.php" class="who">' . csrf_field();
        echo '<span>' . e($u['name']) . ' <small>' . e($u['role']) . '</small></span>';
        echo '<button class="btn small" type="submit">Sign out</button></form>';
        echo '</div></header>';
    }

    echo '<div class="wrap' . ($bare ? ' narrow' : '') . '">';
    foreach (pull_flash() as [$type, $msg]) {
        echo '<p class="note ' . e($type) . '" role="status">' . e($msg) . '</p>';
    }
}

function page_footer(): void
{
    echo '</div><script src="assets/app.js" defer></script></body></html>';
}
