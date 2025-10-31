<?php
declare(strict_types=1);

function sanitize_output(string $data): string
{
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

function base_url(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "{$protocol}://{$host}/hr3";
}

function get_module_path(string $module): string
{
    return base_url() . "/modules/{$module}";
}

function format_date(string $date, string $format = 'Y-m-d'): string
{
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : '';
}

function is_ajax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function flash(string $key = '', $value = null)
{
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    
    if ($value !== null) {
        $_SESSION['flash_messages'][$key] = $value;
        return null;
    }
    
    $message = $_SESSION['flash_messages'][$key] ?? null;
    unset($_SESSION['flash_messages'][$key]);
    
    return $message;
}

function debug($data, bool $die = true): void
{
    echo '<pre>' . print_r($data, true) . '</pre>';
    if ($die) die();
}