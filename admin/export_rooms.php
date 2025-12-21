<?php
// Export rooms to CSV
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../db_rest.php';

$pdo = get_rest_db();

try {
    $stmt = $pdo->query('SELECT * FROM rooms ORDER BY id DESC');
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    $rows = [];
}

$filename = 'room_report_all.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
if ($out === false) exit;

fputcsv($out, ['ID','Number','Name','Type','Price','Capacity','Bed','Status','Image']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['id'],
        $r['room_number'],
        $r['room_name'],
        $r['room_type'],
        number_format((float)$r['price'],2),
        $r['capacity'],
        $r['bed_type'],
        $r['status'],
        $r['image']
    ]);
}

fclose($out);
exit;

