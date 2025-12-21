<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php';
$pdo = get_rest_db();

// Ensure bookings table exists
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
try { $pdo->exec("ALTER TABLE bookings ADD COLUMN price_per_night DECIMAL(10,2) DEFAULT 0"); } catch (Throwable $__ignored) {}
try { $pdo->exec("ALTER TABLE bookings ADD COLUMN total_amount DECIMAL(12,2) DEFAULT 0"); } catch (Throwable $__ignored) {}

// actions: update status
if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['action'])){
  if ($_POST['action']==='update' && !empty($_POST['id'])){
    $id=(int)$_POST['id']; $status = $_POST['status'] ?? 'Booked';
    $pdo->prepare('UPDATE bookings SET status=? WHERE id=?')->execute([$status,$id]);
    header('Location: bookings.php'); exit;
  }
  if ($_POST['action']==='delete' && !empty($_POST['id'])){
    $id=(int)$_POST['id'];
    // delete booking and optionally free the room
    // fetch room id
    $stmt = $pdo->prepare('SELECT room_id FROM bookings WHERE id=?'); $stmt->execute([$id]); $r = $stmt->fetch();
    $pdo->prepare('DELETE FROM bookings WHERE id=?')->execute([$id]);
    if ($r && !empty($r['room_id'])) {
      // set room to Available
      $pdo->prepare('UPDATE tables_info SET status=? WHERE id=?')->execute(['Available',(int)$r['room_id']]);
    }
    header('Location: bookings.php'); exit;
  }
}

$rows = $pdo->query('SELECT b.*, t.table_number FROM bookings b LEFT JOIN tables_info t ON b.room_id=t.id ORDER BY b.id DESC')->fetchAll();
$page_title='Bookings'; include __DIR__ . '/admin_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3>Bookings</h3>
  <div>
    <a class="btn btn-sm btn-primary me-2" href="booking_add.php">Add Booking</a>
    <a class="btn btn-sm btn-outline-secondary me-2" href="orders.php">Orders</a>
    <a class="btn btn-sm btn-primary" href="download.php">Download CSV</a>
  </div>
</div>
<div class="table-responsive">
  <table class="table table-striped">
    <thead><tr><th>ID</th><th>Room</th><th>Customer</th><th>Dates</th><th>Price/night</th><th>Total</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?=$r['id']?></td>
          <td><?=htmlspecialchars($r['table_number'])?></td>
          <td><?=htmlspecialchars($r['customer_name'])?><br><?=htmlspecialchars($r['customer_email'])?><br><?=htmlspecialchars($r['phone'] ?? '')?></td>
          <td><?=htmlspecialchars($r['checkin'])?> → <?=htmlspecialchars($r['checkout'])?></td>
          <td>रु<?=number_format((float)($r['price_per_night'] ?? 0),2)?></td>
          <td>रु<?=number_format((float)($r['total_amount'] ?? 0),2)?></td>
          <td><?=htmlspecialchars($r['status'])?></td>
          <td><?=htmlspecialchars($r['created_at'])?></td>
          <td>
            <div class="d-flex">
              <form method="post" class="d-flex align-items-center me-2">
                <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?=$r['id']?>">
                <select name="status" class="form-select form-select-sm me-2" style="width:140px">
                  <?php foreach(['Booked','Checked-in','Checked-out','Cancelled'] as $s): ?>
                    <option value="<?=$s?>" <?=$r['status']===$s?'selected':''?>><?=$s?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-secondary">Save</button>
              </form>
              <form method="post" onsubmit="return confirm('Delete this booking?');">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>">
                <button class="btn btn-sm btn-danger">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/admin_footer.php';
