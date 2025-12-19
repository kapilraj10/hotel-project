<?php
// Admin order creation interface
// auth.php starts session and provides require_admin()

// Include necessary files (use parent directory where shared files live)

require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../db_rest.php';

$pdo = get_rest_db();

// Load tables for assignment
try {
    $tables = $pdo->query('SELECT id, table_number, table_type FROM tables_info ORDER BY table_number')->fetchAll();
} catch (Exception $e) {
    $tables = [];
}

// Handle AJAX requests for search suggestions
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search_suggestions') {
    $query = trim($_GET['q'] ?? '');
    if (strlen($query) >= 2) {
        $stmt = $pdo->prepare('SELECT name FROM items WHERE LOWER(name) LIKE ? LIMIT 10');
        $stmt->execute(['%' . mb_strtolower($query) . '%']);
        $suggestions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        header('Content-Type: application/json');
        echo json_encode($suggestions);
        exit;
    }
    exit;
}

// Handle AJAX for cart operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Invalid request'];

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_item') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $qty = (int)($_POST['qty'] ?? 1);

        if ($item_id > 0 && $qty > 0) {
            $stmt = $pdo->prepare('SELECT id, name, price FROM items WHERE id = ?');
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();

            if ($item) {
                if (isset($_SESSION['cart'][$item_id])) {
                    $_SESSION['cart'][$item_id]['qty'] += $qty;
                } else {
                    $_SESSION['cart'][$item_id] = [
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'price' => (float)$item['price'],
                        'qty' => $qty
                    ];
                }
                $response = ['success' => true, 'message' => 'Item added to cart'];
            } else {
                $response = ['success' => false, 'message' => 'Item not found'];
            }
        }
    } elseif ($action === 'update_qty') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $qty = (int)($_POST['qty'] ?? 0);

        if ($item_id > 0 && isset($_SESSION['cart'][$item_id])) {
            if ($qty > 0) {
                $_SESSION['cart'][$item_id]['qty'] = $qty;
            } else {
                unset($_SESSION['cart'][$item_id]);
            }
            $response = ['success' => true, 'message' => 'Cart updated'];
        }
    } elseif ($action === 'remove_item') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        if (isset($_SESSION['cart'][$item_id])) {
            unset($_SESSION['cart'][$item_id]);
            $response = ['success' => true, 'message' => 'Item removed'];
        }
    } elseif ($action === 'get_cart') {
        $cart = $_SESSION['cart'] ?? [];
        $subtotal = 0;
        $total_qty = 0;
        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['qty'];
            $total_qty += $item['qty'];
        }
        $response = [
            'success' => true,
            'cart' => array_values($cart),
            'subtotal' => $subtotal,
            'total_qty' => $total_qty
        ];
    }

    echo json_encode($response);
    exit;
}

// Load categories and items for display
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$category_filter = $_GET['category'] ?? 'all';

$items = [];
if ($category_filter === 'all') {
    $stmt = $pdo->query('SELECT i.id, i.name, i.price, i.category_id, c.name AS category_name, COALESCE(i.image_path, "") AS image_path FROM items i JOIN categories c ON i.category_id = c.id ORDER BY c.name, i.name');
    $items = $stmt->fetchAll();
} else {
    $cat_id = (int)$category_filter;
    $stmt = $pdo->prepare('SELECT i.id, i.name, i.price, i.category_id, c.name AS category_name, COALESCE(i.image_path, "") AS image_path FROM items i JOIN categories c ON i.category_id = c.id WHERE i.category_id = ? ORDER BY i.name');
    $stmt->execute([$cat_id]);
    $items = $stmt->fetchAll();
}

// Handle checkout submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $table_number = trim($_POST['table_number'] ?? '');
    $special_instructions = trim($_POST['special_instructions'] ?? '');

    if (empty($_SESSION['cart'])) {
        $errors[] = 'Your cart is empty.';
    }

    // Customer name is optional — do not enforce server-side requirement here.

    if (empty($errors)) {
        // Calculate totals
        $order_items = $_SESSION['cart'];
        $total = 0;
        $total_qty = 0;
        foreach ($order_items as &$oi) {
            $total += $oi['price'] * $oi['qty'];
            $total_qty += $oi['qty'];
        }

        // Insert order (include payment_method with default 'None')
        $order_type = trim($_POST['order_type'] ?? 'Dine-in');
        $stmt = $pdo->prepare('INSERT INTO orders (items_json, total_amount, total_quantity, status, payment_method, customer_name, table_number, order_type, order_note) VALUES (?, ?, ?, "Pending", ?, ?, ?, ?, ?)');
        $stmt->execute([
            json_encode(array_values($order_items), JSON_UNESCAPED_UNICODE),
            $total,
            $total_qty,
            'None', // payment_method default
            $customer_name,
            $table_number,
            $order_type,
            $special_instructions
        ]);

        $order_id = $pdo->lastInsertId();

        // Clear cart
        unset($_SESSION['cart']);

        // Redirect to order view page (do not use order_receipt.php)
        header('Location: orders_view.php?id=' . $order_id);
        exit;
    }
}

