<?php
require_once __DIR__ . '/../auth.php'; 
require_admin(); 
require_once __DIR__ . '/../db_rest.php';

$pdo = get_rest_db();

// Single-item quick-order mode: ?single=1&item_id=xx
$single_mode = !empty($_GET['single']) || !empty($_POST['single']);
$selected_item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

// Load categories with their items
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$itemsByCategory = [];
$allItems = [];

foreach ($categories as $category) {
    $stmt = $pdo->prepare('SELECT id, name, price, category_id, COALESCE(image_path, "") AS image_path FROM items WHERE category_id = ? ORDER BY name');
    $stmt->execute([$category['id']]);
    $items = $stmt->fetchAll();
    if (!empty($items)) {
        $itemsByCategory[$category['id']] = [
            'name' => $category['name'],
            'items' => $items
        ];
        $allItems = array_merge($allItems, $items);
    }
}

// Ensure is_room exists for tables
try { 
    $pdo->exec("ALTER TABLE tables_info ADD COLUMN IF NOT EXISTS is_room TINYINT(1) DEFAULT 0"); 
} catch (Throwable $__ignored) {
    // Ignore error if column already exists
}

$tables = $pdo->query("SELECT id, table_number, table_type FROM tables_info WHERE COALESCE(is_room,0)=0 ORDER BY table_number")->fetchAll();

// Ensure orders table has required columns
$requiredColumns = [
    'purchase_image' => 'VARCHAR(255) DEFAULT NULL',
    'customer_name' => 'VARCHAR(255) DEFAULT NULL',
    'customer_email' => 'VARCHAR(255) DEFAULT NULL',
    'customer_phone' => 'VARCHAR(100) DEFAULT NULL',
    'customer_address' => 'TEXT DEFAULT NULL',
    'order_type' => 'VARCHAR(50) DEFAULT NULL',
    'order_note' => 'TEXT DEFAULT NULL',
    'total_quantity' => 'INT DEFAULT 0'
];

foreach ($requiredColumns as $column => $definition) {
    try { 
        $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS $column $definition"); 
    } catch (Throwable $__ignored) {
        // Ignore error if column already exists
    }
}

// Edit mode support
$editing = false;
$edit_order = null;
$initial_cart_json = '[]';
$edit_order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($edit_order_id) {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$edit_order_id]);
    $edit_order = $stmt->fetch();
    
    if ($edit_order) {
        $editing = true;
        $initial_cart_json = $edit_order['items_json'] ?: '[]';
    }
}

// If single mode and an item_id is provided (and not editing), prefill initial cart with that single item
if (!$editing && $single_mode && $selected_item_id) {
    // try to find the item in loaded lists first
    $found = null;
    foreach ($allItems as $ai) {
        if ((int)$ai['id'] === $selected_item_id) { $found = $ai; break; }
    }
    if (!$found) {
        $stmt = $pdo->prepare('SELECT id, name, price FROM items WHERE id = ? LIMIT 1');
        $stmt->execute([$selected_item_id]);
        $found = $stmt->fetch();
    }
    if ($found) {
        $initial_cart_json = json_encode([[ 'id' => (int)$found['id'], 'name' => $found['name'], 'price' => (float)$found['price'], 'qty' => 1 ]], JSON_UNESCAPED_UNICODE);
    }
}

