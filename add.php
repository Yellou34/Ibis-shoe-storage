<?php
// Receive pairs into storage: a new shoe, another size of a shoe, or more pairs of a size.
require __DIR__ . '/includes/bootstrap.php';
require_login('staff');
$pdo = db();

$existing = null;
$sid = (int)($_GET['shoe'] ?? $_POST['shoe_id'] ?? 0);
if ($sid > 0) {
    $existing = get_shoe($sid);
    if (!$existing) {
        flash('That shoe no longer exists.', 'bad');
        redirect('index.php');
    }
}

$errors = [];
$f = ['brand' => '', 'colour' => '', 'serial' => '', 'size' => '', 'qty' => '1'];
if ($existing) {
    $f['brand']  = $existing['brand'];
    $f['colour'] = $existing['colour'];
    $f['serial'] = (string)($existing['serial_code'] ?? '');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    if (!$existing) {
        $f['brand']  = post_str('brand');
        $f['colour'] = post_str('colour');
        $f['serial'] = post_str('serial');
    }
    $f['size'] = post_str('size');
    $f['qty']  = post_str('qty');

    if ($f['brand'] === '' || mb_strlen($f['brand']) > 60) $errors[] = 'Enter the brand (up to 60 characters).';
    if (mb_strlen($f['colour']) > 60) $errors[] = 'The colour is too long (60 characters at most).';
    if (mb_strlen($f['serial']) > 60) $errors[] = 'The serial code is too long (60 characters at most).';
    $size = parse_size($f['size']);
    if ($size === null) $errors[] = 'Enter a size, for example 42 or 42.5.';
    $qty = ctype_digit($f['qty']) ? (int)$f['qty'] : 0;
    if ($qty < 1 || $qty > 10000) $errors[] = 'Enter how many pairs were received (1 or more).';

    $target = $existing;
    $matchedBy = '';
    if (!$errors && !$target) {
        // A serial code identifies one exact shoe, so it wins over brand and colour.
        $target = find_shoe_by_serial($f['serial']);
        if ($target) {
            $matchedBy = 'serial code';
        } else {
            $target = find_shoe_by_brand_colour($f['brand'], $f['colour']);
            if ($target) $matchedBy = 'brand and colour';
        }
    }

    if (!$errors) {
        [$photo, $photoErr] = $existing ? [null, null] : save_photo($_FILES['photo'] ?? null);
        if ($photoErr) {
            $errors[] = $photoErr;
        }
    }

    if (!$errors) {
        $oldPhoto = null;
        $pdo->beginTransaction();
        try {
            if ($target) {
                $shoeId = (int)$target['id'];
                $set = ['updated_at = ?'];
                $vals = [now()];
                if ($photo) {
                    $set[] = 'photo = ?';
                    $vals[] = $photo;
                    $oldPhoto = $target['photo'] ?? null;
                }
                if ($f['serial'] !== '' && empty($target['serial_code'])) {
                    $set[] = 'serial_code = ?';
                    $vals[] = $f['serial'];
                }
                $vals[] = $shoeId;
                $pdo->prepare('UPDATE shoes SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($vals);
                $label = nm($target);
            } else {
                $pdo->prepare('INSERT INTO shoes (brand, colour, serial_code, photo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$f['brand'], $f['colour'], $f['serial'] !== '' ? $f['serial'] : null, $photo, now(), now()]);
                $shoeId = (int)$pdo->lastInsertId();
                $label = nm(['brand' => $f['brand'], 'colour' => $f['colour']]);
            }
            add_pairs($shoeId, $size, $qty);
            log_stock($shoeId, $label, $size, $qty, 'Received');
            $pdo->commit();

            delete_photo($oldPhoto);
            $msg = 'Added ' . $qty . ($qty === 1 ? ' pair' : ' pairs') . ' of ' . $label . ', size ' . fmt_size($size) . '.';
            if ($matchedBy !== '' && !$existing) {
                $msg .= ' This shoe was already in storage (matched by ' . $matchedBy . '), so the pairs were added to it.';
            }
            flash($msg);
            redirect('index.php#shoe-' . $shoeId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            delete_photo($photo ?? null);
            error_log('add.php failed: ' . $e->getMessage());
            $errors[] = 'The shoes could not be saved. Try again.';
        }
    }
}

$brands = all_brands();
page_header($existing ? 'Add a size' : 'Add shoes', 'add');
?>
<div class="head"><div>
    <h1 class="title"><?= $existing ? 'Add a size' : 'Add shoes' ?></h1>
    <p class="lede"><?= $existing ? 'Add pairs of another size to ' . e(nm($existing)) . '.' : 'New pairs received into storage.' ?></p>
</div></div>
<?php foreach ($errors as $m): ?><p class="note bad" role="alert"><?= e($m) ?></p><?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="panel stack">
    <?= csrf_field() ?>
    <?php if ($existing): ?><input type="hidden" name="shoe_id" value="<?= (int)$existing['id'] ?>"><?php endif; ?>

    <div class="pick">
        <?php if ($existing): ?>
            <?= photo_thumb($existing, 'preview') ?>
        <?php else: ?>
            <div class="thumb preview" id="preview"><?= photo_placeholder() ?></div>
            <div class="side">
                <input id="photo" class="sr" type="file" name="photo" accept="image/*" data-preview="preview">
                <label for="photo" class="btn small">Take or choose photo</label>
                <p class="help">Use a clear side view so staff can match the pair on the shelf.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="fields">
        <div class="field"><label for="brand">Brand</label>
            <input id="brand" name="brand" list="brands" required maxlength="60" autocomplete="off" placeholder="Nike"
                   value="<?= e($f['brand']) ?>"<?= $existing ? ' readonly' : '' ?>>
            <datalist id="brands"><?php foreach ($brands as $b): ?><option value="<?= e($b) ?>"><?php endforeach; ?></datalist></div>
        <div class="field"><label for="colour">Colour</label>
            <input id="colour" name="colour" maxlength="60" autocomplete="off" placeholder="Black / white"
                   value="<?= e($f['colour']) ?>"<?= $existing ? ' readonly' : '' ?>></div>
        <div class="field full"><label for="serial">Serial code (optional)</label>
            <input id="serial" name="serial" maxlength="60" autocomplete="off" placeholder="e.g. SN-004281"
                   value="<?= e($f['serial']) ?>"<?= ($existing && !empty($existing['serial_code'])) ? ' readonly' : '' ?>></div>
        <div class="field"><label for="size">Size</label>
            <input id="size" name="size" inputmode="decimal" required autocomplete="off" placeholder="42" value="<?= e($f['size']) ?>"></div>
        <div class="field"><label for="qty">Pairs received</label>
            <input id="qty" name="qty" inputmode="numeric" required autocomplete="off" value="<?= e($f['qty']) ?>"></div>
    </div>
    <p class="hint">If this brand and colour, or this serial code, is already in storage, the pairs are added to it.</p>
    <div class="actions-row">
        <a class="btn" href="index.php">Cancel</a>
        <button class="btn primary" type="submit">Add to storage</button>
    </div>
</form>
<?php page_footer();
