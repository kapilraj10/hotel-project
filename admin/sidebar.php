<?php
$current = basename($_SERVER['PHP_SELF']);
// Get bookings count (safe if bookings table doesn't exist)
$booking_count = 0;
try {
  require_once __DIR__ . '/../db_rest.php';
  $pdo = get_rest_db();
  $booking_count = (int) $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
} catch (Throwable $e) {
  // ignore - table may not exist yet
}

function a($file,$label){ global $current; $active = ($current==$file)? ' active': ''; echo '<a class="nav-link'.$active.'" href="'.$file.'">'.$label.'</a>'; }
?>
<div class="sidebar-container" id="adminSidebar">
  <div class="sidebar-header">
    <div class="logo d-flex align-items-center">
      <i class="bi bi-building-fill-star me-2" aria-hidden="true" style="font-size:1.25rem;color:#3b82f6"></i>
      <h3 class="mb-0">Hotel Admin</h3>
    </div>
  </div>
  <ul class="sidebar-menu">
    <li class="<?= $current=='dashboard.php' ? 'active':'' ?>"><a href="dashboard.php" aria-label="Dashboard"><span class="icon"><i class="bi bi-speedometer2"></i></span><span class="menu-text">Dashboard</span></a></li>
    <li class="<?= $current=='categories.php' ? 'active':'' ?>"><a href="categories.php" aria-label="Categories"><span class="icon"><i class="bi bi-tags"></i></span><span class="menu-text">Categories</span></a></li>
    <li class="<?= $current=='items.php' ? 'active':'' ?>"><a href="items.php" aria-label="Items"><span class="icon"><i class="bi bi-basket3"></i></span><span class="menu-text">Items</span></a></li>
    <li class="<?= $current=='tables.php' ? 'active':'' ?>"><a href="tables.php" aria-label="Tables"><span class="icon"><i class="bi bi-table"></i></span><span class="menu-text">Tables</span></a></li>
  <li class="<?= $current=='orders.php' ? 'active':'' ?>"><a href="orders.php" aria-label="Orders"><span class="icon"><i class="bi bi-receipt"></i></span><span class="menu-text">Orders</span></a></li>
  <li class="<?= $current=='bookings.php' ? 'active':'' ?>"><a href="bookings.php" aria-label="Bookings"><span class="icon"><i class="bi bi-calendar-check"></i></span><span class="menu-text">Bookings</span><?php if($booking_count>0): ?> <span class="badge bg-info text-dark ms-2"><?= $booking_count ?></span><?php endif; ?></a></li>
    <li class="<?= $current=='users.php' ? 'active':'' ?>"><a href="users.php" aria-label="Users"><span class="icon"><i class="bi bi-people-fill"></i></span><span class="menu-text">Users</span></a></li>
    <li class="<?= $current=='admins.php' ? 'active':'' ?>"><a href="admins.php" aria-label="Admins"><span class="icon"><i class="bi bi-shield-lock-fill"></i></span><span class="menu-text">Admins</span></a></li>
    <li class="<?= $current=='rooms.php' ? 'active':'' ?>"><a href="rooms.php" aria-label="Rooms"><span class="icon"><i class="bi bi-door-open"></i></span><span class="menu-text">Rooms</span></a></li>

  </ul>

  <div class="logout-section">
    <form action="/hotel/admin/logout.php" method="post" aria-label="Logout form">
      <button type="submit" class="logout-btn"><span class="icon"><i class="bi bi-box-arrow-right"></i></span><span class="menu-text">Logout</span></button>
    </form>
  </div>
</div>

<script>
  // Toggle sidebar collapse state
  document.addEventListener('DOMContentLoaded', function(){
    const sidebar = document.getElementById('adminSidebar');
    const toggle = document.getElementById('sidebarToggle');
    const initiallyCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (initiallyCollapsed) sidebar.classList.add('collapsed');
    if (sidebar.classList.contains('collapsed')) document.body.classList.add('sidebar-collapsed');
    if (toggle) toggle.setAttribute('aria-expanded', String(!sidebar.classList.contains('collapsed')));
    toggle && toggle.addEventListener('click', function(){
      const isCollapsed = sidebar.classList.toggle('collapsed');
      localStorage.setItem('sidebarCollapsed', isCollapsed);
      document.body.classList.toggle('sidebar-collapsed', isCollapsed);
      toggle.setAttribute('aria-expanded', String(!isCollapsed));
    });
  });
</script>
