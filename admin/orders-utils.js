// orders-utils.js - Utility functions for orders management
const OrdersUtils = {
    // Format currency
    formatCurrency: (amount) => {
        return 'रु ' + parseFloat(amount || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },
    
    // Format date
    formatDate: (dateString, format = 'medium') => {
        const date = new Date(dateString);
        const options = {
            short: {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            },
            medium: {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            },
            long: {
                weekday: 'short',
                day: '2-digit',
                month: 'long',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            }
        };
        
        return date.toLocaleDateString('en-GB', options[format] || options.medium);
    },
    
    // Get status badge class
    getStatusClass: (status) => {
        const classes = {
            'Pending': 'warning',
            'Processing': 'info',
            'Completed': 'success',
            'Delivered': 'primary',
            'Cancelled': 'danger'
        };
        return classes[status] || 'secondary';
    },
    
    // Calculate order summary
    calculateOrderSummary: (items) => {
        let total = 0;
        let totalQty = 0;
        
        if (Array.isArray(items)) {
            items.forEach(item => {
                const price = parseFloat(item.price) || 0;
                const qty = parseInt(item.qty) || 0;
                total += price * qty;
                totalQty += qty;
            });
        }
        
        return {
            total: total,
            totalQty: totalQty,
            formattedTotal: this.formatCurrency(total)
        };
    },
    
    // Generate items preview
    getItemsPreview: (items, maxItems = 2) => {
        if (!Array.isArray(items)) return 'No items';
        
        const preview = items.slice(0, maxItems).map(item => 
            `${item.name || 'Item'} × ${item.qty || 0}`
        ).join(', ');
        
        if (items.length > maxItems) {
            return preview + ` (+${items.length - maxItems} more)`;
        }
        
        return preview;
    },
    
    // Export to CSV
    exportToCSV: (orders, filename = 'orders.csv') => {
        if (!orders.length) {
            alert('No orders to export');
            return;
        }
        
        const headers = ['ID', 'Customer Name', 'Phone', 'Email', 'Status', 'Payment', 'Table', 'Total Amount', 'Date', 'Items Count'];
        
        const csvContent = [
            headers.join(','),
            ...orders.map(order => {
                const items = order.items || [];
                return [
                    order.id,
                    `"${(order.customer_name || '').replace(/"/g, '""')}"`,
                    order.customer_phone || '',
                    order.customer_email || '',
                    order.status,
                    order.payment_method,
                    order.table_number || '',
                    order.total_amount,
                    order.order_date,
                    items.length
                ].join(',');
            })
        ].join('\n');
        
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
};

// Make it globally available
window.OrdersUtils = OrdersUtils;