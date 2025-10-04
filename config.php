<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'notification_system');
define('DB_USER', 'notification_user');
define('DB_PASS', 'password123');
define('DB_CHARSET', 'utf8mb4');

// API configuration
define('API_VERSION', 'v1');
define('JWT_SECRET', 'your-secret-key-here-change-this');
define('TOKEN_EXPIRY_HOURS', 24);

// Notification configuration
define('MAX_RETRY_ATTEMPTS', 3);
define('RETRY_DELAY_SECONDS', 300); // 5 minutes

// CORS configuration
define('ALLOWED_ORIGINS', '*');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Database connection class
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

// Helper function to get database connection
function getDB() {
    return Database::getInstance()->getConnection();
}
?>