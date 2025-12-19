<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php'; require_once __DIR__ . '/../upload.php';
$pdo = get_rest_db(); $error='';
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $tn = trim($_POST['table_number'] ?? ''); $tt = trim($_POST['table_type'] ?? ''); $st = trim($_POST['status'] ?? 'Available');
  $img = handle_image_upload('image');
  // ensure is_public flag exists
  try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS is_public TINYINT(1) DEFAULT 1"); } catch (Throwable $e) { }
  // ensure is_room flag exists (restaurant tables are is_room=0)
  try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS is_room TINYINT(1) DEFAULT 0"); } catch (Throwable $e) { try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN is_room TINYINT(1) DEFAULT 0"); } catch (Throwable $__ignored) {} }
  // ensure capacity and bed_type columns exist
  try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS capacity INT DEFAULT 1"); } catch (Throwable $e) { }
  try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS bed_type VARCHAR(100) DEFAULT ''"); } catch (Throwable $e) { }
  $action = $_POST['action'] ?? 'save';
  if ($tn==='') $error='Table number required'; else {
  $is_public = isset($_POST['is_public']) ? (int)$_POST['is_public'] : 1;
  $capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 1;
  $bed_type = trim($_POST['bed_type'] ?? '');
  // explicitly mark this as a restaurant table (is_room = 0)
  $stmt=$pdo->prepare('INSERT INTO tables_info (table_number,table_type,status,image_path,is_public,capacity,bed_type,is_room) VALUES (?,?,?,?,?,?,?,?)');
  $stmt->execute([$tn,$tt,$st,$img,$is_public,$capacity,$bed_type,0]);
    $newId = $pdo->lastInsertId();
    if ($action === 'save_and_book') {
      header('Location: booking_add.php?room_id=' . $newId);
      exit;
    }
    header('Location: tables.php'); exit;
  }
}
?>
<?php $page_title='Add Table'; include __DIR__ . '/admin_header.php'; ?>

<h3>Add Table</h3>
<?php if($error) echo '<div class="alert alert-danger">'.htmlspecialchars($error).'</div>'; ?>
<form method="post" enctype="multipart/form-data">
  <div class="mb-3"><label>Table Number</label><input name="table_number" class="form-control" required></div>
  <div class="mb-3"><label>Table Type</label><input name="table_type" class="form-control" placeholder="e.g., 2-Seater"></div>
  <div class="mb-3"><label>Status</label><select name="status" class="form-select"><option>Available</option><option>Occupied</option></select></div>
  <div class="mb-3 form-check">
    <input type="checkbox" class="form-check-input" id="is_public" name="is_public" value="1" checked>
    <label class="form-check-label" for="is_public">Show on public site</label>
  </div>
  <div class="mb-3"><label>Image</label><input type="file" name="image" accept="image/*" class="form-control"></div>
  <div class="d-flex gap-2">
    <button name="action" value="save" class="btn btn-primary">Save</button>
</form>

<?php include __DIR__ . '/admin_footer.php'; ?>
