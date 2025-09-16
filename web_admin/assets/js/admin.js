// Admin Dashboard JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Load recent reports on dashboard
    if (document.getElementById('recent-reports')) {
        loadRecentReports();
    }

    // Auto-refresh dashboard stats every 30 seconds
    const isDashboard = location.pathname.endsWith('/index.php') || location.pathname === '/' || location.pathname.endsWith('/');
    if (isDashboard) {
        // Show loading indicator
        showLoadingIndicator();
        
        // Kick off an immediate fetch so cards populate quickly
        refreshStats();
        
        // Then refresh every 30s
        setInterval(refreshStats, 30000);
    }

    // Provide reports helpers on pages that include only admin.js
    if (typeof window.viewReportDetails !== 'function') {
        window.viewReportDetails = function(reportId) {
            const detailsUrl = 'reports.php?view=' + encodeURIComponent(reportId);
            if (window.location.pathname.endsWith('/reports.php')) {
                const rowBtn = document.querySelector(`button[onclick="viewReportDetails('${reportId}')"]`);
                const row = rowBtn ? rowBtn.closest('tr') : null;
                const descCell = row ? row.querySelector('td:nth-child(5)') : null;
                alert('Report ID: ' + reportId + (descCell ? '\n\n' + descCell.innerText.trim() : ''));
            } else {
                window.location.href = detailsUrl;
            }
        }

        window.showWarningModal = function(reportId) {
            const idField = document.getElementById('warningReportId');
            if (idField) idField.value = reportId;
            const modal = new bootstrap.Modal(document.getElementById('warningModal'));
            modal.show();
        }

        window.showBanModal = function(reportId) {
            const idField = document.getElementById('banReportId');
            if (idField) idField.value = reportId;
            const modal = new bootstrap.Modal(document.getElementById('banModal'));
            modal.show();
        }

        window.resolveReport = function(reportId) { postAction('resolve', reportId); }
        window.dismissReport = function(reportId) { postAction('dismiss', reportId); }

        window.refreshReports = function() { location.reload(); }
    }
});

// Dashboard Functions
function showLoadingIndicator() {
    // Add loading spinners to stat cards
    const statCards = document.querySelectorAll('.h5.mb-0.font-weight-bold.text-gray-800');
    statCards.forEach(card => {
        if (card.textContent === '0' || card.textContent === '0.0') {
            card.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        }
    });
}

function hideLoadingIndicator() {
    // Remove loading spinners
    const loadingElements = document.querySelectorAll('.fa-spinner');
    loadingElements.forEach(element => {
        element.parentElement.innerHTML = '0';
    });
}

function refreshStats() {
    // Prefer the comprehensive stats endpoint; fall back if unavailable
    fetch('api/get_comprehensive_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                updateDashboardStatsFromComprehensive(data.data);
                hideLoadingIndicator();
                showNotification('Stats refreshed successfully', 'success');
            } else {
                hideLoadingIndicator();
                showNotification('Failed to refresh stats', 'error');
            }
        })
        .catch(error => {
            console.error('Error refreshing stats:', error);
            hideLoadingIndicator();
            showNotification('Error refreshing stats', 'error');
        });
}

function updateDashboardStatsFromComprehensive(stats) {
    // Map to the existing UI elements
    const totalUsersEl = document.querySelector('.border-left-primary .h5');
    const activeListingsEl = document.querySelector('.border-left-success .h5');
    const pendingReportsEl = document.querySelector('.border-left-warning .h5');
    const foodSavedEl = document.querySelector('.border-left-info .h5');

    if (totalUsersEl && stats.users) totalUsersEl.textContent = formatNumber(stats.users.total_users || 0);
    if (activeListingsEl && stats.listings) activeListingsEl.textContent = formatNumber(stats.listings.active_listings || 0);
    if (pendingReportsEl && stats.reports) pendingReportsEl.textContent = formatNumber(stats.reports.pending_reports || 0);
    if (foodSavedEl && stats.orders) foodSavedEl.textContent = formatNumber((stats.orders.total_food_saved || 0), 1);

    // Secondary lines under cards
    const usersBreakdown = document.querySelector('.border-left-primary .text-xs.text-muted');
    if (usersBreakdown && stats.users) {
        usersBreakdown.innerHTML = `${stats.users.providers || 0} Providers • ${stats.users.consumers || 0} Consumers`;
    }
    const listingsRevenue = document.querySelector('.border-left-success .text-xs.text-muted');
    if (listingsRevenue && stats.listings) {
        listingsRevenue.innerHTML = `₱${formatNumber(stats.listings.total_revenue || 0)} Revenue`;
    }
    const reportsTotal = document.querySelector('.border-left-warning .text-xs.text-muted');
    if (reportsTotal && stats.reports) {
        reportsTotal.innerHTML = `${stats.reports.total_reports || 0} Total Reports`;
    }
    const savingsText = document.querySelector('.border-left-info .text-xs.text-muted');
    if (savingsText && stats.orders) {
        savingsText.innerHTML = `₱${formatNumber(stats.orders.total_savings || 0)} Saved`;
    }

    // Additional cards
    const totalOrdersEl = document.querySelector('.border-left-secondary .h5');
    if (totalOrdersEl && stats.orders) totalOrdersEl.textContent = formatNumber(stats.orders.total_orders || 0);
    const completedOrdersText = document.querySelector('.border-left-secondary .text-xs.text-muted');
    if (completedOrdersText && stats.orders) completedOrdersText.innerHTML = `${stats.orders.completed_orders || 0} Completed`;
    const avgOrderEl = document.querySelector('.border-left-dark .h5');
    if (avgOrderEl && stats.orders) avgOrderEl.textContent = `₱${formatNumber(stats.orders.average_order_value || 0)}`;
    const recentSignupsEl = document.querySelector('.col-xl-3.col-md-6.mb-4 .card .h5.mb-0.font-weight-bold.text-gray-800');
    // Not reliable selector for recent signups; skip unless needed
}

