<?php
require_once 'geofence_config.php';

class GeofenceAuth {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    // Generate JWT token for store owners
    public function generateToken($userId, $userType = 'store_owner') {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $userId,
            'user_type' => $userType,
            'exp' => time() + (JWT_EXPIRATION_HOURS * 3600),
            'iat' => time(),
            'jti' => bin2hex(random_bytes(16)) // JWT ID for revocation
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
    
    // Store token in database for validation
    public function storeToken($userId, $token, $userType = 'store_owner') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO api_tokens (store_owner_id, token, user_type, expires_at) 
                VALUES (?, ?, ?, ?)
            ");
            
            $expiresAt = date('Y-m-d H:i:s', time() + (JWT_EXPIRATION_HOURS * 3600));
            return $stmt->execute([$userId, $token, $userType, $expiresAt]);
        } catch (PDOException $e) {
            error_log("Token storage error: " . $e->getMessage());
            return false;
        }
    }
    
    // Validate token from database
    public function validateDatabaseToken($token) {
        try {
            $stmt = $this->db->prepare("
                SELECT store_owner_id, user_type, expires_at 
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
            
            return [
                'user_id' => $result['store_owner_id'],
                'user_type' => $result['user_type']
            ];
        } catch (PDOException $e) {
            error_log("Database token validation error: " . $e->getMessage());
            return false;
        }
    }
    
    // Revoke token
    public function revokeToken($token) {
        try {
            $stmt = $this->db->prepare("UPDATE api_tokens SET is_active = 0 WHERE token = ?");
            return $stmt->execute([$token]);
        } catch (PDOException $e) {
            error_log("Token revocation error: " . $e->getMessage());
            return false;
        }
    }
    
    // Hash password
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    }
    
    // Verify password
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    // Middleware to require authentication
    public function requireAuth($userType = 'store_owner') {
        $token = $this->getBearerToken();
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'No token provided']);
            exit;
        }
        
        $userData = $this->validateDatabaseToken($token);
        if (!$userData || $userData['user_type'] !== $userType) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token']);
            exit;
        }
        
        return $userData['user_id'];
    }
    
    // Get bearer token from request
    public function getBearerToken() {
        $headers = getallheaders();
        if (!$headers) {
            return null;
        }
        
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    // Generate secure API key
    public function generateApiKey() {
        return bin2hex(random_bytes(32));
    }
    
    // Validate API key
    public function validateApiKey($apiKey) {
        try {
            $stmt = $this->db->prepare("
                SELECT store_owner_id, is_active, expires_at 
                FROM api_keys 
                WHERE api_key = ? AND is_active = 1
            ");
            $stmt->execute([$apiKey]);
            $result = $stmt->fetch();
            
            if (!$result) {
                return false;
            }
            
            if ($result['expires_at'] && strtotime($result['expires_at']) < time()) {
                return false;
            }
            
            return $result['store_owner_id'];
        } catch (PDOException $e) {
            error_log("API key validation error: " . $e->getMessage());
            return false;
        }
    }
    
    // Create user fingerprint for anonymous users
    public function createUserFingerprint($deviceInfo, $ipAddress) {
        $fingerprintData = [
            'user_agent' => $deviceInfo['user_agent'] ?? '',
            'screen_resolution' => $deviceInfo['screen_resolution'] ?? '',
            'timezone' => $deviceInfo['timezone'] ?? '',
            'language' => $deviceInfo['language'] ?? '',
            'platform' => $deviceInfo['platform'] ?? '',
            'ip_hash' => hash('sha256', $ipAddress . date('Y-m-d')) // Daily rotating IP hash
        ];
        
        return hash('sha256', json_encode($fingerprintData)));
    }
    
    // Check if user is rate limited
    public function isRateLimited($identifier, $action, $windowMinutes = 60, $maxAttempts = 1) {
        return !checkRateLimit($identifier, $action, null, $windowMinutes, $maxAttempts);
    }
    
    // Log security event
    public function logSecurityEvent($eventType, $userId = null, $details = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO security_logs (event_type, user_id, ip_address, user_agent, details) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $detailsJson = json_encode($details);
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            
            return $stmt->execute([$eventType, $userId, $ipAddress, $userAgent, $detailsJson]);
        } catch (PDOException $e) {
            error_log("Security logging error: " . $e->getMessage());
            return false;
        }
    }
    
    // Get user by ID
    public function getUserById($userId, $userType = 'store_owner') {
        try {
            if ($userType === 'store_owner') {
                $stmt = $this->db->prepare("
                    SELECT id, business_name, owner_name, email, phone, verification_status, created_at 
                    FROM store_owners 
                    WHERE id = ?
                ");
                $stmt->execute([$userId]);
                return $stmt->fetch();
            }
            
            return null;
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return null;
        }
    }
    
    // Update user last activity
    public function updateLastActivity($userId, $userType = 'store_owner') {
        try {
            if ($userType === 'store_owner') {
                $stmt = $this->db->prepare("UPDATE store_owners SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                return $stmt->execute([$userId]);
            }
            return false;
        } catch (PDOException $e) {
            error_log("Update last activity error: " . $e->getMessage());
            return false;
        }
    }
}
?>