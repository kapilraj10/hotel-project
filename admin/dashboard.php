<?php
// Admin header: includes navbar and starts layout with sidebar column
require_once __DIR__ . '/../auth.php';
require_admin();

// helper to format numbers as Nepali rupee (Rupee sign "रू")
function format_npr($amount) {
  return 'रू ' . number_format((float)$amount, 2);
}

// Helper function to safely get column sum
function safe_column_sum($pdo, $table, $column, $where = '') {
  try {
    $sql = "SELECT COALESCE(SUM($column), 0) FROM $table";
    if ($where) {
      $sql .= " WHERE $where";
    }
    $stmt = $pdo->query($sql);
    return (float)$stmt->fetchColumn();
  } catch (Throwable $e) {
    return 0.00;
  }
}

// Helper function to safely count rows
function safe_count($pdo, $table, $where = '') {
  try {
    $sql = "SELECT COUNT(*) FROM $table";
    if ($where) {
      $sql .= " WHERE $where";
    }
    $stmt = $pdo->query($sql);
    return (int)$stmt->fetchColumn();
  } catch (Throwable $e) {
    return 0;
  }
}

// Helper function to check if table exists
function table_exists($pdo, $table_name) {
  try {
    $result = $pdo->query("SELECT 1 FROM $table_name LIMIT 1");
    return true;
  } catch (Throwable $e) {
    return false;
  }
}

