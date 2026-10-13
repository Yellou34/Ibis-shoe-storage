<?php
// Photo handling. Photos are re-saved as JPEG (this strips anything hidden in the
// upload), shrunk to 900 px, given a random name and kept in uploads/, which the
// web server cannot serve directly. photo.php hands them out to signed-in people only.

function photo_url(?string $file): string
{
    return $file ? 'photo.php?f=' . rawurlencode($file) : '';
}

function valid_photo_name(?string $f): bool
{
    return is_string($f) && preg_match('/^[a-f0-9]{32}\.jpg$/', $f) === 1;
}

function delete_photo(?string $file): void
{
    if (valid_photo_name($file)) {
        $path = UPLOAD_DIR . '/' . $file;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function photo_placeholder(): string
{
    return '<svg viewBox="0 0 120 80" aria-hidden="true"><path d="M10 58c0-7 5-12 13-14l17-5c7-2 11-9 16-14l9 7c6 5 15 9 27 11 11 2 19 8 19 17v4H10z" fill="currentColor"/></svg>';
}

function photo_thumb(array $s, string $extraClass = ''): string
{
    $inner = !empty($s['photo'])
        ? '<img src="' . e(photo_url($s['photo'])) . '" alt="' . e(nm($s)) . '" loading="lazy">'
        : photo_placeholder();
    return '<div class="thumb ' . e($extraClass) . '">' . $inner . '</div>';
}

/** Handle an <input type="file"> upload. Returns [filename|null, error|null]. */
function save_photo(?array $file): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'The photo could not be uploaded. Try a smaller file.'];
    }
    if ($file['size'] > MAX_UPLOAD_MB * 1048576) {
        return [null, 'The photo is larger than ' . MAX_UPLOAD_MB . ' MB.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return [null, 'The upload was not valid. Try again.'];
    }
    return store_image($file['tmp_name']);
}

/** Validate, shrink and save an image file. Returns [filename|null, error|null]. */
function store_image(string $tmp): array
{
    $info = @getimagesize($tmp);
    $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    if (!$info || !in_array($info[2], $allowed, true)) {
        return [null, 'Use a JPG, PNG, GIF or WebP photo.'];
    }
    if ($info[0] * $info[1] > 40000000) {
        return [null, 'That photo has too many pixels. Use a smaller one.'];
    }
    $data = @file_get_contents($tmp);
    $img  = $data === false ? false : @imagecreatefromstring($data);
    if (!$img) {
        return [null, 'That photo could not be read.'];
    }

    // Phones store rotation in the file. Apply it so the shoe is upright.
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif   = @exif_read_data($tmp);
        $orient = (int)($exif['Orientation'] ?? 1);
        $angle  = [3 => 180, 6 => -90, 8 => 90][$orient] ?? 0;
        if ($angle !== 0) {
            $rot = @imagerotate($img, $angle, 0);
            if ($rot) {
                $img = $rot;
            }
        }
    }

    $w = imagesx($img);
    $h = imagesy($img);
    $k = min(1, 900 / max($w, $h));
    $nw = max(1, (int)round($w * $k));
    $nh = max(1, (int)round($h * $k));
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

    if (!is_dir(UPLOAD_DIR) || !is_writable(UPLOAD_DIR)) {
        return [null, 'The uploads folder is missing or not writable on the server.'];
    }
    $name = bin2hex(random_bytes(16)) . '.jpg';
    if (!imagejpeg($out, UPLOAD_DIR . '/' . $name, 82)) {
        return [null, 'The photo could not be saved.'];
    }
    return [$name, null];
}
