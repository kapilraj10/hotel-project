<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php';

$pdo = get_rest_db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
  // No specific room requested - redirect to rooms list
  header('Location: rooms.php'); exit;
}

// Fetch room
try {
  $stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ? LIMIT 1');
  $stmt->execute([$id]);
  $room = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $room = false;
}

$page_title = $room ? ('Room ' . htmlspecialchars($room['room_number'])) : 'Room';
include __DIR__ . '/admin_header.php';
?>
<div class="page-header">
  <h1><?= $page_title ?></h1>
  <div>
    <a href="roomedit.php?id=<?= $id ?>" class="btn btn-add"><i class="fas fa-edit"></i> Edit</a>
    <a href="rooms.php" class="btn btn-outline-secondary">Back to Rooms</a>
  </div>
</div>

<?php if (!$room): ?>
  <div class="alert alert-warning">Room not found.</div>
<?php else: ?>
  <div class="card mb-3">
    <div class="row g-0">
      <div class="col-md-4">
        <?php
        // Resolve image URL: prefer uploads/rooms/<filename>, fall back to stored path or linked tables_info image
        $imgUrl = null;
        if (!empty($room['image'])) {
            $uploadsDir = rtrim(UPLOADS_DIR, '/') . '/rooms/';
            $uploadsUrl = rtrim(UPLOADS_URL, '/') . '/rooms/';
            if (file_exists($uploadsDir . $room['image'])) {
                $imgUrl = $uploadsUrl . $room['image'];
            } elseif (strpos($room['image'], '/') === 0 || preg_match('#^https?://#i', $room['image'])) {
                // stored as a full/absolute path or URL
                $imgUrl = $room['image'];
            }
        }
        // If still null, try linked tables_info image_path
        if (empty($imgUrl) && !empty($room['tables_info_id'])) {
            try {
                $tstmt = $pdo->prepare('SELECT image_path FROM tables_info WHERE id = ? LIMIT 1');
                $tstmt->execute([(int)$room['tables_info_id']]);
                $ti = $tstmt->fetch(PDO::FETCH_ASSOC);
                if ($ti && !empty($ti['image_path'])) {
                    $uploadsDir = rtrim(UPLOADS_DIR, '/') . '/rooms/';
                    $uploadsUrl = rtrim(UPLOADS_URL, '/') . '/rooms/';
                    if (file_exists($uploadsDir . $ti['image_path'])) {
                        $imgUrl = $uploadsUrl . $ti['image_path'];
                    } else {
                        $imgUrl = $ti['image_path'];
                    }
                }
            } catch (Throwable $__ignored) {}
        }
        if (!empty($imgUrl)): ?>
          <img src="<?= htmlspecialchars($imgUrl) ?>" class="img-fluid rounded-start" alt="Room image">
        <?php endif; ?>
      </div>
      <div class="col-md-8">
        <div class="card-body">
          <h5 class="card-title"><?= htmlspecialchars($room['room_name']) ?></h5>
          <p class="card-text"><?= nl2br(htmlspecialchars($room['description'] ?? '')) ?></p>
          <p class="card-text"><small class="text-muted">Type: <?= htmlspecialchars($room['room_type']) ?> • Capacity: <?= htmlspecialchars($room['capacity']) ?></small></p>
          <p class="card-text"><strong>Price:</strong> $<?= number_format((float)$room['price'],2) ?></p>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/admin_footer.php';
