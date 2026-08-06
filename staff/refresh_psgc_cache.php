<?php
// Admin-only PSGC cache refresher
require_once __DIR__ . '/includes/access_control.php';
header('Content-Type: application/json');

if (!hasPermission('can_backup_restore')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$cacheDir = realpath(__DIR__ . '/../db') . DIRECTORY_SEPARATOR . 'cache';
if ($cacheDir === false) {
    $cacheDir = __DIR__ . '/../db/cache';
}
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}
$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'psgc_provinces.json';

$url = 'https://psgc.gitlab.io/api/provinces/';
$context = stream_context_create([
    'http' => ['method' => 'GET', 'timeout' => 5],
    'https' => ['method' => 'GET', 'timeout' => 5],
]);

try {
    $resp = @file_get_contents($url, false, $context);
    if (!$resp) {
        throw new Exception('Failed to fetch PSGC provinces from source');
    }
    $list = json_decode($resp, true);
    if (!is_array($list)) {
        throw new Exception('Invalid response from PSGC');
    }
    $map = [];
    foreach ($list as $prov) {
        if (!empty($prov['code']) && !empty($prov['name'])) {
            $map[$prov['code']] = $prov['name'];
        }
    }
    if (empty($map)) {
        throw new Exception('No provinces parsed');
    }
    if (@file_put_contents($cacheFile, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
        throw new Exception('Failed to write cache file');
    }
    echo json_encode(['success' => true, 'message' => 'PSGC cache refreshed', 'count' => count($map), 'cacheFile' => $cacheFile]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
