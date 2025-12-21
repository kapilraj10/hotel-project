<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php';
session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
function verify_csrf($token) { return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'],$token); }

$pdo = get_rest_db();
$error = '';
$success = '';

// Upload directory under assets/uploads/rooms
$uploadDir = __DIR__ . '/../uploads/rooms/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Ensure rooms table exists (idempotent) and add link to tables_info
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

// Ensure tables_info exists so we can create a mapping for booking flow
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS tables_info (
  id INT AUTO_INCREMENT PRIMARY KEY,
  table_number VARCHAR(50),
  table_type VARCHAR(100),
  status VARCHAR(50) DEFAULT 'Available',
  image_path VARCHAR(255) DEFAULT '',
  price DECIMAL(10,2) DEFAULT 0,
  is_public TINYINT(1) DEFAULT 1,
  is_room TINYINT(1) DEFAULT 0,
  capacity INT DEFAULT 1,
  bed_type VARCHAR(100) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Throwable $__ignored) {}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!verify_csrf($token)) { $error = 'Invalid CSRF token'; }

  $room_number = trim($_POST['room_number'] ?? '');
  $room_name = trim($_POST['room_name'] ?? '');
  $room_type = trim($_POST['room_type'] ?? '');
  $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
  $capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 1;
  $bed_type = trim($_POST['bed_type'] ?? '');
  $status = trim($_POST['status'] ?? 'Available');
  $description = trim($_POST['description'] ?? '');

  // Validate required
  if (empty($error) && ($room_number === '' || $room_name === '' || $room_type === '')) {
    $error = 'Please fill Room Number, Room Name and Room Type.';
  }

  $imageFilename = '';
  if (empty($error) && !empty($_FILES['image']['name'])) {
    $f = $_FILES['image'];
    if ($f['error'] === UPLOAD_ERR_OK) {
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime = $finfo->file($f['tmp_name']);
      $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
      if (!isset($allowed[$mime])) {
        $error = 'Only JPG and PNG images are allowed.';
      } else {
        $ext = $allowed[$mime];
        $base = bin2hex(random_bytes(8));
        $imageFilename = $base . '.' . $ext;
        $dest = $uploadDir . $imageFilename;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
          $error = 'Failed to move uploaded file.';
        }
      }
    } else {
      $error = 'Image upload error.';
    }
  }

  if (empty($error)) {
    // Insert into rooms
    $stmt = $pdo->prepare('INSERT INTO rooms (room_number,room_name,room_type,price,capacity,bed_type,status,image,description) VALUES (?,?,?,?,?,?,?,?,?)');
    try {
      $stmt->execute([$room_number,$room_name,$room_type,$price,$capacity,$bed_type,$status,$imageFilename,$description]);
      $rooms_id = $pdo->lastInsertId();
      // Also insert into tables_info for existing booking internals
  // ensure is_room column exists for tagging
  try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS is_room TINYINT(1) DEFAULT 0"); } catch (Throwable $__ignored) {}
  $ti = $pdo->prepare('INSERT INTO tables_info (table_number,table_type,status,image_path,price,is_public,capacity,bed_type,is_room) VALUES (?,?,?,?,?,?,?,?,?)');
  $ti->execute([$room_number,$room_type,$status,$imageFilename,$price,1,$capacity,$bed_type,1]);
      $tables_info_id = $pdo->lastInsertId();
      // link back
      $pdo->prepare('UPDATE rooms SET tables_info_id=? WHERE id=?')->execute([$tables_info_id,$rooms_id]);
      $success = 'Room added successfully.';
      // if requested, redirect to booking flow
      if (isset($_POST['action']) && $_POST['action']==='save_and_book') {
        header('Location: booking_add.php?room_id=' . $tables_info_id);
        exit;
      }
      // Reset POST so form clears
      $_POST = [];
    } catch (PDOException $e) {
      $error = 'Database error: ' . $e->getMessage();
    }
  }
}

function old($key, $default='') { return htmlspecialchars($_POST[$key] ?? $default); }
$page_title = 'Add Room'; include __DIR__ . '/admin_header.php';
?>
  <div class="page-header mb-3">
    <h1>Add Room</h1>
    <a href="rooms.php" class="btn btn-outline-secondary btn-add">View Rooms</a>
  </div>

  <?php if ($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
    <div class="card p-3">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label form-required">Room Number</label>
          <input name="room_number" class="form-control" required value="<?=old('room_number')?>" placeholder="101">
        </div>
        <div class="col-md-8">
          <label class="form-label form-required">Room Name</label>
          <input name="room_name" class="form-control" required value="<?=old('room_name')?>" placeholder="Deluxe Room">
        </div>

        <div class="col-md-4">
          <label class="form-label form-required">Room Type</label>
          <select name="room_type" class="form-select" required>
            <?php $types=['Single','Double','Family','Suite']; foreach($types as $t): ?>
              <option value="<?=htmlspecialchars($t)?>" <?= (old('room_type')===$t)?'selected':''?>><?=htmlspecialchars($t)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Price Per Night (USD)</label>
          <input name="price" type="number" step="0.01" min="0" class="form-control" value="<?=old('price','0.00')?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Capacity</label>
          <input name="capacity" type="number" min="1" class="form-control" value="<?=old('capacity','1')?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Bed Type</label>
          <select name="bed_type" class="form-select">
            <?php $beds=['Single','Double','King']; foreach($beds as $b): ?>
              <option value="<?=htmlspecialchars($b)?>" <?= (old('bed_type')===$b)?'selected':''?>><?=htmlspecialchars($b)?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <?php $st=['Available','Booked','Maintenance']; foreach($st as $s): ?>
              <option value="<?=htmlspecialchars($s)?>" <?= (old('status')===$s)?'selected':''?>><?=htmlspecialchars($s)?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-8">
          <label class="form-label">Room Image</label>
          <input name="image" type="file" accept="image/jpeg,image/png" class="form-control">
        </div>

        <div class="col-12">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="4"><?=old('description')?></textarea>
        </div>

        <div class="col-12 mt-2">
          <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Room</button>
        </div>
      </div>
    </div>
  </form>

<?php include __DIR__ . '/admin_footer.php';
