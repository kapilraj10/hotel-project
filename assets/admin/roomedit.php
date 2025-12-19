<?php
// assets/admin/roomedit.php - edit existing room
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php';
session_start();
$pdo = get_rest_db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header('Location: rooms.php'); exit; }

$row = $pdo->prepare('SELECT * FROM rooms WHERE id=?'); $row->execute([$id]); $r = $row->fetch();
if (!$r) { header('Location: rooms.php'); exit; }

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(16));
$error=''; $success='';

if ($_SERVER['REQUEST_METHOD']==='POST'){
  $token = $_POST['csrf_token'] ?? '';
  if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'],$token)) { die('Invalid CSRF'); }
  $room_number = trim($_POST['room_number'] ?? '');
  $room_name = trim($_POST['room_name'] ?? '');
  $room_type = trim($_POST['room_type'] ?? '');
  $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
  $capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 1;
  $bed_type = trim($_POST['bed_type'] ?? '');
  $status = trim($_POST['status'] ?? 'Available');
  $description = trim($_POST['description'] ?? '');

  if ($room_number===''||$room_name===''||$room_type==='') $error='Required fields missing.';

  $imageFilename = $r['image'];
  if (empty($error) && !empty($_FILES['image']['name'])) {
    $f = $_FILES['image'];
    if ($f['error']===UPLOAD_ERR_OK) {
      $finfo = new finfo(FILEINFO_MIME_TYPE); $mime = $finfo->file($f['tmp_name']);
      $allowed = ['image/jpeg'=>'jpg','image/png'=>'png'];
      if (!isset($allowed[$mime])) $error='Only JPG/PNG allowed'; else {
        $ext = $allowed[$mime]; $base=bin2hex(random_bytes(8)); $imageFilename = $base.'.'.$ext; $dest = __DIR__.'/../uploads/rooms/'.$imageFilename; move_uploaded_file($f['tmp_name'],$dest);
      }
    }
  }

  if (!$error) {
    $pdo->prepare('UPDATE rooms SET room_number=?,room_name=?,room_type=?,price=?,capacity=?,bed_type=?,status=?,image=?,description=? WHERE id=?')
      ->execute([$room_number,$room_name,$room_type,$price,$capacity,$bed_type,$status,$imageFilename,$description,$id]);
    // update tables_info if linked
    if (!empty($r['tables_info_id'])) {
      $pdo->prepare('UPDATE tables_info SET table_number=?,table_type=?,status=?,image_path=?,price=?,capacity=?,bed_type=? WHERE id=?')
        ->execute([$room_number,$room_type,$status,$imageFilename,$price,$capacity,$bed_type,(int)$r['tables_info_id']]);
    }
    $success='Saved.'; $row = $pdo->prepare('SELECT * FROM rooms WHERE id=?'); $row->execute([$id]); $r = $row->fetch();
  }
}

function oldv($row,$k,$d=''){ return htmlspecialchars($_POST[$k]??$row[$k]??$d); }

?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Edit Room</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body>
<div class="container py-4">
  <h3>Edit Room #<?=htmlspecialchars($id)?></h3>
  <?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
  <?php if($success): ?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label">Room Number</label><input name="room_number" class="form-control" value="<?=oldv($r,'room_number')?>"></div>
      <div class="col-md-8"><label class="form-label">Room Name</label><input name="room_name" class="form-control" value="<?=oldv($r,'room_name')?>"></div>
      <div class="col-md-4"><label class="form-label">Room Type</label><select name="room_type" class="form-select"><?php foreach(['Single','Double','Family','Suite'] as $t): ?><option value="<?=htmlspecialchars($t)?>" <?= (oldv($r,'room_type')===$t)?'selected':''?>><?=htmlspecialchars($t)?></option><?php endforeach;?></select></div>
      <div class="col-md-4"><label class="form-label">Price</label><input name="price" type="number" step="0.01" class="form-control" value="<?=oldv($r,'price','0.00')?>"></div>
      <div class="col-md-4"><label class="form-label">Capacity</label><input name="capacity" type="number" min="1" class="form-control" value="<?=oldv($r,'capacity','1')?>"></div>
      <div class="col-md-4"><label class="form-label">Bed Type</label><select name="bed_type" class="form-select"><?php foreach(['Single','Double','King'] as $b): ?><option value="<?=htmlspecialchars($b)?>" <?= (oldv($r,'bed_type')===$b)?'selected':''?>><?=htmlspecialchars($b)?></option><?php endforeach;?></select></div>
      <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><?php foreach(['Available','Booked','Maintenance'] as $s):?><option <?=htmlspecialchars($r['status'])===$s?'selected':''?>><?=htmlspecialchars($s)?></option><?php endforeach;?></select></div>
      <div class="col-md-8"><label class="form-label">Image</label><input type="file" name="image" class="form-control"><div class="mt-2"><?php if($r['image']): ?><img src="<?=htmlspecialchars(UPLOADS_URL)?>rooms/<?=htmlspecialchars($r['image'])?>" style="max-width:200px"><?php endif; ?></div></div>
      <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control"><?=oldv($r,'description')?></textarea></div>
      <div class="col-12 mt-2"><button class="btn btn-primary">Save</button> <a href="rooms.php" class="btn btn-secondary">Back</a></div>
    </div>
  </form>
</div>
</body></html>