// Helper to get bookings revenue properly
function get_bookings_revenue($pdo, $date_condition = '', $status_condition = "status='Completed'") {
  $revenue = 0.00;
  
  if (!table_exists($pdo, 'bookings')) {
    return $revenue;
  }
  
  try {
    // Build where clause
    $where = '';
    if ($status_condition) {
      $where = " WHERE $status_condition";
    }
    if ($date_condition) {
      $where .= ($where ? " AND " : " WHERE ") . $date_condition;
    }
    
    // Try to get total_amount column first
    $revenue = safe_column_sum($pdo, 'bookings', 'total_amount', 
      str_replace('WHERE ', '', $where));
    
    // If no total_amount, try amount column
    if ($revenue == 0) {
      $revenue = safe_column_sum($pdo, 'bookings', 'amount', 
        str_replace('WHERE ', '', $where));
    }
    
    // If still no revenue, calculate from price_per_night
    if ($revenue == 0) {
      try {
        $sql = "SELECT price_per_night, checkin, checkout FROM bookings";
        if ($where) {
          $sql .= $where;
        }
        $stmt = $pdo->query($sql);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($bookings as $booking) {
          if (!empty($booking['price_per_night']) && 
              !empty($booking['checkin']) && 
              !empty($booking['checkout'])) {
            $checkin = new DateTime($booking['checkin']);
            $checkout = new DateTime($booking['checkout']);
            $nights = $checkin->diff($checkout)->days;
            $nights = max(1, $nights);
            $revenue += $booking['price_per_night'] * $nights;
          }
        }
      } catch (Throwable $e) {
        // Silently fail
      }
    }
  } catch (Throwable $e) {
    // Silently handle errors
  }
  
  return $revenue;
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
      overflow-x: hidden;
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
      cursor: pointer;
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
      cursor: pointer;
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
      cursor: pointer;
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

    .stat-card h4 {
      font-size: 1.5rem;
      font-weight: 700;
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

    .section-header h6 {
      color: var(--text-dark);
      font-weight: 600;
      margin: 15px 0 10px;
    }

    .total-amount {
      background: var(--primary-light);
      color: var(--primary-color);
      padding: 8px 20px;
      border-radius: 20px;
      font-weight: 700;
      font-size: 1.1rem;
    }

    /* Table styles */
    .table-responsive {
      border-radius: var(--radius-sm);
      overflow: hidden;
      border: 1px solid var(--border-color);
    }

    .table {
      margin-bottom: 0;
    }

    .table thead th {
      background-color: var(--primary-color);
      color: white;
      border: none;
      padding: 12px 16px;
      font-weight: 600;
    }

    .table tbody td {
      padding: 12px 16px;
      vertical-align: middle;
    }

    .table-striped tbody tr:nth-of-type(odd) {
      background-color: var(--bg-light);
    }

    .table-striped tbody tr:hover {
      background-color: rgba(124, 58, 237, 0.05);
    }

    /* Button styles */
    .btn-group-sm .btn {
      padding: 4px 12px;
      font-size: 0.875rem;
    }

    .btn-outline-secondary.active {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
      color: white;
    }

    .badge-count {
      font-size: 0.75rem;
      padding: 3px 8px;
    }

    /* Alert styles */
    .alert {
      border-radius: var(--radius-sm);
      border: none;
      box-shadow: var(--shadow-sm);
      margin-bottom: 20px;
    }

    .btn-close:focus {
      box-shadow: none;
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

      .dashboard-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
      }

      .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
      }
    }

    @media (max-width: 768px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }

      .main-content {
        padding: 20px;
      }

      #revenueRangeCards .col-auto {
        width: 100%;
      }

      .btn-group-sm {
        flex-wrap: wrap;
      }
    }

    @media (max-width: 576px) {
      .top-navbar {
        padding: 0 1rem;
      }

      .user-info span {
        display: none;
      }

      .welcome-message {
        font-size: 0.8rem;
        padding: 6px 12px;
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
  </style>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <!-- top navbar -->
  <nav class="top-navbar navbar navbar-expand">
    <div class="container-fluid">
      <a class="navbar-brand" href="/hotel/admin/dashboard.php">
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
      <li>
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
      <form action="/hotel/admin/logout.php" method="post">
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
      
      // Set timezone for date calculations
      date_default_timezone_set('Asia/Kathmandu');
      $today = date('Y-m-d');
      
      // Basic counts
      $total_categories = safe_count($pdo, 'categories');
      $total_items = safe_count($pdo, 'items');
      $total_tables = safe_count($pdo, 'tables_info');
      
      // Rooms count
      $total_rooms = 0;
      if (table_exists($pdo, 'rooms')) {
        $total_rooms = safe_count($pdo, 'rooms');
      }
      
      // Order statistics
      $pending_orders = safe_count($pdo, 'orders', "status='Pending'");
      $completed_orders = safe_count($pdo, 'orders', "status='Completed'");
      
      // Today's orders count (all orders today regardless of status)
      $today_orders_count = safe_count($pdo, 'orders', "DATE(order_date) = '$today'");
      
      // Today's orders revenue (sum of all orders for today)
      $today_orders_revenue = safe_column_sum($pdo, 'orders', 'total_amount', 
        "DATE(order_date) = '$today'");

      // Total orders revenue (SUM of all orders regardless of status)
      $total_orders_revenue = safe_column_sum($pdo, 'orders', 'total_amount');
      
      // Bookings calculations
      $total_bookings = 0;
      $today_bookings_count = 0;
      $today_bookings_revenue = 0;
      $total_bookings_revenue = 0;
      
      if (table_exists($pdo, 'bookings')) {
        $total_bookings = safe_count($pdo, 'bookings');
        
        // Today's bookings count (trying different date columns)
        $date_columns = ['created_at', 'booking_date', 'checkin', 'date'];
        $detected_booking_date_col = null;
        foreach ($date_columns as $col) {
          try {
            $count = safe_count($pdo, 'bookings', "DATE($col) = '$today'");
            if ($count > 0) {
              $today_bookings_count = $count;
              $detected_booking_date_col = $col;
              break;
            }
          } catch (Throwable $e) {
            continue;
          }
        }

        // Today's bookings revenue: use detected date column if available,
        // and include all bookings (no status filter) when computing today's revenue.
        if ($detected_booking_date_col) {
          $today_bookings_revenue = get_bookings_revenue($pdo, "DATE($detected_booking_date_col) = '$today'", '');
        } else {
          // Fallback: try common columns and pick the first non-zero revenue
          foreach ($date_columns as $col) {
            try {
              $revenue = get_bookings_revenue($pdo, "DATE($col) = '$today'", '');
              if ($revenue > 0) {
                $today_bookings_revenue = $revenue;
                break;
              }
            } catch (Throwable $e) {
              continue;
            }
          }
        }
        
  // Total bookings revenue (SUM of all bookings regardless of status)
  $total_bookings_revenue = get_bookings_revenue($pdo, '', '');
      }
      
      // Calculate totals
      $today_revenue = $today_orders_revenue + $today_bookings_revenue;
      $total_revenue = $total_orders_revenue + $total_bookings_revenue;
      
      // Sales chart data for last 7 days
      $labels = [];
      $data = [];
      
      for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = $date;
        
        // Get daily orders revenue
        $daily_revenue = safe_column_sum($pdo, 'orders', 'total_amount', 
          "DATE(order_date) = '$date' AND status='Completed'");
        
        // Add daily bookings revenue if available
        if (table_exists($pdo, 'bookings')) {
          $daily_bookings_revenue = get_bookings_revenue($pdo, "DATE(created_at) = '$date'");
          $daily_revenue += $daily_bookings_revenue;
        }
        
        $data[] = $daily_revenue;
      }
      // Compute 7-day total (sum of the last 7 days shown in the chart)
      $seven_day_total = 0.0;
      foreach ($data as $v) { $seven_day_total += (float)$v; }
      ?>

      <?php if (!empty($_GET['order_created'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          Order #<?=htmlspecialchars((int)$_GET['order_created'])?> created successfully.
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <div class="dashboard-header">
        <div>
          <h1 class="h2">Dashboard Overview</h1>
          <div class="text-muted small">Welcome to your hotel management dashboard</div>
        </div>
        <div class="d-flex align-items-center gap-3">
          <?php if ($total_bookings > 0): ?>
            <a href="bookings.php" class="btn btn-outline-primary">
              <i class="fas fa-bed me-1"></i>
              Bookings
              <span class="badge bg-info text-dark ms-2"><?php echo $total_bookings; ?></span>
            </a>
          <?php endif; ?>
          <div class="welcome-message">
            <i class="fas fa-calendar-alt"></i>
            <?php echo date('F j, Y'); ?>
          </div>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card" onclick="window.location.href='categories.php'">
          <small><i class="fas fa-folder me-2"></i>Categories</small>
          <h3><?php echo $total_categories; ?></h3>
        </div>
        <div class="stat-card" onclick="window.location.href='items.php'">
          <small><i class="fas fa-utensils me-2"></i>Menu Items</small>
          <h3><?php echo $total_items; ?></h3>
        </div>
        <div class="stat-card" onclick="window.location.href='tables.php'">
          <small><i class="fas fa-chair me-2"></i>Tables</small>
          <h3><?php echo $total_tables; ?></h3>
        </div>
        <?php if ($total_rooms > 0): ?>
          <div class="stat-card" onclick="window.location.href='rooms.php'" style="border-left-color: var(--danger-color);">
            <small><i class="fas fa-door-open me-2"></i>Rooms</small>
            <h3><?php echo $total_rooms; ?></h3>
          </div>
        <?php endif; ?>
        <div class="stat-card" onclick="window.location.href='orders.php?status=Pending'">
          <small><i class="fas fa-clock me-2"></i>Pending Orders</small>
          <h3><?php echo $pending_orders; ?></h3>
        </div>
        <div class="stat-card" onclick="window.location.href='orders.php'">
          <small><i class="fas fa-receipt me-2"></i>Today's Orders</small>
          <h3 id="todayOrdersCount"><?php echo $today_orders_count; ?></h3>
        </div>
        <?php if (table_exists($pdo, 'bookings')): ?>
          <div class="stat-card" onclick="window.location.href='bookings.php'">
            <small><i class="fas fa-bed me-2"></i>Today's Bookings</small>
            <h3 id="todayBookingsCount"><?php echo $today_bookings_count; ?></h3>
          </div>
        <?php endif; ?>
        <div class="stat-card">
          <small><i class="fas fa-sun me-2"></i>Today's Revenue</small>
          <h3><?php echo format_npr($today_revenue); ?></h3>
        </div>
        <?php if (table_exists($pdo, 'bookings') && $today_bookings_revenue > 0): ?>
          <div class="stat-card">
            <small><i class="fas fa-dollar-sign me-2"></i>Today's Bookings Rev</small>
            <h3 id="todayBookingsRevenue"><?php echo format_npr($today_bookings_revenue); ?></h3>
          </div>
        <?php endif; ?>
      </div>

      <div class="chart-section">
        <div class="section-header">
          <h3 class="h5">Orders in Last 7 Days</h3>
          <div class="total-amount"><?php echo format_npr($seven_day_total); ?></div>
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
          <div class="stat-card" onclick="window.location.href='orders.php?status=Completed'">
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

      <!-- Reports Section -->
      <div class="chart-section mt-4">
        <div class="section-header">
          <h5>Reports</h5>
          <div class="d-flex gap-2 align-items-center flex-wrap">
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
              <div class="small text-muted">Orders Total</div>
              <div class="total-amount" id="ordersRangeTotal">रू 0.00</div>
            </div>
            <?php if (table_exists($pdo, 'bookings')): ?>
              <div class="ms-3">
                <div class="small text-muted">Bookings Total</div>
                <div class="total-amount" id="bookingsRangeTotal">रू 0.00</div>
              </div>
              <div class="ms-3">
                <div class="small text-muted">Combined Total</div>
                <div class="total-amount" id="combinedRangeTotal">रू 0.00</div>
              </div>
            <?php endif; ?>
            <button id="exportOrdersBtn" class="btn btn-sm btn-success">
              <i class="fas fa-file-csv"></i> Export Orders
              <span id="ordersCountBadge" class="badge bg-light text-dark ms-2 badge-count">0</span>
            </button>
            <?php if (table_exists($pdo, 'bookings')): ?>
              <button id="exportBookingsBtn" class="btn btn-sm btn-success">
                <i class="fas fa-file-csv"></i> Export Bookings
                <span id="bookingsCountBadge" class="badge bg-light text-dark ms-2 badge-count">0</span>
              </button>
            <?php endif; ?>
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

          <?php if (table_exists($pdo, 'bookings')): ?>
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
          <?php endif; ?>
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

    // Helper to format Nepali rupee in JS
    function formatNPR(value) {
      const num = Number(value) || 0;
      return 'रू ' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Escape HTML special characters
    function escapeHtml(s) { 
      if (s === null || s === undefined) return ''; 
      const div = document.createElement('div');
      div.textContent = s;
      return div.innerHTML;
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

      // Reports functionality
      const rangeButtons = document.getElementById('rangeButtons');
      let currentRange = 'today';

      function setActiveRangeBtn(btn) {
        [...rangeButtons.querySelectorAll('button')].forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentRange = btn.getAttribute('data-range');
        fetchAndRenderOrders(currentRange);
        fetchAndRenderBookings(currentRange);
        loadRangeRevenues();
      }

      rangeButtons.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', function() { setActiveRangeBtn(this); });
      });

      let ordersRawData = [];
      let bookingsRawData = [];
      let lastOrdersRangeTotal = 0;
      let lastBookingsRangeTotal = 0;

      async function fetchAndRenderOrders(range) {
        try {
          const res = await fetch('data_orders.php?range=' + encodeURIComponent(range));
          const json = await res.json();
          
          let rows = [];
          let orderCount = 0;
          let total = 0;
          
          if (Array.isArray(json)) {
            rows = json;
            orderCount = new Set(rows.map(r => r.order_id)).size;
            total = rows.reduce((sum, row) => {
              const price = parseFloat(row.price) || 0;
              const qty = parseInt(row.qty) || 1;
              return sum + (price * qty);
            }, 0);
          } else if (json && typeof json === 'object') {
            rows = json.rows || [];
            orderCount = json.order_count || new Set(rows.map(r => r.order_id)).size;
            total = parseFloat(json.total) || rows.reduce((sum, row) => {
              const price = parseFloat(row.price) || 0;
              const qty = parseInt(row.qty) || 1;
              return sum + (price * qty);
            }, 0);
          }

          ordersRawData = rows;
          renderOrdersTable(rows);
          
          // Update UI
          document.getElementById('ordersCountBadge').textContent = rows.length;
          document.getElementById('ordersRangeTotal').textContent = formatNPR(total);
          
          // Store and update combined total
          lastOrdersRangeTotal = total;
          const combinedEl = document.getElementById('combinedRangeTotal');
          if (combinedEl) {
            combinedEl.textContent = formatNPR(lastOrdersRangeTotal + lastBookingsRangeTotal);
          }

          // Update today's stats if viewing today
          if (range === 'today') {
            document.getElementById('todayOrdersCount').textContent = orderCount;
            const todayOrdersRevenue = document.getElementById('todayOrdersRevenue');
            if (todayOrdersRevenue) {
              todayOrdersRevenue.textContent = formatNPR(total);
            }
          }
        } catch (e) {
          console.error('Failed to load orders:', e);
          document.getElementById('ordersRangeTotal').textContent = 'रू 0.00';
        }
      }

      function renderOrdersTable(data) {
        const tbody = document.querySelector('#ordersTable tbody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        data.forEach(row => {
          const tr = document.createElement('tr');
          const price = parseFloat(row.price) || 0;
          tr.innerHTML = `
            <td>${escapeHtml(row.order_id)}</td>
            <td>${escapeHtml(row.item_name)}</td>
            <td>${formatNPR(price)}</td>
            <td>${escapeHtml(row.qty)}</td>
            <td>${escapeHtml(row.status)}</td>
            <td>${escapeHtml(row.payment_method)}</td>
            <td>${escapeHtml(row.table_number)}</td>
            <td>${escapeHtml(row.order_date)}</td>
          `;
          tbody.appendChild(tr);
        });
      }

      async function fetchAndRenderBookings(range) {
        const bookingsTable = document.getElementById('bookingsTable');
        const bookingsTotalEl = document.getElementById('bookingsRangeTotal');
        
        if (!bookingsTable || !bookingsTotalEl) {
          return;
        }
        
        try {
          const res = await fetch('data_bookings.php?range=' + encodeURIComponent(range));
          const json = await res.json();
          
          let rows = [];
          let total = 0;
          
          if (Array.isArray(json)) {
            rows = json;
            total = rows.reduce((sum, booking) => sum + (parseFloat(booking.amount || 0) || 0), 0);
          } else if (json && typeof json === 'object') {
            rows = json.rows || [];
            total = parseFloat(json.total) || rows.reduce((sum, booking) => sum + (parseFloat(booking.amount || 0) || 0), 0);
          }

          bookingsRawData = rows;
          const tbody = bookingsTable.querySelector('tbody');
          if (tbody) tbody.innerHTML = '';
          
          rows.forEach(booking => {
            if (tbody) {
              const tr = document.createElement('tr');
              const amount = parseFloat(booking.amount) || 0;
              tr.innerHTML = `
                <td>${escapeHtml(booking.id)}</td>
                <td>${escapeHtml(booking.guest)}</td>
                <td>${amount > 0 ? formatNPR(amount) : ''}</td>
                <td>${escapeHtml(booking.status)}</td>
                <td>${escapeHtml(booking.payment_method)}</td>
                <td>${escapeHtml(booking.booking_date)}</td>
              `;
              tbody.appendChild(tr);
            }
          });

          // Update UI
          bookingsTotalEl.textContent = formatNPR(total);
          document.getElementById('bookingsCountBadge').textContent = rows.length;
          
          // Store and update combined total
          lastBookingsRangeTotal = total;
          const combinedEl = document.getElementById('combinedRangeTotal');
          if (combinedEl) {
            combinedEl.textContent = formatNPR(lastOrdersRangeTotal + lastBookingsRangeTotal);
          }

          // Update today's stats if viewing today
          if (range === 'today') {
            document.getElementById('todayBookingsCount').textContent = rows.length;
            const todayBookingsRevenue = document.getElementById('todayBookingsRevenue');
            if (todayBookingsRevenue) {
              todayBookingsRevenue.textContent = formatNPR(total);
            }
          }
        } catch (e) {
          console.error('Failed to load bookings:', e);
          bookingsTotalEl.textContent = 'रू 0.00';
        }
      }

      async function loadRangeRevenues() {
        const ranges = [
          {key: '15', el: document.getElementById('revenue_15')},
          {key: '30', el: document.getElementById('revenue_30')},
          {key: '60', el: document.getElementById('revenue_60')},
          {key: '120', el: document.getElementById('revenue_120')},
          {key: '6m', el: document.getElementById('revenue_6m')},
          {key: '1y', el: document.getElementById('revenue_1y')}
        ];

        for (const range of ranges) {
          try {
            const [ordersRes, bookingsRes] = await Promise.all([
              fetch('data_orders.php?range=' + encodeURIComponent(range.key)),
              fetch('data_bookings.php?range=' + encodeURIComponent(range.key))
            ]);
            
            const ordersData = await ordersRes.json();
            const bookingsData = await bookingsRes.json();
            
            // Calculate orders total
            let ordersTotal = 0;
            if (Array.isArray(ordersData)) {
              ordersTotal = ordersData.reduce((sum, row) => {
                const price = parseFloat(row.price) || 0;
                const qty = parseInt(row.qty) || 1;
                return sum + (price * qty);
              }, 0);
            } else if (ordersData && typeof ordersData === 'object') {
              ordersTotal = parseFloat(ordersData.total) || 0;
            }
            
            // Calculate bookings total
            let bookingsTotal = 0;
            if (Array.isArray(bookingsData)) {
              bookingsTotal = bookingsData.reduce((sum, booking) => {
                return sum + (parseFloat(booking.amount || 0) || 0);
              }, 0);
            } else if (bookingsData && typeof bookingsData === 'object') {
              bookingsTotal = parseFloat(bookingsData.total) || 0;
            }
            
            const total = ordersTotal + bookingsTotal;
            if (range.el) {
              range.el.textContent = formatNPR(total);
            }
          } catch (e) {
            console.error('Failed to load revenue for', range.key, e);
            if (range.el) {
              range.el.textContent = 'रू 0.00';
            }
          }
        }
      }

      // Export buttons
      document.getElementById('exportOrdersBtn').addEventListener('click', function() {
        window.location.href = 'export_orders.php?range=' + encodeURIComponent(currentRange);
      });
      
      const exportBookingsBtn = document.getElementById('exportBookingsBtn');
      if (exportBookingsBtn) {
        exportBookingsBtn.addEventListener('click', function() {
          window.location.href = 'export_bookings.php?range=' + encodeURIComponent(currentRange);
        });
      }

      // Initialize Bootstrap alerts
      const alertList = document.querySelectorAll('.alert');
      alertList.forEach(function (alert) {
        const closeButton = alert.querySelector('.btn-close');
        if (closeButton) {
          closeButton.addEventListener('click', function () {
            alert.remove();
          });
        }
      });

      // Initial load
      fetchAndRenderOrders(currentRange);
      fetchAndRenderBookings(currentRange);
      loadRangeRevenues();
    });
  </script>
</body>
</html>