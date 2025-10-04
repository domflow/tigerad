package com.example.notificationapp.worker

import android.app.NotificationManager
import android.content.Context
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.hilt.work.HiltWorker
import androidx.work.*
import com.example.notificationapp.R
import com.example.notificationapp.data.model.Notification
import com.example.notificationapp.data.repository.NotificationRepository
import dagger.assisted.Assisted
import dagger.assisted.AssistedInject
import kotlinx.coroutines.flow.first

@HiltWorker
class NotificationWorker @AssistedInject constructor(
    @Assisted context: Context,
    @Assisted workerParams: WorkerParameters,
    private val notificationRepository: NotificationRepository
) : CoroutineWorker(context, workerParams) {

    companion object {
        private const val TAG = "NotificationWorker"
        private const val NOTIFICATION_CHANNEL_ID = "notification_channel"
        private const val WORK_NAME = "notification_poll_work"
        
        // Schedule the work to run every 30 minutes
        fun schedule(context: Context) {
            val constraints = Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .setRequiresBatteryNotLow(true)
                .build()

            val periodicWorkRequest = PeriodicWorkRequestBuilder<NotificationWorker>(30, TimeUnit.MINUTES)
                .setConstraints(constraints)
                .setInitialDelay(5, TimeUnit.MINUTES) // Start after 5 minutes
                .build()

            WorkManager.getInstance(context)
                .enqueueUniquePeriodicWork(
                    WORK_NAME,
                    ExistingPeriodicWorkPolicy.KEEP,
                    periodicWorkRequest
                )
            
            Log.d(TAG, "Notification polling scheduled to run every 30 minutes")
        }

        // Cancel the scheduled work
        fun cancel(context: Context) {
            WorkManager.getInstance(context).cancelUniqueWork(WORK_NAME)
            Log.d(TAG, "Notification polling cancelled")
        }
    }

    override suspend fun doWork(): Result {
        return try {
            Log.d(TAG, "Starting notification poll...")
            
            // Get unread notifications
            val result = notificationRepository.getNotifications(1, 20, true)
            
            if (result.isSuccess) {
                val notificationResponse = result.getOrNull()
                val unreadNotifications = notificationResponse?.notifications ?: emptyList()
                
                Log.d(TAG, "Found ${unreadNotifications.size} unread notifications")
                
                // Show local notifications for unread items
                unreadNotifications.forEach { notification ->
                    showLocalNotification(notification)
                }
                
                Result.success()
            } else {
                val error = result.exceptionOrNull()
                Log.e(TAG, "Failed to fetch notifications: ${error?.message}")
                
                if (error != null && isRetryableError(error)) {
                    Result.retry()
                } else {
                    Result.failure()
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error in notification worker: ${e.message}", e)
            
            if (isRetryableError(e)) {
                Result.retry()
            } else {
                Result.failure()
            }
        }
    }

    private fun showLocalNotification(notification: Notification) {
        try {
            val notificationManager = applicationContext.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            
            val localNotification = NotificationCompat.Builder(applicationContext, NOTIFICATION_CHANNEL_ID)
                .setContentTitle(notification.title)
                .setContentText(notification.message)
                .setSmallIcon(R.drawable.ic_notification)
                .setPriority(NotificationCompat.PRIORITY_DEFAULT)
                .setAutoCancel(true)
                .setWhen(System.currentTimeMillis())
                .build()
            
            // Use notification ID as the notification ID
            notificationManager.notify(notification.id, localNotification)
            
            Log.d(TAG, "Local notification shown for notification ${notification.id}")
        } catch (e: Exception) {
            Log.e(TAG, "Error showing local notification: ${e.message}", e)
        }
    }

    private fun isRetryableError(error: Throwable): Boolean {
        // Retry on network errors, timeouts, etc.
        val message = error.message ?: ""
        return message.contains("timeout", ignoreCase = true) ||
               message.contains("network", ignoreCase = true) ||
               message.contains("connection", ignoreCase = true) ||
               message.contains("unavailable", ignoreCase = true)
    }
}