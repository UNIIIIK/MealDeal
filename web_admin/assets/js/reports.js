// Lightweight handlers for the Reports page
document.addEventListener('DOMContentLoaded', function() {
    if (!window.viewReportDetails) {
        window.viewReportDetails = function(reportId) {
            // Show modal with loading spinner
            const modal = new bootstrap.Modal(document.getElementById('reportDetailsModal'));
            const content = document.getElementById('reportDetailsContent');
            content.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            modal.show();
            
            // Fetch report details
            fetch(`api/get_report_details.php?id=${encodeURIComponent(reportId)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayReportDetails(data.report);
                    } else {
                        content.innerHTML = '<div class="alert alert-danger">Error loading report details: ' + (data.error || 'Unknown error') + '</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = '<div class="alert alert-danger">Failed to load report details. Please try again.</div>';
                });
        }
    }
    
    function displayReportDetails(report) {
        const content = document.getElementById('reportDetailsContent');
        const createdAt = report.created_at ? new Date(report.created_at).toLocaleString() : 'Unknown';
        
        content.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Report Information</h6>
                    <table class="table table-sm">
                        <tr><td><strong>ID:</strong></td><td>${report.id || 'N/A'}</td></tr>
                        <tr><td><strong>Type:</strong></td><td><span class="badge bg-${getReportTypeColor(report.type)}">${report.type || 'Unknown'}</span></td></tr>
                        <tr><td><strong>Status:</strong></td><td><span class="badge bg-${getStatusColor(report.status)}">${report.status || 'Unknown'}</span></td></tr>
                        <tr><td><strong>Created:</strong></td><td>${createdAt}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>People Involved</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Reporter:</strong></td><td>${report.reporter_name || 'Anonymous'}</td></tr>
                        <tr><td><strong>Target:</strong></td><td>${report.target_name || 'Unknown'}</td></tr>
                    </table>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <h6>Description</h6>
                    <div class="border p-3 bg-light">
                        ${report.description || 'No description provided'}
                    </div>
                </div>
            </div>
            ${report.admin_notes ? `
            <div class="row mt-3">
                <div class="col-12">
                    <h6>Admin Notes</h6>
                    <div class="border p-3 bg-warning bg-opacity-10">
                        ${report.admin_notes}
                    </div>
                </div>
            </div>
            ` : ''}
        `;
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
    
    function getStatusColor(status) {
        const colors = {
            'pending': 'warning',
            'resolved': 'success',
            'dismissed': 'secondary'
        };
        return colors[status] || 'primary';
    }

    if (!window.showWarningModal) {
        window.showWarningModal = function(reportId) {
            const idField = document.getElementById('warningReportId');
            if (idField) idField.value = reportId;
            const modal = new bootstrap.Modal(document.getElementById('warningModal'));
            modal.show();
        }
    }

    if (!window.showBanModal) {
        window.showBanModal = function(reportId) {
            const idField = document.getElementById('banReportId');
            if (idField) idField.value = reportId;
            const modal = new bootstrap.Modal(document.getElementById('banModal'));
            modal.show();
        }
    }

    if (!window.resolveReport) {
        window.resolveReport = function(reportId) { postAction('resolve', reportId); }
    }

    if (!window.dismissReport) {
        window.dismissReport = function(reportId) { postAction('dismiss', reportId); }
    }

    if (!window.refreshReports) {
        window.refreshReports = function() { location.reload(); }
    }
});

async function postAction(action, reportId) {
    try {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('report_id', reportId);
        const res = await fetch('reports.php', { method: 'POST', body: formData });
        if (res.ok) location.reload();
    } catch (e) { console.error(e); }
}


