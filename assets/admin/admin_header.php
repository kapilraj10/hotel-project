<?php
// admin_header.php
// This file should be included at the top of every admin page
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin - <?= isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard' ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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

    /* Page header */
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      background: white;
      padding: 25px;
      border-radius: var(--radius);
      box-shadow: var(--shadow-sm);
    }

    .page-header h1 {
      color: var(--primary-color);
      font-weight: 700;
      margin-bottom: 5px;
    }

    .page-header .btn-add {
      background: var(--primary-color);
      color: white;
      padding: 12px 24px;
      border-radius: var(--radius-sm);
      text-decoration: none;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s ease;
    }

    .page-header .btn-add:hover {
      background: #6d28d9;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
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
        padding: 20px;
      }

      .page-header {
        flex-direction: column;
        gap: 20px;
        text-align: center;
      }
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

    /* Animation */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .fade-in {
      animation: fadeIn 0.5s ease-out;
    }
  </style>
</head>
<body>
  <!-- top navbar -->
  <nav class="top-navbar navbar navbar-expand">
    <div class="container-fluid">
      <a class="navbar-brand" href="dashboard.php">
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
        <a href="rooms.php" class="<?= $current=='rooms.php' ? 'active' : '' ?>">
          <span class="icon"><i class="fas fa-door-open"></i></span>
          <span class="menu-text">Rooms</span>
          </a>
      </li>
  
    </ul>

    <div class="logout-section">
      <form action="logout.php" method="post">
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