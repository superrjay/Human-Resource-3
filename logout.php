<?php

/**
 * Logout Handler
 * 
 * @package HR3
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/helpers.php';

use HR3\Config\Auth;

// Perform logout
Auth::logout();

// Set success message
flash('success', 'You have been successfully logged out.');

// Redirect to login page
redirect('/hr3/index.php');