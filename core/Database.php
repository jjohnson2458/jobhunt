<?php

/**
 * Database Connection Singleton
 *
 * Provides a single PDO instance for database connections throughout the application.
 *
 * @package    CoverLetterGenerator
 * @subpackage Core
 * @author     J.J.Johnson <email4johnson@gmail.com>
 * @copyright  2026 VisionQuest Services LLC
 */
class Database
{
    /**
     * @var PDO|null The single PDO instance
     */
    private static ?PDO $instance = null;

    /**
     * Get the PDO database instance
     *
     * @return PDO The database connection instance
     * @throws PDOException If connection fails
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../config/database.php';

            $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";

            self::$instance = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return self::$instance;
    }
}
