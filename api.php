<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'notification_manager.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGINS);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));
$endpoint = isset($pathParts[1]) ? $pathParts[1] : '';

// Initialize classes
$db = getDB();
$auth = new Auth();
$notificationManager = new NotificationManager();

// Handle different endpoints
switch ($endpoint) {
    case 'register':
        handleRegister($db, $auth);
        break;
    case 'login':
        handleLogin($db, $auth);
        break;
    case 'users':
        handleUsers($db, $auth, $method, $pathParts);
        break;
    case 'notifications':
        handleNotifications($db, $auth, $notificationManager, $method, $pathParts);
        break;
    case 'notification-types':
        handleNotificationTypes($db, $auth, $method);
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

// Handle user registration
function handleRegister($db, $auth) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    try {
        // Check if user exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$data['username'], $data['email']]);
        
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'User already exists']);
            return;
        }
        
        // Create user
        $passwordHash = $auth->hashPassword($data['password']);
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password_hash, device_token) 
            VALUES (?, ?, ?, ?)
        ");
        
        $deviceToken = isset($data['device_token']) ? $data['device_token'] : null;
        $stmt->execute([$data['username'], $data['email'], $passwordHash, $deviceToken]);
        
        $userId = $db->lastInsertId();
        
        // Generate token
        $token = $auth->generateToken($userId);
        $auth->storeToken($userId, $token);
        
        http_response_code(201);
        echo json_encode([
            'message' => 'User created successfully',
            'token' => $token,
            'user_id' => $userId
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Handle user login
function handleLogin($db, $auth) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['username']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing username or password']);
        return;
    }
    
    try {
        // Get user
        $stmt = $db->prepare("SELECT id, password_hash FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$data['username']]);
        $user = $stmt->fetch();
        
        if (!$user || !$auth->verifyPassword($data['password'], $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            return;
        }
        
        // Update device token if provided
        if (isset($data['device_token'])) {
            $stmt = $db->prepare("UPDATE users SET device_token = ? WHERE id = ?");
            $stmt->execute([$data['device_token'], $user['id']]);
        }
        
        // Generate token
        $token = $auth->generateToken($user['id']);
        $auth->storeToken($user['id'], $token);
        
        echo json_encode([
            'message' => 'Login successful',
            'token' => $token,
            'user_id' => $user['id']
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Handle user operations
function handleUsers($db, $auth, $method, $pathParts) {
    $userId = requireAuth();
    
    if ($method === 'GET' && count($pathParts) === 2) {
        // Get current user info
        try {
            $stmt = $db->prepare("SELECT id, username, email, device_token, is_active, created_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                echo json_encode($user);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    } elseif ($method === 'PUT' && count($pathParts) === 2) {
        // Update user info
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $updateFields = [];
            $params = [];
            
            if (isset($data['email'])) {
                $updateFields[] = "email = ?";
                $params[] = $data['email'];
            }
            
            if (isset($data['device_token'])) {
                $updateFields[] = "device_token = ?";
                $params[] = $data['device_token'];
            }
            
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(['error' => 'No fields to update']);
                return;
            }
            
            $params[] = $userId;
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['message' => 'User updated successfully']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

// Handle notification operations
function handleNotifications($db, $auth, $notificationManager, $method, $pathParts) {
    $userId = requireAuth();
    
    switch ($method) {
        case 'GET':
            if (count($pathParts) === 2) {
                // Get all notifications for user
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
                $unreadOnly = isset($_GET['unread']) && $_GET['unread'] === 'true';
                
                $notifications = $notificationManager->getUserNotifications($userId, $page, $limit, $unreadOnly);
                echo json_encode($notifications);
            } elseif (count($pathParts) === 3) {
                // Get specific notification
                $notificationId = (int)$pathParts[2];
                $notification = $notificationManager->getNotification($userId, $notificationId);
                
                if ($notification) {
                    echo json_encode($notification);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Notification not found']);
                }
            }
            break;
            
        case 'POST':
            if (count($pathParts) === 2) {
                // Create new notification
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($data['title']) || !isset($data['message'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing title or message']);
                    return;
                }
                
                $notificationId = $notificationManager->createNotification(
                    $userId,
                    $data['title'],
                    $data['message'],
                    isset($data['type_id']) ? $data['type_id'] : 1,
                    isset($data['data']) ? $data['data'] : null,
                    isset($data['scheduled_at']) ? $data['scheduled_at'] : null
                );
                
                http_response_code(201);
                echo json_encode([
                    'message' => 'Notification created successfully',
                    'notification_id' => $notificationId
                ]);
            }
            break;
            
        case 'PUT':
            if (count($pathParts) === 3) {
                // Update notification (mark as read)
                $notificationId = (int)$pathParts[2];
                $success = $notificationManager->markAsRead($userId, $notificationId);
                
                if ($success) {
                    echo json_encode(['message' => 'Notification marked as read']);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Notification not found']);
                }
            }
            break;
            
        case 'DELETE':
            if (count($pathParts) === 3) {
                // Delete notification
                $notificationId = (int)$pathParts[2];
                $success = $notificationManager->deleteNotification($userId, $notificationId);
                
                if ($success) {
                    echo json_encode(['message' => 'Notification deleted successfully']);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Notification not found']);
                }
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// Handle notification types
function handleNotificationTypes($db, $auth, $method) {
    requireAuth();
    
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    try {
        $stmt = $db->query("SELECT * FROM notification_types ORDER BY name");
        $types = $stmt->fetchAll();
        echo json_encode($types);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>