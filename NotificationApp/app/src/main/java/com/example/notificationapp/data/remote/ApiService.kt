package com.example.notificationapp.data.remote

import com.example.notificationapp.data.model.*
import retrofit2.Response
import retrofit2.http.*

interface ApiService {

    @POST("register")
    suspend fun register(@Body request: RegisterRequest): Response<RegisterResponse>

    @POST("login")
    suspend fun login(@Body request: LoginRequest): Response<LoginResponse>

    @GET("users")
    suspend fun getCurrentUser(): Response<User>

    @PUT("users")
    suspend fun updateUser(@Body request: UpdateUserRequest): Response<Map<String, String>>

    @GET("notifications")
    suspend fun getNotifications(
        @Query("page") page: Int = 1,
        @Query("limit") limit: Int = 20,
        @Query("unread") unreadOnly: Boolean = false
    ): Response<NotificationResponse>

    @GET("notifications/{id}")
    suspend fun getNotification(@Path("id") id: Int): Response<Notification>

    @POST("notifications")
    suspend fun createNotification(@Body request: CreateNotificationRequest): Response<Map<String, Any>>

    @PUT("notifications/{id}")
    suspend fun markAsRead(@Path("id") id: Int): Response<Map<String, String>>

    @DELETE("notifications/{id}")
    suspend fun deleteNotification(@Path("id") id: Int): Response<Map<String, String>>

    @GET("notification-types")
    suspend fun getNotificationTypes(): Response<List<NotificationType>>
}