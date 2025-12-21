<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php'; require_once __DIR__ . '/../upload.php';
$pdo = get_rest_db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$id = $_POST['id'] ?? null;
	if ($id) {
		$stmt = $pdo->prepare('SELECT image_path FROM items WHERE id=? LIMIT 1'); $stmt->execute([$id]); $row = $stmt->fetch();
		if ($row && !empty($row['image_path'])) delete_local_upload($row['image_path']);
		$stmt = $pdo->prepare('DELETE FROM items WHERE id=?'); $stmt->execute([$id]);
	}
	header('Location: items.php'); exit;
}

$id = $_GET['id'] ?? null; if (!$id) { header('Location: items.php'); exit; }
$stmt = $pdo->prepare('SELECT * FROM items WHERE id=? LIMIT 1'); $stmt->execute([$id]); $it = $stmt->fetch(); if (!$it) { header('Location: items.php'); exit; }
?>
<?php $page_title='Delete Item'; include __DIR__ . '/admin_header.php'; ?>

<div class="card">
	<div class="card-body">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<div>
				<h3 class="mb-0">Delete Item</h3>
				<div class="text-muted small">Confirm permanent deletion</div>
			</div>
			<div>
				<a class="btn btn-outline-secondary btn-sm" href="items.php">Back to items</a>
			</div>
		</div>

		<div class="row">
			<div class="col-12 col-md-6">
				<div class="mb-3"><strong>Name:</strong> <?= htmlspecialchars($it['name']) ?></div>
				<div class="mb-3"><strong>Price:</strong> रु<?= number_format($it['price'],2) ?></div>
				<?php if (!empty($it['description'])): ?><div class="mb-3"><strong>Description:</strong><div class="text-muted"><?= nl2br(htmlspecialchars($it['description'])) ?></div></div><?php endif; ?>
			</div>
			<div class="col-12 col-md-6">
				<?php if (!empty($it['image_path'])): ?>
					<img src="<?= htmlspecialchars($it['image_path']) ?>" style="max-width:100%;height:auto;object-fit:cover;border-radius:.375rem;" alt="Item image">
				<?php else: ?>
					<div style="height:160px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;color:#9ca3af;border-radius:.375rem;">No image</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="mt-4 d-flex gap-2">
			<form method="post" onsubmit="return confirm('Delete this item permanently?')">
				<input type="hidden" name="id" value="<?= $it['id'] ?>">
				<button class="btn btn-danger">Delete permanently</button>
			</form>
			<a class="btn btn-secondary" href="items.php">Cancel</a>
		</div>
	</div>
</div>

<?php include __DIR__ . '/admin_footer.php'; ?>
