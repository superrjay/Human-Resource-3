<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

use HR3\Config\Auth;

Auth::requireRole(['Admin', 'Manager']);
Auth::checkTimeout();

$pageTitle = "Manage Shifts";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize_output($pageTitle) ?> - HR3</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .shift-card { 
            border-left: 4px solid #0d6efd; 
            transition: all 0.3s; 
            cursor: default;
        }
        .shift-card:hover { 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
            transform: translateY(-2px);
        }
        .night-badge { background: #212529; color: white; }
        .day-badge { background: #ffc107; color: #000; }
        .assignment-row:hover { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-cogs me-2"></i>Manage Shifts</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createShiftModal">
                        <i class="fas fa-plus me-1"></i>Create Shift Template
                    </button>
                </div>

                <div id="alertContainer"></div>

                <ul class="nav nav-tabs mb-4" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#shiftsTab" onclick="loadShifts()">
                            <i class="fas fa-list me-1"></i>Shift Templates
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#assignTab">
                            <i class="fas fa-user-clock me-1"></i>Assign Shifts
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#coverageTab">
                            <i class="fas fa-chart-bar me-1"></i>Coverage Report
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Shift Templates Tab -->
                    <div class="tab-pane fade show active" id="shiftsTab">
                        <div id="shiftsContainer">
                            <div class="text-center py-5">
                                <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                                <p class="mt-2">Loading shifts...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Assign Shifts Tab -->
                    <div class="tab-pane fade" id="assignTab">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Assign Employees to Shifts</h5>
                                <form id="assignShiftForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Select Shift Template *</label>
                                            <select class="form-select" id="assignShiftId" required>
                                                <option value="">-- Loading shifts --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Select Employees * (Hold Ctrl/Cmd for multiple)</label>
                                            <select class="form-select" id="assignUserIds" multiple size="8" required>
                                                <option value="">-- Loading employees --</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Start Date *</label>
                                            <input type="date" class="form-control" id="assignStartDate" required 
                                                   value="<?= date('Y-m-d') ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">End Date *</label>
                                            <input type="date" class="form-control" id="assignEndDate" required 
                                                   value="<?= date('Y-m-d', strtotime('+6 days')) ?>">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check me-1"></i>Assign Shifts
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- View Assignments Tab -->
                    <div class="tab-pane fade" id="viewTab">
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" class="form-control" id="viewStartDate" 
                                               value="<?= date('Y-m-d') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">End Date</label>
                                        <input type="date" class="form-control" id="viewEndDate" 
                                               value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">&nbsp;</label>
                                        <button class="btn btn-primary w-100" onclick="loadAssignments()">
                                            <i class="fas fa-search me-1"></i>View Assignments
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="assignmentsContainer"></div>
                    </div>

                    <!-- Coverage Tab -->
                    <div class="tab-pane fade" id="coverageTab">
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label">Coverage Date</label>
                                        <input type="date" class="form-control" id="coverageDate" 
                                               value="<?= date('Y-m-d') ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-primary w-100" onclick="loadCoverage()">
                                            <i class="fas fa-search me-1"></i>View Coverage
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="coverageContainer"></div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Shift Modal -->
    <div class="modal fade" id="createShiftModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Shift Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createShiftForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Shift Name *</label>
                            <input type="text" class="form-control" name="shift_name" required 
                                   placeholder="e.g., Morning Shift, Day Shift">
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label">Start Time *</label>
                                <input type="time" class="form-control" name="start_time" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">End Time *</label>
                                <input type="time" class="form-control" name="end_time" required>
                            </div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_night_shift" 
                                   value="1" id="nightShiftCheck">
                            <label class="form-check-label" for="nightShiftCheck">
                                <i class="fas fa-moon me-1"></i>Night Shift (crosses midnight)
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Create Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const API_URL = '/hr3/api/shift_schedule/';
        const USERS_API = '/hr3/api/users/';
        
        function showAlert(message, type = 'success') {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
            document.getElementById('alertContainer').innerHTML = alertHtml;
            setTimeout(() => {
                const alert = document.querySelector('.alert');
                if (alert) new bootstrap.Alert(alert).close();
            }, 5000);
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            loadShifts();
            loadUsers();
        });
        
        async function loadShifts() {
            try {
                const response = await fetch(`${API_URL}?action=get_shifts`);
                const data = await response.json();
                
                if (data.success) {
                    displayShifts(data.data);
                    populateShiftSelect(data.data);
                } else {
                    document.getElementById('shiftsContainer').innerHTML = 
                        `<div class="alert alert-warning">${data.message}</div>`;
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('shiftsContainer').innerHTML = 
                    '<div class="alert alert-danger">Failed to load shifts</div>';
            }
        }
        
        function displayShifts(shifts) {
            const container = document.getElementById('shiftsContainer');
            
            if (shifts.length === 0) {
                container.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No shift templates created yet. Click "Create Shift Template" to add one.</div>';
                return;
            }
            
            let html = '<div class="row">';
            shifts.forEach(shift => {
                const badgeClass = shift.is_night_shift == 1 ? 'night-badge' : 'day-badge';
                const badgeText = shift.is_night_shift == 1 ? 'Night Shift' : 'Day Shift';
                
                html += `
                    <div class="col-md-6 mb-3">
                        <div class="card shift-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0">${shift.shift_name}</h5>
                                    <span class="badge ${badgeClass}">${badgeText}</span>
                                </div>
                                <p class="card-text mb-2">
                                    <i class="fas fa-clock text-primary me-2"></i>
                                    <strong>${shift.start_time}</strong> - <strong>${shift.end_time}</strong>
                                </p>
                                <p class="card-text text-muted mb-2">
                                    <i class="fas fa-users me-2"></i>${shift.assignment_count || 0} total assignments
                                </p>
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i>Created by: ${shift.first_name} ${shift.last_name}
                                </small>
                                <div class="mt-3">
                                <?php if (Auth::hasAnyRole(['Admin'])): ?>
                                    <button class="btn btn-sm btn-danger" onclick="archiveShift(${shift.shift_id})">
                                        <i class="fas fa-archive me-1"></i>Archive
                                    </button>
                                <?php endif ?>
                                </div>
                            </div>
                        </div>
                    </div>`;
            });
            html += '</div>';
            
            container.innerHTML = html;
        }
        
        function populateShiftSelect(shifts) {
            const select = document.getElementById('assignShiftId');
            select.innerHTML = '<option value="">-- Select Shift Template --</option>';
            
            shifts.forEach(shift => {
                const label = `${shift.shift_name} (${shift.start_time} - ${shift.end_time})`;
                select.innerHTML += `<option value="${shift.shift_id}">${label}</option>`;
            });
        }
        
        async function loadUsers() {
            try {
                const response = await fetch(`${USERS_API}?action=get_all`);
                const data = await response.json();
                
                if (data.success) {
                    const select = document.getElementById('assignUserIds');
                    select.innerHTML = '';
                    
                    data.data.forEach(user => {
                        const label = `${user.first_name} ${user.last_name} - ${user.department_name || 'No Dept'} (${user.role_name})`;
                        select.innerHTML += `<option value="${user.user_id}">${label}</option>`;
                    });
                }
            } catch (error) {
                console.error('Error loading users:', error);
            }
        }
        
        document.getElementById('createShiftForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('action', 'create_shift');
            
            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Shift template created successfully!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('createShiftModal')).hide();
                    e.target.reset();
                    loadShifts();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Failed to create shift', 'danger');
            }
        });
        
        document.getElementById('assignShiftForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const shiftId = document.getElementById('assignShiftId').value;
            const userSelect = document.getElementById('assignUserIds');
            const userIds = Array.from(userSelect.selectedOptions).map(opt => opt.value);
            const startDate = document.getElementById('assignStartDate').value;
            const endDate = document.getElementById('assignEndDate').value;
            
            if (!shiftId || userIds.length === 0) {
                showAlert('Please select a shift and at least one employee', 'warning');
                return;
            }
            
            const start = new Date(startDate);
            const end = new Date(endDate);
            const dates = [];
            
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                dates.push(d.toISOString().split('T')[0]);
            }
            
            const formData = new FormData();
            formData.append('action', 'assign_shift');
            formData.append('shift_id', shiftId);
            formData.append('user_ids', JSON.stringify(userIds));
            formData.append('dates', JSON.stringify(dates));
            
            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    showAlert(data.message, 'success');
                    e.target.reset();
                } else {
                    showAlert(data.message, 'warning');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Failed to assign shifts', 'danger');
            }
        });
        
        async function loadAssignments() {
            const startDate = document.getElementById('viewStartDate').value;
            const endDate = document.getElementById('viewEndDate').value;
            const container = document.getElementById('assignmentsContainer');
            
            container.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
            
            try {
                const response = await fetch(`${API_URL}?action=get_schedule&start_date=${startDate}&end_date=${endDate}`);
                const data = await response.json();
                
                if (data.success) {
                    displayAssignments(data.data);
                } else {
                    container.innerHTML = `<div class="alert alert-warning">${data.message}</div>`;
                }
            } catch (error) {
                console.error('Error:', error);
                container.innerHTML = '<div class="alert alert-danger">Failed to load assignments</div>';
            }
        }
        
        function displayAssignments(assignments) {
            const container = document.getElementById('assignmentsContainer');
            
            if (assignments.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No assignments found for this period</div>';
                return;
            }
            
            let html = `
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Shift</th>
                                <th>Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>`;
            
            assignments.forEach(a => {
                const date = new Date(a.assigned_date);
                const formattedDate = date.toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
                
                html += `
                    <tr class="assignment-row">
                        <td>${formattedDate}</td>
                        <td><i class="fas fa-user me-2"></i>${a.first_name} ${a.last_name}</td>
                        <td>${a.department_name || 'N/A'}</td>
                        <td>
                            ${a.shift_name}
                            ${a.is_night_shift == 1 ? '<span class="badge bg-dark ms-1">Night</span>' : ''}
                        </td>
                        <td><i class="fas fa-clock me-1"></i>${a.start_time} - ${a.end_time}</td>
                        <td>
                            <button class="btn btn-sm btn-danger" onclick="deleteAssignment(${a.assignment_id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>`;
            });
            
            html += `</tbody></table></div>`;
            container.innerHTML = html;
        }
        
        async function deleteAssignment(id) {
            if (!confirm('Delete this assignment?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_assignment');
            formData.append('assignment_id', id);
            
            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Assignment deleted!', 'success');
                    loadAssignments();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Failed to delete', 'danger');
            }
        }
        
        async function archiveShift(id) {
            if (!confirm('Archive this shift template? Existing assignments will remain.')) return;
            
            const formData = new FormData();
            formData.append('action', 'archive_shift');
            formData.append('shift_id', id);
            
            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Shift archived!', 'success');
                    loadShifts();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Failed to archive', 'danger');
            }
        }
        
        async function loadCoverage() {
            const date = document.getElementById('coverageDate').value;
            const container = document.getElementById('coverageContainer');
            
            container.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
            
            try {
                const response = await fetch(`${API_URL}?action=get_coverage&date=${date}`);
                const data = await response.json();
                
                if (data.success) {
                    displayCoverage(data.data, date);
                } else {
                    container.innerHTML = `<div class="alert alert-warning">${data.message}</div>`;
                }
            } catch (error) {
                console.error('Error:', error);
                container.innerHTML = '<div class="alert alert-danger">Failed to load coverage</div>';
            }
        }
        
        function displayCoverage(coverage, date) {
            const container = document.getElementById('coverageContainer');
            
            if (coverage.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No shifts configured</div>';
                return;
            }
            
            const formattedDate = new Date(date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            let html = `<h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Coverage Report for ${formattedDate}</h5>`;
            
            coverage.forEach(shift => {
                const count = parseInt(shift.assigned_count);
                const alertClass = count === 0 ? 'alert-danger' : count < 3 ? 'alert-warning' : 'alert-success';
                
                html += `
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="mb-0">${shift.shift_name}</h5>
                                <span class="badge bg-primary">${shift.start_time} - ${shift.end_time}</span>
                            </div>
                            <div class="${'alert ' + alertClass} mb-2">
                                <i class="fas fa-users me-2"></i>
                                <strong>${count} ${count === 1 ? 'employee' : 'employees'} assigned</strong>
                            </div>
                            ${shift.employees ? `
                                <p class="mb-0"><strong>Employees:</strong></p>
                                <p class="text-muted">${shift.employees}</p>
                            ` : '<p class="text-muted mb-0">No employees assigned</p>'}
                        </div>
                    </div>`;
            });
            
            container.innerHTML = html;
        }
    </script>
</body>
</html>