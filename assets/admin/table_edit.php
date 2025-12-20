<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php'; require_once __DIR__ . '/../upload.php';
$pdo = get_rest_db(); $id = $_GET['id'] ?? null; if (!$id) { header('Location: tables.php'); exit; }
$stmt=$pdo->prepare('SELECT * FROM tables_info WHERE id=? LIMIT 1'); $stmt->execute([$id]); $t=$stmt->fetch(); if(!$t){ header('Location: tables.php'); exit; }
// price column intentionally not managed here (UI removed)
// ensure is_public exists
try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS is_public TINYINT(1) DEFAULT 1"); } catch (Throwable $e) { }
// ensure capacity and bed_type columns exist
try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS capacity INT DEFAULT 1"); } catch (Throwable $e) { }
try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS bed_type VARCHAR(100) DEFAULT ''"); } catch (Throwable $e) { }
// ensure is_room exists (leave unchanged by edits)
try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS is_room TINYINT(1) DEFAULT 0"); } catch (Throwable $e) { try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN is_room TINYINT(1) DEFAULT 0"); } catch (Throwable $__ignored) {} }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $tn = trim($_POST['table_number'] ?? ''); $tt = trim($_POST['table_type'] ?? ''); $st = trim($_POST['status'] ?? 'Available');
  $img = handle_image_upload('image') ?: $t['image_path'];
  $is_public = isset($_POST['is_public']) ? 1 : 0;
  $capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : (int)($t['capacity'] ?? 1);
  $bed_type = trim($_POST['bed_type'] ?? ($t['bed_type'] ?? ''));
  if ($tn==='') $error='Table number required'; else { $stmt=$pdo->prepare('UPDATE tables_info SET table_number=?,table_type=?,status=?,image_path=?,is_public=?,capacity=?,bed_type=? WHERE id=?'); $stmt->execute([$tn,$tt,$st,$img,$is_public,$capacity,$bed_type,$id]); header('Location: tables.php'); exit; }
}
?>
<?php $page_title='Edit Table'; include __DIR__ . '/admin_header.php'; ?>

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="mb-0">Edit Table</h3>
        <div class="text-muted small">Update table details</div>
      </div>
      <div>
        <a class="btn btn-outline-secondary btn-sm" href="tables.php">Back to tables</a>
      </div>
    </div>

    <?php if($error) echo '<div class="alert alert-danger">'.htmlspecialchars($error).'</div>'; ?>
    <form method="post" enctype="multipart/form-data">
      <div class="row">
        <div class="col-12 col-md-7">
          <div class="mb-3"><label>Table Number</label><input name="table_number" class="form-control" required value="<?=htmlspecialchars($t['table_number'])?>"></div>
          <div class="mb-3"><label>Table Type</label><input name="table_type" class="form-control" value="<?=htmlspecialchars($t['table_type'])?>"></div>
          <div class="mb-3"><label>Status</label><select name="status" class="form-select"><option <?= $t['status']==='Available'?'selected':'' ?>>Available</option><option <?= $t['status']==='Occupied'?'selected':'' ?>>Occupied</option></select></div>
          <div class="mb-3"><label>Capacity</label><input name="capacity" class="form-control" type="number" min="1" value="<?=htmlspecialchars($t['capacity'] ?? '1')?>"></div>
          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_public" name="is_public" value="1" <?= (isset($t['is_public']) && $t['is_public']) ? 'checked' : '' ?> >
            <label class="form-check-label" for="is_public">Show on public site</label>
          </div>
          <div class="d-flex gap-2 mt-2">
            <button class="btn btn-primary">Save</button>
            <a class="btn btn-secondary" href="tables.php">Cancel</a>
            <a class="btn btn-danger" href="table_delete.php?id=<?= $t['id'] ?>">Delete</a>
          </div>
        </div>
        <div class="col-12 col-md-5">
          <label class="form-label">Image</label>
          <div class="mb-2"><input type="file" name="image" accept="image/*" class="form-control" id="imageInput"></div>
          <div class="mb-2">
            <?php if($t['image_path']): ?>
              <img id="preview" src="<?=htmlspecialchars($t['image_path'])?>" class="img-preview" alt="Current image">
            <?php else: ?>
              <img id="preview" class="img-preview" style="display:none" alt="Preview">
            <?php endif; ?>
          </div>
          <div class="text-muted small">Upload to replace existing image. Accepted: jpg, png, gif, webp, svg</div>
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
      if (!f) { if(preview) preview.style.display='none'; return; }
      const url = URL.createObjectURL(f);
      if (preview) { preview.src = url; preview.style.display='block'; }
    });
  })();
</script>

<?php include __DIR__ . '/admin_footer.php'; ?>
