package com.example.notificationapp.data.model

data class User(
    val id: Int,
    val username: String,
    val email: String,
    val deviceToken: String?,
    val isActive: Boolean,
    val createdAt: String
)

data class LoginRequest(
    val username: String,
    val password: String,
    val deviceToken: String? = null
)

data class LoginResponse(
    val message: String,
    val token: String,
    val userId: Int
)

data class RegisterRequest(
    val username: String,
    val email: String,
    val password: String,
    val deviceToken: String? = null
)

data class RegisterResponse(
    val message: String,
    val token: String,
    val userId: Int
)

data class UpdateUserRequest(
    val email: String? = null,
    val deviceToken: String? = null
)