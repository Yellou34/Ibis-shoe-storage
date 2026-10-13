<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$rows = db()->query(
    'SELECT l.created_at, l.label, l.size, l.change_qty, l.note, u.name AS who
     FROM stock_log l LEFT JOIN users u ON u.id = l.user_id
     ORDER BY l.id DESC LIMIT 200'
)->fetchAll();

page_header('History', 'history');
?>
<div class="head"><div>
    <h1 class="title">History</h1>
    <p class="lede">The last 200 changes to the counts.</p>
</div></div>

<?php if (!$rows): ?>
    <div class="empty"><h2>No changes yet</h2><p>Every pair added or removed is listed here with who did it.</p></div>
<?php else: ?>
<div class="tablewrap">
<table class="log">
    <thead><tr><th>When</th><th>Shoe</th><th>Size</th><th class="num">Change</th><th>Note</th><th>By</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $c = (int)$r['change_qty']; ?>
        <tr>
            <td><?= e(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
            <td><?= e($r['label']) ?></td>
            <td><?= $r['size'] === null ? '' : e(fmt_size($r['size'])) ?></td>
            <td class="num <?= $c < 0 ? 'neg' : 'pos' ?>"><?= $c > 0 ? '+' . $c : $c ?></td>
            <td><?= e($r['note'] ?? '') ?></td>
            <td><?= e($r['who'] ?? 'Removed account') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php page_footer();
