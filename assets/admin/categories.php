<?php
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../db_rest.php';
$pdo = get_rest_db();
$categories = $pdo->query('SELECT * FROM categories ORDER BY created_at DESC')->fetchAll();
?>
<?php $page_title='Categories'; include __DIR__ . '/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3>Categories</h3>
  <a class="btn btn-primary" href="category_add.php">Add Category</a>
</div>
<div class="row g-3">
  <?php foreach($categories as $c): ?>
    <div class="col-12 col-md-4">
      <div class="card">
        <?php if ($c['image_path']): ?><img src="<?=htmlspecialchars($c['image_path'])?>" class="card-img-top" style="height:180px;object-fit:cover"><?php endif; ?>
        <div class="card-body">
          <h5 class="card-title"><?=htmlspecialchars($c['name'])?></h5>
          <p class="card-text"><?=nl2br(htmlspecialchars($c['description']))?></p>
          <a class="btn btn-sm btn-secondary" href="category_edit.php?id=<?= $c['id'] ?>">Edit</a>
          <a class="btn btn-sm btn-danger" href="category_delete.php?id=<?= $c['id'] ?>" onclick="return confirm('Delete category?')">Delete</a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/admin_footer.php'; ?>
