<?php
require_once __DIR__ . '/../db_rest.php';
header('Content-Type: application/json');
$pdo = get_rest_db();
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['items']) || !is_array($data['items'])) { http_response_code(400); echo json_encode(['error'=>'Invalid payload']); exit; }

$items = $data['items'];
$items_json = json_encode($items, JSON_UNESCAPED_UNICODE);
$total = 0.0;
foreach ($items as $it) {
	$price = isset($it['price']) ? (float)$it['price'] : 0.0;
	$qty = isset($it['qty']) ? (int)$it['qty'] : 1;
	$total += ($price * $qty);
}

$status = 'Pending';
$payment_method = isset($data['payment_method']) ? trim($data['payment_method']) : 'Cash';
$payment_paid = !empty($data['payment_paid']);

// Treat these methods as immediately paid by default; also allow explicit payment flag
$paid_methods = ['Cash', 'Bank', 'Online', 'Card'];
if ($payment_paid || in_array($payment_method, $paid_methods, true)) {
	$status = 'Completed';
}

$stmt = $pdo->prepare('INSERT INTO orders (items_json,total_amount,status,payment_method,table_number,table_type) VALUES (?,?,?,?,?,?)');
$stmt->execute([$items_json, $total, $status, $payment_method, $data['table_number'] ?? null, $data['table_type'] ?? null]);
$order_id = $pdo->lastInsertId();

echo json_encode(['order_id'=>$order_id, 'status'=>$status]);
