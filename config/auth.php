<?php

/**
 * Authentication Configuration and Session Management
 * 
 * @package HR3
 * @subpackage Config
 * @version 1.0.0
 */

declare(strict_types=1);

namespace HR3\Config;

session_start();

/**
 * Authentication handler for user sessions and permissions
 */
class Auth
{
    /**
     * Check if user is logged in
     * 
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get current user ID
     * 
     * @return int|null
     */
    public static function getUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current user role
     * 
     * @return string|null
     */
    public static function getUserRole(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Check if user has specific role
     * 
     * @param string $role
     * @return bool
     */
    public static function hasRole(string $role): bool
    {
        return self::getUserRole() === $role;
    }

    /**
     * Check if user has any of the specified roles
     * 
     * @param array $roles
     * @return bool
     */
    public static function hasAnyRole(array $roles): bool
    {
        return in_array(self::getUserRole(), $roles, true);
    }

    /**
     * Login user
     * 
     * @param int $userId
     * @param string $role
     * @param array $userData
     * @return void
     */
    public static function login(int $userId, string $role, array $userData = []): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $role;
        $_SESSION['user_data'] = $userData;
        $_SESSION['last_activity'] = time();
    }

    /**
     * Logout user
     * 
     * @return void
     */
    public static function logout(): void
    {
        session_unset();
        session_destroy();
        session_start(); // Start fresh session for potential messages
    }

    /**
     * Require authentication
     * 
     * @return void
     */
    public static function requireAuth(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: /hr3/login.php');
            exit;
        }
    }

    /**
     * Require specific role
     * 
     * @param string|array $roles
     * @return void
     */
    public static function requireRole(string|array $roles): void
    {
        self::requireAuth();
        
        $roles = is_array($roles) ? $roles : [$roles];
        
        if (!self::hasAnyRole($roles)) {
            http_response_code(403);
            echo "Access denied. Insufficient permissions.";
            exit;
        }
    }

    /**
     * Check session timeout (30 minutes)
     * 
     * @return bool
     */
    public static function checkTimeout(): bool
    {
        $timeout = 30 * 60; // 30 minutes
        
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > $timeout) {
            self::logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
}
?>