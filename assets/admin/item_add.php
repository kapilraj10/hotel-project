<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php'; require_once __DIR__ . '/../upload.php';
$pdo = get_rest_db(); $cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll(); $error='';
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $name = trim($_POST['name'] ?? ''); $price = (float)$_POST['price']; $cat = (int)$_POST['category_id']; $desc = trim($_POST['description'] ?? '');
  $img = handle_image_upload('image');
  $uploadAttempted = isset($_FILES['image']) && ($_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE);
  if ($uploadAttempted && $img === null) {
    $error = 'Image upload failed or file type not allowed (jpg,png,gif,webp,svg).';
  } elseif ($name==='') {
    $error='Name required';
  } else {
    $stmt=$pdo->prepare('INSERT INTO items (name,price,category_id,description,image_path) VALUES (?,?,?,?,?)'); $stmt->execute([$name,$price,$cat,$desc,$img]); header('Location: items.php'); exit;
  }
}
?>
<?php $page_title='Add Item'; include __DIR__ . '/admin_header.php'; ?>

<h3>Add Item</h3>
<?php if ($error) echo '<div class="alert alert-danger">'.htmlspecialchars($error).'</div>'; ?>
<form method="post" enctype="multipart/form-data">
  <div class="mb-3"><label>Name</label><input name="name" class="form-control" required></div>
  <div class="mb-3"><label>Price</label><input name="price" type="number" step="0.01" class="form-control" required></div>
  <div class="mb-3"><label>Category</label><select name="category_id" class="form-select"><?php foreach($cats as $c) echo '<option value="'.$c['id'].'">'.htmlspecialchars($c['name']).'</option>'; ?></select></div>
  <div class="mb-3"><label>Description</label><textarea name="description" class="form-control"></textarea></div>
  <div class="mb-3"><label>Image</label><input type="file" name="image" accept="image/*" class="form-control"></div>
  <button class="btn btn-primary">Save</button>
</form>

<?php include __DIR__ . '/admin_footer.php'; ?>
