<?php
// Change a shoe's details, correct counts after a stock take, or (admins) remove it.
require __DIR__ . '/includes/bootstrap.php';
require_login('staff');
$pdo = db();

$id   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$shoe = $id > 0 ? get_shoe($id) : null;
if (!$shoe) {
    flash('That shoe no longer exists.', 'bad');
    redirect('index.php');
}

$errors = [];
$f = ['brand' => $shoe['brand'], 'colour' => $shoe['colour'], 'serial' => (string)($shoe['serial_code'] ?? '')];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $do = post_str('do');

    if ($do === 'delete') {
        require_login('admin');
        if (post_str('confirm') !== 'yes') {
            $errors[] = 'Tick the box to confirm you want to remove this shoe.';
        } else {
            $sizes = get_sizes($id);
            $pdo->beginTransaction();
            try {
                log_stock($id, nm($shoe), null, -total_pairs($sizes), 'Shoe removed');
                $pdo->prepare('DELETE FROM shoe_sizes WHERE shoe_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM shoes WHERE id = ?')->execute([$id]);
                $pdo->commit();
                delete_photo($shoe['photo'] ?? null);
                flash(nm($shoe) . ' was removed from storage.');
                redirect('index.php');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('edit.php delete failed: ' . $e->getMessage());
                $errors[] = 'The shoe could not be removed. Try again.';
            }
        }
    } else {
        $f['brand']  = post_str('brand');
        $f['colour'] = post_str('colour');
        $f['serial'] = post_str('serial');

        if ($f['brand'] === '' || mb_strlen($f['brand']) > 60) $errors[] = 'Enter the brand (up to 60 characters).';
        if (mb_strlen($f['colour']) > 60) $errors[] = 'The colour is too long (60 characters at most).';
        if (mb_strlen($f['serial']) > 60) $errors[] = 'The serial code is too long (60 characters at most).';
        if (!$errors && find_shoe_by_brand_colour($f['brand'], $f['colour'], $id)) {
            $errors[] = 'Another card already uses this brand and colour. Add the pairs to that one instead.';
        }
        if (!$errors && $f['serial'] !== '' && find_shoe_by_serial($f['serial'], $id)) {
            $errors[] = 'Another shoe already uses serial code ' . $f['serial'] . '.';
        }

        $newCounts = [];
        $current = [];
        foreach (get_sizes($id) as $z) {
            $current[(int)$z['id']] = $z;
        }
        $posted = $_POST['qty'] ?? [];
        if (!$errors && is_array($posted)) {
            foreach ($posted as $zid => $val) {
                $zid = (int)$zid;
                if (!isset($current[$zid])) continue;
                if (!is_string($val) || !ctype_digit(trim($val)) || (int)trim($val) > 100000) {
                    $errors[] = 'Counts must be whole numbers, 0 or more.';
                    break;
                }
                $newCounts[$zid] = (int)trim($val);
            }
        }

        $photo = null;
        if (!$errors) {
            [$photo, $photoErr] = save_photo($_FILES['photo'] ?? null);
            if ($photoErr) $errors[] = $photoErr;
        }

        if (!$errors) {
            $removePhoto = post_str('remove_photo') === 'yes';
            $newPhotoName = $photo ?: ($removePhoto ? null : ($shoe['photo'] ?? null));
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE shoes SET brand = ?, colour = ?, serial_code = ?, photo = ?, updated_at = ? WHERE id = ?')
                    ->execute([$f['brand'], $f['colour'], $f['serial'] !== '' ? $f['serial'] : null, $newPhotoName, now(), $id]);
                $label = nm(['brand' => $f['brand'], 'colour' => $f['colour']]);
                foreach ($newCounts as $zid => $n) {
                    $old = (int)$current[$zid]['quantity'];
                    if ($n !== $old) {
                        $pdo->prepare('UPDATE shoe_sizes SET quantity = ? WHERE id = ?')->execute([$n, $zid]);
                        log_stock($id, $label, (float)$current[$zid]['size'], $n - $old, 'Count corrected');
                    }
                }
                $pdo->commit();
                if (($photo || $removePhoto) && ($shoe['photo'] ?? null) !== $newPhotoName) {
                    delete_photo($shoe['photo'] ?? null);
                }
                flash('Saved ' . $label . '.');
                redirect('index.php#shoe-' . $id);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                delete_photo($photo);
                error_log('edit.php save failed: ' . $e->getMessage());
                $errors[] = 'The changes could not be saved. Try again.';
            }
        }
    }
}

