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
        <div>
            <h3 class="mb-0">Tables</h3>
            <div class="text-muted small">Manage restaurant tables</div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <input id="tableSearch" class="form-control form-control-sm admin-search" placeholder="Search tables...">
            <a class="btn btn-primary" href="table_add.php">Add Table</a>
        </div>
</div>
<div class="row g-3" id="tablesGrid">
        <?php foreach($tables as $t): ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 table-item" data-number="<?=htmlspecialchars(strtolower($t['table_number'] ?? ''))?>" data-type="<?=htmlspecialchars(strtolower($t['table_type'] ?? ''))?>">
                        <div class="card table-card <?php echo ($t['status'] ?? '')==='Available' ? 'border-success':'border-danger'; ?> h-100">
                                <?php if (!empty($t['image_path'])): ?>
                                    <img src="<?=htmlspecialchars($t['image_path'])?>" alt="Table image">
                                <?php else: ?>
                                    <div class="no-image"></div>
                                <?php endif; ?>
                                <div class="card-body">
                                        <div class="table-title"><?=htmlspecialchars($t['table_number'] ?? '')?> <small class="text-muted"><?=htmlspecialchars($t['table_type'] ?? '')?></small></div>
                                        <?php if (isset($t['capacity'])): ?><div class="table-meta mb-1">Capacity: <?=htmlspecialchars($t['capacity'] ?? '')?> &middot; Bed: <?=htmlspecialchars($t['bed_type'] ?? '')?></div><?php endif; ?>
                                        <div class="mb-2">Status: <strong><?=htmlspecialchars($t['status'] ?? '')?></strong></div>
                                        <div class="table-actions">
                                                <a class="btn btn-sm btn-secondary" href="table_edit.php?id=<?=$t['id']?>">Edit</a>
                                                <a class="btn btn-sm btn-danger" href="table_delete.php?id=<?=$t['id']?>">Delete</a>
                                        </div>
                                </div>
                        </div>
                </div>
        <?php endforeach; ?>
</div>

<script>
    // Client-side search that filters table cards
    (function(){
        const input = document.getElementById('tableSearch');
        if (!input) return;
        input.addEventListener('input', function(){
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('.table-item').forEach(function(card){
                const number = card.getAttribute('data-number') || '';
                const type = card.getAttribute('data-type') || '';
                const match = q === '' || number.indexOf(q) !== -1 || type.indexOf(q) !== -1;
                card.style.display = match ? '' : 'none';
            });
        });
    })();
</script>

<?php include __DIR__ . '/admin_footer.php'; ?>

