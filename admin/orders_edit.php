<?php
// orders_edit.php - Edit Order Page
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../db_rest.php';

$pdo = get_rest_db();

// Get order ID from URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$order_id) {
    header('Location: orders.php');
    exit;
}

// Fetch order details
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Initialize variables
$errors = [];
$success_message = '';

// Handle POST update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $customer_address = trim($_POST['customer_address'] ?? '');
    $status = $_POST['status'] ?? 'Pending';
    $payment_method = $_POST['payment_method'] ?? 'None';
    $table_number = trim($_POST['table_number'] ?? '');
    $order_type = $_POST['order_type'] ?? 'Dine-in';
    $order_note = trim($_POST['order_note'] ?? '');
    
    // Customer name and phone are optional; no required validation
    
    // If no errors, update order
    if (empty($errors)) {
        try {
        // Update the order (avoid updating non-existent columns)
        $sql = 'UPDATE orders SET 
            customer_name = ?,
            customer_phone = ?,
            customer_email = ?,
            customer_address = ?,
            status = ?,
            payment_method = ?,
            table_number = ?,
            order_type = ?,
            order_note = ?
            WHERE id = ?';
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $customer_name,
                $customer_phone,
                $customer_email,
                $customer_address,
                $status,
                $payment_method,
                $table_number ?: null,
                $order_type,
                $order_note,
                $order_id
            ]);
            
            $success_message = 'Order updated successfully!';
            
            // Refresh order data
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get tables for dropdown
$tables = $pdo->query('SELECT table_number FROM tables_info ORDER BY table_number')->fetchAll(PDO::FETCH_COLUMN);

// Get order items and resolve prices/totals (fallback to items table when price missing)
$order_items = json_decode($order['items_json'] ?? '[]', true);

// Compute resolved prices and totals so the UI shows correct values even if historical JSON lacks price
$order_summary_total_qty = 0;
$order_summary_subtotal = 0;
$itemPriceCache = [];
foreach ($order_items as $oi => $it) {
    $resolved_price = floatval($it['price'] ?? 0);

    if (($resolved_price === 0 || empty($it['price'])) && !empty($it['id'])) {
        $iid = (int)$it['id'];
        if (isset($itemPriceCache[$iid])) {
            $resolved_price = $itemPriceCache[$iid];
        } else {
            try {
                $pstmt = $pdo->prepare('SELECT price FROM items WHERE id = ? LIMIT 1');
                $pstmt->execute([$iid]);
                $prow = $pstmt->fetch();
                $resolved_price = $prow ? floatval($prow['price']) : 0;
            } catch (Throwable $e) {
                $resolved_price = 0;
            }
            $itemPriceCache[$iid] = $resolved_price;
        }
    }

    $resolved_qty = intval($it['qty'] ?? 1);
    $resolved_total = $resolved_price * $resolved_qty;

    $order_items[$oi]['_resolved_price'] = $resolved_price;
    $order_items[$oi]['_resolved_total'] = $resolved_total;

    $order_summary_total_qty += $resolved_qty;
    $order_summary_subtotal += $resolved_total;
}

// Ensure format_npr fallback exists
if (!function_exists('format_npr')) {
    function format_npr($amount) {
        return 'रू ' . number_format((float)($amount ?? 0), 2);
    }
}

