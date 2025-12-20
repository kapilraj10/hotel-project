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

<style>
  .img-preview { max-width: 100%; max-height: 240px; object-fit: cover; display:block; }
  @media (max-width:576px){ .img-preview { max-height:160px; } }
</style>

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="mb-0">Edit Category</h3>
        <div class="text-muted small">Update category details</div>
      </div>
      <div>
        <a class="btn btn-outline-secondary btn-sm" href="categories.php">Back to categories</a>
      </div>
    </div>

    <?php if ($error) echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>'; ?>
    <form method="post" enctype="multipart/form-data">
      <div class="row">
        <div class="col-12 col-md-7">
          <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" required value="<?=htmlspecialchars($cat['name'])?>"></div>
          <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="5"><?=htmlspecialchars($cat['description'])?></textarea></div>
          <div class="mb-3 d-flex gap-2">
            <button class="btn btn-primary">Save</button>
            <a class="btn btn-secondary" href="categories.php">Cancel</a>
            <a class="btn btn-danger" href="category_delete.php?id=<?= $cat['id'] ?>" onclick="return confirm('Delete category?')">Delete</a>
          </div>
        </div>
        <div class="col-12 col-md-5">
          <label class="form-label">Image</label>
          <div class="mb-2"><input type="file" name="image" accept="image/*" class="form-control" id="imageInput"></div>
          <div class="mb-2">
            <?php if ($cat['image_path']): ?>
              <img id="preview" src="<?=htmlspecialchars($cat['image_path'])?>" class="img-preview" alt="Current image">
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
