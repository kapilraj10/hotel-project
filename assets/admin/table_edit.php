<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php'; require_once __DIR__ . '/../upload.php';
$pdo = get_rest_db(); $id = $_GET['id'] ?? null; if (!$id) { header('Location: tables.php'); exit; }
$stmt=$pdo->prepare('SELECT * FROM tables_info WHERE id=? LIMIT 1'); $stmt->execute([$id]); $t=$stmt->fetch(); if(!$t){ header('Location: tables.php'); exit; }
// ensure price column exists
try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) DEFAULT 0"); } catch (Throwable $e) { }
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
  $price = isset($_POST['price']) ? (float)$_POST['price'] : (float)$t['price'];
  $is_public = isset($_POST['is_public']) ? 1 : 0;
  $capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : (int)($t['capacity'] ?? 1);
  $bed_type = trim($_POST['bed_type'] ?? ($t['bed_type'] ?? ''));
   if ($tn==='') $error='Table number required'; else { $stmt=$pdo->prepare('UPDATE tables_info SET table_number=?,table_type=?,status=?,image_path=?,price=?,is_public=?,capacity=?,bed_type=? WHERE id=?'); $stmt->execute([$tn,$tt,$st,$img,$price,$is_public,$capacity,$bed_type,$id]); header('Location: tables.php'); exit; }
}
?>
<?php $page_title='Edit Table'; include __DIR__ . '/admin_header.php'; ?>

<h3>Edit Table</h3>
<?php if($error) echo '<div class="alert alert-danger">'.htmlspecialchars($error).'</div>'; ?>
<form method="post" enctype="multipart/form-data">
  <div class="mb-3"><label>Table Number</label><input name="table_number" class="form-control" required value="<?=htmlspecialchars($t['table_number'])?>"></div>
  <div class="mb-3"><label>Table Type</label><input name="table_type" class="form-control" value="<?=htmlspecialchars($t['table_type'])?>"></div>
  <div class="mb-3"><label>Status</label><select name="status" class="form-select"><option <?= $t['status']==='Available'?'selected':'' ?>>Available</option><option <?= $t['status']==='Occupied'?'selected':'' ?>>Occupied</option></select></div>
  <div class="mb-3"><label>Price per night (USD)</label><input name="price" class="form-control" type="number" step="0.01" min="0" value="<?=htmlspecialchars($t['price'] ?? '0.00')?>"></div>
  <div class="mb-3"><label>Capacity</label><input name="capacity" class="form-control" type="number" min="1" value="<?=htmlspecialchars($t['capacity'] ?? '1')?>"></div>
  <div class="mb-3"><label>Bed type</label><input name="bed_type" class="form-control" type="text" placeholder="e.g., Single, Double, King" value="<?=htmlspecialchars($t['bed_type'] ?? '')?>"></div>
  <div class="mb-3"><label>Image</label><input type="file" name="image" accept="image/*" class="form-control"><div class="mt-2"><?php if($t['image_path']): ?><img src="<?=htmlspecialchars($t['image_path'])?>" style="max-width:200px"><?php endif; ?></div></div>
  <div class="mb-3 form-check">
    <input type="checkbox" class="form-check-input" id="is_public" name="is_public" value="1" <?= (isset($t['is_public']) && $t['is_public']) ? 'checked' : '' ?> >
    <label class="form-check-label" for="is_public">Show on public site</label>
  </div>
  <button class="btn btn-primary">Save</button>
</form>

<?php include __DIR__ . '/admin_footer.php'; ?>
