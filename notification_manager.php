<?php
require_once 'config.php';

class NotificationManager {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    // Create a new notification
    public function createNotification($userId, $title, $message, $typeId = 1, $data = null, $scheduledAt = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (user_id, type_id, title, message, data, scheduled_at) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $dataJson = $data ? json_encode($data) : null;
            $stmt->execute([$userId, $typeId, $title, $message, $dataJson, $scheduledAt]);
            
            $notificationId = $this->db->lastInsertId();
            
            // Add to queue if not scheduled
            if (!$scheduledAt) {
                $this->addToQueue($notificationId);
            }
            
            return $notificationId;
        } catch (PDOException $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return false;
        }
    }
    
    // Add notification to queue
    public function addToQueue($notificationId) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notification_queue (notification_id) 
                VALUES (?)
            ");
            return $stmt->execute([$notificationId]);
        } catch (PDOException $e) {
            error_log("Error adding to queue: " . $e->getMessage());
            return false;
        }
    }
    
    // Get notifications for user
    public function getUserNotifications($userId, $page = 1, $limit = 20, $unreadOnly = false) {
        $offset = ($page - 1) * $limit;
        
        $whereClause = "WHERE n.user_id = ?";
        if ($unreadOnly) {
            $whereClause .= " AND n.is_read = 0";
        }
        
        try {
            // Get total count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total 
                FROM notifications n 
                $whereClause
            ");
            $countStmt->execute([$userId]);
            $total = $countStmt->fetch()['total'];
            
            // Get notifications
            $stmt = $this->db->prepare("
                SELECT 
                    n.id,
                    n.title,
                    n.message,
                    n.data,
                    n.is_read,
                    n.is_sent,
                    n.scheduled_at,
                    n.sent_at,
                    n.read_at,
                    n.created_at,
                    nt.name as type_name,
                    nt.priority
                FROM notifications n
                JOIN notification_types nt ON n.type_id = nt.id
                $whereClause
                ORDER BY n.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$userId, $limit, $offset]);
            $notifications = $stmt->fetchAll();
            
            // Decode JSON data
            foreach ($notifications as &$notification) {
                if ($notification['data']) {
                    $notification['data'] = json_decode($notification['data'], true);
                }
            }
            
            return [
                'notifications' => $notifications,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ];
        } catch (PDOException $e) {
            error_log("Error getting notifications: " . $e->getMessage());
            return ['notifications' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'pages' => 0];
        }
    }
    
    // Get specific notification
    public function getNotification($userId, $notificationId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    n.id,
                    n.title,
                    n.message,
                    n.data,
                    n.is_read,
                    n.is_sent,
                    n.scheduled_at,
                    n.sent_at,
                    n.read_at,
                    n.created_at,
                    nt.name as type_name,
                    nt.priority
                FROM notifications n
                JOIN notification_types nt ON n.type_id = nt.id
                WHERE n.id = ? AND n.user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);
            $notification = $stmt->fetch();
            
            if ($notification && $notification['data']) {
                $notification['data'] = json_decode($notification['data'], true);
            }
            
            return $notification;
        } catch (PDOException $e) {
            error_log("Error getting notification: " . $e->getMessage());
            return null;
        }
    }
    
    // Mark notification as read
    public function markAsRead($userId, $notificationId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND user_id = ? AND is_read = 0
            ");
            $stmt->execute([$notificationId, $userId]);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error marking as read: " . $e->getMessage());
            return false;
        }
    }
    
    // Delete notification
    public function deleteNotification($userId, $notificationId) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM notifications 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error deleting notification: " . $e->getMessage());
            return false;
        }
    }
    
    // Process notification queue
    public function processQueue() {
        try {
            // Get pending notifications
            $stmt = $this->db->prepare("
                SELECT 
                    nq.id as queue_id,
                    n.id as notification_id,
                    n.user_id,
                    n.title,
                    n.message,
                    n.data,
                    u.device_token,
                    u.email,
                    nt.name as type_name,
                    nt.priority
                FROM notification_queue nq
                JOIN notifications n ON nq.notification_id = n.id
                JOIN users u ON n.user_id = u.id
                JOIN notification_types nt ON n.type_id = nt.id
                WHERE nq.status = 'pending' 
                AND n.scheduled_at IS NULL
                AND nq.retry_count < ?
                ORDER BY n.created_at ASC
                LIMIT 10
            ");
            $stmt->execute([MAX_RETRY_ATTEMPTS]);
            $notifications = $stmt->fetchAll();
            
            $processed = 0;
            $failed = 0;
            
            foreach ($notifications as $notification) {
                $result = $this->sendNotification($notification);
                
                if ($result) {
                    $this->markQueueItemProcessed($notification['queue_id'], 'sent');
                    $this->markNotificationSent($notification['notification_id']);
                    $processed++;
                } else {
                    $retryCount = $this->getRetryCount($notification['queue_id']);
                    if ($retryCount < MAX_RETRY_ATTEMPTS) {
                        $this->incrementRetryCount($notification['queue_id']);
                        $this->scheduleRetry($notification['queue_id'], $retryCount);
                    } else {
                        $this->markQueueItemProcessed($notification['queue_id'], 'failed');
                        $failed++;
                    }
                }
            }
            
            return ['processed' => $processed, 'failed' => $failed];
        } catch (PDOException $e) {
            error_log("Error processing queue: " . $e->getMessage());
            return ['processed' => 0, 'failed' => 0];
        }
    }
    
    // Send notification (simulated - in real app, this would integrate with FCM/APNS)
    private function sendNotification($notification) {
        // Simulate sending notification
        // In a real application, this would integrate with:
        // - Firebase Cloud Messaging (FCM) for Android
        // - Apple Push Notification Service (APNS) for iOS
        // - Email services for email notifications
        
        try {
            // Log the notification sending
            error_log("Sending notification {$notification['notification_id']} to user {$notification['user_id']}");
            
            // Simulate API call delay
            usleep(100000); // 0.1 second
            
            // Simulate 90% success rate
            return rand(1, 100) <= 90;
        } catch (Exception $e) {
            error_log("Error sending notification: " . $e->getMessage());
            return false;
        }
    }
    
    // Mark queue item as processed
    private function markQueueItemProcessed($queueId, $status) {
        try {
            $stmt = $this->db->prepare("
                UPDATE notification_queue 
                SET status = ?, processed_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            return $stmt->execute([$status, $queueId]);
        } catch (PDOException $e) {
            error_log("Error marking queue item: " . $e->getMessage());
            return false;
        }
    }
    
    // Mark notification as sent
    private function markNotificationSent($notificationId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_sent = 1, sent_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            return $stmt->execute([$notificationId]);
        } catch (PDOException $e) {
            error_log("Error marking notification sent: " . $e->getMessage());
            return false;
        }
    }
    
    // Get retry count
    private function getRetryCount($queueId) {
        try {
            $stmt = $this->db->prepare("SELECT retry_count FROM notification_queue WHERE id = ?");
            $stmt->execute([$queueId]);
            $result = $stmt->fetch();
            return $result ? $result['retry_count'] : 0;
        } catch (PDOException $e) {
            error_log("Error getting retry count: " . $e->getMessage());
            return 0;
        }
    }
    
    // Increment retry count
    private function incrementRetryCount($queueId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE notification_queue 
                SET retry_count = retry_count + 1 
                WHERE id = ?
            ");
            return $stmt->execute([$queueId]);
        } catch (PDOException $e) {
            error_log("Error incrementing retry count: " . $e->getMessage());
            return false;
        }
    }
    
    // Schedule retry
    private function scheduleRetry($queueId, $retryCount) {
        $delay = RETRY_DELAY_SECONDS * pow(2, $retryCount); // Exponential backoff
        $retryAt = date('Y-m-d H:i:s', time() + $delay);
        
        try {
            $stmt = $this->db->prepare("
                UPDATE notification_queue 
                SET status = 'pending', updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            return $stmt->execute([$queueId]);
        } catch (PDOException $e) {
            error_log("Error scheduling retry: " . $e->getMessage());
            return false;
        }
    }
}
?>