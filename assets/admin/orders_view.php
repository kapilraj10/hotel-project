<?php
// orders_view.php - View order details
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../db_rest.php';

$pdo = get_rest_db();
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$order_id) {
    header('Location: orders.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

$items = json_decode($order['items_json'], true) ?: [];

$computed_total_note = '';
// Calculate totals (be resilient if items_json lacks embedded price)
$subtotal = 0;
$total_quantity = 0;
$itemPriceCache = []; // cache DB lookups by item id
foreach ($items as $idx => $item) {
    $item_price = floatval($item['price'] ?? 0);
    // if price missing or zero, try to fetch from items table using id
    if (($item_price === 0 || empty($item['price'])) && !empty($item['id'])) {
        $iid = (int)$item['id'];
        if (isset($itemPriceCache[$iid])) {
            $item_price = $itemPriceCache[$iid];
        } else {
            try {
                $pstmt = $pdo->prepare('SELECT price FROM items WHERE id = ? LIMIT 1');
                $pstmt->execute([$iid]);
                $prow = $pstmt->fetch();
                $item_price = $prow ? floatval($prow['price']) : 0;
            } catch (Throwable $e) {
                $item_price = 0;
            }
            $itemPriceCache[$iid] = $item_price;
        }
    }

    $item_qty = intval($item['qty'] ?? 0);
    $item_total = $item_price * $item_qty;

    // store resolved values back into items array so rendering can reuse them
    $items[$idx]['_resolved_price'] = $item_price;
    $items[$idx]['_resolved_total'] = $item_total;

    $subtotal += $item_total;
    $total_quantity += $item_qty;
}

// Determine grand total: prefer stored total_amount, otherwise compute from parts
$tax = floatval($order['tax_amount'] ?? 0);
$discount = floatval($order['discount_amount'] ?? 0);
$grand_total = isset($order['total_amount']) ? floatval($order['total_amount']) : 0;
if (empty($grand_total)) {
    $grand_total = $subtotal + $tax - $discount;
    $computed_total_note = ' (computed)';
}

$page_title = 'View Order #' . $order_id;
include __DIR__ . '/admin_header.php';

// Ensure format_npr exists (fallback) so amounts always render
if (!function_exists('format_npr')) {
    function format_npr($amount) {
        return 'रू ' . number_format((float)($amount ?? 0), 2);
    }
}
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0">Order Details #<?= $order_id ?></h3>
            <p class="text-muted mb-0">View complete order information</p>
        </div>
        <div class="d-flex gap-2">
            <a href="orders.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Orders
            </a>
            <a href="orders_edit.php?id=<?= $order_id ?>" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i> Edit Order
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Order Items -->
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Order Items</h5>
                        <span class="badge bg-primary"><?= count($items) ?> items</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($items)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No items in this order</h5>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Item Name</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $counter = 1;
                                    foreach ($items as $item): 
                                        $item_name  = $item['name'] ?? 'Unknown Item';
                                        $item_qty   = intval($item['qty'] ?? 0);
                                        $item_price = floatval($item['_resolved_price'] ?? $item['price'] ?? 0);
                                        $item_total = floatval($item['_resolved_total'] ?? ($item_price * $item_qty));
                                    ?>

                                    <tr>
                                        <td class="fw-bold"><?= $counter++ ?></td>
                                        <td><?= htmlspecialchars($item_name) ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary"><?= $item_qty ?></span>
                                        </td>
                                        <td class="text-end"><?= format_npr($item_price) ?></td>
                                        <td class="text-end fw-bold"><?= format_npr($item_total ?? 0) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="2" class="text-end fw-bold">Subtotal:</td>
                                        <td class="text-center fw-bold"><?= $total_quantity ?> items</td>
                                        <td></td>
                                        <td class="text-end fw-bold"><?= format_npr($subtotal ?? 0) ?></td>
                                    </tr>
                                    <?php if (!empty($order['tax_amount']) && $order['tax_amount'] > 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold">Tax:</td>
                                        <td class="text-end fw-bold"><?= format_npr($order['tax_amount'] ?? 0) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($order['discount_amount']) && $order['discount_amount'] > 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold">Discount:</td>
                                        <td class="text-end fw-bold text-danger">-<?= format_npr($order['discount_amount'] ?? 0) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold fs-5">Grand Total:</td>
                                        <td class="text-end fw-bold fs-5 text-primary"><?= format_npr($grand_total ?? 0) ?><?= $computed_total_note ? '<br><small class="text-muted">' . htmlspecialchars($computed_total_note) . '</small>' : '' ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Order & Customer Info -->
        <div class="col-lg-4">
            <!-- Order Summary Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <div class="text-muted small">Order ID</div>
                            <div class="fw-bold">#<?= $order_id ?></div>
                        </div>
                        <div class="col-6 text-end">
                            <div class="text-muted small">Order Date</div>
                            <div class="fw-bold"><?= date('d M Y', strtotime($order['order_date'])) ?></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="text-muted small">Status</div>
                        <?php
                        $status_badge = match($order['status']) {
                            'Pending' => 'warning',
                            'Processing' => 'info',
                            'Completed' => 'success',
                            'Delivered' => 'primary',
                            'Cancelled' => 'danger',
                            default => 'secondary'
                        };
                        ?>
                        <span class="badge bg-<?= $status_badge ?>"><?= $order['status'] ?></span>
                    </div>
                    
                    <div class="mb-3">
                        <div class="text-muted small">Payment Method</div>
                        <div class="fw-bold"><?= $order['payment_method'] ?? 'Not specified' ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="text-muted small">Order Type</div>
                        <div class="fw-bold"><?= $order['order_type'] ?? 'Dine-in' ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="text-muted small">Table</div>
                        <div class="fw-bold">
                            <?php if (!empty($order['table_number'])): ?>
                                Table <?= htmlspecialchars($order['table_number']) ?>
                                <?php if (!empty($order['table_type'])): ?>
                                    <span class="text-muted">(<?= htmlspecialchars($order['table_type']) ?>)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Takeaway / No table</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="text-muted small">Time</div>
                        <div class="fw-bold"><?= date('h:i A', strtotime($order['order_date'])) ?></div>
                    </div>
                    
                    <?php if (!empty($order['order_note'])): ?>
                    <div class="mt-3 pt-3 border-top">
                        <div class="text-muted small">Order Note</div>
                        <div class="fw-light"><?= nl2br(htmlspecialchars($order['order_note'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Customer Information Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Customer Information</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($order['customer_name']) && empty($order['customer_phone'])): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-user fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">Walk-in Customer</p>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <div class="text-muted small">Name</div>
                            <div class="fw-bold"><?= htmlspecialchars($order['customer_name'] ?? 'Not specified') ?></div>
                        </div>
                        
                        <?php if (!empty($order['customer_phone'])): ?>
                        <div class="mb-3">
                            <div class="text-muted small">Phone</div>
                            <div class="fw-bold">
                                <a href="tel:<?= htmlspecialchars($order['customer_phone']) ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($order['customer_phone']) ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($order['customer_email'])): ?>
                        <div class="mb-3">
                            <div class="text-muted small">Email</div>
                            <div class="fw-bold">
                                <a href="mailto:<?= htmlspecialchars($order['customer_email']) ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($order['customer_email']) ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($order['customer_address'])): ?>
                        <div class="mt-3 pt-3 border-top">
                            <div class="text-muted small">Address</div>
                            <div class="fw-light"><?= nl2br(htmlspecialchars($order['customer_address'])) ?></div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <h6 class="mb-3">Quick Actions</h6>
                    <div class="d-grid gap-2">
                        <a href="orders_edit.php?id=<?= $order_id ?>" class="btn btn-outline-primary">
                            <i class="fas fa-edit me-2"></i> Edit Order
                        </a>
                        <?php if ($order['status'] !== 'Completed' && $order['status'] !== 'Cancelled'): ?>
                            <a href="orders.php?action=complete&id=<?= $order_id ?>" class="btn btn-outline-warning">
                                <i class="fas fa-check-circle me-2"></i> Mark as Completed
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Timestamps -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3">Timestamps</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-muted small">Created</div>
                            <div class="fw-bold">
                                <?= date('d M Y, h:i A', strtotime($order['created_at'] ?? $order['order_date'])) ?>
                            </div>
                        </div>
                        <?php if (!empty($order['updated_at']) && $order['updated_at'] != $order['created_at']): ?>
                        <div class="col-md-4">
                            <div class="text-muted small">Last Updated</div>
                            <div class="fw-bold">
                                <?= date('d M Y, h:i A', strtotime($order['updated_at'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($order['completed_at'])): ?>
                        <div class="col-md-4">
                            <div class="text-muted small">Completed</div>
                            <div class="fw-bold">
                                <?= date('d M Y, h:i A', strtotime($order['completed_at'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.card-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    background-color: #f8f9fa;
}

.table th {
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.table td, .table th {
    padding: 0.75rem;
    vertical-align: middle;
}

.badge {
    font-weight: 500;
    padding: 0.35em 0.65em;
}

.text-muted {
    font-size: 0.875rem; 
}

.fw-bold {
    font-weight: 600 !important;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .btn {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }
}
</style>

<?php include __DIR__ . '/admin_footer.php'; ?>