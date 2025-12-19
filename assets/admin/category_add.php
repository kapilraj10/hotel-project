<?php
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../db_rest.php';
require_once __DIR__ . '/../upload.php';
$pdo = get_rest_db();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $desc = trim($_POST['description'] ?? '');
  $img = handle_image_upload('image');
  // If an upload was attempted but returned null, surface an error
  $uploadAttempted = isset($_FILES['image']) && ($_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE);
  if ($uploadAttempted && $img === null) {
    $error = 'Image upload failed or file type not allowed (jpg,png,gif,webp,svg).';
  } elseif ($name === '') {
    $error = 'Name required';
  } else {
    $stmt = $pdo->prepare('INSERT INTO categories (name,description,image_path) VALUES (?,?,?)');
    $stmt->execute([$name,$desc,$img]);
    header('Location: categories.php'); exit;
  }
}
?>
<?php $page_title='Add Category'; include __DIR__ . '/admin_header.php'; ?>

<h3>Add Category</h3>
<?php if ($error) echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>'; ?>
<form method="post" enctype="multipart/form-data">
  <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
  <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control"></textarea></div>
  <div class="mb-3"><label class="form-label">Image</label><input type="file" name="image" accept="image/*" class="form-control"></div>
  <button class="btn btn-primary">Save</button>
</form>

<?php include __DIR__ . '/admin_footer.php'; ?>
