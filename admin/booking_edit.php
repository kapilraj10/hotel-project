<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php'; require_once __DIR__ . '/../upload.php';
$pdo = get_rest_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header('Location: bookings.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM bookings WHERE id=? LIMIT 1'); $stmt->execute([$id]); $b = $stmt->fetch();
if (!$b) { header('Location: bookings.php'); exit; }

// room list for reassignment
$rooms = $pdo->query('SELECT id,table_number,status FROM tables_info ORDER BY table_number')->fetchAll();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $room_id = (int)($_POST['room_id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $checkin = $_POST['checkin'] ?? '';
  $checkout = $_POST['checkout'] ?? '';
  $status = $_POST['status'] ?? 'Booked';
  if (!$room_id || !$name || !$email || !$checkin || !$checkout) { $errors[] = 'Please fill required fields.'; }
  if (!$errors) {
    // if room changed, free old room and mark new room as Booked
    $old_room = $b['room_id'];
    if ($old_room != $room_id) {
      if ($old_room) $pdo->prepare('UPDATE tables_info SET status=? WHERE id=?')->execute(['Available',(int)$old_room]);
      if ($room_id) $pdo->prepare('UPDATE tables_info SET status=? WHERE id=?')->execute(['Booked',$room_id]);
    }
    // recalculate pricing for assigned room
  $room_price = 0.0;
  if ($room_id) { $r2 = $pdo->prepare('SELECT price FROM tables_info WHERE id=?'); $r2->execute([$room_id]); $rr2 = $r2->fetch(); if ($rr2) $room_price = (float)$rr2['price']; }
  $nights = 1; if ($checkin && $checkout) { $d1 = strtotime($checkin); $d2 = strtotime($checkout); if ($d2 > $d1) $nights = max(1, floor(($d2 - $d1) / 86400)); }
  $total = $room_price * $nights;
  $stmt = $pdo->prepare('UPDATE bookings SET room_id=?,customer_name=?,customer_email=?,phone=?,checkin=?,checkout=?,price_per_night=?,total_amount=?,status=? WHERE id=?');
  $stmt->execute([$room_id,$name,$email,$phone,$checkin,$checkout,$room_price,$total,$status,$id]);
    header('Location: bookings.php'); exit;
  }
}

$page_title = 'Edit Booking'; include __DIR__ . '/admin_header.php';
?>
<div class="page-header mb-3">
  <h1>Edit Booking #<?=htmlspecialchars($b['id'])?></h1>
  <a href="bookings.php" class="btn btn-outline-secondary btn-add">Back to Bookings</a>
</div>

<?php if($errors): ?><div class="alert alert-danger"><?=htmlspecialchars(implode('\n',$errors))?></div><?php endif; ?>

<form method="post" class="card p-3">
  <div class="row">
    <div class="col-md-6 mb-2">
      <label class="form-label">Room</label>
      <select name="room_id" class="form-select">
        <?php foreach($rooms as $r): ?>
          <option value="<?=$r['id']?>" <?= $b['room_id']===$r['id'] ? 'selected' : '' ?>><?=htmlspecialchars($r['table_number'])?> <?php if($r['status']!=='Available' && $r['id'] != $b['room_id']): ?>(<?=$r['status']?>)<?php endif; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6 mb-2"><label class="form-label">Name</label><input name="name" class="form-control" required value="<?=htmlspecialchars($b['customer_name'])?>"></div>
    <div class="col-md-6 mb-2"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required value="<?=htmlspecialchars($b['customer_email'])?>"></div>
    <div class="col-md-6 mb-2"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?=htmlspecialchars($b['phone'])?>"></div>
    <div class="col-md-6 mb-2"><label class="form-label">Check-in</label><input name="checkin" type="date" class="form-control" required value="<?=htmlspecialchars($b['checkin'])?>"></div>
    <div class="col-md-6 mb-2"><label class="form-label">Check-out</label><input name="checkout" type="date" class="form-control" required value="<?=htmlspecialchars($b['checkout'])?>"></div>
    <div class="col-md-6 mb-2"><label class="form-label">Price per night</label>
      <input name="price_per_night" class="form-control" value="<?=htmlspecialchars(number_format((float)($b['price_per_night'] ?? 0),2))?>" readonly>
    </div>
    <div class="col-md-6 mb-2"><label class="form-label">Total amount</label>
      <input name="total_amount" class="form-control" value="<?=htmlspecialchars(number_format((float)($b['total_amount'] ?? 0),2))?>" readonly>
    </div>
    <div class="col-md-6 mb-2"><label class="form-label">Status</label>
      <select name="status" class="form-select">
        <?php foreach(['Booked','Checked-in','Checked-out','Cancelled'] as $s): ?>
          <option value="<?=$s?>" <?=$b['status']===$s?'selected':''?>><?=$s?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="mt-3"><button class="btn btn-primary">Save Changes</button></div>
</form>

<?php include __DIR__ . '/admin_footer.php';
