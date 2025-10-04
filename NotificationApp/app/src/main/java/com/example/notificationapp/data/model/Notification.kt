package com.example.notificationapp.data.model

data class Notification(
    val id: Int,
    val title: String,
    val message: String,
    val data: Map<String, Any>?,
    val isRead: Boolean,
    val isSent: Boolean,
    val scheduledAt: String?,
    val sentAt: String?,
    val readAt: String?,
    val createdAt: String,
    val typeName: String,
    val priority: String
)

data class NotificationResponse(
    val notifications: List<Notification>,
    val total: Int,
    val page: Int,
    val limit: Int,
    val pages: Int
)

data class CreateNotificationRequest(
    val title: String,
    val message: String,
    val typeId: Int = 1,
    val data: Map<String, Any>? = null,
    val scheduledAt: String? = null
)

data class NotificationType(
    val id: Int,
    val name: String,
    val description: String,
    val priority: String
)