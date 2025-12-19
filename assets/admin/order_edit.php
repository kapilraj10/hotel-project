<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php';
$pdo = get_rest_db();

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if (!$order_id) { header('Location: orders.php'); exit; }

// ensure columns exist (same as create)
try { $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS purchase_image VARCHAR(255) DEFAULT NULL"); } catch (Throwable $__ignored) {}
try { $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS customer_name VARCHAR(255) DEFAULT NULL"); } catch (Throwable $__ignored) {}
try { $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS customer_email VARCHAR(255) DEFAULT NULL"); } catch (Throwable $__ignored) {}
try { $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS customer_phone VARCHAR(100) DEFAULT NULL"); } catch (Throwable $__ignored) {}
try { $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS customer_address TEXT DEFAULT NULL"); } catch (Throwable $__ignored) {}
try { $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS order_type VARCHAR(50) DEFAULT NULL"); } catch (Throwable $__ignored) {}
try { $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS order_note TEXT DEFAULT NULL"); } catch (Throwable $__ignored) {}
try { $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS total_quantity INT DEFAULT 0"); } catch (Throwable $__ignored) {}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $items_json = $_POST['items_json'] ?? '[]';
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $customer_address = trim($_POST['customer_address'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'None';
    $status = $_POST['status'] ?? 'Pending';
    $table_id = $_POST['table_id'] ?? null;
    $order_type = $_POST['order_type'] ?? 'Dine-in';
    $order_note = trim($_POST['order_note'] ?? '');

    $order_items = json_decode($items_json, true) ?: [];
    $total = 0.0; $total_qty = 0;
    foreach ($order_items as $oi) { $total += ($oi['price']*$oi['qty']); $total_qty += (int)$oi['qty']; }

    if (empty($order_items)) { $errors[] = 'Please add at least one item to the order.'; }

    $table_number = null; $table_type = null;
    if ($table_id) {
        $tstmt = $pdo->prepare('SELECT table_number,table_type FROM tables_info WHERE id=?');
        $tstmt->execute([(int)$table_id]);
        $t = $tstmt->fetch(); if ($t) { $table_number = $t['table_number']; $table_type = $t['table_type']; }
    }

    // handle image upload
    $purchase_image = null;
    if (!empty($_FILES['purchase_image']) && $_FILES['purchase_image']['error']===UPLOAD_ERR_OK) {
        $uploaddir = __DIR__ . '/../uploads/orders';
        if (!is_dir($uploaddir)) mkdir($uploaddir, 0755, true);
        $tmp = $_FILES['purchase_image']['tmp_name'];
        $name = basename($_FILES['purchase_image']['name']);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $fname = 'order_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (move_uploaded_file($tmp, $uploaddir . '/' . $fname)) {
            $purchase_image = 'uploads/orders/' . $fname;
        }
    }

    if (!$errors) {
        // build update SQL with prepared statements
        $sql = 'UPDATE orders SET items_json=?, total_amount=?, total_quantity=?, status=?, payment_method=?, table_number=?, table_type=?, customer_name=?, customer_email=?, customer_phone=?, customer_address=?, order_type=?, order_note=?';
        $params = [json_encode($order_items, JSON_UNESCAPED_UNICODE), $total, $total_qty, $status, $payment_method, $table_number, $table_type, $customer_name, $customer_email, $customer_phone, $customer_address, $order_type, $order_note];
        if ($purchase_image) { $sql .= ', purchase_image=?'; $params[] = $purchase_image; }
        $sql .= ' WHERE id=?'; $params[] = $order_id;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        header('Location: order_edit.php?order_id=' . $order_id . '&saved=1'); exit;
    }
}

// fetch order
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id=?'); $stmt->execute([$order_id]);
$order = $stmt->fetch(); if (!$order) { header('Location: orders.php'); exit; }

// load tables and items and categories
$tables = $pdo->query("SELECT id,table_number,table_type FROM tables_info WHERE COALESCE(is_room,0)=0 ORDER BY table_number")->fetchAll();
$categories = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
$catMap = []; foreach($categories as $c) $catMap[$c['id']] = $c['name'];
$dbItems = $pdo->query("SELECT id,name,price,category_id,COALESCE(image_path,'') AS image_path FROM items ORDER BY name")->fetchAll();

$page_title = 'Edit Order'; include __DIR__ . '/admin_header.php';
$initial_cart = json_encode(json_decode($order['items_json'], true) ?: []);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3>Edit Order #<?= $order_id ?></h3>
  <a class="btn btn-secondary" href="orders.php">Back</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger"><?php echo htmlspecialchars(implode('\n', $errors)); ?></div>
<?php endif; ?>
<?php if (!empty($_GET['saved'])): ?>
  <div class="alert alert-success">Order saved successfully. <a href="order_receipt.php?order_id=<?= $order_id ?>">View receipt</a></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" id="orderForm">
  <div class="row">
    <div class="col-md-8">
      <div class="card mb-3 p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="mb-0">Items</h5>
          <div class="d-flex gap-2">
            <select id="categoryFilter" class="form-select form-select-sm">
              <option value="">All categories</option>
              <?php foreach($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
+            <input id="searchItem" placeholder="Search item" class="form-control form-control-sm" />
          </div>
        </div>

        <div class="row g-2" id="itemsList">
          <?php foreach ($dbItems as $it): ?>
            <div class="col-sm-6 col-md-4 item-card" data-category="<?= (int)$it['category_id'] ?>">
              <div class="card h-100 p-2">
                <?php if (!empty($it['image_path'])): ?>
                  <img src="/assets/<?= ltrim($it['image_path'], '/') ?>" class="card-img-top" style="height:140px;object-fit:cover" alt="<?= htmlspecialchars($it['name']) ?>">
                <?php endif; ?>
                <div class="card-body p-2">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <strong><?= htmlspecialchars($it['name']) ?></strong>
                      <div class="text-muted small"><?= htmlspecialchars($catMap[$it['category_id']] ?? '') ?></div>
                      <div class="text-muted small"><?= format_npr($it['price']) ?></div>
                    </div>
                    <div style="min-width:120px">
                      <div class="input-group input-group-sm">
                        <button type="button" class="btn btn-outline-secondary btn-sm qty-minus">−</button>
                        <input type="number" class="form-control item-qty text-center" min="1" value="1" style="width:60px">
                        <button type="button" class="btn btn-outline-secondary btn-sm qty-plus">+</button>
                      </div>
                      <div class="mt-2"><button type="button" class="btn btn-primary btn-sm add-item w-100" data-id="<?= (int)$it['id'] ?>" data-name="<?= htmlspecialchars($it['name'], ENT_QUOTES) ?>" data-price="<?= (float)$it['price'] ?>">Add</button></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card p-3 mb-3">
        <h5>Customer</h5>
        <div class="row g-2">
          <div class="col-md-6"><input name="customer_name" class="form-control" placeholder="Customer name" value="<?= htmlspecialchars($order['customer_name'] ?? '') ?>"></div>
          <div class="col-md-6"><input name="customer_email" class="form-control" placeholder="Email" value="<?= htmlspecialchars($order['customer_email'] ?? '') ?>"></div>
          <div class="col-md-6 mt-2"><input name="customer_phone" class="form-control" placeholder="Phone" value="<?= htmlspecialchars($order['customer_phone'] ?? '') ?>"></div>
          <div class="col-md-6 mt-2"><input name="customer_address" class="form-control" placeholder="Address" value="<?= htmlspecialchars($order['customer_address'] ?? '') ?>"></div>
        </div>
      </div>

      <div class="card p-3 mb-3">
        <h5>Upload payment / purchase image (optional)</h5>
        <input type="file" name="purchase_image" accept="image/*" class="form-control">
        <?php if (!empty($order['purchase_image'])): ?>
          <div class="mt-2"><img src="/assets/<?= ltrim($order['purchase_image'], '/') ?>" style="max-width:100%"></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card p-3 mb-3">
        <h5>Cart</h5>
        <div id="cartList"></div>
        <div class="mt-2">
          <div class="d-flex justify-content-between"><div><strong>Total quantity:</strong> <span id="cartQty">0</span></div><div><strong>Total:</strong> <span id="cartTotal"><?= format_npr($order['total_amount'] ?? 0) ?></span></div></div>
        </div>
      </div>

      <div class="card p-3 mb-3">
        <h5>Table (optional)</h5>
        <select name="table_id" class="form-select">
          <option value="">-- none --</option>
          <?php foreach($tables as $tb): ?>
            <option value="<?= $tb['id'] ?>" <?= ($tb['table_number']==($order['table_number'] ?? '')) ? 'selected' : '' ?>><?=htmlspecialchars($tb['table_number'])?> (<?=htmlspecialchars($tb['table_type'])?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="card p-3 mb-3">
        <h5>Status & Payment</h5>
        <select name="status" class="form-select mb-2">
          <option <?= ($order['status']=='Pending')? 'selected':'' ?>>Pending</option>
          <option <?= ($order['status']=='Preparing')? 'selected':'' ?>>Preparing</option>
          <option <?= ($order['status']=='Completed')? 'selected':'' ?>>Completed</option>
          <option <?= ($order['status']=='Cancelled')? 'selected':'' ?>>Cancelled</option>
        </select>
        <div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="payment_method" id="payNone" value="None" <?= ($order['payment_method']=='None' || empty($order['payment_method']))? 'checked':'' ?>>
            <label class="form-check-label" for="payNone">None</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="payment_method" id="payCash" value="Cash" <?= ($order['payment_method']=='Cash')? 'checked':'' ?>>
            <label class="form-check-label" for="payCash">Cash</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="payment_method" id="payCard" value="Card" <?= ($order['payment_method']=='Card')? 'checked':'' ?>>
            <label class="form-check-label" for="payCard">Card</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="payment_method" id="payOnline" value="Online" <?= ($order['payment_method']=='Online')? 'checked':'' ?>>
            <label class="form-check-label" for="payOnline">Online</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="payment_method" id="payPaytm" value="Paytm" <?= ($order['payment_method']=='Paytm')? 'checked':'' ?>>
            <label class="form-check-label" for="payPaytm">Paytm</label>
          </div>
        </div>
        <div id="paymentPaidWrapAdmin" class="form-check mt-2" style="display:none">
          <input class="form-check-input" type="checkbox" id="paymentPaid" name="payment_paid">
          <label class="form-check-label" for="paymentPaid">Payment completed (tick if already paid)</label>
        </div>
      </div>

      <div class="card p-3 mb-3">
        <h5>Order type & note</h5>
        <select name="order_type" class="form-select mb-2">
          <option <?= (($order['order_type'] ?? '')=='Dine-in')? 'selected':'' ?>>Dine-in</option>
          <option <?= (($order['order_type'] ?? '')=='Takeaway')? 'selected':'' ?>>Takeaway</option>
        </select>
        <textarea name="order_note" class="form-control" placeholder="Order note"><?= htmlspecialchars($order['order_note'] ?? '') ?></textarea>
      </div>

      <input type="hidden" name="items_json" id="items_json">
      <div class="d-grid gap-2">
        <a class="btn btn-outline-secondary" href="order_receipt.php?order_id=<?= $order_id ?>">Print Slip</a>
        <button class="btn btn-primary">Save Changes</button>
      </div>
    </div>
  </div>
</form>

<?php include __DIR__ . '/admin_footer.php'; ?>

<script>
// reuse small cart logic, initialize with server cart
const cart = JSON.parse(<?= $initial_cart ?> || '[]');
function findCartItem(id){ return cart.find(c=>c.id===id); }
function renderCart(){
  const list = document.getElementById('cartList'); list.innerHTML = '';
  let total = 0; let totalQty = 0;
  cart.forEach(item=>{
    const div = document.createElement('div'); div.className='d-flex justify-content-between align-items-center my-1';
    div.innerHTML = `<div><strong>${item.name}</strong><br><small>${item.qty} × ${formatNPR(item.price)}</small></div><div><input type="number" min="1" value="${item.qty}" class="form-control form-control-sm me-2 cart-qty" data-id="${item.id}" style="width:70px;display:inline-block"> <button class="btn btn-sm btn-danger remove-item" data-id="${item.id}">×</button></div>`;
    list.appendChild(div);
    total += item.price * item.qty; totalQty += item.qty;
  });
  document.getElementById('cartTotal').innerText = formatNPR(total);
  document.getElementById('cartQty').innerText = totalQty;
  document.querySelectorAll('.cart-qty').forEach(el=> el.addEventListener('change', (e)=>{ const id=parseInt(e.target.dataset.id); const v=parseInt(e.target.value)||1; const it=findCartItem(id); if(it){ it.qty=v; renderCart(); } }));
  document.querySelectorAll('.remove-item').forEach(b=> b.addEventListener('click', (e)=>{ const id=parseInt(e.target.dataset.id); const idx = cart.findIndex(c=>c.id===id); if(idx>=0){ cart.splice(idx,1); renderCart(); } }));
}

// add item buttons
document.querySelectorAll('.add-item').forEach(btn=> btn.addEventListener('click', function(e){
  const id = parseInt(this.dataset.id); const name = this.dataset.name; const price = parseFloat(this.dataset.price);
  const qtyInput = this.parentElement.querySelector('.item-qty'); const qty = Math.max(1, parseInt(qtyInput.value)||1);
  const existing = findCartItem(id); if (existing) existing.qty += qty; else cart.push({id, name, price, qty}); renderCart();
}));
// +/- buttons
document.getElementById('itemsList').addEventListener('click', function(e){ if (e.target.classList.contains('qty-plus')||e.target.classList.contains('qty-minus')){ const card = e.target.closest('.item-card'); if(!card) return; const input = card.querySelector('.item-qty'); let v = parseInt(input.value)||1; if (e.target.classList.contains('qty-plus')) v++; else v = Math.max(1, v-1); input.value = v; } });

// category filter & search
document.getElementById('categoryFilter').addEventListener('change', function(){ filterItems(); });
if (document.getElementById('searchItem')) document.getElementById('searchItem').addEventListener('input', function(){ filterItems(); });
function filterItems(){ const cat = document.getElementById('categoryFilter').value; const q = (document.getElementById('searchItem')?document.getElementById('searchItem').value.toLowerCase():''); document.querySelectorAll('#itemsList .item-card').forEach(card=>{ const matchesCat = !cat || card.dataset.category===cat; const text = card.innerText.toLowerCase(); const matchesQ = !q || text.indexOf(q)!==-1; card.style.display = (matchesCat && matchesQ) ? '' : 'none'; }); }

// submit
document.querySelector('form').addEventListener('submit', function(e){ document.getElementById('items_json').value = JSON.stringify(cart); });

function formatNPR(v){ try { const n = Number(v); return 'रु ' + n.toLocaleString(); } catch(e){ return v; } }

// initial render
renderCart();
</script>
