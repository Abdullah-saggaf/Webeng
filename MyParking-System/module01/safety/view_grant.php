<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../database/db_functions.php';

requireRole(['safety_staff', 'fk_staff']);

$vehicleId = (int)($_GET['vehicle_id'] ?? 0);
$vehicle = getVehicleById($vehicleId);

if (!$vehicle || empty($vehicle['grant_document'])) {
    http_response_code(404);
    echo 'Grant document not found.';
    exit();
}

$path = realpath(__DIR__ . '/../../' . $vehicle['grant_document']);
if (!$path || !file_exists($path)) {
    http_response_code(404);
    echo 'Grant document missing on disk.';
    exit();
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $path) ?: 'application/octet-stream';
finfo_close($finfo);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . basename($path) . '"');
readfile($path);
exit();