// Process form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $items_json = $_POST['items_json'] ?? '[]';
    $posted_order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $customer_address = trim($_POST['customer_address'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'None';
    $status = $_POST['status'] ?? 'Pending';
    $order_type = $_POST['order_type'] ?? 'Dine-in';
    $order_note = trim($_POST['order_note'] ?? '');
    $payment_paid = !empty($_POST['payment_paid']);
    $table_id = $_POST['table_id'] ?? null;

    $order_items = json_decode($items_json, true) ?: [];
    
    // Validate and calculate total
    $total = 0.0;
    $total_qty = 0;
    foreach ($order_items as &$oi) {
        $oi['id'] = (int)($oi['id'] ?? 0);
        $oi['qty'] = (int)($oi['qty'] ?? 0);
        $oi['price'] = (float)($oi['price'] ?? 0);
        $total += $oi['price'] * $oi['qty'];
        $total_qty += $oi['qty'];
    }

    if (empty($order_items)) {
        $errors[] = 'Please add at least one item to the order.';
    }

    // Get table details
    $table_number = null;
    $table_type = null;
    if ($table_id && $table_id !== '') {
        $tstmt = $pdo->prepare('SELECT table_number, table_type FROM tables_info WHERE id = ?');
        $tstmt->execute([(int)$table_id]);
        $table = $tstmt->fetch();
        if ($table) {
            $table_number = $table['table_number'];
            $table_type = $table['table_type'];
        }
    } else {
        // allow manual table_number/table_type from posted form (public-style)
        $posted_table_number = trim($_POST['table_number'] ?? '');
        $posted_table_type = trim($_POST['table_type'] ?? '');
        if ($posted_table_number !== '') {
            $table_number = $posted_table_number;
            $table_type = $posted_table_type ?: null;
        }
    }

    // Handle image upload
    $purchase_image = null;
    if (!empty($_FILES['purchase_image']) && $_FILES['purchase_image']['error'] === UPLOAD_ERR_OK) {
        $uploaddir = __DIR__ . '/../uploads/orders';
        if (!is_dir($uploaddir)) {
            mkdir($uploaddir, 0755, true);
        }
        
        $tmp = $_FILES['purchase_image']['tmp_name'];
        $name = basename($_FILES['purchase_image']['name']);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $fname = 'order_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        
        if (move_uploaded_file($tmp, $uploaddir . '/' . $fname)) {
            $purchase_image = 'uploads/orders/' . $fname;
        }
    }

    if (empty($errors)) {
        // Determine status
        $paid_methods = ['Card', 'Online', 'Paytm'];
        if ($payment_paid || in_array($payment_method, $paid_methods, true)) {
            $status = 'Completed';
        }

        // Save to database
        if ($posted_order_id) {
            // Update existing order
            $stmt = $pdo->prepare('UPDATE orders SET items_json = ?, total_amount = ?, total_quantity = ?, status = ?, payment_method = ?, table_number = ?, table_type = ?, customer_name = ?, customer_email = ?, customer_phone = ?, customer_address = ?, order_type = ?, order_note = ?, purchase_image = COALESCE(?, purchase_image) WHERE id = ?');
            $stmt->execute([
                json_encode($order_items, JSON_UNESCAPED_UNICODE),
                $total,
                $total_qty,
                $status,
                $payment_method,
                $table_number,
                $table_type,
                $customer_name,
                $customer_email,
                $customer_phone,
                $customer_address,
                $order_type,
                $order_note,
                $purchase_image,
                $posted_order_id
            ]);
            
            $order_id = $posted_order_id;
            // Redirect back to orders list with a saved flag for consistent UX
            header('Location: orders.php?updated=1&order_id=' . $order_id);
        } else {
            // Create new order
            $stmt = $pdo->prepare('INSERT INTO orders (items_json, total_amount, total_quantity, status, payment_method, table_number, table_type, purchase_image, customer_name, customer_email, customer_phone, customer_address, order_type, order_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                json_encode($order_items, JSON_UNESCAPED_UNICODE),
                $total,
                $total_qty,
                $status,
                $payment_method,
                $table_number,
                $table_type,
                $purchase_image,
                $customer_name,
                $customer_email,
                $customer_phone,
                $customer_address,
                $order_type,
                $order_note
            ]);
            
            $order_id = $pdo->lastInsertId();
            // Redirect to orders list so admin sees the new order in context
            header('Location: orders.php?created=1&order_id=' . $order_id);
        }
        exit;
    }
}

$page_title = $editing ? 'Edit Order' : 'Create Order'; 
include __DIR__ . '/admin_header.php';