function updateDashboardStats(stats) {
    // Update statistics cards
    const statElements = {
        'total_users': document.querySelector('.border-left-primary .h5'),
        'active_listings': document.querySelector('.border-left-success .h5'),
        'pending_reports': document.querySelector('.border-left-warning .h5'),
        'food_saved': document.querySelector('.border-left-info .h5')
    };

    if (statElements.total_users) {
        statElements.total_users.textContent = formatNumber(stats.total_users);
    }
    if (statElements.active_listings) {
        statElements.active_listings.textContent = formatNumber(stats.active_listings);
    }
    if (statElements.pending_reports) {
        statElements.pending_reports.textContent = formatNumber(stats.pending_reports);
    }
    if (statElements.food_saved) {
        statElements.food_saved.textContent = formatNumber(stats.food_saved, 1) + ' kg';
    }
}

function loadRecentReports() {
    fetch('api/get_recent_reports.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRecentReports(data.reports);
            }
        })
        .catch(error => {
            console.error('Error loading recent reports:', error);
        });
}

function displayRecentReports(reports) {
    const container = document.getElementById('recent-reports');
    if (!container) return;

    if (reports.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">No recent reports</p>';
        return;
    }

    let html = '<div class="list-group list-group-flush">';
    reports.forEach(report => {
        const statusColor = getStatusColor(report.status);
        const typeColor = getReportTypeColor(report.type);
        
        html += `
            <div class="list-group-item d-flex justify-content-between align-items-start">
                <div class="ms-2 me-auto">
                    <div class="fw-bold">${escapeHtml(report.reporter_name)}</div>
                    <small class="text-muted">${escapeHtml(report.description)}</small>
                </div>
                <div class="d-flex flex-column align-items-end">
                    <span class="badge bg-${typeColor} mb-1">${escapeHtml(report.type)}</span>
                    <span class="badge bg-${statusColor}">${escapeHtml(report.status)}</span>
                    <small class="text-muted mt-1">${formatDate(report.created_at)}</small>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

// Utility Functions
function formatNumber(num, decimals = 0) {
    return new Intl.NumberFormat().format(num.toFixed(decimals));
}

function formatDate(timestamp) {
    const date = new Date(timestamp * 1000);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'resolved': 'success',
        'dismissed': 'secondary'
    };
    return colors[status] || 'primary';
}

function getReportTypeColor(type) {
    const colors = {
        'inappropriate_content': 'danger',
        'poor_quality': 'warning',
        'fake_listing': 'info',
        'spam': 'secondary',
        'other': 'dark'
    };
    return colors[type] || 'primary';
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Search and Filter Functions
function searchTable(tableId, searchTerm) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    const searchLower = searchTerm.toLowerCase();
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchLower) ? '' : 'none';
    });
}

function filterTable(tableId, column, value) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const cell = row.querySelector(`td:nth-child(${column})`);
        if (cell) {
            const cellText = cell.textContent.toLowerCase();
            row.style.display = value === 'all' || cellText.includes(value.toLowerCase()) ? '' : 'none';
        }
    });
}

// Export Functions
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    rows.forEach(row => {
        const rowData = [];
        row.querySelectorAll('th, td').forEach(cell => {
            rowData.push(`"${cell.textContent.trim()}"`);
        });
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename || 'export.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Print Functions
function printPage() {
    window.print();
}

function printTable(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Print Table</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { font-size: 12px; }
                    .table { font-size: 11px; }
                    @media print {
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                <div class="container-fluid">
                    <h4 class="mb-3">MealDeal Admin Report</h4>
                    ${table.outerHTML}
                </div>
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Modal Functions
function showModal(modalId) {
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    modal.show();
}

function hideModal(modalId) {
    const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
    if (modal) {
        modal.hide();
    }
}

// Form Validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// AJAX Helper Functions
function makeRequest(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        }
    };
    
    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }
    
    return fetch(url, options)
        .then(response => response.json())
        .catch(error => {
            console.error('Request failed:', error);
            throw error;
        });
}

// Chart Functions (if using Chart.js)
function createChart(canvasId, type, data, options = {}) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    
    const ctx = canvas.getContext('2d');
    return new Chart(ctx, {
        type: type,
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            ...options
        }
    });
}

// Keyboard Shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + R to refresh
    if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
        e.preventDefault();
        refreshStats();
    }
    
    // Ctrl/Cmd + S to search
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.focus();
        }
    }
});

// Performance Monitoring
function logPerformance(label, startTime) {
    const endTime = performance.now();
    console.log(`${label}: ${endTime - startTime}ms`);
}

// Error Handling
window.addEventListener('error', function(e) {
    console.error('Global error:', e.error);
    showNotification('An error occurred. Please check the console for details.', 'error');
});

// Unhandled Promise Rejection
window.addEventListener('unhandledrejection', function(e) {
    console.error('Unhandled promise rejection:', e.reason);
    showNotification('An unexpected error occurred.', 'error');
});
