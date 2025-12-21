<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php';
// Enable error display for debugging this page (remove or disable in production)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$pdo = null;
try {
  $pdo = get_rest_db();
} catch (Throwable $e) {
  http_response_code(500);
  echo '<div class="alert alert-danger">Database connection error: ' . htmlspecialchars($e->getMessage()) . '</div>';
  exit;
}

$rooms = [];
// Ensure tables_info has the needed columns and the is_room flag
try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS capacity INT DEFAULT 1"); } catch (Throwable $__ignored) {}
try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS bed_type VARCHAR(100) DEFAULT ''"); } catch (Throwable $__ignored) {}
try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS is_room TINYINT(1) DEFAULT 0"); } catch (Throwable $__ignored) { try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN is_room TINYINT(1) DEFAULT 0"); } catch (Throwable $__ignored2) {} }
// Now fetch only room-backed entries (is_room = 1)
try {
  $rooms = $pdo->query('SELECT id, table_number, status, price, capacity, bed_type FROM tables_info WHERE COALESCE(is_room,0)=1 ORDER BY table_number')->fetchAll();
} catch (Throwable $e) {
  // fallback if columns are not supported by the DB / older schema
  $rooms = $pdo->query('SELECT id, table_number, status, price FROM tables_info ORDER BY table_number')->fetchAll();
}
$pre_room = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $room_id = (int)($_POST['room_id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $checkin = $_POST['checkin'] ?? '';
  $checkout = $_POST['checkout'] ?? '';
  if (!$room_id || !$name || !$email || !$checkin || !$checkout) { $errors[] = 'Please fill required fields.'; }
    if (!$errors) {
    // ensure bookings table with pricing
    try {
      $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT,
        customer_name VARCHAR(255),
        customer_email VARCHAR(255),
        phone VARCHAR(50),
        checkin DATE,
        checkout DATE,
        price_per_night DECIMAL(10,2) DEFAULT 0,
        total_amount DECIMAL(12,2) DEFAULT 0,
        status VARCHAR(50) DEFAULT 'Booked',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (Throwable $e) { /* ignore */ }

    // In case the bookings table existed previously without the new pricing columns,
    // try to add the columns safely. Some MySQL versions support ADD COLUMN IF NOT EXISTS,
    // but to be robust we attempt ALTER and ignore any errors if column already exists.
    try {
      $pdo->exec("ALTER TABLE bookings ADD COLUMN price_per_night DECIMAL(10,2) DEFAULT 0");
    } catch (Throwable $__ignored) { /* ignore if column exists or ALTER not supported */ }
    try {
      $pdo->exec("ALTER TABLE bookings ADD COLUMN total_amount DECIMAL(12,2) DEFAULT 0");
    } catch (Throwable $__ignored) { /* ignore if column exists or ALTER not supported */ }

    // determine price from room
    $room_price = 0.00;
    if ($room_id) {
      $r = $pdo->prepare('SELECT price FROM tables_info WHERE id=?'); $r->execute([$room_id]); $rr = $r->fetch(); if ($rr) $room_price = (float)$rr['price'];
    }
    $nights = 1;
    if ($checkin && $checkout) {
      $d1 = strtotime($checkin); $d2 = strtotime($checkout);
      if ($d2 > $d1) $nights = max(1, floor(($d2 - $d1) / 86400));
    }
    $total = $room_price * $nights;

    $stmt = $pdo->prepare('INSERT INTO bookings (room_id,customer_name,customer_email,phone,checkin,checkout,price_per_night,total_amount) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$room_id,$name,$email,$phone,$checkin,$checkout,$room_price,$total]);
    // mark room as Booked
    $up = $pdo->prepare('UPDATE tables_info SET status=? WHERE id=?'); $up->execute(['Booked',$room_id]);
    header('Location: bookings.php'); exit;
  }
}

$page_title = 'Add Booking'; include __DIR__ . '/admin_header.php';
?>
<div class="page-header mb-3">
  <h1>Add Booking</h1>
  <a href="bookings.php" class="btn btn-outline-secondary btn-add">Back to Bookings</a>
</div>

<?php if($errors): ?><div class="alert alert-danger"><?=htmlspecialchars(implode('\n',$errors))?></div><?php endif; ?>

<form method="post" class="card p-3">
  <div class="row">
    <div class="col-md-6 mb-2">
      <label class="form-label">Room</label>
      <select name="room_id" id="roomSelect" class="form-select">
        <?php foreach($rooms as $r): ?>
          <option value="<?=$r['id']?>" data-price="<?=htmlspecialchars($r['price'] ?? 0)?>" data-capacity="<?=htmlspecialchars($r['capacity'] ?? 1)?>" data-bed="<?=htmlspecialchars($r['bed_type'] ?? '')?>" <?= ($pre_room && $pre_room===$r['id']) ? 'selected' : '' ?>><?=htmlspecialchars($r['table_number'])?> <?php if($r['status']!=='Available'): ?>(<?=$r['status']?>)<?php endif; ?></option>
        <?php endforeach; ?>
      </select>
  <div class="mt-2"><small class="text-muted">Price per night: $<span id="adminPrice">0.00</span> — Calculated total: $<span id="adminTotal">0.00</span><br>Capacity: <span id="adminCapacity">-</span> &middot; Bed: <span id="adminBed">-</span></small></div>
    </div>
    <div class="col-md-6 mb-2"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
    <div class="col-md-6 mb-2"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
    <div class="col-md-6 mb-2"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
    <div class="col-md-6 mb-2"><label class="form-label">Check-in</label><input name="checkin" type="date" class="form-control" required></div>
    <div class="col-md-6 mb-2"><label class="form-label">Check-out</label><input name="checkout" type="date" class="form-control" required></div>
  </div>
  <div class="mt-3"><button class="btn btn-primary">Create Booking</button></div>
</form>

<?php include __DIR__ . '/admin_footer.php'; ?>

<script>
  // admin: show selected room price and calculate total based on dates
  function adminCalc(){
    const sel = document.getElementById('roomSelect');
    const opt = sel.options[sel.selectedIndex];
    const price = parseFloat(opt.dataset.price || 0);
    document.getElementById('adminPrice').textContent = price.toFixed(2);
    document.getElementById('adminCapacity').textContent = opt.dataset.capacity || '-';
    document.getElementById('adminBed').textContent = opt.dataset.bed || '-';
    const d1 = document.querySelector('input[name="checkin"]').value;
    const d2 = document.querySelector('input[name="checkout"]').value;
    let nights = 1;
    if (d1 && d2){
      const t1 = new Date(d1); const t2 = new Date(d2);
      const diff = (t2 - t1) / 86400000;
      if (diff > 0) nights = Math.max(1, Math.floor(diff));
    }
    document.getElementById('adminTotal').textContent = (price * nights).toFixed(2);
  }
  document.getElementById('roomSelect').addEventListener('change', adminCalc);
  document.querySelector('input[name="checkin"]').addEventListener('change', adminCalc);
  document.querySelector('input[name="checkout"]').addEventListener('change', adminCalc);
  // init
  adminCalc();
</script>
