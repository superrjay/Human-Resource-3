<?php
declare(strict_types=1);
use HR3\Config\Auth;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= base_url() ?>/dashboard.php">
            <i class="fas fa-clock me-2"></i>HR3 Workforce
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <?php if (Auth::isLoggedIn()): ?>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <?= sanitize_output($_SESSION['user_data']['first_name'] ?? 'User') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Signed in as <?= Auth::getUserRole() ?></h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= base_url() ?>/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
            <?php else: ?>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url() ?>/login.php">
                        <i class="fas fa-sign-in-alt me-1"></i>Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url() ?>/registration.php">
                        <i class="fas fa-user-plus me-1"></i>Register
                    </a>
                </li>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>