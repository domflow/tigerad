package com.example.notificationapp.data.repository

import com.example.notificationapp.data.model.*
import com.example.notificationapp.data.remote.ApiService
import com.example.notificationapp.data.remote.TokenProvider
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class AuthRepository @Inject constructor(
    private val apiService: ApiService,
    private val tokenProvider: TokenProvider
) {

    suspend fun login(username: String, password: String, deviceToken: String? = null): Result<LoginResponse> {
        return try {
            val response = apiService.login(LoginRequest(username, password, deviceToken))
            if (response.isSuccessful) {
                response.body()?.let { loginResponse ->
                    tokenProvider.setToken(loginResponse.token)
                    Result.success(loginResponse)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Login failed: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun register(username: String, email: String, password: String, deviceToken: String? = null): Result<RegisterResponse> {
        return try {
            val response = apiService.register(RegisterRequest(username, email, password, deviceToken))
            if (response.isSuccessful) {
                response.body()?.let { registerResponse ->
                    tokenProvider.setToken(registerResponse.token)
                    Result.success(registerResponse)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Registration failed: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getCurrentUser(): Result<User> {
        return try {
            val response = apiService.getCurrentUser()
            if (response.isSuccessful) {
                response.body()?.let { user ->
                    Result.success(user)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to get user: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun updateUser(email: String? = null, deviceToken: String? = null): Result<String> {
        return try {
            val response = apiService.updateUser(UpdateUserRequest(email, deviceToken))
            if (response.isSuccessful) {
                response.body()?.let { result ->
                    Result.success(result["message"] ?: "User updated successfully")
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to update user: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    fun logout() {
        tokenProvider.clearToken()
    }

    fun isLoggedIn(): Boolean = tokenProvider.getToken() != null
}