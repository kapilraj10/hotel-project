<?php
// Returns JSON array of order item rows according to a date range
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../db_rest.php';
header('Content-Type: application/json');

$pdo = get_rest_db();

$range = $_GET['range'] ?? '7';
// Map range to start date
switch ($range) {
    case 'today':
        $start = date('Y-m-d 00:00:00');
        break;
    case '7':
        $start = date('Y-m-d 00:00:00', strtotime('-6 days'));
        break;
    case '15':
        $start = date('Y-m-d 00:00:00', strtotime('-14 days'));
        break;
    case '30':
        $start = date('Y-m-d 00:00:00', strtotime('-29 days'));
        break;
    case '3m':
        $start = date('Y-m-d 00:00:00', strtotime('-3 months'));
        break;
    case '6m':
        $start = date('Y-m-d 00:00:00', strtotime('-6 months'));
        break;
    case '1y':
        $start = date('Y-m-d 00:00:00', strtotime('-1 year'));
        break;
    default:
        // numeric days fallback
        if (is_numeric($range)) {
            $days = (int)$range;
            $start = date('Y-m-d 00:00:00', strtotime("-" . max(0,$days-1) . " days"));
        } else {
            $start = date('Y-m-d 00:00:00', strtotime('-6 days'));
        }
}

// For today range, match DATE(order_date)=CURDATE() to return only today's orders.
$rows = [];
$total_sum = 0.0;
$order_count = 0;
if ($range === 'today') {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE DATE(order_date) = CURDATE() ORDER BY order_date DESC');
    $stmt->execute();
    $orders = $stmt->fetchAll();
    $order_count = count($orders);
} else {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_date >= ? ORDER BY order_date DESC');
    $stmt->execute([$start]);
    $orders = $stmt->fetchAll();
    $order_count = count($orders);
}

foreach ($orders as $o) {
    $items = [];
    try { $items = json_decode($o['items_json'], true) ?: []; } catch (Throwable $e) { $items = []; }
    if (empty($items)) {
        // fallback: create a single row with generic info
        $price = (float)($o['total_amount'] ?? 0);
        $total_sum += $price;
        $rows[] = [
            'item_name' => '(no items)',
            'price' => number_format($price,2,'.',''),
            'status' => $o['status'],
            'payment_method' => $o['payment_method'],
            'qty' => 1,
            'table_number' => $o['table_number'] ?? '',
            'order_date' => $o['order_date'] ?? $o['created_at'] ?? null,
            'order_id' => $o['id']
        ];
    } else {
        foreach ($items as $it) {
            $price = (float)($it['price'] ?? 0) * ((int)($it['qty'] ?? 1));
            $total_sum += $price;
            $rows[] = [
                'item_name' => $it['name'] ?? ($it['id'] ?? 'Item'),
                'price' => number_format((float)($it['price'] ?? 0),2,'.',''),
                'status' => $o['status'],
                'payment_method' => $o['payment_method'],
                'qty' => (int)($it['qty'] ?? 1),
                'table_number' => $o['table_number'] ?? '',
                'order_date' => $o['order_date'] ?? $o['created_at'] ?? null,
                'order_id' => $o['id']
            ];
        }
    }
}

$out = [
    'rows' => array_values($rows),
    'order_count' => $order_count,
    'total' => number_format($total_sum,2,'.','')
];

echo json_encode($out);

