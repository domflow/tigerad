<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'notification_manager.php';

echo "Testing Notification System Backend\n";
echo "====================================\n\n";

// Test database connection
echo "1. Testing database connection... ";
try {
    $db = getDB();
    echo "SUCCESS\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit;
}

// Test user creation
echo "2. Testing user registration... ";
try {
    $auth = new Auth();
    $db = getDB();
    
    // Clean up test user if exists
    $stmt = $db->prepare("DELETE FROM users WHERE username = 'testuser'");
    $stmt->execute();
    
    // Create test user
    $passwordHash = $auth->hashPassword('testpass123');
    $stmt = $db->prepare("
        INSERT INTO users (username, email, password_hash, device_token) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute(['testuser', 'test@example.com', $passwordHash, 'test_device_token_123']);
    $userId = $db->lastInsertId();
    echo "SUCCESS (User ID: $userId)\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}

// Test authentication
echo "3. Testing authentication... ";
try {
    $token = $auth->generateToken($userId);
    $auth->storeToken($userId, $token);
    echo "SUCCESS (Token generated)\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}

// Test notification creation
echo "4. Testing notification creation... ";
try {
    $notificationManager = new NotificationManager();
    $notificationId = $notificationManager->createNotification(
        $userId,
        'Test Notification',
        'This is a test notification from the backend test script',
        1,
        ['test' => 'data', 'timestamp' => time()]
    );
    echo "SUCCESS (Notification ID: $notificationId)\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}

// Test getting notifications
echo "5. Testing notification retrieval... ";
try {
    $notifications = $notificationManager->getUserNotifications($userId, 1, 10);
    echo "SUCCESS (Found " . count($notifications['notifications']) . " notifications)\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}

// Test queue processing
echo "6. Testing queue processing... ";
try {
    $result = $notificationManager->processQueue();
    echo "SUCCESS (Processed: {$result['processed']}, Failed: {$result['failed']})\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}

echo "\nBackend testing completed!\n";
echo "You can now test the API endpoints using curl or Postman.\n";
echo "Example API calls:\n";
echo "- POST http://localhost/api.php/register\n";
echo "- POST http://localhost/api.php/login\n";
echo "- GET http://localhost/api.php/notifications (requires auth token)\n";
?>