$page_title = 'Edit Order #' . $order_id;
include __DIR__ . '/admin_header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0">Edit Order #<?= $order_id ?></h3>
            <p class="text-muted mb-0">Update order information</p>
        </div>
        <div class="d-flex gap-2">
            <a href="orders.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Orders
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4">
            <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4">
            <i class="fas fa-exclamation-circle me-2"></i> Please fix the following errors:
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Order Details Form -->
        <div class="col-lg-8">
            <form method="post" id="editOrderForm">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Order Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Order ID</label>
                                <input type="text" class="form-control" value="#<?= $order_id ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Order Date</label>
                                <input type="text" class="form-control" 
                                       value="<?= date('d M Y, h:i A', strtotime($order['order_date'])) ?>" 
                                       readonly>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Status *</label>
                                <select name="status" class="form-select" required>
                                    <option value="Pending" <?= $order['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Processing" <?= $order['status'] == 'Processing' ? 'selected' : '' ?>>Processing</option>
                                    <option value="Completed" <?= $order['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="Delivered" <?= $order['status'] == 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                    <option value="Cancelled" <?= $order['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Payment Method *</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="None" <?= $order['payment_method'] == 'None' ? 'selected' : '' ?>>None</option>
                                    <option value="Cash" <?= $order['payment_method'] == 'Cash' ? 'selected' : '' ?>>Cash</option>
                                    <option value="Card" <?= $order['payment_method'] == 'Card' ? 'selected' : '' ?>>Card</option>
                                    <option value="Online" <?= $order['payment_method'] == 'Online' ? 'selected' : '' ?>>Online</option>
                                    <option value="Bank Transfer" <?= $order['payment_method'] == 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Table Number</label>
                                <select name="table_number" class="form-select">
                                    <option value="">-- No Table (Takeaway) --</option>
                                    <?php foreach ($tables as $table): ?>
                                        <option value="<?= htmlspecialchars($table) ?>" 
                                                <?= $order['table_number'] == $table ? 'selected' : '' ?>>
                                            Table <?= htmlspecialchars($table) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Order Type</label>
                                <select name="order_type" class="form-select">
                                    <option value="Dine-in" <?= ($order['order_type'] ?? 'Dine-in') == 'Dine-in' ? 'selected' : '' ?>>Dine-in</option>
                                    <option value="Takeaway" <?= ($order['order_type'] ?? 'Dine-in') == 'Takeaway' ? 'selected' : '' ?>>Takeaway</option>
                                    <option value="Delivery" <?= ($order['order_type'] ?? 'Dine-in') == 'Delivery' ? 'selected' : '' ?>>Delivery</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Order Note</label>
                                <textarea name="order_note" class="form-control" rows="3" 
                                          placeholder="Special instructions, allergies, etc."><?= htmlspecialchars($order['order_note'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
<!--                 
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Customer Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Customer Name</label>
                                <input type="text" name="customer_name" class="form-control" 
                                       value="<?= htmlspecialchars($order['customer_name'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="customer_phone" class="form-control" 
                                       value="<?= htmlspecialchars($order['customer_phone'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="customer_email" class="form-control" 
                                       value="<?= htmlspecialchars($order['customer_email'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Address</label>
                                <input type="text" name="customer_address" class="form-control" 
                                       value="<?= htmlspecialchars($order['customer_address'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div> -->
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Order Summary -->
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Order Items</h6>
                            <span class="badge bg-primary"><?= count($order_items) ?> items</span>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Qty</th>
                                        <th>Price</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_qty = 0;
                                    $subtotal = 0;
                                    foreach ($order_items as $item): 
                                        $item_qty = intval($item['qty'] ?? 1);
                                        $item_price = floatval($item['_resolved_price'] ?? $item['price'] ?? 0);
                                        $item_total = floatval($item['_resolved_total'] ?? ($item_price * $item_qty));
                                        $total_qty += $item_qty;
                                        $subtotal += $item_total;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['name'] ?? 'Item') ?></td>
                                        <td><?= $item_qty ?></td>
                                        <td><?= format_npr($item_price) ?></td>
                                        <td><?= format_npr($item_total) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="2">Total</th>
                                        <th><?= $order_summary_total_qty ?> items</th>
                                        <th><?= format_npr($order_summary_subtotal) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <div class="border-top pt-3">
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-2">
                                    <small class="text-muted d-block">Order Total</small>
                                    <h4 class="mb-0"><?= format_npr($order['total_amount']) ?></h4>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-2">
                                    <small class="text-muted d-block">Total Quantity</small>
                                    <h4 class="mb-0"><?= $total_qty ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3">Quick Actions</h6>
                    <div class="d-grid gap-2">
                        <!-- <a href="order_receipt.php?order_id=<?= $order_id ?>" 
                           target="_blank" 
                           class="btn btn-outline-primary">
                            <i class="fas fa-receipt me-2"></i> Print Receipt
                        </a> -->
                        
                        <a href="orders.php" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-2"></i> View All Orders
                        </a>
                        
                        <button type="button" class="btn btn-outline-danger" id="deleteOrderBtn">
                            <i class="fas fa-trash me-2"></i> Delete Order
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete order <strong>#<?= $order_id ?></strong>?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Order</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('editOrderForm');
    form.addEventListener('submit', function(e) {
        // Customer name and phone are optional; do not block submission here.
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
        submitBtn.disabled = true;
    });
    
    // Delete order functionality
    const deleteBtn = document.getElementById('deleteOrderBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function() {
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        });
    }
    
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            // Send AJAX request to delete order
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=1&action=delete_order&order_id=<?= $order_id ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal
                    const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                    if (deleteModal) deleteModal.hide();
                    
                    // Redirect to orders page
                    window.location.href = 'orders.php?deleted=1';
                } else {
                    alert('Failed to delete order: ' + data.message);
                }
            })
            .catch(error => {
                alert('Network error: ' + error.message);
            });
        });
    }
    
    // Status badge update
    function updateStatusBadge() {
        const statusSelect = document.querySelector('select[name="status"]');
        const currentValue = statusSelect.value;
        const statusColors = {
            'Pending': 'warning',
            'Processing': 'info',
            'Completed': 'success',
            'Delivered': 'primary',
            'Cancelled': 'danger'
        };
        
        // You can add visual feedback when status changes
        statusSelect.style.borderColor = '#0d6efd';
        setTimeout(() => {
            statusSelect.style.borderColor = '';
        }, 1000);
    }
    
    // Add change event to status select
    const statusSelect = document.querySelector('select[name="status"]');
    if (statusSelect) {
        statusSelect.addEventListener('change', updateStatusBadge);
    }
});
</script>

<style>
.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.form-control:focus, .form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.btn-lg {
    padding: 0.75rem 1.5rem;
    font-size: 1.1rem;
}

.table-sm th, .table-sm td {
    padding: 0.5rem;
}

.badge {
    font-weight: 500;
}

@media (max-width: 768px) {
    .col-lg-8, .col-lg-4 {
        width: 100%;
    }
    
    .btn-lg {
        width: 100%;
    }
}
</style>

<?php include __DIR__ . '/admin_footer.php'; ?>