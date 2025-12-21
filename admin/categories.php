<?php
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../db_rest.php';
$pdo = get_rest_db();
$categories = $pdo->query('SELECT * FROM categories ORDER BY created_at DESC')->fetchAll();
?>
<?php $page_title='Categories'; include __DIR__ . '/admin_header.php'; ?>

<style>
  /* Categories responsive card improvements */
  .category-card img { height: 180px; object-fit: cover; width: 100%; border-top-left-radius: .375rem; border-top-right-radius: .375rem; }
  .category-card .card-body { min-height: 120px; }
  .category-title { font-size: 1.05rem; font-weight: 700; margin-bottom: .25rem; }
  .category-desc { color: #6b7280; font-size: .95rem; display: block; max-height: 3.6rem; overflow: hidden; text-overflow: ellipsis; }
  .category-actions { display:flex; gap:8px; margin-top:10px; }
  @media (max-width: 576px) {
    .category-card img { height: 140px; }
  }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-0">Categories</h3>
    <div class="text-muted small">Manage your menu categories</div>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <input id="categorySearch" class="form-control form-control-sm" style="min-width:220px" placeholder="Search categories...">
    <a class="btn btn-primary" href="category_add.php">Add Category</a>
  </div>
</div>

<?php if (empty($categories)): ?>
  <div class="alert alert-info">No categories found. <a href="category_add.php">Create the first category</a>.</div>
<?php else: ?>
  <div class="row g-3" id="categoriesGrid">
    <?php foreach($categories as $c): ?>
      <?php
        $name = htmlspecialchars($c['name']);
        $desc = htmlspecialchars(strip_tags($c['description'] ?? ''));
        $img = $c['image_path'] ? htmlspecialchars($c['image_path']) : '';
      ?>
      <div class="col-12 col-sm-6 col-md-4 col-lg-3 category-item" data-name="<?= strtolower($name) ?>" data-desc="<?= strtolower($desc) ?>">
        <div class="card category-card h-100">
          <?php if ($img): ?>
            <img src="<?= $img ?>" loading="lazy" alt="<?= $name ?>">
          <?php else: ?>
            <div style="height:180px; background:#f3f4f6; display:flex;align-items:center;justify-content:center;color:#9ca3af">No image</div>
          <?php endif; ?>
          <div class="card-body d-flex flex-column">
            <div>
              <div class="category-title"><?= $name ?></div>
              <?php if (!empty($desc)): ?>
                <div class="category-desc"><?= nl2br($desc) ?></div>
              <?php endif; ?>
            </div>
            <div class="mt-auto category-actions">
              <a class="btn btn-sm btn-secondary" href="category_edit.php?id=<?= $c['id'] ?>">Edit</a>
              <a class="btn btn-sm btn-danger" href="category_delete.php?id=<?= $c['id'] ?>" onclick="return confirm('Delete category?')">Delete</a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
  // Client-side search for categories
  (function(){
    const input = document.getElementById('categorySearch');
    if (!input) return;
    input.addEventListener('input', function(){
      const q = this.value.trim().toLowerCase();
      document.querySelectorAll('.category-item').forEach(function(card){
        const name = card.getAttribute('data-name') || '';
        const desc = card.getAttribute('data-desc') || '';
        const match = q === '' || name.indexOf(q) !== -1 || desc.indexOf(q) !== -1;
        card.style.display = match ? '' : 'none';
      });
    });
  })();
</script>

<?php include __DIR__ . '/admin_footer.php'; ?>
