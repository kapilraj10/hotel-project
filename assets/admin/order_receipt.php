<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php';
$pdo = get_rest_db();

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if (!$order_id) { header('Location: orders.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id=?');
$stmt->execute([$order_id]);
$order = $stmt->fetch();
if (!$order) { header('Location: orders.php'); exit; }

$page_title = 'Order Receipt'; include __DIR__ . '/admin_header.php';
$items = json_decode($order['items_json'], true) ?: [];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3>Order #<?= htmlspecialchars($order_id) ?></h3>
  <div>
    <a class="btn btn-secondary" href="orders.php">Back to orders</a>
    <button class="btn btn-primary" onclick="window.print()">Print Slip</button>
  </div>
</div>

<div class="card p-3 mb-3">
  <div class="row">
    <div class="col-md-6">
      <h5>Customer</h5>
      <p>
        <strong><?= htmlspecialchars($order['customer_name'] ?? '') ?></strong><br>
        <?= htmlspecialchars($order['customer_email'] ?? '') ?><br>
        <?= htmlspecialchars($order['customer_phone'] ?? '') ?><br>
        <?= nl2br(htmlspecialchars($order['customer_address'] ?? '')) ?>
      </p>
    </div>
    <div class="col-md-6 text-end">
      <h6>Order Info</h6>
      <div><strong>Order ID:</strong> <?= htmlspecialchars($order_id) ?></div>
      <div><strong>Date:</strong> <?= htmlspecialchars($order['order_date'] ?? $order['created_at']) ?></div>
      <div><strong>Status:</strong> <?= htmlspecialchars($order['status'] ?? '') ?></div>
      <div><strong>Payment:</strong> <?= htmlspecialchars($order['payment_method'] ?? '') ?></div>
      <div><strong>Table:</strong> <?= htmlspecialchars($order['table_number'] ?? '') ?></div>
      <div><strong>Order type:</strong> <?= htmlspecialchars($order['order_type'] ?? '') ?></div>
    </div>
  </div>
</div>

<div class="card p-3 mb-3">
  <h5>Items</h5>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
      <tbody>
        <?php $sum = 0; foreach($items as $it): $line = ($it['price']*$it['qty']); $sum += $line; ?>
          <tr>
            <td><?= htmlspecialchars($it['name'] ?? '') ?></td>
            <td><?= (int)($it['qty'] ?? 0) ?></td>
            <td><?= format_npr($it['price'] ?? 0) ?></td>
            <td><?= format_npr($line) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><th colspan="3">Subtotal</th><th><?= format_npr($sum) ?></th></tr>
        <tr><th colspan="3">Total</th><th><?= format_npr($order['total_amount'] ?? $sum) ?></th></tr>
      </tfoot>
    </table>
  </div>
</div>

<?php if (!empty($order['order_note'])): ?>
  <div class="card p-3 mb-3">
    <h5>Order Note</h5>
    <p><?= nl2br(htmlspecialchars($order['order_note'])) ?></p>
  </div>
<?php endif; ?>

<?php if (!empty($order['purchase_image'])): ?>
  <div class="card p-3 mb-3">
    <h5>Payment / Purchase image</h5>
    <img src="/assets/<?= ltrim($order['purchase_image'], '/') ?>" alt="purchase" style="max-width:100%">
  </div>
<?php endif; ?>

<style>
  /* Print friendly: center receipt and hide navigation elements */
  @media print {
    body * { visibility: hidden; }
    .card, .card * { visibility: visible; }
    .card { position: absolute; left: 0; top: 0; width: 100%; }
  }
</style>

<div class="mb-3">
  <a class="btn btn-outline-secondary" href="orders.php">Edit / Manage Orders</a>
</div>

<?php include __DIR__ . '/admin_footer.php'; ?>
