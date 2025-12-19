<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php';
$pdo = get_rest_db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id=?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
}

// Handle payment method update from admin (mark completed when appropriate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'update_payment' && $id) {
  $new_pm = $_POST['payment_method'] ?? $order['payment_method'];
  $payment_paid = !empty($_POST['payment_paid']);
  // Only Card/Online are treated as paid-by-method by default
  $paid_methods = ['Card','Online'];
  $new_status = $order['status'];
  if ($payment_paid || in_array($new_pm, $paid_methods, true)) {
    $new_status = 'Completed';
  }
  $ust = $pdo->prepare('UPDATE orders SET payment_method=?, status=? WHERE id=?');
  $ust->execute([$new_pm, $new_status, $id]);
  header('Location: orders_view.php?id=' . $id);
  exit;
}

$page_title = 'Order #' . ($order['id'] ?? ''); include __DIR__ . '/admin_header.php';
?>

<?php if (!$order): ?>
  <div class="alert alert-warning">Order not found.</div>
<?php else: ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Order #<?= $order['id'] ?></h3>
    <a class="btn btn-secondary" href="orders.php">Back</a>
  </div>

  <div class="card p-3 mb-3">
    <div class="row">
      <div class="col-md-8">
        <h5>Items</h5>
        <ul>
          <?php
            $items = json_decode($order['items_json'], true);
            if (is_array($items)) {
              foreach ($items as $it) {
                echo '<li>' . htmlspecialchars($it['name']) . ' — ' . intval($it['qty']) . ' × ' . format_npr($it['price']) . '</li>';
              }
            }
          ?>
        </ul>
      </div>
      <div class="col-md-4">
        <h5>Summary</h5>
        <p>Total: <?= format_npr($order['total_amount']) ?></p>
        <p>Status: <?= htmlspecialchars($order['status']) ?></p>
        <form method="post" class="mb-2" id="paymentUpdateForm">
          <input type="hidden" name="action" value="update_payment">
          <label class="form-label">Payment Method</label>
          <select name="payment_method" class="form-select mb-2" style="width:200px;" id="adminPaymentMethod">
            <?php foreach (['None','Cash','Bank','Card','Online','Other'] as $pm): ?>
              <option value="<?= $pm ?>" <?= ($order['payment_method']===$pm ? 'selected' : '') ?>><?= $pm ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-check mb-2" id="paymentPaidWrapView" style="display:none">
            <input class="form-check-input" type="checkbox" id="payment_paid" name="payment_paid">
            <label class="form-check-label" for="payment_paid">Mark as paid / Payment completed</label>
          </div>
          <div><button class="btn btn-sm btn-primary">Update Payment</button></div>
        </form>
        <script>
          (function(){
            const paidMethods = ['Card','Online'];
            const sel = document.getElementById('adminPaymentMethod');
            const wrap = document.getElementById('paymentPaidWrapView');
            function update(){ if (paidMethods.includes(sel.value)) wrap.style.display='block'; else wrap.style.display='none'; }
            sel.addEventListener('change', update);
            update();
          })();
        </script>
        <p>Current: <?= htmlspecialchars($order['payment_method']) ?></p>
        <p>Table: <?= htmlspecialchars($order['table_number'] ?? '') ?></p>
        <p>Date: <?= htmlspecialchars($order['order_date']) ?></p>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/admin_footer.php';
