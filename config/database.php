<?php

declare(strict_types=1);

/**
 * Database connection (singleton).
 *
 * Requires this file once from any script that needs the database:
 *
 *     require_once __DIR__ . '/../config/database.php';
 *     $stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
 *     $stmt->execute([$id]);
 *
 * Still exposes a single, reusable PDO instance as $pdo (assigned at the very
 * end of this file) so every existing caller keeps working unchanged. New code
 * may also call Database::getConnection() directly.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

final class Database
{
    /** The one shared connection for the whole request. */
    private static ?PDO $instance = null;

    // Not instantiable or cloneable — the connection is reached only through
    // getConnection(), which enforces the single-instance guarantee.
    private function __construct() {}
    private function __clone() {}

    public static function getConnection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        // Load .env from the /config directory (only once per request).
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();

        // Fail fast if required credentials are missing.
        // These must be present AND non-blank.
        $dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER'])->notEmpty();
        // DB_PASS must be present in .env, but an empty value is valid
        // (e.g. the default XAMPP/MySQL root user has no password).
        $dotenv->required(['DB_PASS']);

        $host    = $_ENV['DB_HOST'];
        $dbName  = $_ENV['DB_NAME'];
        $dbUser  = $_ENV['DB_USER'];
        $dbPass  = $_ENV['DB_PASS'];
        $dbPort  = $_ENV['DB_PORT'] ?? '3306'; // optional, defaults to MySQL's standard port
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};port={$dbPort};dbname={$dbName};charset={$charset}";

        $options = [
            // Throw exceptions on error instead of silent failures.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Return associative arrays by default.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use real (server-side) prepared statements, not emulated ones.
            // This is the key setting for safe, prepared-statement-ready queries.
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            self::$instance = new PDO($dsn, $dbUser, $dbPass, $options);
        } catch (PDOException $e) {
            // Never leak credentials or DSN details to the client.
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit('A database error occurred. Please try again later.');
        }

        return self::$instance;
    }
}

// Backwards-compatible global: every existing file that does
// `require_once database.php` and then uses $pdo continues to work unchanged.
$pdo = Database::getConnection();
