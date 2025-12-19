<?php
require_once __DIR__ . '/../auth.php'; require_admin();
require_once __DIR__ . '/../db_rest.php'; require_once __DIR__ . '/../upload.php';
$pdo = get_rest_db();
$id = $_GET['id'] ?? null; if (!$id) { header('Location: categories.php'); exit; }
$stmt = $pdo->prepare('SELECT * FROM categories WHERE id=? LIMIT 1'); $stmt->execute([$id]); $cat = $stmt->fetch();
if (!$cat) { header('Location: categories.php'); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $name = trim($_POST['name'] ?? ''); $desc = trim($_POST['description'] ?? '');
  $newImg = handle_image_upload('image');
  $uploadAttempted = isset($_FILES['image']) && ($_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE);
  if ($uploadAttempted && $newImg === null) {
    $error = 'Image upload failed or file type not allowed (jpg,png,gif,webp,svg).';
  } else {
    if ($newImg) {
      // delete old file if present and different
      if (!empty($cat['image_path']) && $cat['image_path'] !== $newImg) {
        delete_local_upload($cat['image_path']);
      }
      $img = $newImg;
    } else {
      $img = $cat['image_path'];
    }
    if ($name==='') $error='Name required'; else { $stmt=$pdo->prepare('UPDATE categories SET name=?,description=?,image_path=? WHERE id=?'); $stmt->execute([$name,$desc,$img,$id]); header('Location: categories.php'); exit; }
  }
}
?>
<?php $page_title='Edit Category'; include __DIR__ . '/admin_header.php'; ?>

<h3>Edit Category</h3>
<?php if ($error) echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>'; ?>
<form method="post" enctype="multipart/form-data">
  <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" required value="<?=htmlspecialchars($cat['name'])?>"></div>
  <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control"><?=htmlspecialchars($cat['description'])?></textarea></div>
  <div class="mb-3"><label class="form-label">Image</label><input type="file" name="image" accept="image/*" class="form-control"><div class="mt-2"><?php if ($cat['image_path']): ?><img src="<?=htmlspecialchars($cat['image_path'])?>" style="max-width:200px"><?php endif; ?></div></div>
  <button class="btn btn-primary">Save</button>
</form>

<?php include __DIR__ . '/admin_footer.php'; ?>
