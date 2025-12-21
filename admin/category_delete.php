<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php'; require_once __DIR__ . '/../upload.php';
$pdo = get_rest_db();
$id = $_GET['id'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$id = $_POST['id'] ?? null;
	if ($id) {
		// fetch record to delete image file
		$stmt = $pdo->prepare('SELECT image_path FROM categories WHERE id=? LIMIT 1'); $stmt->execute([$id]); $cat = $stmt->fetch();
		if ($cat) {
			if (!empty($cat['image_path'])) delete_local_upload($cat['image_path']);
		}
		$stmt = $pdo->prepare('DELETE FROM categories WHERE id=?'); $stmt->execute([$id]);
	}
	header('Location: categories.php'); exit;
}

if (!$id) { header('Location: categories.php'); exit; }
$stmt = $pdo->prepare('SELECT * FROM categories WHERE id=? LIMIT 1'); $stmt->execute([$id]); $cat = $stmt->fetch();
if (!$cat) { header('Location: categories.php'); exit; }
?>
<?php $page_title='Delete Category'; include __DIR__ . '/admin_header.php'; ?>

<div class="card">
	<div class="card-body">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<div>
				<h3 class="mb-0">Delete Category</h3>
				<div class="text-muted small">Confirm permanent deletion</div>
			</div>
			<div>
				<a class="btn btn-outline-secondary btn-sm" href="categories.php">Back to categories</a>
			</div>
		</div>

		<div class="row">
			<div class="col-12 col-md-6">
				<div class="mb-3"><strong>Name:</strong> <?= htmlspecialchars($cat['name']) ?></div>
				<?php if (!empty($cat['description'])): ?>
					<div class="mb-3"><strong>Description:</strong><div class="text-muted"><?= nl2br(htmlspecialchars($cat['description'])) ?></div></div>
				<?php endif; ?>
			</div>
			<div class="col-12 col-md-6">
				<?php if (!empty($cat['image_path'])): ?>
					<img src="<?= htmlspecialchars($cat['image_path']) ?>" style="max-width:100%;height:auto;object-fit:cover;border-radius:.375rem;" alt="Category image">
				<?php else: ?>
					<div style="height:160px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;color:#9ca3af;border-radius:.375rem;">No image</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="mt-4 d-flex gap-2">
			<form method="post" onsubmit="return confirm('Delete this category permanently?')">
				<input type="hidden" name="id" value="<?= $cat['id'] ?>">
				<button class="btn btn-danger">Delete permanently</button>
			</form>
			<a class="btn btn-secondary" href="categories.php">Cancel</a>
		</div>
	</div>
</div>

<?php include __DIR__ . '/admin_footer.php'; ?>
