<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

function json_out($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['success' => false, 'error' => 'POST required'], 405);
}

$deviceId = trim($_POST['device_id'] ?? '');
if ($deviceId === '') {
    json_out(['success' => false, 'error' => 'device_id required'], 400);
}

$device = fetch_one("SELECT * FROM devices WHERE device_id = ?", [$deviceId]);
if (!$device) {
    json_out(['success' => false, 'error' => 'Unknown device'], 403);
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    json_out(['success' => false, 'error' => 'Upload failed'], 400);
}

$file = $_FILES['image'];
$tmp  = $file['tmp_name'];

if ($file['size'] > MAX_UPLOAD_BYTES) {
    json_out(['success' => false, 'error' => 'File too large'], 413);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
if (!in_array($finfo->file($tmp), ALLOWED_MIME)) {
    json_out(['success' => false, 'error' => 'Invalid file type'], 415);
}

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);

$name = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.png';
$path = UPLOAD_DIR . $name;
$rel  = 'uploads/' . $name;

if (!move_uploaded_file($tmp, $path)) {
    json_out(['success' => false, 'error' => 'Save failed'], 500);
}

$pdo = db();

$pdo->prepare("
    INSERT INTO submissions (image_path, device_id, status, uploaded_at)
    VALUES (?, ?, 'pending', NOW())
")->execute([$rel, $deviceId]);

$id = $pdo->lastInsertId();

json_out([
    'success' => true,
    'id' => $id,
    'device_id' => $deviceId,
    'image_path' => $rel,
    'status' => 'pending'
]);