<?php
require_once __DIR__ . '/../db_rest.php';
header('Content-Type: application/json');
$pdo = get_rest_db();
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['room_id']) || empty($data['name']) || empty($data['email']) ) { http_response_code(400); echo json_encode(['error'=>'Invalid payload']); exit; }

$room_id = (int)$data['room_id'];
$name = trim($data['name']);
$email = trim($data['email']);
$phone = trim($data['phone'] ?? '');
$checkin = $data['checkin'] ?? null; $checkout = $data['checkout'] ?? null;

// create bookings table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT,
  customer_name VARCHAR(255),
  customer_email VARCHAR(255),
  phone VARCHAR(50),
  checkin DATE,
  checkout DATE,
  status VARCHAR(50) DEFAULT 'Booked',
  price_per_night DECIMAL(10,2) DEFAULT 0,
  total_amount DECIMAL(12,2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Ensure pricing columns exist on older installs where bookings may have been created without them
try {
  $pdo->exec("ALTER TABLE bookings ADD COLUMN price_per_night DECIMAL(10,2) DEFAULT 0");
} catch (Throwable $__ignored) {}
try {
  $pdo->exec("ALTER TABLE bookings ADD COLUMN total_amount DECIMAL(12,2) DEFAULT 0");
} catch (Throwable $__ignored) {}

$stmt = $pdo->prepare('INSERT INTO bookings (room_id,customer_name,customer_email,phone,checkin,checkout) VALUES (?,?,?,?,?,?)');
$stmt->execute([$room_id,$name,$email,$phone,$checkin,$checkout]);
$id = $pdo->lastInsertId();

// mark room as Booked
$up = $pdo->prepare('UPDATE tables_info SET status=? WHERE id=?'); $up->execute(['Booked',$room_id]);

echo json_encode(['booking_id'=>$id]);
