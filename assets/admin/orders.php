<?php
require_once __DIR__ . '/../auth.php'; 
require_admin(); 
require_once __DIR__ . '/../db_rest.php';

$pdo = get_rest_db();

// Handle POST actions: update status or delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['action']) && $_POST['action'] === 'update_status' && !empty($_POST['order_id'])) {
        $id = (int)$_POST['order_id'];
        $status = $_POST['status'] ?? 'Pending';
        $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
        header('Location: orders.php?updated=1'); 
        exit;
    }
    
    if (!empty($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['order_id'])) {
        $id = (int)$_POST['order_id'];
        $stmt = $pdo->prepare('DELETE FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        header('Location: orders.php?deleted=1'); 
        exit;
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build WHERE clause
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = '(customer_name LIKE ? OR customer_phone LIKE ? OR customer_email LIKE ? OR id = ?)';
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    if (is_numeric($search)) {
        $params[] = (int)$search;
    } else {
        $params[] = 0; // Ensure parameter count matches
    }
}

if (!empty($status_filter) && $status_filter !== 'all') {
    $where[] = 'status = ?';
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $where[] = 'DATE(order_date) = ?';
    $params[] = $date_filter;
}

// Build SQL query
$sql = 'SELECT * FROM orders';
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY order_date DESC, id DESC';

// Execute query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get statistics
$stats_sql = 'SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = "Completed" THEN 1 ELSE 0 END) as completed_orders,
    SUM(CASE WHEN status = "Pending" THEN 1 ELSE 0 END) as pending_orders,
    SUM(CASE WHEN status = "Processing" THEN 1 ELSE 0 END) as processing_orders,
    SUM(total_amount) as total_revenue
    FROM orders';

if (!empty($where)) {
    $stats_sql .= ' WHERE ' . implode(' AND ', $where);
}

$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch();

$page_title = 'Orders'; 
include __DIR__ . '/admin_header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0">Orders Management</h3>
            <p class="text-muted mb-0">Manage all customer orders</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="dashboard.php">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
            <a class="btn btn-primary" href="order_create.php">
                <i class="fas fa-plus-circle me-2"></i> Create Order
            </a>
        </div>
    </div>

    <!-- Success Messages -->
    <?php if (!empty($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> Order status updated successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['deleted'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-trash-alt me-2"></i> Order deleted successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['created']) && !empty($_GET['order_id'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> New order created successfully. 
            <a href="order_receipt.php?order_id=<?= (int)$_GET['order_id'] ?>" class="ms-2">View Receipt</a>
            <a href="order_edit.php?order_id=<?= (int)$_GET['order_id'] ?>" class="ms-2">Edit Order</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['order_id']) && !empty($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> Order #<?= (int)$_GET['order_id'] ?> updated successfully. 
            <a href="order_receipt.php?order_id=<?= (int)$_GET['order_id'] ?>" class="ms-2">View Receipt</a>
            <a href="order_edit.php?order_id=<?= (int)$_GET['order_id'] ?>" class="ms-2">Edit Order</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Total Orders</h6>
                            <h3 class="mb-0"><?= $stats['total_orders'] ?? 0 ?></h3>
                        </div>
                        <i class="fas fa-shopping-cart fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Completed</h6>
                            <h3 class="mb-0"><?= $stats['completed_orders'] ?? 0 ?></h3>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Pending</h6>
                            <h3 class="mb-0"><?= $stats['pending_orders'] ?? 0 ?></h3>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">Total Revenue</h6>
                            <h3 class="mb-0"><?= format_npr($stats['total_revenue'] ?? 0) ?></h3>
                        </div>
                        <i class="fas fa-rupee-sign fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by ID, name, phone or email" value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all">All Status</option>
                        <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Processing" <?= $status_filter === 'Processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="Delivered" <?= $status_filter === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="Cancelled" <?= $status_filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-flex gap-2 w-100">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="fas fa-filter me-2"></i> Filter
                        </button>
                        <a href="orders.php" class="btn btn-outline-secondary">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Table</th>
                            <th>Date</th>
                            <th class="pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="fas fa-inbox fa-2x text-muted mb-3"></i>
                                    <p class="text-muted mb-0">No orders found</p>
                                    <?php if (!empty($search) || !empty($status_filter) || !empty($date_filter)): ?>
                                        <small class="text-muted">Try adjusting your filters</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): 
                                $items = json_decode($order['items_json'] ?? '[]', true);
                                $items_summary = [];
                                if (is_array($items)) {
                                    foreach (array_slice($items, 0, 3) as $item) {
                                        $items_summary[] = htmlspecialchars($item['name'] ?? '') . ' ×' . intval($item['qty'] ?? 0);
                                    }
                                    if (count($items) > 3) {
                                        $items_summary[] = '... +' . (count($items) - 3) . ' more';
                                    }
                                }
                                $status_class = match($order['status']) {
                                    'Completed' => 'success',
                                    'Processing' => 'warning',
                                    'Pending' => 'secondary',
                                    'Delivered' => 'info',
                                    'Cancelled' => 'danger',
                                    default => 'secondary'
                                };
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold">#<?= $order['id'] ?></td>
                                <td>
                                    <?php if (!empty($order['customer_name'])): ?>
                                        <div class="fw-medium"><?= htmlspecialchars($order['customer_name']) ?></div>
                                        <?php if (!empty($order['customer_phone'])): ?>
                                            <small class="text-muted"><?= htmlspecialchars($order['customer_phone']) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Walk-in Customer</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($items_summary)): ?>
                                        <small><?= implode(', ', $items_summary) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">No items</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold"><?= format_npr($order['total_amount'] ?? 0) ?></span><br>
                                    <small class="text-muted">Qty: <?= $order['total_quantity'] ?? 0 ?></small>
                                </td>
                                <td>
                                    <form method="post" class="d-flex gap-1">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <select name="status" class="form-select form-select-sm status-select" data-order-id="<?= $order['id'] ?>">
                                            <?php foreach (['Pending', 'Processing', 'Completed', 'Delivered', 'Cancelled'] as $status): ?>
                                                <option value="<?= $status ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-save"></i>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <?php if (!empty($order['payment_method'])): ?>
                                        <span class="badge bg-light text-dark"><?= htmlspecialchars($order['payment_method']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($order['table_number'])): ?>
                                        <span class="badge bg-info"><?= htmlspecialchars($order['table_number']) ?></span>
                                        <?php if (!empty($order['table_type'])): ?>
                                            <small class="d-block text-muted"><?= htmlspecialchars($order['table_type']) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="d-block"><?= date('d M Y', strtotime($order['order_date'])) ?></small>
                                    <small class="text-muted"><?= date('h:i A', strtotime($order['order_date'])) ?></small>
                                </td>
                                <td class="pe-4">
                                    <div class="d-flex gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="orders_view.php?id=<?= $order['id'] ?>" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a class="btn btn-sm btn-outline-secondary" href="order_edit.php?order_id=<?= $order['id'] ?>" title="Edit Order">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a class="btn btn-sm btn-outline-info" href="order_receipt.php?order_id=<?= $order['id'] ?>" target="_blank" title="Print Receipt">
                                            <i class="fas fa-receipt"></i>
                                        </a>
                                        <form method="post" onsubmit="return confirm('Are you sure you want to delete order #<?= $order['id'] ?>? This action cannot be undone.')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Order">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Order Count -->
    <div class="mt-3 text-end">
        <small class="text-muted">Showing <?= count($orders) ?> order(s)</small>
    </div>
</div>

<style>
.status-select {
    min-width: 120px;
}

.table th {
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.table td {
    vertical-align: middle;
}

.badge {
    font-weight: 500;
}

.form-select-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
}

@media (max-width: 768px) {
    .d-flex.gap-1 {
        flex-wrap: wrap;
    }
    
    .status-select {
        min-width: 100px;
    }
}
</style>

<script>
// Auto-submit status change on select change
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.status-select').forEach(select => {
        select.addEventListener('change', function() {
            // Highlight the select when changed
            this.classList.add('border-primary');
            
            // Find the form and submit button
            const form = this.closest('form');
            const submitBtn = form.querySelector('button[type="submit"]');
            
            // Trigger click on submit button
            submitBtn.classList.add('btn-primary');
            submitBtn.classList.remove('btn-outline-secondary');
            submitBtn.innerHTML = '<i class="fas fa-check"></i>';
            
            // Optional: auto-submit after 500ms
            setTimeout(() => {
                form.submit();
            }, 500);
        });
    });
    
    // Add confirmation for delete buttons
    document.querySelectorAll('form[onsubmit]').forEach(form => {
        const deleteBtn = form.querySelector('button[type="submit"]');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this order?')) {
                    e.preventDefault();
                }
            });
        }
    });
    
    // Show success message fade out
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-danger)');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 3000);
        });
    }, 1000);
});
</script>

<?php include __DIR__ . '/admin_footer.php'; ?>