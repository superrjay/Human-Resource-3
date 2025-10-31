<?php

/**
 * Home Page / Landing Page
 * 
 * @package HR3
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/helpers.php';

use HR3\Config\Auth;

// Redirect to dashboard if already logged in
if (Auth::isLoggedIn()) {
    redirect('/hr3/dashboard.php');
}

$pageTitle = "HR3 Workforce Operations";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize_output($pageTitle) ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            color: white;
        }
        .feature-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        .cta-section {
            background: #f8f9fa;
            padding: 80px 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: rgba(0,0,0,0.2);">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-clock me-2"></i>HR3 Workforce
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="<?= base_url() ?>/login.php">Login</a>
                <a class="nav-link" href="<?= base_url() ?>/registration.php">Register</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <h1 class="display-4 fw-bold mb-4">Streamline Your Workforce Operations</h1>
                    <p class="lead mb-4">Comprehensive time tracking, attendance management, leave processing, and workforce optimization in one powerful platform.</p>
                    <div class="d-flex gap-3">
                        <a href="<?= base_url() ?>/registration.php" class="btn btn-light btn-lg px-4">
                            Get Started <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg px-4">
                            Learn More
                        </a>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <img src="https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80" 
                         alt="HR Management" class="img-fluid rounded shadow">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3">Powerful Features</h2>
                <p class="lead text-muted">Everything you need to manage your workforce efficiently</p>
            </div>

            <div class="row g-4">
                <!-- Feature 1 -->
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="text-center">
                        <div class="feature-icon mx-auto">
                            <i class="fas fa-fingerprint text-white fa-2x"></i>
                        </div>
                        <h4>Time & Attendance</h4>
                        <p class="text-muted">Track employee hours, monitor attendance patterns, and manage clock-ins with precision.</p>
                    </div>
                </div>

                <!-- Feature 2 -->
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="text-center">
                        <div class="feature-icon mx-auto">
                            <i class="fas fa-umbrella-beach text-white fa-2x"></i>
                        </div>
                        <h4>Leave Management</h4>
                        <p class="text-muted">Streamline leave requests, approvals, and balance tracking with automated workflows.</p>
                    </div>
                </div>

                <!-- Feature 3 -->
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="text-center">
                        <div class="feature-icon mx-auto">
                            <i class="fas fa-calendar-alt text-white fa-2x"></i>
                        </div>
                        <h4>Shift Scheduling</h4>
                        <p class="text-muted">Create optimized schedules, manage shift swaps, and ensure proper coverage.</p>
                    </div>
                </div>

                <!-- Feature 4 -->
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="text-center">
                        <div class="feature-icon mx-auto">
                            <i class="fas fa-file-invoice-dollar text-white fa-2x"></i>
                        </div>
                        <h4>Claims & Reimbursement</h4>
                        <p class="text-muted">Process expense claims, track reimbursements, and maintain financial records.</p>
                    </div>
                </div>

                <!-- Feature 5 -->
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="500">
                    <div class="text-center">
                        <div class="feature-icon mx-auto">
                            <i class="fas fa-chart-bar text-white fa-2x"></i>
                        </div>
                        <h4>Analytics & Reports</h4>
                        <p class="text-muted">Gain insights with comprehensive reports and real-time analytics dashboards.</p>
                    </div>
                </div>

                <!-- Feature 6 -->
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="600">
                    <div class="text-center">
                        <div class="feature-icon mx-auto">
                            <i class="fas fa-mobile-alt text-white fa-2x"></i>
                        </div>
                        <h4>Mobile Ready</h4>
                        <p class="text-muted">Access all features on-the-go with our responsive mobile-friendly design.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8" data-aos="zoom-in">
                    <h2 class="display-6 fw-bold mb-4">Ready to Transform Your Workforce Management?</h2>
                    <p class="lead mb-4">Join hundreds of companies using HR3 to streamline their operations and boost productivity.</p>
                    <a href="<?= base_url() ?>/registration.php" class="btn btn-primary btn-lg px-5">
                        Start Free Trial <i class="fas fa-rocket ms-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; 2024 HR3 Workforce Operations. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-end">
                    <a href="#" class="text-white me-3"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-white"><i class="fab fa-linkedin"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 1000,
            once: true
        });
    </script>
</body>
</html>