<?php
/**
 * Leave Approval - Manager/Admin View
 * 
 * @package HR3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

use HR3\Config\Auth;

Auth::requireRole(['Admin', 'Manager']);
Auth::checkTimeout();

$pageTitle = "Approve Leave Requests";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize_output($pageTitle) ?> - HR3</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-tasks me-2"></i>Approve Leave Requests</h1>
                    <div>
                        <button class="btn btn-outline-secondary btn-sm" onclick="loadData()">
                            <i class="fas fa-sync me-1"></i>Refresh
                        </button>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h6>Pending Approval</h6>
                                <h2 id="pendingCount">0</h2>
                                <small>Awaiting action</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6>Approved Today</h6>
                                <h2 id="approvedToday">0</h2>
                                <small>Processed requests</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h6>Rejected Today</h6>
                                <h2 id="rejectedToday">0</h2>
                                <small>Declined requests</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6>Team Members</h6>
                                <h2 id="teamCount">-</h2>
                                <small>In your department</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Requests -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Pending Leave Requests</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Days</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="pendingTable">
                                    <tr>
                                        <td colspan="8" class="text-center">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- View Details Modal -->
                <div class="modal fade" id="detailsModal">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Leave Request Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body" id="detailsContent">
                                <div class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios@1.6.0/dist/axios.min.js"></script>
    <script>
        const API_BASE = '<?= base_url() ?>/api/leave';

        async function loadData() {
            try {
                const response = await axios.get(`${API_BASE}/pending.php`);
                if (response.data.success) {
                    populateTable(response.data.data);
                    updateCounts(response.data.data);
                } else {
                    showNotification('Error loading data', 'danger');
                }
            } catch (error) {
                console.error('Error loading data:', error);
                showNotification('Failed to load leave requests', 'danger');
            }
        }

        function populateTable(requests) {
            const tbody = document.getElementById('pendingTable');
            
            if (requests.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-check-circle fa-2x mb-2"></i><br>No pending requests</td></tr>';
                return;
            }

            tbody.innerHTML = requests.map(r => `
                <tr>
                    <td>
                        <strong>${r.first_name} ${r.last_name}</strong><br>
                        <small class="text-muted">${r.email || ''}</small>
                    </td>
                    <td>${r.department_name || '-'}</td>
                    <td><span class="badge bg-primary">${r.leave_type_name}</span></td>
                    <td>${r.start_date}</td>
                    <td>${r.end_date}</td>
                    <td><strong>${r.total_days}</strong> days</td>
                    <td>
                        ${r.reason ? 
                            (r.reason.length > 30 ? 
                                `<span title="${r.reason}">${r.reason.substring(0, 30)}...</span>` : 
                                r.reason
                            ) : '-'
                        }
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-success" onclick="approveLeave(${r.leave_id})" title="Approve">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn btn-danger" onclick="rejectLeave(${r.leave_id})" title="Reject">
                                <i class="fas fa-times"></i>
                            </button>
                            <button class="btn btn-info" onclick="viewDetails(${r.leave_id}, '${r.first_name} ${r.last_name}', '${r.leave_type_name}', '${r.start_date}', '${r.end_date}', ${r.total_days}, '${(r.reason || '').replace(/'/g, "\\'")}', '${r.email || ''}')" title="Details">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function updateCounts(requests) {
            document.getElementById('pendingCount').textContent = requests.length;
            // Note: For accurate today's stats, backend should provide these
            document.getElementById('approvedToday').textContent = '-';
            document.getElementById('rejectedToday').textContent = '-';
            document.getElementById('teamCount').textContent = '-';
        }

        async function approveLeave(leaveId) {
            if (!confirm('Approve this leave request?')) return;

            try {
                const response = await axios.post(`${API_BASE}/approve.php`, 
                    JSON.stringify({ leave_id: leaveId }),
                    { headers: { 'Content-Type': 'application/json' }}
                );
                
                if (response.data.success) {
                    showNotification('Leave approved successfully!', 'success');
                    loadData();
                } else {
                    showNotification(response.data.message, 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to approve leave', 'danger');
            }
        }

        async function rejectLeave(leaveId) {
            if (!confirm('Reject this leave request? This action cannot be undone.')) return;

            try {
                const response = await axios.post(`${API_BASE}/reject.php`, 
                    JSON.stringify({ leave_id: leaveId }),
                    { headers: { 'Content-Type': 'application/json' }}
                );
                
                if (response.data.success) {
                    showNotification('Leave rejected', 'warning');
                    loadData();
                } else {
                    showNotification(response.data.message, 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to reject leave', 'danger');
            }
        }

        function viewDetails(leaveId, name, type, start, end, days, reason, email) {
            document.getElementById('detailsContent').innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted">Employee Information</h6>
                        <p class="mb-1"><strong>Name:</strong> ${name}</p>
                        <p class="mb-3"><strong>Email:</strong> ${email || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Leave Details</h6>
                        <p class="mb-1"><strong>Type:</strong> <span class="badge bg-primary">${type}</span></p>
                        <p class="mb-1"><strong>Duration:</strong> ${days} days</p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Start Date:</strong> ${start}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>End Date:</strong> ${end}</p>
                    </div>
                </div>
                <hr>
                <div>
                    <h6 class="text-muted">Reason</h6>
                    <p>${reason || 'No reason provided'}</p>
                </div>
                <hr>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Review the leave request carefully before approving or rejecting.
                </div>
            `;
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        }

        function showNotification(message, type) {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            alert.style.zIndex = '9999';
            alert.style.minWidth = '300px';
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alert);
            setTimeout(() => alert.remove(), 4000);
        }

        // Load data on page load
        loadData();
        
        // Auto-refresh every 60 seconds
        setInterval(loadData, 60000);
    </script>
</body>
</html>