<?php
// Adds or removes one pair of a size (the + and - buttons on the stock page).
require __DIR__ . '/includes/bootstrap.php';
require_login('staff');
require_post();

$id    = (int)($_POST['id'] ?? 0);
$size  = parse_size(post_str('size'));
$delta = (int)($_POST['delta'] ?? 0);
$back  = safe_back(post_str('back'));
$to    = 'index.php' . ($back !== '' ? '?' . $back : '');

$shoe = $id ? get_shoe($id) : null;
if (!$shoe || $size === null || !in_array($delta, [-1, 1], true)) {
    flash('That change was not valid.', 'bad');
    redirect($to);
}

// One atomic statement: it never lets a count go below zero, even if two people click at once.
$st = db()->prepare('UPDATE shoe_sizes SET quantity = quantity + ? WHERE shoe_id = ? AND size = ? AND quantity + ? >= 0');
$st->execute([$delta, $id, size_param($size), $delta]);

if ($st->rowCount() === 0) {
    flash('No pairs left in size ' . fmt_size($size) . ' of ' . nm($shoe) . '.', 'bad');
} else {
    log_stock($id, nm($shoe), $size, $delta, $delta > 0 ? 'Added one pair' : 'Removed one pair');
    db()->prepare('UPDATE shoes SET updated_at = ? WHERE id = ?')->execute([now(), $id]);
}
redirect($to . '#shoe-' . $id);