// Properly escape the JSON for JavaScript
$initial_cart_js = json_encode($initial_cart_json);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0"><?= $editing ? 'Edit Order #' . $edit_order_id : 'Create New Order' ?></h3>
            <p class="text-muted mb-0">Add items to cart and complete order details</p>
        </div>
        <div>
            <a class="btn btn-outline-secondary" href="orders.php">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars(implode('<br>', $errors)); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ((!empty($_GET['success']) || !empty($_GET['saved'])) && !empty($_GET['order_id'])): ?>
        <div class="alert alert-success alert-dismissible fade show" id="orderSuccessAlert">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Order <?= !empty($_GET['saved']) ? 'updated' : 'placed' ?> successfully!</strong>
                    <div class="mt-1">
                        <a href="order_receipt.php?order_id=<?= (int)$_GET['order_id'] ?>" target="_blank" class="text-decoration-none">
                            <i class="fas fa-receipt me-1"></i> View Receipt
                        </a>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-outline-primary" id="viewReceiptBtn" href="order_receipt.php?order_id=<?= (int)$_GET['order_id'] ?>" target="_blank">
                        <i class="fas fa-print me-1"></i> Print Receipt
                    </a>
                    <a class="btn btn-sm btn-secondary" href="orders.php">
                        <i class="fas fa-list me-1"></i> View All Orders
                    </a>
                </div>
            </div>
        </div>
        <!-- Note: auto-open/print removed to avoid sending/printing automatically -->
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="orderForm">
        <div class="row g-4">
            <!-- Left Column: Items Selection -->
            <div class="col-lg-8">
                <!-- Search and Filter -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <?php if (!$single_mode): ?>
                            <div class="col-md-6">
                                <label class="form-label">Search Items</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" id="searchItem" class="form-control" placeholder="Search by item name...">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Filter by Category</label>
                                <select id="categoryFilter" class="form-select">
                                    <option value="all">All Categories</option>
                                    <?php foreach($categories as $category): ?>
                                        <option value="cat-<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Items by Category -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div id="itemsContainer">
                            <?php if ($single_mode && $selected_item_id):
                                // show only the selected item
                                $si = null;
                                foreach ($allItems as $ai) { if ((int)$ai['id'] === $selected_item_id) { $si = $ai; break; } }
                                if (!$si) {
                                    $sstmt = $pdo->prepare('SELECT id,name,price,COALESCE(image_path,"") AS image_path FROM items WHERE id=?');
                                    $sstmt->execute([$selected_item_id]); $si = $sstmt->fetch();
                                }
                            ?>
                                <?php if ($si): ?>
                                    <div class="mb-3">
                                        <div class="card h-100 item-card-shadow">
                                            <?php if (!empty($si['image_path'])): ?>
                                                <div style="height:150px;overflow:hidden;"><img src="/assets/<?= ltrim($si['image_path'],'/') ?>" style="width:100%;height:100%;object-fit:cover"></div>
                                            <?php endif; ?>
                                            <div class="card-body">
                                                <h5><?= htmlspecialchars($si['name']) ?></h5>
                                                <p class="text-muted"><?= format_npr($si['price']) ?></p>
                                                <div class="input-group" style="width:140px">
                                                    <button class="btn btn-outline-secondary btn-sm qty-minus" type="button"><i class="fas fa-minus"></i></button>
                                                    <input type="number" class="form-control form-control-sm text-center item-qty" min="1" value="1" style="width:60px">
                                                    <button class="btn btn-outline-secondary btn-sm qty-plus" type="button"><i class="fas fa-plus"></i></button>
                                                </div>
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-primary add-single-item" data-id="<?= (int)$si['id'] ?>" data-name="<?= htmlspecialchars($si['name'], ENT_QUOTES) ?>" data-price="<?= (float)$si['price'] ?>">Add & Place Order</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">Item not found.</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (empty($itemsByCategory)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-utensils fa-3x text-muted mb-3"></i>
                                        <h5>No items available</h5>
                                        <p class="text-muted">Please add items from the Items Management page first.</p>
                                        <a href="items.php" class="btn btn-primary">
                                            <i class="fas fa-plus-circle me-2"></i> Add Items
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <?php foreach($itemsByCategory as $catId => $categoryData): ?>
                                        <?php if (!empty($categoryData['items'])): ?>
                                            <div class="mb-5 category-section" id="cat-<?= (int)$catId ?>">
                                                <h5 class="mb-3 border-bottom pb-2">
                                                    <?= htmlspecialchars($categoryData['name']) ?>
                                                    <span class="badge bg-secondary ms-2"><?= count($categoryData['items']) ?></span>
                                                </h5>
                                                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                                                    <?php foreach($categoryData['items'] as $item): ?>
                                                        <div class="col">
                                                            <div class="card h-100 item-card shadow-sm" 
                                                                 data-category="cat-<?= (int)$catId ?>"
                                                                 data-name="<?= htmlspecialchars(strtolower($item['name'])) ?>">
                                                                <?php if (!empty($item['image_path'])): ?>
                                                                    <div class="position-relative" style="height: 150px; overflow: hidden;">
                                                                        <img src="/assets/<?= ltrim($item['image_path'], '/') ?>" 
                                                                             class="card-img-top h-100 w-100" 
                                                                             style="object-fit: cover;"
                                                                             alt="<?= htmlspecialchars($item['name']) ?>"
                                                                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y4ZjhmOCI+PC9yZWN0Pjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIiBmaWxsPSIjYWFhIj5ObyBJbWFnZTwvdGV4dD48L3N2Zz4='">
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="bg-light d-flex align-items-center justify-content-center" style="height: 150px;">
                                                                        <i class="fas fa-utensils fa-3x text-muted"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <div class="card-body d-flex flex-column">
                                                                    <div class="mb-2">
                                                                        <h6 class="card-title mb-1"><?= htmlspecialchars($item['name']) ?></h6>
                                                                        <p class="text-muted mb-0"><?= format_npr($item['price']) ?></p>
                                                                    </div>
                                                                    
                                                                    <div class="mt-auto">
                                                                        <div class="input-group">
                                                                            <button class="btn btn-outline-secondary btn-sm qty-minus" type="button">
                                                                                <i class="fas fa-minus"></i>
                                                                            </button>
                                                                            <input type="number" 
                                                                                   class="form-control form-control-sm text-center item-qty" 
                                                                                   min="1" 
                                                                                   value="1"
                                                                                   data-item-id="<?= (int)$item['id'] ?>"
                                                                                   style="width: 60px">
                                                                            <button class="btn btn-outline-secondary btn-sm qty-plus" type="button">
                                                                                <i class="fas fa-plus"></i>
                                                                            </button>
                                                                        </div>
                                                                        
                                                                        <button type="button" 
                                                                                class="btn btn-primary btn-sm mt-2 w-100 add-item"
                                                                                data-id="<?= (int)$item['id'] ?>"
                                                                                data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>"
                                                                                data-price="<?= (float)$item['price'] ?>">
                                                                            <i class="fas fa-cart-plus me-1"></i> Add to Cart
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Cart & Order Details -->
            <div class="col-lg-4">
                <!-- Cart Summary -->
                <div class="card shadow-sm sticky-top" style="top: 20px; z-index: 100;">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i> Order Cart</h5>
                    </div>
                    <div class="card-body">
                        <div id="cartList" class="mb-3" style="max-height: 300px; overflow-y: auto;">
                            <!-- Cart items will be populated here -->
                            <div class="text-center py-4 text-muted" id="emptyCartMessage">
                                <i class="fas fa-shopping-cart fa-2x mb-3"></i>
                                <p class="mb-0">Your cart is empty</p>
                                <small>Add items from the menu</small>
                            </div>
                        </div>
                        
                        <div class="border-top pt-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Total Items:</span>
                                <strong id="cartQty">0</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="text-muted">Subtotal:</span>
                                <strong id="cartTotal"><?= format_npr(0) ?></strong>
                            </div>
                            
                            <div class="alert alert-info py-2 mb-3">
                                <small class="d-flex align-items-center">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <span>Items added will appear here</span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Information -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-user me-2"></i> Customer Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Customer Name</label>
                            <input type="text" 
                                   name="customer_name" 
                                   class="form-control" 
                                   placeholder="Enter customer name"
                                   value="<?= htmlspecialchars($edit_order['customer_name'] ?? '') ?>">
                        </div>
                        
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" 
                                       name="customer_phone" 
                                       class="form-control" 
                                       placeholder="Phone number"
                                       value="<?= htmlspecialchars($edit_order['customer_phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" 
                                       name="customer_email" 
                                       class="form-control" 
                                       placeholder="Email address"
                                       value="<?= htmlspecialchars($edit_order['customer_email'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="customer_address" 
                                      class="form-control" 
                                      rows="2" 
                                      placeholder="Delivery address (for takeaway)"><?= htmlspecialchars($edit_order['customer_address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Order Settings -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-cogs me-2"></i> Order Settings</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Table</label>
                                <select name="table_id" class="form-select">
                                    <option value="">-- Select Table --</option>
                                    <?php foreach($tables as $table): ?>
                                        <option value="<?= $table['id'] ?>" 
                                                <?= ($editing && ($table['table_number'] == ($edit_order['table_number'] ?? ''))) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($table['table_number']) ?> (<?= htmlspecialchars($table['table_type']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="mt-2">
                                    <label class="form-label">Or enter Table Number</label>
                                    <input type="text" name="table_number" class="form-control" placeholder="e.g., A1" value="<?= htmlspecialchars($edit_order['table_number'] ?? '') ?>">
                                </div>
                                <div class="mt-2">
                                    <label class="form-label">Table Type</label>
                                    <select name="table_type" class="form-select">
                                        <option value="2-Seater" <?= (isset($edit_order['table_type']) && $edit_order['table_type']=='2-Seater')? 'selected':'' ?>>2-Seater</option>
                                        <option value="4-Seater" <?= (isset($edit_order['table_type']) && $edit_order['table_type']=='4-Seater')? 'selected':'' ?>>4-Seater</option>
                                        <option value="Family" <?= (isset($edit_order['table_type']) && $edit_order['table_type']=='Family')? 'selected':'' ?>>Family</option>
                                        <option value="Other" <?= (isset($edit_order['table_type']) && !in_array($edit_order['table_type'], ['2-Seater', '4-Seater', 'Family']))? 'selected':'' ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Order Type</label>
                                <select name="order_type" class="form-select">
                                    <option value="Dine-in" <?= (!$editing || ($edit_order['order_type'] ?? '') == 'Dine-in') ? 'selected' : '' ?>>Dine-in</option>
                                    <option value="Takeaway" <?= ($editing && ($edit_order['order_type'] ?? '') == 'Takeaway') ? 'selected' : '' ?>>Takeaway</option>
                                    <option value="Delivery" <?= ($editing && ($edit_order['order_type'] ?? '') == 'Delivery') ? 'selected' : '' ?>>Delivery</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Payment Method</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="payNone" value="None" 
                                               <?= (!$editing || ($edit_order['payment_method'] ?? '') == 'None') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="payNone">None</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="payCash" value="Cash" 
                                               <?= (!$editing || ($edit_order['payment_method'] ?? '') == 'Cash') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="payCash">Cash</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="payCard" value="Card"
                                               <?= ($editing && ($edit_order['payment_method'] ?? '') == 'Card') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="payCard">Card</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="payOnline" value="Online"
                                               <?= ($editing && ($edit_order['payment_method'] ?? '') == 'Online') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="payOnline">Online</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="payPaytm" value="Paytm"
                                               <?= ($editing && ($edit_order['payment_method'] ?? '') == 'Paytm') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="payPaytm">Paytm</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12" id="paymentPaidWrapAdmin" style="display: none;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="paymentPaid" name="payment_paid"
                                           <?= ($editing && !empty($edit_order['status']) && $edit_order['status'] == 'Completed') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="paymentPaid">
                                        <i class="fas fa-check-circle me-1"></i> Payment Completed
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Order Status</label>
                                <select name="status" class="form-select">
                                    <option value="Pending" <?= (!$editing || ($edit_order['status'] ?? '') == 'Pending') ? 'selected' : '' ?>>Pending</option>
                                    <option value="Processing" <?= ($editing && ($edit_order['status'] ?? '') == 'Processing') ? 'selected' : '' ?>>Processing</option>
                                    <option value="Completed" <?= ($editing && ($edit_order['status'] ?? '') == 'Completed') ? 'selected' : '' ?>>Completed</option>
                                    <option value="Delivered" <?= ($editing && ($edit_order['status'] ?? '') == 'Delivered') ? 'selected' : '' ?>>Delivered</option>
                                    <option value="Cancelled" <?= ($editing && ($edit_order['status'] ?? '') == 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Order Note (Optional)</label>
                                <textarea name="order_note" 
                                          class="form-control" 
                                          rows="2" 
                                          placeholder="Special instructions, allergies, etc."><?= htmlspecialchars($edit_order['order_note'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Payment Proof / Receipt</label>
                                <input type="file" name="purchase_image" accept="image/*" class="form-control">
                                <small class="text-muted">Upload payment receipt or screenshot (optional)</small>
                                <?php if ($editing && !empty($edit_order['purchase_image'])): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Current image:</small>
                                        <img src="/assets/<?= ltrim($edit_order['purchase_image'], '/') ?>" 
                                             class="img-thumbnail mt-1" 
                                             style="max-width: 100px; max-height: 100px;"
                                             onerror="this.style.display='none'">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card shadow-sm mt-4">
                    <div class="card-body">
                        <input type="hidden" name="items_json" id="items_json" value="<?= htmlspecialchars($initial_cart_json) ?>">
                        <?php if ($editing): ?>
                            <input type="hidden" name="order_id" value="<?= (int)$edit_order_id ?>">
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2">
                            <button type="button" id="paytmBtn" class="btn btn-success" style="display: none;">
                                <i class="fas fa-rupee-sign me-2"></i> Pay with Paytm
                            </button>
                            
                            <div class="d-flex gap-2">
                                <button type="button" id="resetBtn" class="btn btn-outline-danger flex-fill">
                                    <i class="fas fa-redo me-2"></i> Clear All
                                </button>
                                <button type="button" id="submitBtn" class="btn btn-primary flex-fill">
                                    <?php if ($editing): ?>
                                        <i class="fas fa-save me-2"></i> Update Order
                                    <?php else: ?>
                                        <i class="fas fa-check-circle me-2"></i> Create Order
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Cart Management - FIXED: Properly parse the initial cart JSON
const cart = JSON.parse(<?= $initial_cart_js ?> || '[]');
let cartChangeListeners = [];

function findCartItem(id) {
    return cart.find(c => c.id === id);
}

function renderCart() {
    const cartList = document.getElementById('cartList');
    const emptyCartMessage = document.getElementById('emptyCartMessage');
    
    if (cart.length === 0) {
        cartList.innerHTML = '<div class="text-center py-4 text-muted" id="emptyCartMessage">' +
            '<i class="fas fa-shopping-cart fa-2x mb-3"></i>' +
            '<p class="mb-0">Your cart is empty</p>' +
            '<small>Add items from the menu</small>' +
            '</div>';
        updateCartSummary();
        return;
    }
    
    emptyCartMessage?.remove();
    
    let html = '<div class="list-group list-group-flush">';
    let total = 0;
    let totalQty = 0;
    
    cart.forEach((item, index) => {
        const itemTotal = item.price * item.qty;
        total += itemTotal;
        totalQty += item.qty;
        
        html += `
            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-bottom">
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between">
                        <strong class="mb-1">${item.name}</strong>
                        <span class="text-primary fw-bold">${formatNPR(itemTotal)}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <small class="text-muted">${formatNPR(item.price)} each</small>
                        <div class="d-flex align-items-center">
                            <div class="input-group input-group-sm" style="width: 120px;">
                                <button class="btn btn-outline-secondary btn-sm cart-qty-minus" data-index="${index}">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" 
                                       class="form-control form-control-sm text-center cart-qty-input"
                                       min="1" 
                                       value="${item.qty}"
                                       data-index="${index}"
                                       style="width: 50px">
                                <button class="btn btn-outline-secondary btn-sm cart-qty-plus" data-index="${index}">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <button class="btn btn-sm btn-outline-danger ms-2 cart-remove" data-index="${index}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    cartList.innerHTML = html;
    updateCartSummary();
    
    // Add event listeners for cart controls
    document.querySelectorAll('.cart-qty-minus').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const index = parseInt(e.target.closest('button').dataset.index);
            if (cart[index].qty > 1) {
                cart[index].qty--;
                renderCart();
                notifyCartChange();
            }
        });
    });
    
    document.querySelectorAll('.cart-qty-plus').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const index = parseInt(e.target.closest('button').dataset.index);
            cart[index].qty++;
            renderCart();
            notifyCartChange();
        });
    });
    
    document.querySelectorAll('.cart-qty-input').forEach(input => {
        input.addEventListener('change', (e) => {
            const index = parseInt(e.target.dataset.index);
            const value = parseInt(e.target.value) || 1;
            cart[index].qty = Math.max(1, value);
            renderCart();
            notifyCartChange();
        });
    });
    
    document.querySelectorAll('.cart-remove').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const index = parseInt(e.target.closest('button').dataset.index);
            cart.splice(index, 1);
            renderCart();
            notifyCartChange();
        });
    });
}

function updateCartSummary() {
    let total = 0;
    let totalQty = 0;
    
    cart.forEach(item => {
        total += item.price * item.qty;
        totalQty += item.qty;
    });
    
    document.getElementById('cartQty').textContent = totalQty;
    document.getElementById('cartTotal').textContent = formatNPR(total);
    document.getElementById('items_json').value = JSON.stringify(cart);
}

// Item Management
document.addEventListener('DOMContentLoaded', function() {
    // Add item to cart
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('add-item') || e.target.closest('.add-item')) {
            const btn = e.target.classList.contains('add-item') ? e.target : e.target.closest('.add-item');
            const card = btn.closest('.item-card');
            const qtyInput = card.querySelector('.item-qty');
            
            const item = {
                id: parseInt(btn.dataset.id),
                name: btn.dataset.name,
                price: parseFloat(btn.dataset.price),
                qty: Math.max(1, parseInt(qtyInput.value) || 1)
            };
            
            const existing = findCartItem(item.id);
            if (existing) {
                existing.qty += item.qty;
            } else {
                cart.push(item);
            }
            
            renderCart();
            notifyCartChange();
            
            // Show success feedback
            showToast(`${item.name} added to cart!`, 'success');
            
            // Reset quantity to 1
            qtyInput.value = 1;
        }
    });
    
    // Quantity controls on item cards
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('qty-minus') || e.target.closest('.qty-minus')) {
            const btn = e.target.classList.contains('qty-minus') ? e.target : e.target.closest('.qty-minus');
            const input = btn.closest('.input-group').querySelector('.item-qty');
            let value = parseInt(input.value) || 1;
            input.value = Math.max(1, value - 1);
        }
        
        if (e.target.classList.contains('qty-plus') || e.target.closest('.qty-plus')) {
            const btn = e.target.classList.contains('qty-plus') ? e.target : e.target.closest('.qty-plus');
            const input = btn.closest('.input-group').querySelector('.item-qty');
            let value = parseInt(input.value) || 1;
            input.value = value + 1;
        }
    });
    
    // Search and filter
    const searchInput = document.getElementById('searchItem');
    const categoryFilter = document.getElementById('categoryFilter');
    
    if (searchInput) searchInput.addEventListener('input', filterItems);
    if (categoryFilter) categoryFilter.addEventListener('change', filterItems);
    
    function filterItems() {
        const searchTerm = searchInput?.value.toLowerCase() || '';
        const selectedCategory = categoryFilter?.value || 'all';
        
        document.querySelectorAll('.category-section').forEach(section => {
            const categoryId = section.id;
            const itemsInSection = section.querySelectorAll('.item-card');
            let visibleItems = 0;
            
            itemsInSection.forEach(card => {
                const itemName = card.dataset.name;
                const matchesSearch = !searchTerm || itemName.includes(searchTerm);
                const matchesCategory = selectedCategory === 'all' || categoryId === selectedCategory;
                
                if (matchesSearch && matchesCategory) {
                    card.style.display = 'block';
                    visibleItems++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Show/hide category section based on visible items
            section.style.display = visibleItems > 0 ? 'block' : 'none';
        });
    }
    
    // Payment UI
    function updatePaymentUI() {
        const paidMethods = ['Card', 'Online', 'Paytm'];
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked')?.value || 'Cash';
        const paidWrap = document.getElementById('paymentPaidWrapAdmin');
        const paytmBtn = document.getElementById('paytmBtn');
        
        if (paidWrap) {
            paidWrap.style.display = paidMethods.includes(selectedMethod) ? 'block' : 'none';
        }
        
        if (paytmBtn) {
            paytmBtn.style.display = selectedMethod === 'Paytm' ? 'block' : 'none';
        }
        
        // Auto-set status to Completed for paid methods
        if (paidMethods.includes(selectedMethod)) {
            const statusSelect = document.querySelector('select[name="status"]');
            if (statusSelect && statusSelect.value !== 'Completed') {
                statusSelect.value = 'Completed';
            }
        }
    }
    
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', updatePaymentUI);
    });
    
    updatePaymentUI();
    
    // Paytm button action
    const paytmBtn = document.getElementById('paytmBtn');
    if (paytmBtn) {
        paytmBtn.addEventListener('click', function() {
            const total = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
            const orderId = <?= $editing ? $edit_order_id : 'null' ?>;
            
            // In production, integrate with actual Paytm API
            alert('Paytm integration would be implemented here.\n\nTotal: ' + formatNPR(total));
            
            // For demo, mark as paid
            const paymentPaid = document.querySelector('input[name="payment_paid"]');
            if (paymentPaid) paymentPaid.checked = true;
            
            const statusSelect = document.querySelector('select[name="status"]');
            if (statusSelect) statusSelect.value = 'Completed';
        });
    }
    
    // Form submission
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (cart.length === 0) {
                showToast('Please add items to the cart before submitting.', 'error');
                return;
            }
            
            const action = <?= $editing ? "'update'" : "'create'" ?>;
            const message = action === 'update' 
                ? 'Are you sure you want to update this order?' 
                : 'Are you sure you want to create this order?';
            
            if (!confirm(message)) {
                return;
            }
            
            document.getElementById('orderForm').submit();
        });
    }
    
    // Reset order
    const resetBtn = document.getElementById('resetBtn');
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            if (cart.length === 0 && !document.querySelector('input[name="customer_name"]').value) {
                return;
            }
            
            if (!confirm('This will clear all items from the cart and reset all fields. Continue?')) {
                return;
            }
            
            // Clear cart
            cart.length = 0;
            renderCart();
            
            // Reset form fields
            document.querySelector('input[name="customer_name"]').value = '';
            document.querySelector('input[name="customer_email"]').value = '';
            document.querySelector('input[name="customer_phone"]').value = '';
            document.querySelector('textarea[name="customer_address"]').value = '';
            document.querySelector('select[name="table_id"]').value = '';
            const tableNumberInput = document.querySelector('input[name="table_number"]');
            if (tableNumberInput) tableNumberInput.value = '';
            const tableTypeSelect = document.querySelector('select[name="table_type"]');
            if (tableTypeSelect) tableTypeSelect.value = '2-Seater';
            document.querySelector('select[name="status"]').value = 'Pending';
            document.querySelector('select[name="order_type"]').value = 'Dine-in';
            document.querySelector('textarea[name="order_note"]').value = '';
            document.querySelector('input[name="purchase_image"]').value = '';
            
            // Reset payment
            const cashRadio = document.querySelector('input[name="payment_method"][value="Cash"]');
            if (cashRadio) cashRadio.checked = true;
            const paymentPaid = document.querySelector('input[name="payment_paid"]');
            if (paymentPaid) paymentPaid.checked = false;
            updatePaymentUI();
            
            showToast('Order form has been reset.', 'info');
        });
    }
    
    // Initial render
    renderCart();
});

// Helper functions
function formatNPR(v) {
    try {
        const n = Number(v);
        if (isNaN(n)) return 'रु 0';
        return 'रु ' + n.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    } catch(e) {
        return 'रु 0';
    }
}

function showToast(message, type = 'info') {
    // Create toast element
    const toastId = 'toast-' + Date.now();
    const typeIcon = {
        'success': 'check-circle',
        'error': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    }[type] || 'info-circle';
    
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${typeIcon} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    // Add to toast container
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    // Show toast
    const toastEl = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
    toast.show();
    
    // Remove after hide
    toastEl.addEventListener('hidden.bs.toast', function () {
        toastEl.remove();
    });
}

function notifyCartChange() {
    updateCartSummary();
}
</script>

<style>
.category-section {
    transition: all 0.3s ease;
}

.item-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.item-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1) !important;
}

.input-group .btn {
    padding: 0.25rem 0.5rem;
}

.input-group input[type="number"] {
    -moz-appearance: textfield;
}

.input-group input[type="number"]::-webkit-outer-spin-button,
.input-group input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

#cartList::-webkit-scrollbar {
    width: 6px;
}

#cartList::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

#cartList::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 10px;
}

#cartList::-webkit-scrollbar-thumb:hover {
    background: #555;
}

.sticky-top {
    z-index: 1020;
}

@media (max-width: 992px) {
    .sticky-top {
        position: relative !important;
        top: 0 !important;
    }
}
</style>

<?php include __DIR__ . '/admin_footer.php'; ?>