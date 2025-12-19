<?php
require_once __DIR__ . '/../auth.php'; require_admin(); require_once __DIR__ . '/../db_rest.php';
$pdo = get_rest_db();

// ensure admins table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action']==='delete_admin' ) {
    $id = (int)($_POST['admin_id'] ?? 0);
    if ($id) {
  // prevent deleting currently logged in admin
  $cur_username = $_SESSION['admin_username'] ?? null;
        $stmt = $pdo->prepare('SELECT username FROM admins WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $a = $stmt->fetch();
        if ($a && $a['username'] === ($cur_username ?? '')) {
            // don't allow deleting own account
        } else {
            $dst = $pdo->prepare('DELETE FROM admins WHERE id = ?');
            $dst->execute([$id]);
        }
    }
    header('Location: admins.php'); exit;
}

$admins = $pdo->query('SELECT id,username,created_at FROM admins ORDER BY id DESC')->fetchAll();

$page_title = 'Admin Accounts'; include __DIR__ . '/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3>Admin Accounts</h3>
  <a class="btn btn-secondary" href="dashboard.php">Back</a>
</div>

<div class="table-responsive">
  <table class="table table-striped">
    <thead><tr><th>ID</th><th>Username</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($admins as $a): ?>
      <tr>
        <td><?= $a['id'] ?></td>
        <td><?= htmlspecialchars($a['username']) ?></td>
        <td><?= htmlspecialchars($a['created_at']) ?></td>
        <td>
          <a class="btn btn-sm btn-secondary" href="admin_edit.php?id=<?= $a['id'] ?>">Edit</a>
          <form method="post" style="display:inline-block" onsubmit="return confirm('Delete admin account?')">
            <input type="hidden" name="action" value="delete_admin">
            <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
            <button class="btn btn-sm btn-danger">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/admin_footer.php';
