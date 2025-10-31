<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

use HR3\Config\Auth;

Auth::requireAuth();
Auth::checkTimeout();

$pageTitle = "My Schedule";
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
        .schedule-card { border-left: 4px solid #0d6efd; margin-bottom: 1rem; }
        .shift-badge { padding: 0.5rem 1rem; border-radius: 20px; display: inline-block; }
        .morning { background: #fff3cd; color: #856404; }
        .afternoon { background: #cfe2ff; color: #084298; }
        .night { background: #212529; color: #fff; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-calendar-alt me-2"></i>My Schedule</h1>
                    <button class="btn btn-sm btn-outline-secondary" onclick="loadSchedule()">
                        <i class="fas fa-sync me-1"></i>Refresh
                    </button>
                </div>

                <div id="alertContainer"></div>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="startDate" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" id="endDate" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button class="btn btn-primary d-block w-100" onclick="loadSchedule()">
                                    <i class="fas fa-search me-1"></i>View Schedule
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="scheduleContainer">
                    <div class="text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                        <p class="mt-2">Loading your schedule...</p>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_URL = '/hr3/api/shift_schedule/';
        
        function showAlert(msg, type = 'info') {
            document.getElementById('alertContainer').innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show">
                    ${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
        }
        
        async function loadSchedule() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const container = document.getElementById('scheduleContainer');
            
            container.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
            
            try {
                const response = await fetch(`${API_URL}?action=get_schedule&start_date=${startDate}&end_date=${endDate}`);
                const data = await response.json();
                
                if (data.success) {
                    displaySchedule(data.data);
                } else {
                    showAlert(data.message || 'Failed to load schedule', 'warning');
                    container.innerHTML = '';
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Connection error. Please try again.', 'danger');
                container.innerHTML = '';
            }
        }
        
        function displaySchedule(schedule) {
            const container = document.getElementById('scheduleContainer');
            
            if (!schedule || schedule.length === 0) {
                container.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No shifts scheduled for this period</div>';
                return;
            }
            
            let html = '';
            schedule.forEach(shift => {
                const hour = parseInt(shift.start_time.split(':')[0]);
                const shiftClass = shift.is_night_shift == 1 ? 'night' : (hour < 12 ? 'morning' : 'afternoon');
                const date = new Date(shift.assigned_date);
                const formattedDate = date.toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
                
                html += `
                    <div class="card schedule-card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h5 class="card-title mb-1">${shift.shift_name}</h5>
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-calendar me-2"></i>${formattedDate}
                                    </p>
                                    <span class="shift-badge ${shiftClass}">
                                        <i class="fas fa-clock me-1"></i>${shift.start_time} - ${shift.end_time}
                                    </span>
                                </div>
                                <div class="col-md-4 text-end">
                                    ${shift.is_night_shift == 1 ? '<span class="badge bg-dark"><i class="fas fa-moon me-1"></i>Night Shift</span>' : ''}
                                </div>
                            </div>
                        </div>
                    </div>`;
            });
            
            container.innerHTML = html;
        }
        
        // Auto-load on page ready
        document.addEventListener('DOMContentLoaded', loadSchedule);
    </script>
</body>
</html>