<?php

/**
 * Database Configuration and Connection Handler
 * 
 * @package HR3
 * @subpackage Config
 * @version 1.0.0
 * 
 * @description Handles PDO database connection with error logging and singleton pattern
 * @requires PHP >= 8.1, MySQL >= 5.7
 */

declare(strict_types=1);

namespace HR3\Config;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Database connection manager using PDO with singleton pattern
 */
class Database
{
    /**
     * @var PDO|null Singleton PDO instance
     */
    private static ?PDO $connection = null;

    /**
     * Database configuration constants
     */
    private const DB_HOST = 'localhost';
    private const DB_NAME = 'hr3_db';
    private const DB_CHARSET = 'utf8mb4';
    private const DB_USER = 'root';
    private const DB_PASS = '';
    
    /**
     * PDO options for secure and optimized connections
     */
    private const PDO_OPTIONS = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        PDO::ATTR_PERSISTENT => false
    ];

    /**
     * Get PDO database connection instance (singleton pattern)
     * 
     * @return PDO
     * @throws RuntimeException If database connection fails
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=%s',
                    self::DB_HOST,
                    self::DB_NAME,
                    self::DB_CHARSET
                );

                self::$connection = new PDO(
                    $dsn,
                    self::DB_USER,
                    self::DB_PASS,
                    self::PDO_OPTIONS
                );

            } catch (PDOException $e) {
                self::logError('Database Connection Failed', $e);
                throw new RuntimeException('Database connection unavailable. Please try again later.');
            }
        }

        return self::$connection;
    }

    /**
     * Log database errors securely
     * 
     * @param string $message Error description
     * @param PDOException $exception The caught exception
     * @return void
     */
    private static function logError(string $message, PDOException $exception): void
    {
        $logMessage = sprintf(
            "[%s] %s: %s (Code: %d) in %s on line %d" . PHP_EOL,
            date('Y-m-d H:i:s'),
            $message,
            $exception->getMessage(),
            $exception->getCode(),
            $exception->getFile(),
            $exception->getLine()
        );

        // Log to file (create logs directory if it doesn't exist)
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        error_log($logMessage, 3, $logDir . '/database_errors.log');
    }

    /**
     * Close database connection
     * 
     * @return void
     */
    public static function closeConnection(): void
    {
        self::$connection = null;
    }

    /**
     * Check if database connection is alive
     * 
     * @return bool
     */
    public static function isConnected(): bool
    {
        try {
            if (self::$connection instanceof PDO) {
                self::$connection->query('SELECT 1');
                return true;
            }
        } catch (PDOException) {
            // Connection is dead
        }
        
        return false;
    }
}

// Convenience function for global access (optional)
/**
 * Get database connection instance
 * 
 * @return PDO
 */
function db(): PDO
{
    return Database::getConnection();
}

?>