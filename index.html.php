<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
$canEdit = has_role('staff');
$pdo = db();

$view  = get_str('view') === 'size' ? 'size' : 'shoe';
$q     = get_str('q');
$brand = get_str('brand');

// Query string used to come back to this same view after a button press.
$backParts = [];
if ($view === 'size') $backParts['view'] = 'size';
if ($q !== '')        $backParts['q'] = $q;
if ($brand !== '')    $backParts['brand'] = $brand;
$back = http_build_query($backParts);

// ----- totals (always for all stock, ignoring filters) -----
$tot = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) AS pairs, COALESCE(SUM(CASE WHEN quantity <= ? THEN 1 ELSE 0 END), 0) AS low FROM shoe_sizes');
$tot->execute([LOW_STOCK]);
$t = $tot->fetch();
$cnt = $pdo->query('SELECT COUNT(*) AS variants, COUNT(DISTINCT LOWER(brand)) AS brands FROM shoes')->fetch();

// ----- filters -----
$where = [];
$args  = [];
if ($brand !== '') {
    $where[] = 's.brand = ?';
    $args[]  = $brand;
}
if ($q !== '') {
    $like = '%' . $q . '%';
    $c = '(s.brand LIKE ? OR s.colour LIKE ? OR s.serial_code LIKE ?';
    array_push($args, $like, $like, $like);
    $sz = parse_size($q);
    if ($sz !== null) {
        $c .= ' OR EXISTS (SELECT 1 FROM shoe_sizes x WHERE x.shoe_id = s.id AND x.size = ?)';
        $args[] = size_param($sz);
    }
    $where[] = $c . ')';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

function stepper(array $shoe, string $size, int $n, string $back, bool $canEdit): string
{
    $label = nm($shoe) . ', size ' . fmt_size($size);
    if (!$canEdit) {
        return '<div class="step"><output>' . $n . '</output></div>';
    }
    return '<form method="post" action="adjust.php" class="step">' . csrf_field()
        . '<input type="hidden" name="id" value="' . (int)$shoe['id'] . '">'
        . '<input type="hidden" name="size" value="' . e(fmt_size($size)) . '">'
        . '<input type="hidden" name="back" value="' . e($back) . '">'
        . '<button name="delta" value="-1" aria-label="Remove one pair, ' . e($label) . '"' . ($n <= 0 ? ' disabled' : '') . '>&minus;</button>'
        . '<output>' . $n . '</output>'
        . '<button name="delta" value="1" aria-label="Add one pair, ' . e($label) . '">+</button></form>';
}

function flag(int $n): string
{
    if ($n === 0) return '<span class="flag out">Out</span>';
    if ($n <= LOW_STOCK) return '<span class="flag low">Low</span>';
    return '<span class="flag ok">&nbsp;</span>';
}

$shoes = [];
$groups = [];
if ($view === 'shoe') {
    $st = $pdo->prepare('SELECT s.* FROM shoes s' . $whereSql . ' ORDER BY s.brand, s.colour');
    $st->execute($args);
    $shoes = $st->fetchAll();
    $sizesBy = [];
    foreach ($pdo->query('SELECT shoe_id, size, quantity FROM shoe_sizes ORDER BY size') as $r) {
        $sizesBy[(int)$r['shoe_id']][] = $r;
    }
} else {
    $st = $pdo->prepare('SELECT s.*, z.size AS z_size, z.quantity AS z_qty FROM shoe_sizes z JOIN shoes s ON s.id = z.shoe_id'
        . ($whereSql !== '' ? $whereSql . ' AND' : ' WHERE') . ' z.quantity > 0 ORDER BY z.size, s.brand, s.colour');
    $st->execute($args);
    foreach ($st->fetchAll() as $r) {
        $groups[fmt_size($r['z_size'])][] = $r;
    }
}

$brands = all_brands();
$anyShoes = (int)$cnt['variants'] > 0;

page_header('Stock', 'stock');
?>
<div class="head">
    <div><h1 class="title">Shoe stock</h1><p class="lede">Pairs in storage by brand, colour and size.</p></div>
    <?php if ($canEdit): ?><a class="btn primary" href="add.php">+ Add shoes</a><?php endif; ?>
</div>

<section class="summary" aria-label="Totals">
    <div><b><?= (int)$t['pairs'] ?></b><span>pairs</span></div>
    <div><b><?= (int)$cnt['variants'] ?></b><span>brand and colour</span></div>
    <div><b><?= (int)$cnt['brands'] ?></b><span>brands</span></div>
    <div class="lowstat"><b><?= (int)$t['low'] ?></b><span>low or out sizes</span></div>
</section>

<?php if ($anyShoes): ?>
<form class="toolbar" method="get">
    <div class="tabs" role="group" aria-label="View">
        <button type="submit" name="view" value="shoe" aria-pressed="<?= $view === 'shoe' ? 'true' : 'false' ?>">By shoe</button>
        <button type="submit" name="view" value="size" aria-pressed="<?= $view === 'size' ? 'true' : 'false' ?>">By size</button>
    </div>
    <label class="sr" for="q">Search</label>
    <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search brand, colour, serial or size">
    <label class="sr" for="brand">Brand</label>
    <select id="brand" name="brand" data-autosubmit>
        <option value="">All brands</option>
        <?php foreach ($brands as $b): ?>
            <option<?= $b === $brand ? ' selected' : '' ?>><?= e($b) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Search</button>
    <?php if ($q !== '' || $brand !== ''): ?><a class="btn" href="index.php<?= $view === 'size' ? '?view=size' : '' ?>">Clear</a><?php endif; ?>
</form>
<?php endif; ?>

<?php if (!$anyShoes): ?>
    <div class="empty">
        <h2>Storage is empty</h2>
        <p>Add the first pair with a photo, brand, colour, size and count. Another colour of the same brand gets its own card. Another size of the same colour adds to that card.</p>
        <?php if ($canEdit): ?><a class="btn primary" href="add.php">+ Add shoes</a><?php endif; ?>
    </div>
<?php elseif ($view === 'shoe'): ?>
    <?php if (!$shoes): ?>
        <div class="empty"><h2>No match</h2><p>Nothing in storage matches that search or brand.</p></div>
    <?php else: ?>
    <div class="grid">
    <?php foreach ($shoes as $s):
        $sizes = $sizesBy[(int)$s['id']] ?? [];
        $sum = 0; foreach ($sizes as $z) $sum += (int)$z['quantity']; ?>
        <article class="card" id="shoe-<?= (int)$s['id'] ?>">
            <?= photo_thumb($s) ?>
            <div class="cbody">
                <div class="ctitle">
                    <div>
                        <h2 class="model"><?= e($s['brand']) ?></h2>
                        <?php if ($s['colour'] !== ''): ?><div class="colour"><?= e($s['colour']) ?></div><?php endif; ?>
                        <?php if (!empty($s['serial_code'])): ?><div class="serial">S/N <?= e($s['serial_code']) ?></div><?php endif; ?>
                    </div>
                    <div class="total"><b><?= $sum ?></b><span>pairs</span></div>
                </div>
                <div class="sizes">
                    <?php foreach ($sizes as $z): $n = (int)$z['quantity']; ?>
                        <div class="sz">
                            <span class="sz-n"><small>Size</small><?= e(fmt_size($z['size'])) ?></span>
                            <?= stepper($s, (string)$z['size'], $n, $back, $canEdit) ?>
                            <?= flag($n) ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$sizes): ?><span class="colour">No sizes yet.</span><?php endif; ?>
                </div>
            </div>
            <?php if ($canEdit): ?>
            <div class="actions">
                <a class="link" href="add.php?shoe=<?= (int)$s['id'] ?>">+ Add size</a>
                <a class="link" href="edit.php?id=<?= (int)$s['id'] ?>">Edit</a>
            </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php else: ?>
    <?php if (!$groups): ?>
        <div class="empty"><h2>No pairs in stock</h2><p>Sizes with at least one pair appear here, grouped so you can compare brands in the same size.</p></div>
    <?php else: ?>
    <div class="stack">
    <?php foreach ($groups as $size => $rows):
        $pairs = 0; $bs = [];
        foreach ($rows as $r) { $pairs += (int)$r['z_qty']; $bs[strtolower($r['brand'])] = 1; } ?>
        <section class="sizegroup">
            <h2><span class="big">Size <?= e($size) ?></span>
                <span class="meta"><?= $pairs ?> pairs &middot; <?= count($bs) ?> <?= count($bs) === 1 ? 'brand' : 'brands' ?></span></h2>
            <div class="rows">
            <?php foreach ($rows as $r): $n = (int)$r['z_qty']; ?>
                <div class="row" id="shoe-<?= (int)$r['id'] ?>">
                    <?= photo_thumb($r, 'mini') ?>
                    <div class="who">
                        <div class="model"><?= e($r['brand']) ?></div>
                        <?php if ($r['colour'] !== ''): ?><div class="colour"><?= e($r['colour']) ?></div><?php endif; ?>
                        <?php if (!empty($r['serial_code'])): ?><div class="serial">S/N <?= e($r['serial_code']) ?></div><?php endif; ?>
                    </div>
                    <?= flag($n) ?>
                    <?= stepper($r, (string)$r['z_size'], $n, $back, $canEdit) ?>
                </div>
            <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
<?php page_footer();
