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

<style>
  .img-preview { max-width: 320px; max-height: 200px; object-fit: cover; display:block; }
  @media (max-width:576px){ .img-preview { max-width:100%; height:auto; } }
</style>

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="mb-0">Add Category</h3>
        <div class="text-muted small">Create a new menu category</div>
      </div>
      <div>
        <a class="btn btn-outline-secondary btn-sm" href="categories.php">Back to categories</a>
      </div>
    </div>

    <?php if ($error) echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>'; ?>
    <form method="post" enctype="multipart/form-data">
      <div class="row">
        <div class="col-12 col-md-7">
          <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="5"></textarea></div>
          <div class="mb-3 d-flex gap-2">
            <button class="btn btn-primary">Save</button>
            <a class="btn btn-secondary" href="categories.php">Cancel</a>
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
