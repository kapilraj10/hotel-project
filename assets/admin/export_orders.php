<?php
// Export orders (expanded by item) to CSV for a given date range
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../db_rest.php';

$pdo = get_rest_db();
$range = $_GET['range'] ?? '7';

switch ($range) {
    case 'today': $start = date('Y-m-d 00:00:00'); break;
    case '7': $start = date('Y-m-d 00:00:00', strtotime('-6 days')); break;
    case '15': $start = date('Y-m-d 00:00:00', strtotime('-14 days')); break;
    case '30': $start = date('Y-m-d 00:00:00', strtotime('-29 days')); break;
    case '3m': $start = date('Y-m-d 00:00:00', strtotime('-3 months')); break;
    case '6m': $start = date('Y-m-d 00:00:00', strtotime('-6 months')); break;
    case '1y': $start = date('Y-m-d 00:00:00', strtotime('-1 year')); break;
    default:
        if (is_numeric($range)) {
            $days = (int)$range;
            $start = date('Y-m-d 00:00:00', strtotime("-" . max(0,$days-1) . " days"));
        } else {
            $start = date('Y-m-d 00:00:00', strtotime('-6 days'));
        }
}

$stmt = $pdo->prepare('SELECT * FROM orders WHERE order_date >= ? ORDER BY order_date DESC');
$stmt->execute([$start]);
$orders = $stmt->fetchAll();

$rows = [];
foreach ($orders as $o) {
    $items = [];
    try { $items = json_decode($o['items_json'], true) ?: []; } catch (Throwable $e) { $items = []; }
    if (empty($items)) {
        $rows[] = [
            'Order ID' => $o['id'],
            'Item Name' => '(no items)',
            'Price' => number_format((float)$o['total_amount'],2),
            'Status' => $o['status'],
            'Payment Method' => $o['payment_method'],
            'Quantity' => 1,
            'Table/Room' => $o['table_number'] ?? '',
            'Order Date' => $o['order_date'] ?? $o['created_at'] ?? ''
        ];
    } else {
        foreach ($items as $it) {
            $rows[] = [
                'Order ID' => $o['id'],
                'Item Name' => $it['name'] ?? ($it['id'] ?? 'Item'),
                'Price' => number_format((float)($it['price'] ?? 0),2),
                'Status' => $o['status'],
                'Payment Method' => $o['payment_method'],
                'Quantity' => (int)($it['qty'] ?? 1),
                'Table/Room' => $o['table_number'] ?? '',
                'Order Date' => $o['order_date'] ?? $o['created_at'] ?? ''
            ];
        }
    }
}

// stream CSV
// human-friendly filename mapping
$map = [
    'today' => 'today',
    '7' => 'last_7_days',
    '15' => 'last_15_days',
    '30' => 'last_30_days',
    '3m' => 'last_3_months',
    '6m' => 'last_6_months',
    '1y' => 'last_1_year'
];
$label = $map[$range] ?? null;
if (!$label) {
    if (is_numeric($range)) {
        $label = 'last_' . intval($range) . '_days';
    } else {
        // sanitize fallback
        $label = preg_replace('/[^a-z0-9_\-]/i', '_', (string)$range);
        if ($label === '') $label = 'custom';
    }
}
$filename = 'order_report_' . $label . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
if ($out === false) exit;

if (!empty($rows)) {
    // headers
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $r) {
        // ensure values are scalar
        $line = array_map(function($v){ if (is_bool($v)) return $v? '1':'0'; if (is_null($v)) return ''; return (string)$v; }, $r);
        fputcsv($out, $line);
    }
} else {
    fputcsv($out, ['Order ID','Item Name','Price','Status','Payment Method','Quantity','Table/Room','Order Date']);
}
fclose($out);
exit;

