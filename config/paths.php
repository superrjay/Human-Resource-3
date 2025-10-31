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

define('ROOT_PATH', dirname(__DIR__));

// Define directory paths
define('CONFIG_PATH', ROOT_PATH . '/config');
define('MODULES_PATH', ROOT_PATH . '/modules');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('API_PATH', ROOT_PATH . '/api');
define('CONTROLLERS_PATH', ROOT_PATH . '/controllers');
define('MODELS_PATH', ROOT_PATH . '/models');

/**
 * Detect current file location and return appropriate path prefix
 */
function getPathPrefix() {
    $currentPath = dirname($_SERVER['PHP_SELF']);
    $depth = substr_count($currentPath, '/') - 1;
    return str_repeat('../', max(0, $depth));
}

/**
 * Get path to root from current location
 */
function getRootPath() {
    $currentPath = dirname($_SERVER['PHP_SELF']);
    
    // Check if we're in modules
    if (strpos($currentPath, '/modules/') !== false) {
        return '../../';
    }
    
    // Check if we're in api
    if (strpos($currentPath, '/api/') !== false) {
        return '../';
    }
    
    // We're in root
    return './';
}

/**
 * Get URL to a module file
 */
function getModuleUrl($module, $file = '') {
    $root = getRootPath();
    return $root . 'modules/' . $module . '/' . $file;
}

/**
 * Get URL to an asset
 */
function getAssetUrl($asset) {
    $root = getRootPath();
    return $root . 'assets/' . $asset;
}

/**
 * Get URL to an API endpoint
 */
function getApiUrl($endpoint) {
    $root = getRootPath();
    return $root . 'api/' . $endpoint;
}

/**
 * Get current page for active menu highlighting
 */
function getCurrentPage() {
    return basename($_SERVER['PHP_SELF']);
}

/**
 * Check if current URL matches a path
 */
function isActivePage($path) {
    $currentFile = str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']);
    $currentFile = str_replace('\\', '/', $currentFile);
    $path = str_replace(['../', './'], '', $path);
    
    return strpos($currentFile, $path) !== false;
}