<?php
/**
 * Leave Management - Employee View
 * 
 * @package HR3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

use HR3\Config\Auth;

Auth::requireAuth();
Auth::checkTimeout();

$pageTitle = "My Leave Requests";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize_output($pageTitle) ?> - HR3</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-umbrella-beach me-2"></i>Leave Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestModal">
                        <i class="fas fa-plus me-1"></i>Request Leave
                    </button>
                </div>

                <!-- Leave Balance Cards -->
                <div class="row mb-4" id="balanceCards">
                    <div class="col-12 text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>

                <!-- Filter & Stats -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <select class="form-select" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-9 text-end">
                                <span class="badge bg-info me-2">Total: <span id="totalRequests">0</span></span>
                                <span class="badge bg-warning me-2">Pending: <span id="pendingCount">0</span></span>
                                <span class="badge bg-success">Approved Days: <span id="approvedDays">0</span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Leave Requests Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="leavesTable">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Days</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Approver</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="8" class="text-center">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Request Modal -->
    <div class="modal fade" id="requestModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Leave</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="leaveForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Leave Type*</label>
                            <select class="form-select" name="leave_type_id" required>
                                <option value="">Loading...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Start Date*</label>
                            <input type="text" class="form-control" name="start_date" id="startDate" required readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">End Date*</label>
                            <input type="text" class="form-control" name="end_date" id="endDate" required readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea class="form-control" name="reason" rows="3" placeholder="Optional reason for leave"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios@1.6.0/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script>
        const API_BASE = '<?= base_url() ?>/api/leave';

        // Date pickers
        flatpickr('#startDate', { 
            minDate: 'today', 
            dateFormat: 'Y-m-d',
            onChange: function(dates) {
                const endPicker = document.getElementById('endDate')._flatpickr;
                endPicker.set('minDate', dates[0]);
            }
        });
        flatpickr('#endDate', { minDate: 'today', dateFormat: 'Y-m-d' });

        // Load data
        async function loadData() {
            try {
                const [types, leaves] = await Promise.all([
                    axios.get(`${API_BASE}/types.php`),
                    axios.get(`${API_BASE}/user.php`)
                ]);
                
                if (types.data.success) populateLeaveTypes(types.data.data);
                if (leaves.data.success) {
                    populateBalances(leaves.data.data.balances || []);
                    populateTable(leaves.data.data.leaves || []);
                    updateStats(leaves.data.data.statistics || {});
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error loading data', 'danger');
            }
        }

        function populateLeaveTypes(types) {
            const select = document.querySelector('[name="leave_type_id"]');
            select.innerHTML = '<option value="">Select leave type</option>' + 
                types.map(t => `<option value="${t.leave_type_id}">${t.leave_type_name} (${t.max_days_per_year} days/year)</option>`).join('');
        }

        function populateBalances(balances) {
            const container = document.getElementById('balanceCards');
            if (balances.length === 0) {
                container.innerHTML = '<div class="col-12"><div class="alert alert-info">No leave balances configured</div></div>';
                return;
            }
            container.innerHTML = balances.map(b => `
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">${b.leave_type_name}</h6>
                            <h3 class="mb-0">${b.remaining_days || 0}</h3>
                            <small class="text-success">Available of ${b.max_days_per_year} days</small>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function populateTable(leaves) {
            const tbody = document.querySelector('#leavesTable tbody');
            if (leaves.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No leave requests found</td></tr>';
                return;
            }
            tbody.innerHTML = leaves.map(l => `
                <tr>
                    <td><span class="badge bg-info">${l.leave_type_name}</span></td>
                    <td>${l.start_date}</td>
                    <td>${l.end_date}</td>
                    <td><strong>${l.total_days}</strong></td>
                    <td>${l.reason || '-'}</td>
                    <td><span class="badge bg-${getStatusColor(l.status)}">${l.status}</span></td>
                    <td>${l.approver_name || '-'}</td>
                    <td>
                        ${l.status === 'Pending' ? 
                            `<button class="btn btn-sm btn-danger" onclick="cancelLeave(${l.leave_id})">
                                <i class="fas fa-times"></i> Cancel
                            </button>` 
                            : '-'}
                    </td>
                </tr>
            `).join('');
        }

        function updateStats(stats) {
            document.getElementById('totalRequests').textContent = stats.total_requests || 0;
            document.getElementById('pendingCount').textContent = stats.pending_count || 0;
            document.getElementById('approvedDays').textContent = stats.approved_days || 0;
        }

        function getStatusColor(status) {
            const colors = { 
                Pending: 'warning', 
                Approved: 'success', 
                Rejected: 'danger', 
                Cancelled: 'secondary' 
            };
            return colors[status] || 'info';
        }

        // Submit form
        document.getElementById('leaveForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';
            
            const formData = new FormData(e.target);
            
            try {
                const response = await axios.post(`${API_BASE}/create.php`, formData);
                if (response.data.success) {
                    showAlert('Leave request submitted successfully!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('requestModal')).hide();
                    e.target.reset();
                    loadData();
                } else {
                    showAlert(response.data.message, 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error submitting request', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Submit Request';
            }
        });

        // Cancel leave
        async function cancelLeave(leaveId) {
            if (!confirm('Are you sure you want to cancel this leave request?')) return;
            
            try {
                const response = await axios.post(`${API_BASE}/cancel.php`, 
                    JSON.stringify({ leave_id: leaveId }),
                    { headers: { 'Content-Type': 'application/json' }}
                );
                
                if (response.data.success) {
                    showAlert('Leave request cancelled!', 'success');
                    loadData();
                } else {
                    showAlert(response.data.message, 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error cancelling leave', 'danger');
            }
        }

        // Filter
        document.getElementById('statusFilter').addEventListener('change', (e) => {
            const status = e.target.value;
            axios.get(`${API_BASE}/user.php${status ? '?status=' + status : ''}`)
                .then(res => {
                    if (res.data.success) {
                        populateTable(res.data.data.leaves || []);
                    }
                })
                .catch(err => console.error('Filter error:', err));
        });

        // Alert helper
        function showAlert(message, type) {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            alert.style.zIndex = '9999';
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }

        // Load on start
        loadData();
    </script>
</body>
</html>