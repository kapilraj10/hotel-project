<?php
// admin/rooms.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../db_rest.php';
$pdo = get_rest_db();

// Ensure related tables exist (idempotent) to avoid fatal errors on older databases
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(50),
    room_name VARCHAR(255),
    room_type VARCHAR(50),
    price DECIMAL(10,2) DEFAULT 0,
    capacity INT DEFAULT 1,
    bed_type VARCHAR(50),
    status VARCHAR(50) DEFAULT 'Available',
    image VARCHAR(255) DEFAULT '',
    description TEXT,
    tables_info_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Throwable $__ignored) {}

/* CSRF token */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

/* Handle POST actions: update status or delete */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Update status
  if (!empty($_POST['action']) && $_POST['action'] === 'update_status' && !empty($_POST['room_id'])) {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
      die('Invalid CSRF token');
    }
    $rid = (int)$_POST['room_id'];
    $status = $_POST['status'] ?? 'Available';
    $stmt = $pdo->prepare('UPDATE rooms SET status=? WHERE id=?');
    $stmt->execute([$status, $rid]);
    header('Location: rooms.php'); exit;
  }

  // Delete
  if (isset($_POST['delete_id'])) {
    if (
      empty($_POST['csrf_token']) ||
      !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
      die('Invalid CSRF token');
    }

    $id = (int)$_POST['delete_id'];

    $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: rooms.php");
    exit;
  }

}

// Fetch rooms (safe: if SELECT fails because of missing schema, fall back to empty list)
try {
  $rows = $pdo->query('SELECT r.*, t.id AS tables_info_id FROM rooms r LEFT JOIN tables_info t ON r.tables_info_id = t.id ORDER BY r.id DESC')->fetchAll();
} catch (Throwable $e) {
  // table missing or other DB error - show empty list instead of fatal
  $rows = [];
}

/* Uploads URL fallback */
$uploadsUrl = '/hotel/uploads/rooms/';

$page_title = 'Rooms';
include __DIR__ . '/admin_header.php';
?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Rooms</h3>
    <a href="roomcreate.php" class="btn btn-primary">Add Room</a>
  </div>

  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>Number</th>
          <th>Name</th>
          <th>Type</th>
          <th>Price</th>
          <th>Capacity</th>
          <th>Bed</th>
          <th>Status</th>
          <th>Image</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['id']) ?></td>
          <td><?= htmlspecialchars($r['room_number']) ?></td>
          <td><?= htmlspecialchars($r['room_name']) ?></td>
          <td><?= htmlspecialchars($r['room_type']) ?></td>
          <td>रु<?= number_format((float)$r['price'], 2) ?></td>
          <td><?= htmlspecialchars($r['capacity']) ?></td>
          <td><?= htmlspecialchars($r['bed_type']) ?></td>
          <td>
            <form method="post" class="d-flex align-items-center">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
              <select name="status" class="form-select form-select-sm me-2" style="width:140px">
                <?php foreach (['Available','Booked','Maintenance'] as $s): ?>
                  <option value="<?= $s ?>" <?= $r['status']===$s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-sm btn-outline-secondary">Save</button>
            </form>
          </td>
          <td>
            <?php if (!empty($r['image'])): ?>
              <img src="<?= $uploadsUrl . htmlspecialchars($r['image']) ?>"
                   height="45" class="rounded">
            <?php endif; ?>
          </td>
          <td>
            <a href="room.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-primary">View</a>
            <a href="roomedit.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>

            <form method="post" class="d-inline"
                  onsubmit="return confirm('Delete this room?');">
              <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <button class="btn btn-sm btn-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>

    </table>
  </div>

<?php include __DIR__ . '/admin_footer.php';
