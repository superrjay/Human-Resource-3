<?php
declare(strict_types=1);
use HR3\Config\Auth;

if (!Auth::isLoggedIn()) return;

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="col-md-3 col-lg-2 bg-light sidebar">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4 p-3 bg-white rounded shadow-sm">
            <div class="mb-3">
                <i class="fas fa-user-circle fa-3x text-primary"></i>
            </div>
            <h6 class="mb-1"><?= sanitize_output($_SESSION['user_data']['first_name'] ?? 'User') ?></h6>
            <small class="text-muted"><?= Auth::getUserRole() ?></small>
            <div class="mt-2">
                <span class="badge bg-success">Active</span>
            </div>
        </div>

        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" 
                   href="<?= base_url() ?>/dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <?php if (Auth::hasAnyRole(['Admin', 'Manager', 'Employee'])): ?>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#timeMenu">
                    <i class="fas fa-clock me-2"></i>Time Management
                    <i class="fas fa-chevron-down float-end mt-1"></i>
                </a>
                <div class="collapse show" id="timeMenu">
                    <ul class="nav flex-column ms-3">
                        <?php if (Auth::hasAnyRole(['Employee'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= get_module_path('time_attendance') ?>/index.php">
                                <i class="fas fa-fingerprint me-2"></i>Attendance
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (Auth::hasAnyRole(['Admin', 'Manager'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= get_module_path('time_attendance') ?>/manage.php">
                                <i class="fas fa-tasks me-2"></i>Manage
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </li>
            <?php if (Auth::hasAnyRole(['Employee'])): ?>
            <li class="nav-item">
                <a class="nav-link" href="<?= get_module_path('timesheet') ?>/index.php">
                    <i class="fas fa-file-alt me-2"></i>Timesheet
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#leaveMenu">
                    <i class="fas fa-umbrella-beach me-2"></i>Leave
                    <i class="fas fa-chevron-down float-end mt-1"></i>
                </a>
                <div class="collapse" id="leaveMenu">
                    <ul class="nav flex-column ms-3">
                        <?php if (Auth::hasAnyRole(['Employee'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= get_module_path('leave') ?>/index.php">
                                <i class="fas fa-list me-2"></i>My Leaves
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (Auth::hasAnyRole(['Admin', 'Manager'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= get_module_path('leave') ?>/manage.php">
                                <i class="fas fa-check-circle me-2"></i>Approve
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#scheduleMenu">
                    <i class="fas fa-calendar-alt me-2"></i>Schedule
                    <i class="fas fa-chevron-down float-end mt-1"></i>
                </a>
                <div class="collapse" id="scheduleMenu">
                    <ul class="nav flex-column ms-3">
                        <?php if (Auth::hasAnyRole(['Employee'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= get_module_path('shift_schedule') ?>/index.php">
                                <i class="fas fa-calendar-alt me-2"></i>Schedule
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (Auth::hasAnyRole(['Admin', 'Manager'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= get_module_path('shift_schedule') ?>/manage.php">
                                <i class="fas fa-management me-2"></i>Manage
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#claimsMenu">
                    <i class="fas fa-receipt me-2"></i>Claims
                    <i class="fas fa-chevron-down float-end mt-1"></i>
                </a>
                <div class="collapse" id="claimsMenu">
                    <ul class="nav flex-column ms-3">
                        <?php if (Auth::hasAnyRole(['Employee'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= get_module_path('claims_reimbursement') ?>/index.php">
                                <i class="fas fa-file-invoice-dollar me-2"></i>My Claims
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (Auth::hasAnyRole(['Admin', 'Manager'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= get_module_path('claims_reimbursement') ?>/manage.php">
                                <i class="fas fa-check me-2"></i>Approve
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (Auth::hasRole('Finance')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= get_module_path('claims_reimbursement') ?>/finance.php">
                                <i class="fas fa-dollar-sign me-2"></i>Finance
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </li>

            <!--
            <?php if (Auth::hasAnyRole(['Admin', 'Manager'])): ?>
            <li class="nav-item mt-3">
                <small class="text-muted px-3">ADMINISTRATION</small>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-users me-2"></i>Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </a>
            </li>
            <?php endif; ?>
            -->
        </ul>
    </div>
</div>

<style>
.sidebar {
    min-height: calc(100vh - 56px);
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
}
.sidebar .nav-link {
    color: #333;
    border-radius: 0.375rem;
    margin-bottom: 0.25rem;
}
.sidebar .nav-link:hover {
    background-color: #e9ecef;
}
.sidebar .nav-link.active {
    background-color: #0d6efd;
    color: white;
}
.sidebar .nav-link[data-bs-toggle="collapse"]:not(.collapsed) .fa-chevron-down {
    transform: rotate(180deg);
}
.sidebar .nav-link .fa-chevron-down {
    transition: transform 0.3s ease;
}
</style>