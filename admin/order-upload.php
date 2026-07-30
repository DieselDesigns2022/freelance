<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    http_response_code(404);
    exit('File not found.');
}
$stmt = db()->prepare('SELECT original_name,mime_type,file_path FROM order_uploads WHERE id = ?');
$stmt->execute([$id]);
$upload = $stmt->fetch();
$base = realpath(__DIR__ . '/../' . ORDER_UPLOAD_RELATIVE_DIR);
$path = $upload ? realpath(__DIR__ . '/../' . $upload['file_path']) : false;
if (!$upload || !$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}
$name = basename(str_replace(["\r", "\n", '"', '\\'], '', $upload['original_name'] ?: basename($path)));
if ($name === '' || $name === '.' || $name === '..') {
    $name = 'download';
}
$mime = is_string($upload['mime_type']) && preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $upload['mime_type'])
    ? $upload['mime_type']
    : 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
