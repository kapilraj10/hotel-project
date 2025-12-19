<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php';
$pdo = get_rest_db();
$items = $pdo->query('SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id=c.id ORDER BY i.created_at DESC')->fetchAll();
?>
<?php $page_title='Items'; include __DIR__ . '/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
	<h3>Items</h3>
	<a class="btn btn-primary" href="item_add.php">Add Item</a>
</div>
<table class="table table-striped">
	<thead>
		<tr><th>ID</th><th>Name</th><th>Price</th><th>Category</th><th>Image</th><th>Actions</th></tr>
	</thead>
	<tbody>
		<?php foreach($items as $it): ?>
			<tr>
				<td><?= $it['id'] ?></td>
				<td><?=htmlspecialchars($it['name'])?></td>
				<td>रु<?=number_format($it['price'],2)?></td>
				<td><?=htmlspecialchars($it['category_name'] ?? 'Uncategorized')?></td>
				<td><?php if ($it['image_path']): ?><img src="<?=htmlspecialchars($it['image_path'])?>" style="width:60px;height:40px;object-fit:cover"><?php endif; ?></td>
				<td><a class="btn btn-sm btn-secondary" href="item_edit.php?id=<?=$it['id']?>">Edit</a> <a class="btn btn-sm btn-danger" href="item_delete.php?id=<?=$it['id']?>" onclick="return confirm('Delete item?')">Delete</a></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<?php include __DIR__ . '/admin_footer.php'; ?>