// Calculate cart summary
$cart = $_SESSION['cart'] ?? [];
$subtotal = 0;
$total_qty = 0;
foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['qty'];
    $total_qty += $item['qty'];
}

// Include admin header
$page_title = 'Create Order';
include __DIR__ . '/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <h2 class="mb-4">Menu</h2>

            <!-- Search and Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Search Items</label>
                            <input type="text" id="searchInput" class="form-control" placeholder="Type to search...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select id="categoryFilter" class="form-select">
                                <option value="all">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Grid -->
            <div id="itemsGrid" class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($items as $item): ?>
                    <div class="col item-card" data-name="<?= htmlspecialchars(strtolower($item['name'])) ?>" data-category="<?= $item['category_id'] ?>">
                        <div class="card h-100 shadow-sm">
                            <?php if (!empty($item['image_path'])): ?>
                                <img src="/assets/<?= ltrim($item['image_path'], '/') ?>" class="card-img-top" alt="<?= htmlspecialchars($item['name']) ?>" style="height: 200px; object-fit: cover;">
                            <?php endif; ?>
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?= htmlspecialchars($item['name']) ?></h5>
                                <p class="text-muted mb-1"><?= htmlspecialchars($item['category_name']) ?></p>
                                <p class="card-text fw-bold text-primary mb-3">रु <?= number_format($item['price'], 2) ?></p>
                                <div class="mt-auto">
                                    <button class="btn btn-primary w-100 add-to-cart" data-id="<?= $item['id'] ?>">
                                        <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($items)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-utensils fa-3x text-muted mb-3"></i>
                    <h5>No items found</h5>
                </div>
            <?php endif; ?>
        </div>

        <!-- Cart Sidebar -->
        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Your Cart</h5>
                </div>
                <div class="card-body">
                    <div id="cartItems" class="mb-3" style="max-height: 400px; overflow-y: auto;">
                        <!-- Cart items loaded via AJAX -->
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Items:</span>
                        <strong id="cartTotalQty">0</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Subtotal:</span>
                        <strong id="cartSubtotal">रु 0.00</strong>
                    </div>
                    <button class="btn btn-success w-100" id="checkoutBtn" disabled>
                        <i class="fas fa-credit-card me-2"></i>Checkout
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Checkout Modal -->
<div class="modal fade" id="checkoutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Checkout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Customer Name (optional)</label>
                        <input type="text" name="customer_name" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Table Number</label>
                        <select name="table_number" class="form-select">
                            <option value="">None / Takeaway</option>
                            <?php foreach ($tables as $t): ?>
                                <option value="<?= htmlspecialchars($t['table_number']) ?>">Table <?= htmlspecialchars($t['table_number']) ?> <?= !empty($t['table_type']) ? '(' . htmlspecialchars($t['table_type']) . ')' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Order Type</label>
                        <select name="order_type" class="form-select">
                            <option value="Dine-in">Dine-in</option>
                            <option value="Takeaway">Takeaway</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Special Instructions</label>
                        <textarea name="special_instructions" class="form-control" rows="3"></textarea>
                    </div>
                    <div id="orderSummary">
                        <!-- Order summary loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Place Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// AJAX functions
function updateCartDisplay() {
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax=1&action=get_cart'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const cartItems = document.getElementById('cartItems');
            const checkoutBtn = document.getElementById('checkoutBtn');
            
            if (data.cart.length === 0) {
                cartItems.innerHTML = '<p class="text-muted text-center">Your cart is empty</p>';
                checkoutBtn.disabled = true;
            } else {
                let html = '';
                data.cart.forEach(item => {
                    const itemTotal = item.price * item.qty;
                    html += `
                        <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2">
                            <div class="flex-grow-1">
                                <strong>${item.name}</strong>
                                <div class="d-flex align-items-center mt-1">
                                    <button class="btn btn-sm btn-outline-secondary qty-btn" data-action="decrease" data-id="${item.id}">-</button>
                                    <span class="mx-2">${item.qty}</span>
                                    <button class="btn btn-sm btn-outline-secondary qty-btn" data-action="increase" data-id="${item.id}">+</button>
                                    <button class="btn btn-sm btn-outline-danger ms-2 remove-btn" data-id="${item.id}">×</button>
                                </div>
                            </div>
                            <span class="fw-bold">रु ${itemTotal.toFixed(2)}</span>
                        </div>
                    `;
                });
                cartItems.innerHTML = html;
                checkoutBtn.disabled = false;
            }
            
            document.getElementById('cartTotalQty').textContent = data.total_qty;
            document.getElementById('cartSubtotal').textContent = `रु ${data.subtotal.toFixed(2)}`;
        }
    });
}

