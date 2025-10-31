<?php

/**
 * User Login Page
 * 
 * @package HR3
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/helpers.php';

use HR3\Config\Auth;
use HR3\Config\Database;

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    redirect('/hr3/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid form submission. Please try again.";
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($email) || empty($password)) {
        $errors[] = "Please enter both email and password.";
    }

    if (empty($errors)) {
        try {
            $db = Database::getConnection();
            
            // Get user with role information
            $stmt = $db->prepare("
                SELECT u.*, r.role_name 
                FROM users u 
                JOIN roles r ON u.role_id = r.role_id 
                WHERE u.email = ? AND u.status = 'active'
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login successful
                Auth::login(
                    (int) $user['user_id'],
                    $user['role_name'],
                    [
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'email' => $user['email']
                    ]
                );
                
                flash('success', 'Welcome back, ' . $user['first_name'] . '!');
                redirect('/hr3/dashboard.php');
            } else {
                $errors[] = "Invalid email or password.";
            }
            
        } catch (PDOException $e) {
            $errors[] = "Login temporarily unavailable. Please try again later.";
        }
    }
}

$pageTitle = "Login - HR3 Workforce";
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
    
    <style>
        .login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
        }
        .login-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .form-floating > .form-control:focus ~ label {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <!-- Login Card -->
                    <div class="card login-card">
                        <div class="card-body p-5">
                            <!-- Header -->
                            <div class="text-center mb-4">
                                <a href="<?= base_url() ?>/index.php" class="text-decoration-none">
                                    <h3 class="fw-bold text-primary">
                                        <i class="fas fa-clock me-2"></i>HR3 Workforce
                                    </h3>
                                </a>
                                <p class="text-muted">Sign in to your account</p>
                            </div>

                            <!-- Flash Messages -->
                            <?php if ($message = flash('success')): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?= sanitize_output($message) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <!-- Error Messages -->
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= sanitize_output($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <!-- Login Form -->
                            <form method="POST" action="">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                                <!-- Email -->
                                <div class="form-floating mb-3">
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?= sanitize_output($_POST['email'] ?? '') ?>" 
                                           required maxlength="150">
                                    <label for="email">Email Address</label>
                                </div>

                                <!-- Password -->
                                <div class="form-floating mb-3">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <label for="password">Password</label>
                                </div>

                                <!-- Remember Me & Forgot Password 
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                        <label class="form-check-label" for="remember">Remember me</label>
                                    </div>
                                    <a href="#" class="text-decoration-none">Forgot password?</a>
                                </div>
                                -->


                                <!-- Submit Button -->
                                <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                </button>

                                <!-- Registration Link -->
                                <div class="text-center">
                                    <p class="mb-0">
                                        Don't have an account? 
                                        <a href="<?= base_url() ?>/registration.php" class="text-decoration-none">Create one</a>
                                    </p>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Demo Accounts Info 
                    <div class="text-center mt-3">
                        <div class="card bg-light">
                            <div class="card-body py-3">
                                <small class="text-muted">
                                    <strong>Demo Accounts:</strong><br>
                                    Admin: admin@hr3.com / manager@hr3.com<br>
                                    Employee: employee@hr3.com<br>
                                    Password: password123
                                </small>
                            </div>
                        </div>
                    </div>
                    -->

                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>