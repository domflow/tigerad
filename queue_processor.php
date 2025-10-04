<?php
require_once 'config.php';
require_once 'notification_manager.php';

// This script should be run via cron job every minute
// Example cron entry: * * * * * /usr/bin/php /path/to/queue_processor.php >> /var/log/notification_queue.log 2>&1

// Set time limit to prevent timeout
set_time_limit(300); // 5 minutes

// Log start
error_log("[" . date('Y-m-d H:i:s') . "] Queue processor started");

try {
    $notificationManager = new NotificationManager();
    $result = $notificationManager->processQueue();
    
    error_log("[" . date('Y-m-d H:i:s') . "] Queue processor completed. Processed: {$result['processed']}, Failed: {$result['failed']}");
    
    echo json_encode([
        'status' => 'success',
        'processed' => $result['processed'],
        'failed' => $result['failed'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Queue processor error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>