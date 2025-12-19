<?php
// Returns JSON array of bookings according to a date range. Safe if bookings table missing.
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../db_rest.php';
header('Content-Type: application/json');

$pdo = get_rest_db();

$range = $_GET['range'] ?? '7';
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
        if (is_numeric($range)) {
            $days = (int)$range;
            $start = date('Y-m-d 00:00:00', strtotime("-" . max(0,$days-1) . " days"));
        } else {
            $start = date('Y-m-d 00:00:00', strtotime('-6 days'));
        }
}

// Try to query bookings; use created_at (exists in bookings schema) for filtering
$debug = !empty($_GET['debug']);
$bookings = [];
try {
    $sql = "SELECT b.* , t.table_number FROM bookings b LEFT JOIN tables_info t ON b.room_id=t.id WHERE b.created_at >= ? ORDER BY b.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start]);
    $bookings = $stmt->fetchAll();
    // if debug and still empty, try returning some rows to inspect structure
    if ($debug && empty($bookings)) {
        try {
            $all = $pdo->query("SELECT b.* , t.table_number FROM bookings b LEFT JOIN tables_info t ON b.room_id=t.id LIMIT 50");
            $bookings = $all->fetchAll();
        } catch (Throwable $inner) { }
    }
} catch (Throwable $e) {
    if ($debug) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
    $bookings = [];
}

$rows = [];
foreach ($bookings as $b) {
    // determine booking amount: prefer total_amount, fallback to price_per_night * nights
    $amount = null;
    if (isset($b['total_amount'])) $amount = $b['total_amount'];
    if ($amount === null && isset($b['price_per_night'])) {
        $checkin = $b['checkin'] ?? null;
        $checkout = $b['checkout'] ?? null;
        if ($checkin && $checkout) {
            $days = (int) ((strtotime($checkout) - strtotime($checkin)) / 86400);
            if ($days < 1) $days = 1;
            $amount = (float)$b['price_per_night'] * $days;
        }
    }
    if ($amount === null && isset($b['amount'])) $amount = $b['amount'];

    $rows[] = [
        'id' => $b['id'] ?? null,
        'guest' => $b['customer_name'] ?? $b['guest_name'] ?? $b['name'] ?? '',
        'amount' => $amount !== null ? number_format((float)$amount,2,'.','') : null,
        'status' => $b['status'] ?? '',
        'payment_method' => $b['payment_method'] ?? '',
        'booking_date' => $b['created_at'] ?? $b['checkin'] ?? $b['checkout'] ?? null,
        'room_number' => $b['table_number'] ?? $b['room_id'] ?? null,
        'raw' => $debug ? $b : null,
    ];
}

echo json_encode(array_values($rows));

