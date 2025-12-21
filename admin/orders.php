<?php
// orders.php - Main orders management page
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../db_rest.php';

$pdo = get_rest_db();

// Handle AJAX POST actions (get_orders, delete_order, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Unknown action'];

    switch ($action) {
        case 'get_orders':
            $search = $_POST['search'] ?? '';
            $status = $_POST['status'] ?? '';
            $date = $_POST['date'] ?? '';
            $table = $_POST['table'] ?? '';
            $page = (int)($_POST['page'] ?? 1);
            $per_page = (int)($_POST['per_page'] ?? 10);
            $offset = ($page - 1) * $per_page;

            // Build WHERE clause
            $where = [];
            $params = [];

            if (!empty($search)) {
                $where[] = '(customer_name LIKE ? OR customer_phone LIKE ? OR customer_email LIKE ? OR id = ?)';
                $search_term = "%$search%";
                $params[] = $search_term;
                $params[] = $search_term;
                $params[] = $search_term;
                $params[] = is_numeric($search) ? (int)$search : 0;
            }

            if (!empty($status) && $status !== 'all') {
                $where[] = 'status = ?';
                $params[] = $status;
            }

            if (!empty($date)) {
                $where[] = 'DATE(order_date) = ?';
                $params[] = $date;
            }

            if (!empty($table) && $table !== 'all') {
                $where[] = 'table_number = ?';
                $params[] = $table;
            }

            // Count total orders
            $count_sql = 'SELECT COUNT(*) as total FROM orders';
            if (!empty($where)) {
                $count_sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $count_stmt = $pdo->prepare($count_sql);
            $count_stmt->execute($params);
            $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get orders with pagination
            $sql = 'SELECT * FROM orders';
            if (!empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY order_date DESC, id DESC LIMIT ? OFFSET ?';

            $params[] = $per_page;
            $params[] = $offset;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format items for each order
            foreach ($orders as &$order) {
                $order['items'] = json_decode($order['items_json'] ?? '[]', true);
                unset($order['items_json']);
            }

            $response = [
                'success' => true,
                'orders' => $orders,
                'total' => $total_count,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($total_count / $per_page)
            ];
            break;

        case 'update_status':
            $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
            $new_status = $_POST['status'] ?? '';
            if ($order_id && !empty($new_status)) {
                try {
                    $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
                    $ok = $stmt->execute([$new_status, $order_id]);
                    $response = ['success' => (bool)$ok, 'message' => $ok ? 'Status updated' : 'Failed to update status'];
                } catch (Exception $e) {
                    $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
                }
            } else {
                $response = ['success' => false, 'message' => 'Invalid parameters'];
            }
            break;

        case 'delete_order':
            $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
            if ($order_id) {
                try {
                    $stmt = $pdo->prepare('DELETE FROM orders WHERE id = ?');
                    $ok = $stmt->execute([$order_id]);
                    $response = ['success' => (bool)$ok, 'message' => $ok ? 'Order deleted successfully' : 'Failed to delete order'];
                } catch (Exception $e) {
                    $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
                }
            } else {
                $response = ['success' => false, 'message' => 'Invalid order id'];
            }
            break;

        default:
            $response = ['success' => false, 'message' => 'Unknown or unsupported action'];
    }

    echo json_encode($response);
    exit;
}

// Regular page load
$page_title = 'Orders Management';
include __DIR__ . '/admin_header.php';

// Get tables for filter
$tables = $pdo->query('SELECT table_number FROM tables_info ORDER BY table_number')->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0">Orders Management</h3>
            <p class="text-muted mb-0">View and manage all customer orders</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="dashboard.php">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
            <a class="btn btn-primary" href="order_create.php">
                <i class="fas fa-plus-circle me-2"></i> Create Order
            </a>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search orders...">
                    </div>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select id="statusFilter" class="form-select">
                        <option value="all">All Status</option>
                        <option value="Pending">Pending</option>
                        <option value="Processing">Processing</option>
                        <option value="Completed">Completed</option>
                        <option value="Delivered">Delivered</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Table</label>
                    <select id="tableFilter" class="form-select">
                        <option value="all">All Tables</option>
                        <?php foreach ($tables as $table): ?>
                            <option value="<?= htmlspecialchars($table) ?>">Table <?= htmlspecialchars($table) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Date</label>
                    <input type="date" id="dateFilter" class="form-control">
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <div class="d-flex gap-2 w-100">
                        <button type="button" id="applyFilters" class="btn btn-primary flex-fill">
                            <i class="fas fa-filter me-2"></i> Filter
                        </button>
                        <button type="button" id="resetFilters" class="btn btn-outline-secondary">
                            <i class="fas fa-redo"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Table Card -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">All Orders</h5>
                <div class="d-flex gap-2">
                    <div class="input-group input-group-sm" style="width: 120px;">
                        <span class="input-group-text">Show</span>
                        <select id="perPageSelect" class="form-select form-select-sm">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    <button type="button" id="refreshBtn" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="ordersTable">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Table</th>
                            <th>Date & Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody">
                        <!-- Orders will be loaded here via AJAX -->
                    </tbody>
                </table>
            </div>
            
            <!-- Loading State -->
            <div id="loadingState" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading orders...</p>
            </div>
            
            <!-- Empty State -->
            <div id="emptyState" class="text-center py-5 d-none">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No orders found</h5>
                <p class="text-muted mb-0">Try adjusting your filters</p>
            </div>
            
            <!-- Error State -->
            <div id="errorState" class="text-center py-5 d-none">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <h5 class="text-danger">Failed to load orders</h5>
                <p class="text-muted mb-3" id="errorMessage"></p>
                <button type="button" id="retryBtn" class="btn btn-primary">Retry</button>
            </div>
        </div>
        
        <!-- Pagination -->
        <div class="card-footer bg-white">
            <nav aria-label="Orders pagination">
                <ul class="pagination justify-content-center mb-0" id="pagination">
                    <!-- Pagination will be generated here -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- View Order Modal -->
<div class="modal fade" id="viewOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Details #<span id="modalOrderId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Customer Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Name:</th>
                                <td id="modalCustomerName"></td>
                            </tr>
                            <tr>
                                <th>Phone:</th>
                                <td id="modalCustomerPhone"></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td id="modalCustomerEmail"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Order Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Status:</th>
                                <td><span class="badge" id="modalStatus"></span></td>
                            </tr>
                            <tr>
                                <th>Payment:</th>
                                <td id="modalPayment"></td>
                            </tr>
                            <tr>
                                <th>Table:</th>
                                <td id="modalTable"></td>
                            </tr>
                            <tr>
                                <th>Type:</th>
                                <td id="modalOrderType"></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <h6 class="mt-4">Order Items</h6>
                <div class="table-responsive">
                    <table class="table table-sm" id="modalItemsTable">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="modalItemsBody">
                            <!-- Items will be added here -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total:</th>
                                <th id="modalTotalAmount"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Order Notes</h6>
                        <p id="modalOrderNotes" class="text-muted"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="modalReceiptBtn" class="btn btn-primary" target="_blank">
                    <i class="fas fa-receipt me-2"></i> View Receipt
                </a>
                <a href="#" id="modalEditBtn" class="btn btn-warning">
                    <i class="fas fa-edit me-2"></i> Edit Order
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="statusOrderId">
                <div class="mb-3">
                    <label class="form-label">Select Status</label>
                    <select id="statusSelect" class="form-select">
                        <option value="Pending">Pending</option>
                        <option value="Processing">Processing</option>
                        <option value="Completed">Completed</option>
                        <option value="Delivered">Delivered</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="saveStatusBtn" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentPage = 1;
    let perPage = 10;
    let filters = {
        search: '',
        status: 'all',
        table: 'all',
        date: ''
    };
    
    // DOM Elements
    const tableBody = document.getElementById('ordersTableBody');
    const loadingState = document.getElementById('loadingState');
    const emptyState = document.getElementById('emptyState');
    const errorState = document.getElementById('errorState');
    const errorMessage = document.getElementById('errorMessage');
    const pagination = document.getElementById('pagination');
    const perPageSelect = document.getElementById('perPageSelect');
    
    // Initialize
    loadOrders();
    
    // Event Listeners
    document.getElementById('applyFilters').addEventListener('click', function() {
        filters.search = document.getElementById('searchInput').value;
        filters.status = document.getElementById('statusFilter').value;
        filters.table = document.getElementById('tableFilter').value;
        filters.date = document.getElementById('dateFilter').value;
        currentPage = 1;
        loadOrders();
    });
    
    document.getElementById('resetFilters').addEventListener('click', function() {
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = 'all';
        document.getElementById('tableFilter').value = 'all';
        document.getElementById('dateFilter').value = '';
        filters = { search: '', status: 'all', table: 'all', date: '' };
        currentPage = 1;
        loadOrders();
    });
    
    document.getElementById('refreshBtn').addEventListener('click', function() {
        loadOrders();
        showAlert('Orders refreshed!', 'success');
    });
    
    document.getElementById('retryBtn').addEventListener('click', loadOrders);
    
    perPageSelect.addEventListener('change', function() {
        perPage = parseInt(this.value);
        currentPage = 1;
        loadOrders();
    });
    
    // Status badge class mapping
    const statusClasses = {
        'Pending': 'bg-warning',
        'Processing': 'bg-info',
        'Completed': 'bg-success',
        'Delivered': 'bg-primary',
        'Cancelled': 'bg-danger'
    };
    
    // Load orders via AJAX
    function loadOrders() {
        showLoading();
        
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('action', 'get_orders');
        formData.append('search', filters.search);
        formData.append('status', filters.status);
        formData.append('date', filters.date);
        formData.append('table', filters.table);
        formData.append('page', currentPage);
        formData.append('per_page', perPage);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                renderOrders(data.orders);
                renderPagination(data.total, data.page, data.per_page, data.total_pages);
                if (data.orders.length === 0) {
                    showEmpty();
                } else {
                    hideStates();
                }
            } else {
                showError('Failed to load orders');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Network error: ' + error.message);
        });
    }
    
    // Render orders table
    function renderOrders(orders) {
        tableBody.innerHTML = '';
        
        orders.forEach(order => {
            const items = order.items || [];
            const itemsCount = items.length;
            const firstItems = items.slice(0, 2);
            
            // Calculate total quantity
            const totalQty = items.reduce((sum, item) => sum + (parseInt(item.qty) || 0), 0);
            
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="fw-bold">#${order.id}</td>
                <td>
                    <div class="fw-medium">${escapeHtml(order.customer_name || 'Walk-in Customer')}</div>
                    ${order.customer_phone ? `<small class="text-muted">${escapeHtml(order.customer_phone)}</small>` : ''}
                </td>
                <td>
                    <div class="d-flex flex-column">
                        ${firstItems.map(item => 
                            `<small>${escapeHtml(item.name || 'Item')} × ${item.qty || 0}</small>`
                        ).join('')}
                        ${itemsCount > 2 ? 
                            `<small class="text-muted">+${itemsCount - 2} more items</small>` : ''}
                    </div>
                </td>
                <td>
                    <div class="fw-bold">रु ${parseFloat(order.total_amount || 0).toFixed(2)}</div>
                    <small class="text-muted">${totalQty} items</small>
                </td>
                <td>
                    <span class="badge ${statusClasses[order.status] || 'bg-secondary'}">
                        ${order.status}
                    </span>
                </td>
                <td>
                    ${order.payment_method ? 
                        `<span class="badge bg-light text-dark">${escapeHtml(order.payment_method)}</span>` : 
                        '<span class="text-muted">None</span>'}
                </td>
                <td>
                    ${order.table_number ? 
                        `<span class="badge bg-info">Table ${escapeHtml(order.table_number)}</span>` : 
                        '<span class="text-muted">—</span>'}
                </td>
                <td>
                    <small class="d-block">${formatDate(order.order_date)}</small>
                    <small class="text-muted">${formatTime(order.order_date)}</small>
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary view-btn" data-id="${order.id}" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-warning status-btn" data-id="${order.id}" title="Change Status">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <a class="btn btn-sm btn-outline-success" href="orders_edit.php?id=${order.id}" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                    </div>
                </td>
            `;
            
            tableBody.appendChild(row);
        });
        
        // Add event listeners to buttons
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const orderId = this.dataset.id;
                viewOrder(orderId);
            });
        });
        
        document.querySelectorAll('.status-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const orderId = this.dataset.id;
                showStatusModal(orderId);
            });
        });
    }
    
    // Render pagination
    function renderPagination(total, current, per_page, total_pages) {
        pagination.innerHTML = '';
        
        if (total_pages <= 1) return;
        
        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${current === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `
            <a class="page-link" href="#" data-page="${current - 1}" ${current === 1 ? 'tabindex="-1"' : ''}>
                <i class="fas fa-chevron-left"></i>
            </a>
        `;
        pagination.appendChild(prevLi);
        
        // Page numbers
        const maxVisible = 5;
        let start = Math.max(1, current - Math.floor(maxVisible / 2));
        let end = Math.min(total_pages, start + maxVisible - 1);
        
        if (end - start + 1 < maxVisible) {
            start = Math.max(1, end - maxVisible + 1);
        }
        
        for (let i = start; i <= end; i++) {
            const pageLi = document.createElement('li');
            pageLi.className = `page-item ${i === current ? 'active' : ''}`;
            pageLi.innerHTML = `
                <a class="page-link" href="#" data-page="${i}">${i}</a>
            `;
            pagination.appendChild(pageLi);
        }
        
        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${current === total_pages ? 'disabled' : ''}`;
        nextLi.innerHTML = `
            <a class="page-link" href="#" data-page="${current + 1}" ${current === total_pages ? 'tabindex="-1"' : ''}>
                <i class="fas fa-chevron-right"></i>
            </a>
        `;
        pagination.appendChild(nextLi);
        
        // Add event listeners
        pagination.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = parseInt(this.dataset.page);
                if (page && page !== currentPage) {
                    currentPage = page;
                    loadOrders();
                }
            });
        });
    }
    
    // View order details
    function viewOrder(orderId) {
        // In a real implementation, you would fetch order details via AJAX
        // For now, redirect to view page
        window.location.href = `orders_view.php?id=${orderId}`;
    }
    
    // Show status update modal
    function showStatusModal(orderId) {
        document.getElementById('statusOrderId').value = orderId;
        const modal = new bootstrap.Modal(document.getElementById('statusModal'));
        modal.show();
    }
    
    // Save status update
    document.getElementById('saveStatusBtn').addEventListener('click', function() {
        const orderId = document.getElementById('statusOrderId').value;
        const newStatus = document.getElementById('statusSelect').value;
        
        if (!orderId) return;
        
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('action', 'update_status');
        formData.append('order_id', orderId);
        formData.append('status', newStatus);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Status updated successfully!', 'success');
                loadOrders();
                const modal = bootstrap.Modal.getInstance(document.getElementById('statusModal'));
                if (modal) modal.hide();
            } else {
                showAlert('Failed to update status: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            showAlert('Network error: ' + error.message, 'danger');
        });
    });
    
    // State management functions
    function showLoading() {
        loadingState.classList.remove('d-none');
        tableBody.innerHTML = '';
        emptyState.classList.add('d-none');
        errorState.classList.add('d-none');
        pagination.innerHTML = '';
    }
    
    function showEmpty() {
        loadingState.classList.add('d-none');
        tableBody.innerHTML = '';
        emptyState.classList.remove('d-none');
        errorState.classList.add('d-none');
        pagination.innerHTML = '';
    }
    
    function showError(message) {
        loadingState.classList.add('d-none');
        tableBody.innerHTML = '';
        emptyState.classList.add('d-none');
        errorState.classList.remove('d-none');
        errorMessage.textContent = message;
        pagination.innerHTML = '';
    }
    
    function hideStates() {
        loadingState.classList.add('d-none');
        emptyState.classList.add('d-none');
        errorState.classList.add('d-none');
    }
    
    // Helper functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }
    
    function formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    }
    
    function showAlert(message, type = 'info') {
        // Remove existing alerts
        const existingAlert = document.querySelector('.alert[role="alert"]');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        // Create new alert
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '1055';
        alertDiv.style.minWidth = '300px';
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'danger' ? 'fa-exclamation-circle' : 'fa-info-circle'} me-2"></i>
                <div class="flex-grow-1">${message}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                const bsAlert = new bootstrap.Alert(alertDiv);
                bsAlert.close();
            }
        }, 3000);
    }
});
</script>

<style>
.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.table th {
    border-top: none;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
}

.table td {
    vertical-align: middle;
}

.badge {
    font-weight: 500;
    padding: 0.35em 0.65em;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.page-item.active .page-link {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.table-responsive {
    min-height: 400px;
}

#loadingState, #emptyState, #errorState {
    min-height: 400px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}
</style>

<?php include __DIR__ . '/admin_footer.php'; ?>