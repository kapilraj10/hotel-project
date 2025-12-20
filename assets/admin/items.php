<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php';
$pdo = get_rest_db();
$items = $pdo->query('SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id=c.id ORDER BY i.created_at DESC')->fetchAll();
?>
<?php $page_title='Items'; include __DIR__ . '/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
		<div>
			<h3 class="mb-0">Items</h3>
			<div class="text-muted small">Manage menu items</div>
		</div>
		<div class="d-flex gap-2 align-items-center">
			<input id="itemSearch" class="form-control form-control-sm admin-search" placeholder="Search items...">
			<a class="btn btn-primary" href="item_add.php">Add Item</a>
		</div>
</div>

<div class="row g-3" id="itemsGrid">
	<?php foreach($items as $it): ?>
		<?php $name = htmlspecialchars($it['name']); $cat = htmlspecialchars($it['category_name'] ?? 'Uncategorized'); $img = $it['image_path'] ? htmlspecialchars($it['image_path']) : ''; ?>
		<div class="col-12 col-sm-6 col-md-4 col-lg-3 item-card" data-name="<?= strtolower($name) ?>" data-cat="<?= strtolower($cat) ?>">
			<div class="card table-card h-100">
				<?php if ($img): ?>
					<img src="<?= $img ?>" alt="<?= $name ?>">
				<?php else: ?>
					<div class="no-image"></div>
				<?php endif; ?>
				<div class="card-body d-flex flex-column">
					<div>
						<div class="table-title"><?= $name ?></div>
						<div class="table-meta"><?= $cat ?> • रु<?= number_format($it['price'],2) ?></div>
					</div>
					<div class="table-actions">
						<a class="btn btn-sm btn-secondary" href="item_edit.php?id=<?=$it['id']?>">Edit</a>
						<a class="btn btn-sm btn-danger" href="item_delete.php?id=<?=$it['id']?>">Delete</a>
					</div>
				</div>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<script>
	(function(){
		const input = document.getElementById('itemSearch');
		if (!input) return;
		input.addEventListener('input', function(){
			const q = this.value.trim().toLowerCase();
			document.querySelectorAll('.item-card').forEach(function(card){
				const name = card.getAttribute('data-name') || '';
				const cat = card.getAttribute('data-cat') || '';
				const match = q === '' || name.indexOf(q) !== -1 || cat.indexOf(q) !== -1;
				card.style.display = match ? '' : 'none';
			});
		});
	})();
</script>

<?php include __DIR__ . '/admin_footer.php'; ?>
