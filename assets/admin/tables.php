<?php
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../db_rest.php';

$pdo = get_rest_db();

// Ensure legacy installs have the newer columns used by booking/room pages
try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS capacity INT DEFAULT 1"); } catch (Throwable $__ignored) {}
try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS bed_type VARCHAR(100) DEFAULT ''"); } catch (Throwable $__ignored) {}
// Ensure is_room flag exists to differentiate rooms vs tables
try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS is_room TINYINT(1) DEFAULT 0"); } catch (Throwable $__ignored) {
    // Older MySQL may not support IF NOT EXISTS - try a plain ADD and ignore errors
    try { $pdo->exec("ALTER TABLE tables_info ADD COLUMN is_room TINYINT(1) DEFAULT 0"); } catch (Throwable $__ignored2) {}
}

$tables = [];
try {
    // prefer explicit column list (newer schema)
    $tables = $pdo->query("SELECT id, table_number, table_type, status, price, capacity, bed_type, image_path FROM tables_info WHERE COALESCE(is_room,0)=0 ORDER BY id DESC")->fetchAll();
} catch (Throwable $e) {
    // fallback to a generic select if older schema doesn't support the columns
    $tables = $pdo->query('SELECT * FROM tables_info ORDER BY id DESC')->fetchAll();
}
?>
<?php $page_title='Tables'; include __DIR__ . '/admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Tables</h3>
    <div>
      <a class="btn btn-primary me-2" href="table_add.php">Add Table</a>
      <a class="btn btn-outline-primary" href="crearoom.php">Create Room</a>
    </div>
</div>
<div class="row g-3">
    <?php foreach($tables as $t): ?>
        <div class="col-12 col-md-4">
            <div class="card <?php echo ($t['status'] ?? '')==='Available' ? 'border-success':'border-danger'; ?>">
                <?php if (!empty($t['image_path'])): ?><img src="<?=htmlspecialchars($t['image_path'])?>" class="card-img-top" style="height:160px;object-fit:cover"><?php endif; ?>
                <div class="card-body">
                    <h5 class="card-title"><?=htmlspecialchars($t['table_number'] ?? '')?> <small class="text-muted"><?=htmlspecialchars($t['table_type'] ?? '')?></small></h5>
                    <?php if (isset($t['price'])): ?><p class="mb-1">Price: $<?=number_format((float)($t['price'] ?? 0),2)?></p><?php endif; ?>
                    <?php if (isset($t['capacity'])): ?><p class="mb-1">Capacity: <?=htmlspecialchars($t['capacity'] ?? '')?> &middot; Bed: <?=htmlspecialchars($t['bed_type'] ?? '')?></p><?php endif; ?>
                    <p class="card-text"><?=htmlspecialchars($t['status'] ?? '')?></p>
                    <div class="mt-2">
                        <a class="btn btn-sm btn-secondary" href="table_edit.php?id=<?=$t['id']?>">Edit</a>
                        <a class="btn btn-sm btn-primary ms-1" href="booking_add.php?room_id=<?=$t['id']?>">Book</a>
                        <a class="btn btn-sm btn-danger ms-1" href="table_delete.php?id=<?=$t['id']?>" onclick="return confirm('Delete table?')">Delete</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/admin_footer.php'; ?>

