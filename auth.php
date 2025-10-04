<?php
require_once 'config.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    // Generate JWT token
    public function generateToken($userId) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $userId,
            'exp' => time() + (TOKEN_EXPIRY_HOURS * 3600),
            'iat' => time()
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    // Validate JWT token
    public function validateToken($token) {
        $tokenParts = explode('.', $token);
        if (count($tokenParts) !== 3) {
            return false;
        }
        
        $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[0]));
        $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
        $signatureProvided = $tokenParts[2];
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if ($signatureProvided !== $base64Signature) {
            return false;
        }
        
        $payloadData = json_decode($payload, true);
        if (!isset($payloadData['exp']) || $payloadData['exp'] < time()) {
            return false;
        }
        
        return $payloadData;
    }
    
    // Store token in database
    public function storeToken($userId, $token) {
        $stmt = $this->db->prepare("
            INSERT INTO api_tokens (user_id, token, expires_at) 
            VALUES (?, ?, ?)
        ");
        
        $expiresAt = date('Y-m-d H:i:s', time() + (TOKEN_EXPIRY_HOURS * 3600));
        return $stmt->execute([$userId, $token, $expiresAt]);
    }
    
    // Validate token from database
    public function validateDatabaseToken($token) {
        $stmt = $this->db->prepare("
            SELECT user_id, expires_at 
            FROM api_tokens 
            WHERE token = ? AND is_active = 1
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return false;
        }
        
        if (strtotime($result['expires_at']) < time()) {
            return false;
        }
        
        return $result['user_id'];
    }
    
    // Hash password
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }
    
    // Verify password
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}

// Function to get Authorization header
function getAuthorizationHeader() {
    $headers = null;
    
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } else if (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    
    return $headers;
}

// Function to get bearer token
function getBearerToken() {
    $headers = getAuthorizationHeader();
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    return null;
}

// Middleware to check authentication
function requireAuth() {
    $token = getBearerToken();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'No token provided']);
        exit;
    }
    
    $auth = new Auth();
    $userId = $auth->validateDatabaseToken($token);
    
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token']);
        exit;
    }
    
    return $userId;
}
?>