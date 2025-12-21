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
  $uploadAttempted = isset($_FILES['image']) && ($_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE);
  if ($uploadAttempted && $img === null) {
    $err = function_exists('upload_last_error') ? upload_last_error() : null;
    $error = $err ? $err : 'Image upload failed or file type not allowed (jpg,png,gif,webp,svg).';
  }
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

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="mb-0">Add Table</h3>
        <div class="text-muted small">Create a new table</div>
      </div>
      <div>
        <a class="btn btn-outline-secondary btn-sm" href="tables.php">Back to tables</a>
      </div>
    </div>

    <?php if($error) echo '<div class="alert alert-danger">'.htmlspecialchars($error).'</div>'; ?>
    <form method="post" enctype="multipart/form-data">
      <div class="row">
        <div class="col-12 col-md-7">
          <div class="mb-3"><label>Table Number</label><input name="table_number" class="form-control" required></div>
          <div class="mb-3"><label>Table Type</label><input name="table_type" class="form-control" placeholder="e.g., 2-Seater"></div>
          <div class="mb-3"><label>Status</label><select name="status" class="form-select"><option>Available</option><option>Occupied</option></select></div>
          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_public" name="is_public" value="1" checked>
            <label class="form-check-label" for="is_public">Show on public site</label>
          </div>
          <div class="d-flex gap-2 mt-2">
            <button name="action" value="save" class="btn btn-primary">Save</button>
            <a class="btn btn-secondary" href="tables.php">Cancel</a>
          </div>
        </div>
        <div class="col-12 col-md-5">
          <label class="form-label">Image</label>
          <div class="mb-2"><input type="file" name="image" accept="image/*" class="form-control" id="imageInput"></div>
          <div><img id="preview" class="img-preview" style="display:none" alt="Preview"></div>
          <div class="text-muted small mt-2">Accepted: jpg, png, gif, webp, svg</div>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
  (function(){
    const input = document.getElementById('imageInput');
    const preview = document.getElementById('preview');
    if (!input) return;
    input.addEventListener('change', function(){
      const f = this.files && this.files[0];
      if (!f) { preview.style.display='none'; preview.src=''; return; }
      const url = URL.createObjectURL(f);
      preview.src = url; preview.style.display='block';
    });
  })();
</script>

<?php include __DIR__ . '/admin_footer.php'; ?>
