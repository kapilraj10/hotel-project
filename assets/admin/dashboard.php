<?php
// Admin header: includes navbar and starts layout with sidebar column
require_once __DIR__ . '/../auth.php';
require_admin();
// helper to format numbers as Nepali rupee (Rupee sign "रू")
function format_npr($amount) {
  return 'रू ' . number_format((float)$amount, 2);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin - <?= isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard' ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary-color: #7c3aed;
      --primary-light: #ede9fe;
      --secondary-color: #10b981;
      --danger-color: #ef4444;
      --warning-color: #f59e0b;
      --info-color: #3b82f6;
      --text-dark: #1f2937;
      --text-light: #6b7280;
      --bg-light: #f8fafc;
      --border-color: #e5e7eb;
      --sidebar-width: 250px;
      --sidebar-collapsed: 70px;
      --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
      --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
      --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
      --radius: 12px;
      --radius-sm: 8px;
    }

    body {
      background-color: #f8fafc;
      color: var(--text-dark);
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    }

    /* Top navbar */
    .top-navbar {
      background: white;
      box-shadow: var(--shadow-sm);
      height: 70px;
      padding: 0 1.5rem;
      position: sticky;
      top: 0;
      z-index: 1000;
    }

    .top-navbar .navbar-brand {
      color: var(--primary-color);
      font-weight: 700;
      font-size: 1.4rem;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .user-info {
      background: var(--bg-light);
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .user-info i {
      color: var(--primary-color);
    }

    /* Sidebar */
    .sidebar-container {
      width: var(--sidebar-width);
      background: white;
      min-height: calc(100vh - 70px);
      position: fixed;
      left: 0;
      top: 70px;
      box-shadow: var(--shadow-md);
      transition: all 0.3s ease;
      z-index: 999;
      overflow-y: auto;
      padding: 20px 0;
    }

    body.sidebar-collapsed .sidebar-container {
      width: var(--sidebar-collapsed);
    }

    .sidebar-header {
      padding: 0 20px 20px;
      border-bottom: 1px solid var(--border-color);
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 12px;
      color: var(--primary-color);
    }

    .logo i {
      font-size: 1.5rem;
    }

    .logo h3 {
      font-size: 1.2rem;
      margin: 0;
      font-weight: 700;
      transition: opacity 0.3s;
    }

    body.sidebar-collapsed .logo h3 {
      opacity: 0;
      width: 0;
      overflow: hidden;
    }

    .sidebar-toggle-btn {
      background: var(--primary-light);
      border: none;
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary-color);
      transition: all 0.3s;
    }

    .sidebar-toggle-btn:hover {
      background: var(--primary-color);
      color: white;
    }

    .sidebar-menu {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .sidebar-menu li {
      margin-bottom: 5px;
      padding: 0 15px;
    }

    .sidebar-menu a {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 12px 15px;
      color: var(--text-light);
      text-decoration: none;
      border-radius: var(--radius-sm);
      transition: all 0.2s;
      font-weight: 500;
    }

    .sidebar-menu a:hover {
      background: var(--primary-light);
      color: var(--primary-color);
      transform: translateX(3px);
    }

    .sidebar-menu a.active {
      background: var(--primary-color);
      color: white;
      box-shadow: 0 2px 8px rgba(124, 58, 237, 0.3);
    }

    .sidebar-menu .icon {
      width: 24px;
      height: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
    }

    .menu-text {
      transition: opacity 0.3s;
      white-space: nowrap;
    }

    body.sidebar-collapsed .menu-text {
      opacity: 0;
      width: 0;
      overflow: hidden;
    }

    .logout-section {
      margin-top: 30px;
      padding: 20px 15px 0;
      border-top: 1px solid var(--border-color);
    }

    .logout-btn {
      display: flex;
      align-items: center;
      gap: 15px;
      width: 100%;
      padding: 12px 15px;
      background: var(--bg-light);
      border: none;
      border-radius: var(--radius-sm);
      color: var(--danger-color);
      font-weight: 500;
      transition: all 0.2s;
    }

    .logout-btn:hover {
      background: #fee2e2;
      color: #dc2626;
      transform: translateX(3px);
    }

    /* Main content */
    .main-content {
      margin-left: var(--sidebar-width);
      padding: 30px;
      min-height: calc(100vh - 70px);
      transition: margin-left 0.3s ease;
    }

    body.sidebar-collapsed .main-content {
      margin-left: var(--sidebar-collapsed);
    }

    /* Dashboard specific */
    .dashboard-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      background: white;
      padding: 25px;
      border-radius: var(--radius);
      box-shadow: var(--shadow-sm);
    }

    .dashboard-header h1 {
      color: var(--primary-color);
      font-weight: 700;
      margin-bottom: 5px;
    }

    .welcome-message {
      font-size: 0.95rem;
      color: var(--text-light);
      background: var(--bg-light);
      padding: 8px 16px;
      border-radius: 20px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    /* Stats cards */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: white;
      padding: 25px;
      border-radius: var(--radius);
      box-shadow: var(--shadow-sm);
      transition: all 0.3s ease;
      border-left: 4px solid var(--primary-color);
      position: relative;
      overflow: hidden;
    }

    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-lg);
    }

    .stat-card:nth-child(2) { border-left-color: var(--secondary-color); }
    .stat-card:nth-child(3) { border-left-color: var(--info-color); }
    .stat-card:nth-child(4) { border-left-color: var(--warning-color); }

    .stat-card small {
      color: var(--text-light);
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .stat-card h3 {
      font-size: 2.2rem;
      font-weight: 800;
      margin: 10px 0 0;
      color: var(--text-dark);
    }

    .stat-card::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--primary-color), transparent);
      opacity: 0.1;
    }

    /* Chart section */
    .chart-section {
      background: white;
      padding: 25px;
      border-radius: var(--radius);
      box-shadow: var(--shadow-sm);
      margin-bottom: 30px;
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }

    .section-header h5 {
      color: var(--text-dark);
      font-weight: 700;
      margin: 0;
    }

    .total-amount {
      background: var(--primary-light);
      color: var(--primary-color);
      padding: 8px 20px;
      border-radius: 20px;
      font-weight: 700;
      font-size: 1.1rem;
    }

    /* Responsive */
    @media (max-width: 992px) {
      .sidebar-container {
        transform: translateX(-100%);
        width: var(--sidebar-width);
      }

      .sidebar-container.show {
        transform: translateX(0);
      }

      .main-content {
        margin-left: 0 !important;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 768px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }

      .main-content {
        padding: 20px;
      }
    }

    /* Animation for loading */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .stat-card, .chart-section {
      animation: fadeIn 0.5s ease-out;
    }

    /* Custom scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }

    ::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
      background: var(--primary-color);
      border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #6d28d9;
    }
    /* Reports UI tweaks */
    .report-controls { display:flex; gap:0.5rem; align-items:center; }
    .report-controls .form-control-sm { min-width:160px; }
    #ordersTable tbody tr:hover { background: rgba(124,58,237,0.03); }
    .badge-count { font-size:0.75rem; }
  </style>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <!-- top navbar -->
  <nav class="top-navbar navbar navbar-expand">
    <div class="container-fluid">
      <a class="navbar-brand" href="/hotel/assets/admin/dashboard.php">
        <i class="fas fa-hotel"></i>
        Hotel Admin
      </a>
      <div class="d-flex align-items-center">
        <div class="user-info">
          <i class="fas fa-user-circle"></i>
          <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
        </div>
      </div>
    </div>
  </nav>

  <!-- Sidebar -->
  <div class="sidebar-container" id="adminSidebar">
    <div class="sidebar-header">
      <div class="logo">
        <i class="fas fa-star"></i>
        <h3>Hotel Admin</h3>
      </div>
      <button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
        <i class="fas fa-bars"></i>
      </button>
    </div>
    
    <?php
    $current = basename($_SERVER['PHP_SELF']);
    ?>
    
    <ul class="sidebar-menu">
      <li>
        <a href="dashboard.php" class="<?= $current=='dashboard.php' ? 'active' : '' ?>">
          <span class="icon"><i class="fas fa-tachometer-alt"></i></span>
          <span class="menu-text">Dashboard</span>
        </a>
      </li>
      <li>
        <a href="categories.php" class="<?= $current=='categories.php' ? 'active' : '' ?>">
          <span class="icon"><i class="fas fa-tags"></i></span>
          <span class="menu-text">Categories</span>
        </a>
      </li>
      <li>
        <a href="items.php" class="<?= $current=='items.php' ? 'active' : '' ?>">
          <span class="icon"><i class="fas fa-utensils"></i></span>
          <span class="menu-text">Menu Items</span>
        </a>
      </li>
      <li>
        <a href="tables.php" class="<?= $current=='tables.php' ? 'active' : '' ?>">
          <span class="icon"><i class="fas fa-chair"></i></span>
          <span class="menu-text">Tables</span>
        </a>
      </li>
      <li>
        <a href="orders.php" class="<?= $current=='orders.php' ? 'active' : '' ?>">
          <span class="icon"><i class="fas fa-receipt"></i></span>
          <span class="menu-text">Orders</span>
        </a>
      </li>
      <li>
        <a href="bookings.php" class="<?= $current=='bookings.php' ? 'active' : '' ?>">
          <span class="icon"><i class="fas fa-book"></i></span>
          <span class="menu-text">Bookings</span>
        </a>
      </li>
      <li >
        <a href="users.php" class="<?= $current=='users.php' ? 'active' : '' ?>">
          <span class="icon"><i class="fas fa-users"></i></span>
          <span class="menu-text">Users</span>
        </a>
      </li>
      <li>
        <a href="admins.php" class="<?= $current=='admins.php' ? 'active' : '' ?>">
          <span class="icon"><i class="fas fa-user-shield"></i></span>
          <span class="menu-text">Admins</span>
        </a>
      </li>
      <li>
        <a href="rooms.php" class="<?= $current=='rooms.php' ? 'active' : '' ?>">
          <span class="icon"><i class="fas fa-bed"></i></span>
          <span class="menu-text">Rooms</span>
        </a>
      </li>
    </ul>

    <div class="logout-section">
      <form action="/hotel/assets/admin/logout.php" method="post">
        <button type="submit" class="logout-btn">
          <span class="icon"><i class="fas fa-sign-out-alt"></i></span>
          <span class="menu-text">Logout</span>
        </button>
      </form>
    </div>
  </div>

  <!-- main content -->
  <div class="main-content">
    <div class="container-fluid">
      <?php
      require_once __DIR__ . '/../db_rest.php';
      $pdo = get_rest_db();

      // Metrics
      $total_categories = $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
      $total_items = $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
      $total_tables = $pdo->query('SELECT COUNT(*) FROM tables_info')->fetchColumn();
  // bookings count (safe if table missing)
  try { $total_bookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(); } catch (Throwable $e) { $total_bookings = 0; }
      $pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='Pending'")->fetchColumn();
      $completed_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='Completed'")->fetchColumn();
      $total_revenue = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status='Completed'")->fetchColumn();
      // Include bookings revenue if bookings table exists (try common column names)
      try {
        $b_total = 0;
        // try several common column names
        $tryCols = ['total_amount','amount','checkout_price','price'];
        foreach ($tryCols as $col) {
          try {
            $q = $pdo->query("SELECT COALESCE(SUM($col),0) FROM bookings WHERE status='Completed'");
            if ($q) {
              $b_total = (float)$q->fetchColumn();
              if ($b_total > 0) break;
            }
          } catch (Throwable $e) { /* ignore and try next */ }
        }
      } catch (Throwable $e) { $b_total = 0; }
      $total_revenue = (float)$total_revenue + (float)$b_total;
      // Today's orders and bookings counts
      try {
        $today_orders_count = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(order_date)=CURDATE()")->fetchColumn();
      } catch (Throwable $e) { $today_orders_count = 0; }
      try {
        $today_bookings_count = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE DATE(created_at)=CURDATE()")->fetchColumn();
      } catch (Throwable $e) { $today_bookings_count = 0; }
      // Today's revenue (completed orders today)
      try {
        $today_revenue = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(order_date)=CURDATE() AND status='Completed'")->fetchColumn();
      } catch (Throwable $e) {
        $today_revenue = 0;
      }
      // Add today's bookings revenue if present
      try {
        // Prefer bookings recorded today (created_at), fallback to booking_date/checkin
        $b_today = 0;
        // First try sum of total_amount for bookings created today
        try {
          $q = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE DATE(created_at)=CURDATE() AND status='Completed'");
          if ($q) $b_today = (float)$q->fetchColumn();
        } catch (Throwable $__e) { $b_today = 0; }

        // If no total_amounts recorded, try to compute from price_per_night * nights for bookings created today
        if (empty($b_today)) {
          try {
            $q2 = $pdo->query("SELECT COALESCE(SUM(price_per_night * GREATEST(DATEDIFF(checkout, checkin),1)),0) FROM bookings WHERE DATE(created_at)=CURDATE() AND status='Completed'");
            if ($q2) $b_today = (float)$q2->fetchColumn();
          } catch (Throwable $__e) { /* ignore */ }
        }

        // As a last fallback, check rows where checkin/checkout equals today (occupied/checkout today)
        if (empty($b_today)) {
          try {
            $q3 = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE (DATE(checkin)=CURDATE() OR DATE(checkout)=CURDATE()) AND status='Completed'");
            if ($q3) $b_today = (float)$q3->fetchColumn();
          } catch (Throwable $__e) { }
        }
      } catch (Throwable $e) { $b_today = 0; }
      $today_revenue = (float)$today_revenue + (float)$b_today;
  // expose today's bookings revenue separately
  $today_bookings_revenue = (float)$b_today;

      // Simple daily sales for last 7 days
      $stmt = $pdo->prepare("SELECT DATE(order_date) as d, COALESCE(SUM(total_amount),0) as s FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(order_date) ORDER BY DATE(order_date)");
      $stmt->execute();
      $rows = $stmt->fetchAll();
      $labels = [];$data = [];
      $map = [];
      foreach ($rows as $r) { $map[$r['d']] = $r['s']; }
      for ($i=6;$i>=0;$i--) { $d = date('Y-m-d', strtotime("-{$i} days")); $labels[] = $d; $data[] = isset($map[$d]) ? (float)$map[$d] : 0; }
      ?>

      <?php if (!empty($_GET['order_created'])): ?>
        <div class="alert alert-success">Order #<?=htmlspecialchars((int)$_GET['order_created'])?> created successfully.</div>
      <?php endif; ?>

      <div class="dashboard-header">
        <div>
          <h1 class="h2">Dashboard Overview</h1>
          <div class="text-muted small">Welcome to your hotel management dashboard</div>
        </div>
        <div class="d-flex align-items-center gap-3">
          <a href="bookings.php" class="btn btn-outline-primary">
            <i class="fas fa-bed me-1"></i>
            Bookings
            <?php if(!empty($total_bookings)): ?> <span class="badge bg-info text-dark ms-2"><?php echo $total_bookings; ?></span><?php endif; ?>
          </a>
          <div class="welcome-message">
            <i class="fas fa-calendar-alt"></i>
            <?php echo date('F j, Y'); ?>
          </div>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <small><i class="fas fa-folder me-2"></i>Categories</small>
          <h3><?php echo $total_categories; ?></h3>
        </div>
        <div class="stat-card">
          <small><i class="fas fa-utensils me-2"></i>Menu Items</small>
          <h3><?php echo $total_items; ?></h3>
        </div>
        <div class="stat-card">
          <small><i class="fas fa-chair me-2"></i>Tables</small>
          <h3><?php echo $total_tables; ?></h3>
        </div>
          <a href="rooms.php" style="text-decoration:none">
          <div class="stat-card" style="border-left-color: var(--danger-color);">
            <small><i class="fas fa-door-open me-2"></i>Rooms</small>
            <h3><?php echo (int)($pdo->query('SELECT COUNT(*) FROM rooms')->fetchColumn() ?? 0); ?></h3>
          </div>
          </a>
        <div class="stat-card">
          <small><i class="fas fa-clock me-2"></i>Pending Orders</small>
          <h3><?php echo $pending_orders; ?></h3>
        </div>
        <div class="stat-card">
          <small><i class="fas fa-receipt me-2"></i>Today's Orders</small>
          <h3><?php echo (int)$today_orders_count; ?></h3>
        </div>
        <div class="stat-card">
          <small><i class="fas fa-bed me-2"></i>Today's Bookings</small>
          <h3><?php echo (int)$today_bookings_count; ?></h3>
        </div>
         <div class="stat-card">
          <small><i class="fas fa-sun me-2"></i>Today's Revenue</small>
          <h3><?php echo format_npr($today_revenue); ?></h3>
        </div>
        <div class="stat-card">
          <small><i class="fas fa-dollar-sign me-2"></i>Today's Bookings Rev</small>
          <h3><?php echo format_npr($today_bookings_revenue); ?></h3>
        </div>
      </div>

      <div class="chart-section">
        <div class="section-header">
          <h5>Sales Trend (Last 7 Days)</h5>
          <div class="total-amount">
            <i class="fas fa-money-bill-wave me-1"></i>
            Total: <?php echo format_npr($total_revenue); ?>
          </div>
        </div>
        <div style="height: 320px;">
          <canvas id="salesChart" style="width:100%; height:100%"></canvas>
        </div>
      </div>

      <!-- Revenue range quick review -->
      <div class="row mt-3" id="revenueRangeCards">
        <div class="col-auto">
          <div class="stat-card">
            <small>15 Days</small>
            <h4 id="revenue_15">रू 0.00</h4>
          </div>
        </div>
        <div class="col-auto">
          <div class="stat-card">
            <small>30 Days</small>
            <h4 id="revenue_30">रू 0.00</h4>
          </div>
        </div>
        <div class="col-auto">
          <div class="stat-card">
            <small>60 Days</small>
            <h4 id="revenue_60">रू 0.00</h4>
          </div>
        </div>
        <div class="col-auto">
          <div class="stat-card">
            <small>120 Days</small>
            <h4 id="revenue_120">रू 0.00</h4>
          </div>
        </div>
        <div class="col-auto">
          <div class="stat-card">
            <small>6 Months</small>
            <h4 id="revenue_6m">रू 0.00</h4>
          </div>
        </div>
        <div class="col-auto">
          <div class="stat-card">
            <small>1 Year</small>
            <h4 id="revenue_1y">रू 0.00</h4>
          </div>
        </div>
      </div>

      <!-- Additional stats row -->
      <div class="row mt-4">
        <div class="col-md-6">
          <div class="stat-card">
            <small><i class="fas fa-check-circle me-2"></i>Completed Orders</small>
            <h3><?php echo $completed_orders; ?></h3>
          </div>
        </div>
        <div class="col-md-6">
          <div class="stat-card">
            <small><i class="fas fa-chart-line me-2"></i>Total Revenue</small>
            <h3 id="totalRevenueValue"><?php echo format_npr($total_revenue); ?></h3>
          </div>
        </div>
      </div>

        <!-- Reports Section: Orders & Rooms -->
        <div class="chart-section mt-4">
          <div class="section-header">
            <h5>Reports</h5>
            <div class="d-flex gap-2 align-items-center">
              <div class="btn-group btn-group-sm" role="group" aria-label="Date ranges" id="rangeButtons">
                <button class="btn btn-outline-secondary active" data-range="today">Today</button>
                <button class="btn btn-outline-secondary" data-range="7">7 Days</button>
                <button class="btn btn-outline-secondary" data-range="15">15 Days</button>
                <button class="btn btn-outline-secondary" data-range="30">30 Days</button>
                <button class="btn btn-outline-secondary" data-range="3m">3 Months</button>
                <button class="btn btn-outline-secondary" data-range="6m">6 Months</button>
                <button class="btn btn-outline-secondary" data-range="1y">1 Year</button>
              </div>
              <div class="ms-3">
                <div class="small text-muted">Orders Total (selected range)</div>
                <div class="total-amount" id="ordersRangeTotal">रू 0.00</div>
              </div>
              <div class="ms-3">
                <div class="small text-muted">Bookings Total (selected range)</div>
                <div class="total-amount" id="bookingsRangeTotal">रू 0.00</div>
              </div>
              <!-- filters removed per request: search/status/payment -->
              <button id="exportOrdersBtn" class="btn btn-sm btn-success">
                <i class="fas fa-file-csv"></i> Export Orders
                <span id="ordersCountBadge" class="badge bg-light text-dark ms-2 badge-count">0</span>
              </button>
            </div>
          </div>

          <div class="row">
            <div class="col-12">
              <h6>Orders</h6>
              <div class="table-responsive">
                <table class="table table-striped table-sm" id="ordersTable">
                  <thead class="table-dark">
                    <tr>
                      <th>Order ID</th>
                      <th>Item</th>
                      <th>Price</th>
                      <th>Qty</th>
                      <th>Status</th>
                      <th>Payment</th>
                      <th>Table/Room</th>
                      <th>Order Date</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>

            <!-- Rooms section removed per request -->

            <div class="col-12 mt-4">
              <h6>Bookings</h6>
              <div class="table-responsive">
                <table class="table table-striped table-sm" id="bookingsTable">
                  <thead class="table-dark">
                    <tr>
                      <th>ID</th>
                      <th>Guest</th>
                      <th>Amount</th>
                      <th>Status</th>
                      <th>Payment</th>
                      <th>Booking Date</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <script>
        // Format labels as short day names + date
        const rawLabels = <?php echo json_encode($labels); ?>;
        const labels = rawLabels.map(d => {
          try {
            const dt = new Date(d + 'T00:00:00');
            return dt.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
          } catch(e) {
            return d;
          }
        });
        
        const data = <?php echo json_encode($data); ?>;
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        // Create gradient
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(124, 58, 237, 0.2)');
        gradient.addColorStop(1, 'rgba(124, 58, 237, 0.05)');
        
        new Chart(ctx, {
          type: 'line',
          data: {
            labels,
            datasets: [{
              label: 'Sales',
              data,
              tension: 0.4,
              borderColor: '#7c3aed',
              backgroundColor: gradient,
              borderWidth: 3,
              pointBackgroundColor: '#7c3aed',
              pointBorderColor: '#ffffff',
              pointBorderWidth: 2,
              pointRadius: 5,
              pointHoverRadius: 7
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                display: false
              },
              tooltip: {
                mode: 'index',
                intersect: false,
                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                titleColor: '#1f2937',
                bodyColor: '#1f2937',
                borderColor: '#e5e7eb',
                borderWidth: 1,
                cornerRadius: 8,
                callbacks: {
                  label: function(context) {
                    return 'Sales: ' + formatNPR(context.parsed.y);
                  }
                }
              }
            },
            scales: {
              x: {
                grid: {
                  color: '#f3f4f6',
                  drawBorder: false
                },
                ticks: {
                  color: '#6b7280'
                }
              },
              y: {
                grid: {
                  color: '#f3f4f6',
                  drawBorder: false
                },
                ticks: {
                  color: '#6b7280',
                  callback: function(value) {
                    return formatNPR(value);
                  }
                },
                beginAtZero: true
              }
            },
            interaction: {
              intersect: false,
              mode: 'nearest'
            }
          }
        });

        // helper to format Nepali rupee in JS
        function formatNPR(value) {
          const num = Number(value) || 0;
          return 'रू ' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
          const sidebar = document.getElementById('adminSidebar');
          const toggle = document.getElementById('sidebarToggle');
          const body = document.body;
          
          // Check for saved state
          const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
          if (isCollapsed) {
            body.classList.add('sidebar-collapsed');
          }
          
          // Mobile menu toggle
          const mobileMenuBtn = document.getElementById('mobileMenuToggle');
          if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', function() {
              sidebar.classList.toggle('show');
            });
          }
          
          // Toggle sidebar collapse
          if (toggle) {
            toggle.addEventListener('click', function() {
              body.classList.toggle('sidebar-collapsed');
              localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
            });
          }

          // Reports: fetch and render orders/rooms
          const rangeButtons = document.getElementById('rangeButtons');
          let currentRange = 'today';

          function setActiveRangeBtn(btn) {
            [...rangeButtons.querySelectorAll('button')].forEach(b=>b.classList.remove('active'));
            btn.classList.add('active');
            currentRange = btn.getAttribute('data-range');
            fetchAndRenderOrders(currentRange);
            fetchAndRenderBookings(currentRange);
            // refresh per-range revenue cards as ranges may have changed
            loadRangeRevenues();
          }

          rangeButtons.querySelectorAll('button').forEach(btn=>{
            btn.addEventListener('click', function(){ setActiveRangeBtn(this); });
          });

          // search and filter inputs - re-render table on change
          // Search and filter UI removed — dashboard will show server-provided range data without client-side filters

          let ordersRawData = [];
          let bookingsRawData = [];

          async function fetchAndRenderOrders(range) {
            try {
              const res = await fetch('data_orders.php?range=' + encodeURIComponent(range));
              const data = await res.json();
              ordersRawData = data;
              renderOrdersTable(data);
              // update total badge and revenue quick stat
              document.getElementById('ordersCountBadge').textContent = data.length;
              const total = data.reduce((s,r)=> s + (parseFloat(r.price) * (parseInt(r.qty) || 1)), 0);
              // update orders range total display
              const ordersTotalEl = document.getElementById('ordersRangeTotal');
              if (ordersTotalEl) ordersTotalEl.textContent = formatNPR(total);
            } catch (e) {
              console.error('Failed to load orders', e);
            }
          }

          function renderOrdersTable(data) {
            const tbody = document.querySelector('#ordersTable tbody');
            tbody.innerHTML = '';
            const filtered = data; // no client-side filters (search/status/payment removed)
            filtered.forEach(r=>{
              const tr = document.createElement('tr');
              tr.innerHTML = `<td>${escapeHtml(r.order_id)}</td>
                <td>${escapeHtml(r.item_name)}</td>
                <td>${formatNPR(parseFloat(r.price).toFixed(2))}</td>
                <td>${r.qty}</td>
                <td>${escapeHtml(r.status)}</td>
                <td>${escapeHtml(r.payment_method)}</td>
                <td>${escapeHtml(r.table_number)}</td>
                <td>${escapeHtml(r.order_date)}</td>`;
              tbody.appendChild(tr);
            });
            document.getElementById('ordersCountBadge').textContent = filtered.length;
          }

          async function fetchAndRenderBookings(range) {
            try {
              const res = await fetch('data_bookings.php?range=' + encodeURIComponent(range));
              const data = await res.json();
              bookingsRawData = data;
              // compute totals and render summary
              const tbody = document.querySelector('#bookingsTable tbody');
              if (tbody) tbody.innerHTML = '';
              let total = 0;
              data.forEach(b=>{
                if (b.amount) total += parseFloat(b.amount) || 0;
                if (tbody) {
                  const tr = document.createElement('tr');
                  tr.innerHTML = `<td>${escapeHtml(b.id)}</td>
                    <td>${escapeHtml(b.guest)}</td>
                    <td>${b.amount ? formatNPR(parseFloat(b.amount).toFixed(2)) : ''}</td>
                    <td>${escapeHtml(b.status)}</td>
                    <td>${escapeHtml(b.payment_method)}</td>
                    <td>${escapeHtml(b.booking_date)}</td>`;
                  tbody.appendChild(tr);
                }
              });
              const bookingsTotalEl = document.getElementById('bookingsRangeTotal');
              if (bookingsTotalEl) bookingsTotalEl.textContent = formatNPR(total);
            } catch (e) {
              console.error('Failed to load bookings', e);
            }
          }

          // Load revenue totals for multiple ranges
          async function loadRangeRevenues() {
            const ranges = [
              {key: '15', el: document.getElementById('revenue_15')},
              {key: '30', el: document.getElementById('revenue_30')},
              {key: '60', el: document.getElementById('revenue_60')},
              {key: '120', el: document.getElementById('revenue_120')},
              {key: '6m', el: document.getElementById('revenue_6m')},
              {key: '1y', el: document.getElementById('revenue_1y')}
            ];

              await Promise.all(ranges.map(async r => {
                try {
                  // fetch orders and bookings for the same range in parallel
                  const [ordersRes, bookingsRes] = await Promise.all([
                    fetch('data_orders.php?range=' + encodeURIComponent(r.key)),
                    fetch('data_bookings.php?range=' + encodeURIComponent(r.key))
                  ]);
                  const [orders, bookings] = await Promise.all([ordersRes.json(), bookingsRes.json()]);
                  const ordersTotal = orders.reduce((s,row) => s + (parseFloat(row.price) * (parseInt(row.qty) || 1)), 0);
                  const bookingsTotal = (bookings || []).reduce((s,b) => s + (parseFloat(b.amount || 0) || 0), 0);
                  const total = ordersTotal + bookingsTotal;
                  if (r.el) r.el.textContent = formatNPR(total);
                } catch (e) {
                  console.error('Failed to load revenue for', r.key, e);
                  if (r.el) r.el.textContent = 'रू 0.00';
                }
              }));
          }

          // Rooms data removed from reports per request

          function escapeHtml(s){ if (s===null||s===undefined) return ''; return String(s).replace(/[&<>\"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c]; }); }

          // Export buttons
          document.getElementById('exportOrdersBtn').addEventListener('click', function(){
            window.location = 'export_orders.php?range=' + encodeURIComponent(currentRange);
          });

          // initial load: fetch orders and bookings, and populate revenue range cards
          fetchAndRenderOrders(currentRange);
          fetchAndRenderBookings(currentRange);
          loadRangeRevenues();
        });
      </script>
    </div>
  </div>
</body>
</html>