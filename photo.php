<?php
// Serves a shoe photo to signed-in people only.
require __DIR__ . '/includes/bootstrap.php';

if (!current_user()) {
    http_response_code(403);
    exit;
}
session_write_close(); // do not block other requests while sending the image

$f = $_GET['f'] ?? '';
if (!is_string($f) || !valid_photo_name($f) || !is_file(UPLOAD_DIR . '/' . $f)) {
    http_response_code(404);
    exit;
}
$path = UPLOAD_DIR . '/' . $f;
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=86400');
readfile($path);