function addToCart(itemId, qty = 1) {
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax=1&action=add_item&item_id=${itemId}&qty=${qty}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartDisplay();
            showToast(data.message, 'success');
        } else {
            showToast(data.message, 'error');
        }
    });
}

function updateQty(itemId, qty) {
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax=1&action=update_qty&item_id=${itemId}&qty=${qty}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartDisplay();
        }
    });
}

function removeItem(itemId) {
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax=1&action=remove_item&item_id=${itemId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartDisplay();
            showToast(data.message, 'info');
        }
    });
}

// Search suggestions
let searchTimeout;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value.trim();
    
    if (query.length >= 2) {
        searchTimeout = setTimeout(() => {
            fetch(`?ajax=search_suggestions&q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(suggestions => {
                // Show suggestions (implement dropdown if needed)
                console.log(suggestions);
            });
        }, 300);
    }
    
    filterItems();
});

// Category filter
document.getElementById('categoryFilter').addEventListener('change', function() {
    const category = this.value;
    window.location.href = `?category=${category}`;
});

// Filter items client-side for search
function filterItems() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const selectedCategory = document.getElementById('categoryFilter').value;
    
    document.querySelectorAll('.item-card').forEach(card => {
        const itemName = card.dataset.name;
        const itemCategory = card.dataset.category;
        
        const matchesSearch = !searchTerm || itemName.includes(searchTerm);
        const matchesCategory = selectedCategory === 'all' || itemCategory == selectedCategory;
        
        card.style.display = (matchesSearch && matchesCategory) ? 'block' : 'none';
    });
}

// Add to cart and cart controls (use delegation and closest() to handle clicks on icons)
document.addEventListener('click', function(e) {
    const addBtn = e.target.closest('.add-to-cart');
    if (addBtn) {
        const itemId = addBtn.dataset.id;
        addToCart(itemId);
        return;
    }

    const qtyBtn = e.target.closest('.qty-btn');
    if (qtyBtn) {
        const action = qtyBtn.dataset.action;
        const itemId = qtyBtn.dataset.id;
        const cartItem = qtyBtn.closest('.d-flex');
        const qtySpan = cartItem ? cartItem.querySelector('span') : null;
        let qty = qtySpan ? parseInt(qtySpan.textContent) : 1;

        if (action === 'increase') {
            qty++;
        } else if (action === 'decrease' && qty > 1) {
            qty--;
        }

        if (qtySpan) qtySpan.textContent = qty;
        updateQty(itemId, qty);
        return;
    }

    const removeBtn = e.target.closest('.remove-btn');
    if (removeBtn) {
        const itemId = removeBtn.dataset.id;
        removeItem(itemId);
        return;
    }
});

// Checkout
document.getElementById('checkoutBtn').addEventListener('click', function() {
    // Load order summary
    const orderSummary = document.getElementById('orderSummary');
    orderSummary.innerHTML = document.getElementById('cartItems').innerHTML;
    orderSummary.innerHTML += `
        <hr>
        <div class="d-flex justify-content-between">
            <strong>Total:</strong>
            <strong>${document.getElementById('cartSubtotal').textContent}</strong>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('checkoutModal'));
    modal.show();
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    updateCartDisplay();
});

// Toast function
function showToast(message, type = 'info') {
    // Non-blocking toast: create a temporary Bootstrap alert and auto-dismiss
    const existing = document.querySelector('.order-toast');
    if (existing) existing.remove();

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} order-toast alert-dismissible fade show position-fixed`;
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '1055';
    alertDiv.style.minWidth = '260px';
    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <div class="flex-grow-1">${message}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    document.body.appendChild(alertDiv);

    // Auto remove after 3 seconds
    setTimeout(() => {
        try {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alertDiv);
            bsAlert.close();
        } catch (e) {
            if (alertDiv.parentNode) alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 3000);
}
</script>

<style>
.item-card {
    transition: transform 0.2s;
}

.item-card:hover {
    transform: translateY(-5px);
}

.sticky-top {
    z-index: 1020;
}

@media (max-width: 992px) {
    .sticky-top {
        position: relative !important;
    }
}
</style>

<?php include __DIR__ . '/admin_footer.php'; ?>