<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php'; require_once __DIR__ . '/../upload.php';
$pdo = get_rest_db(); $id = $_GET['id'] ?? null; if (!$id) { header('Location: items.php'); exit; }
$stmt=$pdo->prepare('SELECT * FROM items WHERE id=? LIMIT 1'); $stmt->execute([$id]); $it=$stmt->fetch(); if(!$it){ header('Location: items.php'); exit; }
$cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll(); $error='';
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $name=trim($_POST['name']); $price=(float)$_POST['price']; $cat=(int)$_POST['category_id']; $desc=trim($_POST['description']);
  $newImg = handle_image_upload('image');
  $uploadAttempted = isset($_FILES['image']) && ($_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE);
  if ($uploadAttempted && $newImg === null) {
    $error = 'Image upload failed or file type not allowed (jpg,png,gif,webp,svg).';
  } else {
    if ($newImg) {
      if (!empty($it['image_path']) && $it['image_path'] !== $newImg) delete_local_upload($it['image_path']);
      $img = $newImg;
    } else {
      $img = $it['image_path'];
    }
    if ($name==='') $error='Name required'; else { $stmt=$pdo->prepare('UPDATE items SET name=?,price=?,category_id=?,description=?,image_path=? WHERE id=?'); $stmt->execute([$name,$price,$cat,$desc,$img,$id]); header('Location: items.php'); exit; }
  }
}
?>
<?php $page_title='Edit Item'; include __DIR__ . '/admin_header.php'; ?>

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="mb-0">Edit Item</h3>
        <div class="text-muted small">Update item details</div>
      </div>
      <div>
        <a class="btn btn-outline-secondary btn-sm" href="items.php">Back to items</a>
      </div>
    </div>

    <?php if($error) echo '<div class="alert alert-danger">'.htmlspecialchars($error).'</div>'; ?>
    <form method="post" enctype="multipart/form-data">
      <div class="row">
        <div class="col-12 col-md-7">
          <div class="mb-3"><label>Name</label><input name="name" class="form-control" required value="<?=htmlspecialchars($it['name'])?>"></div>
          <div class="mb-3"><label>Price</label><input name="price" type="number" step="0.01" class="form-control" required value="<?=htmlspecialchars($it['price'])?>"></div>
          <div class="mb-3"><label>Category</label><select name="category_id" class="form-select"><?php foreach($cats as $c) echo '<option value="'.$c['id'].'"'.($c['id']==$it['category_id']? ' selected':'').'>'.htmlspecialchars($c['name']).'</option>'; ?></select></div>
          <div class="mb-3"><label>Description</label><textarea name="description" class="form-control" rows="4"><?=htmlspecialchars($it['description'])?></textarea></div>
          <div class="d-flex gap-2 mt-2"><button class="btn btn-primary">Save</button> <a class="btn btn-secondary" href="items.php">Cancel</a> <a class="btn btn-danger" href="item_delete.php?id=<?= $it['id'] ?>">Delete</a></div>
        </div>
        <div class="col-12 col-md-5">
          <label class="form-label">Image</label>
          <div class="mb-2"><input type="file" name="image" accept="image/*" class="form-control" id="imageInput"></div>
          <div class="mb-2">
            <?php if($it['image_path']): ?>
              <img id="preview" src="<?=htmlspecialchars($it['image_path'])?>" class="img-preview" alt="Current image">
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
