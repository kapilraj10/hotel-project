<?php
// Returns JSON array of rooms (for report) with basic fields
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../db_rest.php';
header('Content-Type: application/json');

$pdo = get_rest_db();

try {
    $stmt = $pdo->query('SELECT * FROM rooms ORDER BY id DESC');
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    $rows = [];
}

$uploadsUrl = '/hotel/uploads/rooms/';
$out = [];
foreach ($rows as $r) {
    $out[] = [
        'id' => $r['id'],
        'room_number' => $r['room_number'],
        'room_name' => $r['room_name'],
        'room_type' => $r['room_type'],
        'price' => number_format((float)$r['price'],2,'.',''),
        'capacity' => $r['capacity'],
        'bed_type' => $r['bed_type'],
        'status' => $r['status'],
        'image' => (!empty($r['image']) ? $uploadsUrl . $r['image'] : '')
    ];
}

echo json_encode($out);

