<?php
// Geofence Advertising App Configuration
// Database and API configuration with enhanced security

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'geofence_ads');
define('DB_USER', 'geofence_user');
define('DB_PASS', 'secure_password_123');
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT', '3306');

// API configuration
define('API_VERSION', 'v1');
define('API_BASE_URL', 'https://your-domain.com/api/');
define('JWT_SECRET', 'your-super-secure-jwt-secret-key-change-this');
define('JWT_EXPIRATION_HOURS', 24);

// Geofence configuration
define('DEFAULT_GEOFENCE_RADIUS_METERS', 1609); // 1 mile
define('DEFAULT_TRIGGER_RADIUS_METERS', 3); // 3 meters
define('MAX_IMAGES_PER_AD', 3);
define('AD_CREATION_INTERVAL_MINUTES', 15);
define('GEOFENCE_ENTRY_LIMIT_HOURS', 1);
define('CREDIT_EXPIRY_MONTHS', 12);

// Payment configuration
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_your_stripe_publishable_key');
define('STRIPE_SECRET_KEY', 'sk_test_your_stripe_secret_key');
define('GOOGLE_WALLET_MERCHANT_ID', 'your_google_wallet_merchant_id');
define('PAYMENT_CURRENCY', 'USD');

// File upload configuration
define('MAX_FILE_SIZE_BYTES', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', 'jpg,jpeg,png,gif,webp');
define('UPLOAD_BASE_PATH', '/var/www/uploads/');
define('IMAGE_BASE_URL', 'https://your-domain.com/uploads/');

// Rate limiting configuration
define('RATE_LIMIT_GEOFENCE_ENTRY', 1); // 1 entry per hour
define('RATE_LIMIT_AD_CREATION', 1); // 1 ad per 15 minutes
define('RATE_LIMIT_IMAGE_UPLOAD', 3); // 3 images per 15 minutes
define('RATE_LIMIT_WINDOW_MINUTES', 60);

// Security configuration
define('BCRYPT_COST', 12);
define('API_RATE_LIMIT_PER_MINUTE', 60);
define('MAX_GEOFENCES_PER_DEVICE', 100);
define('LOCATION_UPDATE_INTERVAL_SECONDS', 30);

// Geospatial configuration
define('SPATIAL_REFERENCE_SYSTEM', 4326); // WGS 84
define('EARTH_RADIUS_KM', 6371);
define('EARTH_RADIUS_MILES', 3959);

// Cache configuration
define('CACHE_ENABLED', true);
define('CACHE_TTL_SECONDS', 300); // 5 minutes
define('REDIS_HOST', 'localhost');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', '');

// Email configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@your-domain.com');
define('SMTP_PASSWORD', 'your_email_password');
define('EMAIL_FROM_NAME', 'Geofence Ads');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/geofence_api.log');

// Timezone
date_default_timezone_set('UTC');

// CORS configuration
define('ALLOWED_ORIGINS', [
    'https://your-domain.com',
    'https://app.your-domain.com',
    'http://localhost:3000',
    'http://localhost:8080'
]);

// Security headers
define('SECURITY_HEADERS', [
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'DENY',
    'X-XSS-Protection' => '1; mode=block',
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    'Content-Security-Policy' => "default-src 'self'",
    'Referrer-Policy' => 'strict-origin-when-cross-origin'
]);

// Database connection class with enhanced security
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET . ";port=" . DB_PORT;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Enable spatial functions if available
            $this->pdo->exec("SET @radius = " . DEFAULT_GEOFENCE_RADIUS_METERS);
            
        } catch (PDOException $e) {
            $this->logError("Database connection failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
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
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    private function logError($message) {
        error_log("[" . date('Y-m-d H:i:s') . "] " . $message . "\n", 3, ini_get('error_log'));
    }
}

// Helper function to get database connection
function getDB() {
    return Database::getInstance()->getConnection();
}

// Security helper functions
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function validateLatitude($latitude) {
    return is_numeric($latitude) && $latitude >= -90 && $latitude <= 90;
}

function validateLongitude($longitude) {
    return is_numeric($longitude) && $longitude >= -180 && $longitude <= 180;
}

function calculateDistance($lat1, $lon1, $lat2, $lon2, $unit = 'meters') {
    $earthRadius = ($unit === 'meters') ? EARTH_RADIUS_KM * 1000 : EARTH_RADIUS_MILES;
    
    $latDelta = deg2rad($lat2 - $lat1);
    $lonDelta = deg2rad($lon2 - $lon1);
    
    $a = sin($latDelta / 2) * sin($latDelta / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lonDelta / 2) * sin($lonDelta / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distance = $earthRadius * $c;
    
    return ($unit === 'meters') ? $distance * 1000 : $distance;
}

function isWithinGeofence($userLat, $userLon, $storeLat, $storeLon, $radiusMeters) {
    $distance = calculateDistance($userLat, $userLon, $storeLat, $storeLon, 'meters');
    return $distance <= $radiusMeters;
}

// Rate limiting functions
function checkRateLimit($identifier, $limitType, $storeId = null, $windowMinutes = 60, $maxRequests = 1) {
    try {
        $db = getDB();
        
        $sql = "SELECT request_count, window_start 
                FROM rate_limits 
                WHERE identifier = ? AND limit_type = ? AND store_id = ? 
                AND window_start > DATE_SUB(NOW(), INTERVAL ? MINUTE)
                ORDER BY window_start DESC 
                LIMIT 1";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$identifier, $limitType, $storeId, $windowMinutes]);
        $result = $stmt->fetch();
        
        if ($result && $result['request_count'] >= $maxRequests) {
            return false; // Rate limit exceeded
        }
        
        return true; // Within rate limit
        
    } catch (PDOException $e) {
        error_log("Rate limit check failed: " . $e->getMessage());
        return false; // Fail closed for security
    }
}

function incrementRateLimit($identifier, $limitType, $storeId = null, $windowMinutes = 60) {
    try {
        $db = getDB();
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic operations
        $sql = "INSERT INTO rate_limits (identifier, limit_type, store_id, window_start, window_duration_minutes) 
                VALUES (?, ?, ?, NOW(), ?) 
                ON DUPLICATE KEY UPDATE 
                request_count = request_count + 1, 
                updated_at = NOW()";
        
        $stmt = $db->prepare($sql);
        return $stmt->execute([$identifier, $limitType, $storeId, $windowMinutes]);
        
    } catch (PDOException $e) {
        error_log("Rate limit increment failed: " . $e->getMessage());
        return false;
    }
}

// Security headers function
function setSecurityHeaders() {
    foreach (SECURITY_HEADERS as $header => $value) {
        header("$header: $value");
    }
    
    // CORS headers
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, ALLOWED_ORIGINS) || ALLOWED_ORIGINS[0] === '*') {
        header("Access-Control-Allow-Origin: $origin");
    }
    
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Max-Age: 86400");
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// Initialize security headers
setSecurityHeaders();
?>