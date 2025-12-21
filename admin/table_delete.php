<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php'; require_once __DIR__ . '/../upload.php';
$pdo = get_rest_db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
	if ($id) {
		// prevent deleting a tables_info row that is linked to a room
		$linked = $pdo->prepare('SELECT COUNT(*) AS cnt FROM rooms WHERE tables_info_id = ?');
		$linked->execute([$id]); $l = $linked->fetch();
		if ($l && $l['cnt'] > 0) {
			header('Location: tables.php?error=linked'); exit;
		}
		// delete image if present
		$stmt = $pdo->prepare('SELECT image_path FROM tables_info WHERE id=? LIMIT 1'); $stmt->execute([$id]); $row = $stmt->fetch();
		if ($row && !empty($row['image_path'])) delete_local_upload($row['image_path']);
		$stmt = $pdo->prepare('DELETE FROM tables_info WHERE id=?'); $stmt->execute([$id]);
	}
	header('Location: tables.php'); exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header('Location: tables.php'); exit; }
$stmt = $pdo->prepare('SELECT * FROM tables_info WHERE id=? LIMIT 1'); $stmt->execute([$id]); $t = $stmt->fetch();
if (!$t) { header('Location: tables.php'); exit; }
?>
<?php $page_title='Delete Table'; include __DIR__ . '/admin_header.php'; ?>

<div class="card">
	<div class="card-body">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<div>
				<h3 class="mb-0">Delete Table</h3>
				<div class="text-muted small">Confirm permanent deletion</div>
			</div>
			<div>
				<a class="btn btn-outline-secondary btn-sm" href="tables.php">Back to tables</a>
			</div>
		</div>

		<div class="row">
			<div class="col-12 col-md-6">
				<div class="mb-3"><strong>Table:</strong> <?= htmlspecialchars($t['table_number'] ?? '') ?> <div class="text-muted"><?= htmlspecialchars($t['table_type'] ?? '') ?></div></div>
				<?php if (!empty($t['capacity'])): ?>
					<div class="mb-3"><strong>Capacity:</strong> <?= htmlspecialchars($t['capacity']) ?></div>
				<?php endif; ?>
			</div>
			<div class="col-12 col-md-6">
				<?php if (!empty($t['image_path'])): ?>
					<img src="<?= htmlspecialchars($t['image_path']) ?>" style="max-width:100%;height:auto;object-fit:cover;border-radius:.375rem;" alt="Table image">
				<?php else: ?>
					<div style="height:160px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;color:#9ca3af;border-radius:.375rem;">No image</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="mt-4 d-flex gap-2">
			<form method="post" onsubmit="return confirm('Delete this table permanently?')">
				<input type="hidden" name="id" value="<?= $t['id'] ?>">
				<button class="btn btn-danger">Delete permanently</button>
			</form>
			<a class="btn btn-secondary" href="tables.php">Cancel</a>
		</div>
	</div>
</div>

<?php include __DIR__ . '/admin_footer.php'; ?>
