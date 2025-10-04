package com.example.notificationapp.data.repository

import com.example.notificationapp.data.model.*
import com.example.notificationapp.data.remote.ApiService
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class NotificationRepository @Inject constructor(
    private val apiService: ApiService
) {

    suspend fun getNotifications(page: Int = 1, limit: Int = 20, unreadOnly: Boolean = false): Result<NotificationResponse> {
        return try {
            val response = apiService.getNotifications(page, limit, unreadOnly)
            if (response.isSuccessful) {
                response.body()?.let { notificationResponse ->
                    Result.success(notificationResponse)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to get notifications: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getNotification(id: Int): Result<Notification> {
        return try {
            val response = apiService.getNotification(id)
            if (response.isSuccessful) {
                response.body()?.let { notification ->
                    Result.success(notification)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to get notification: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun createNotification(title: String, message: String, typeId: Int = 1, data: Map<String, Any>? = null, scheduledAt: String? = null): Result<Int> {
        return try {
            val response = apiService.createNotification(CreateNotificationRequest(title, message, typeId, data, scheduledAt))
            if (response.isSuccessful) {
                response.body()?.let { result ->
                    val notificationId = (result["notification_id"] as Double).toInt()
                    Result.success(notificationId)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to create notification: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun markAsRead(id: Int): Result<String> {
        return try {
            val response = apiService.markAsRead(id)
            if (response.isSuccessful) {
                response.body()?.let { result ->
                    Result.success(result["message"] ?: "Notification marked as read")
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to mark notification as read: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun deleteNotification(id: Int): Result<String> {
        return try {
            val response = apiService.deleteNotification(id)
            if (response.isSuccessful) {
                response.body()?.let { result ->
                    Result.success(result["message"] ?: "Notification deleted successfully")
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to delete notification: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getNotificationTypes(): Result<List<NotificationType>> {
        return try {
            val response = apiService.getNotificationTypes()
            if (response.isSuccessful) {
                response.body()?.let { types ->
                    Result.success(types)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to get notification types: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}