$sizes = get_sizes($id);
page_header('Edit shoe', 'stock');
?>
<div class="head"><div>
    <h1 class="title">Edit shoe</h1>
    <p class="lede"><?= e(nm($shoe)) ?></p>
</div></div>
<?php foreach ($errors as $m): ?><p class="note bad" role="alert"><?= e($m) ?></p><?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="panel stack">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="do" value="save">

    <div class="pick">
        <div class="thumb preview" id="preview">
            <?php if (!empty($shoe['photo'])): ?><img src="<?= e(photo_url($shoe['photo'])) ?>" alt="<?= e(nm($shoe)) ?>"><?php else: ?><?= photo_placeholder() ?><?php endif; ?>
        </div>
        <div class="side">
            <input id="photo" class="sr" type="file" name="photo" accept="image/*" data-preview="preview">
            <label for="photo" class="btn small"><?= !empty($shoe['photo']) ? 'Replace photo' : 'Take or choose photo' ?></label>
            <?php if (!empty($shoe['photo'])): ?>
                <label class="check"><input type="checkbox" name="remove_photo" value="yes"> Remove photo</label>
            <?php endif; ?>
        </div>
    </div>

    <div class="fields">
        <div class="field"><label for="brand">Brand</label>
            <input id="brand" name="brand" required maxlength="60" value="<?= e($f['brand']) ?>"></div>
        <div class="field"><label for="colour">Colour</label>
            <input id="colour" name="colour" maxlength="60" value="<?= e($f['colour']) ?>"></div>
        <div class="field full"><label for="serial">Serial code (optional)</label>
            <input id="serial" name="serial" maxlength="60" value="<?= e($f['serial']) ?>"></div>
    </div>

    <?php if ($sizes): ?>
    <fieldset class="counts">
        <legend>Counts by size</legend>
        <p class="help">Use this to correct a count after checking the shelf. Each change is recorded in History.</p>
        <div class="fields">
        <?php foreach ($sizes as $z): ?>
            <div class="field"><label for="q<?= (int)$z['id'] ?>">Size <?= e(fmt_size($z['size'])) ?></label>
                <input id="q<?= (int)$z['id'] ?>" name="qty[<?= (int)$z['id'] ?>]" inputmode="numeric" value="<?= (int)$z['quantity'] ?>"></div>
        <?php endforeach; ?>
        </div>
    </fieldset>
    <?php endif; ?>

    <div class="actions-row">
        <a class="btn" href="index.php#shoe-<?= (int)$id ?>">Cancel</a>
        <a class="btn" href="add.php?shoe=<?= (int)$id ?>">+ Add size</a>
        <button class="btn primary" type="submit">Save changes</button>
    </div>
</form>

<?php if (has_role('admin')): ?>
<form method="post" class="panel stack danger-zone">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="do" value="delete">
    <h2>Remove this shoe</h2>
    <p class="help">This deletes the card, its photo and all <?= total_pairs($sizes) ?> pairs from the counts. The History keeps a record.</p>
    <label class="check"><input type="checkbox" name="confirm" value="yes" data-enables="del-btn"> I understand and want to remove it</label>
    <button class="btn danger" id="del-btn" type="submit" disabled>Remove shoe</button>
</form>
<?php endif; ?>
<?php page_footer();
