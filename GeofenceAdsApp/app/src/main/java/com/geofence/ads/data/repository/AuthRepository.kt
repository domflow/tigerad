package com.geofence.ads.data.repository

import com.geofence.ads.data.local.UserPreferences
import com.geofence.ads.data.model.*
import com.geofence.ads.data.remote.ApiService
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class AuthRepository @Inject constructor(
    private val apiService: ApiService,
    private val userPreferences: UserPreferences
) {
    
    suspend fun registerStoreOwner(request: StoreOwnerRegistrationRequest): Result<StoreOwnerRegistrationResponse> {
        return try {
            val response = apiService.registerStoreOwner(request)
            if (response.isSuccessful) {
                response.body()?.let { result ->
                    // Save authentication data
                    userPreferences.saveToken(result.token)
                    userPreferences.saveUserInfo(result.ownerId.toString(), UserPreferences.USER_TYPE_STORE_OWNER)
                    Result.success(result)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Registration failed: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun login(email: String, password: String): Result<LoginResponse> {
        return try {
            val response = apiService.login(LoginRequest(email, password))
            if (response.isSuccessful) {
                response.body()?.let { loginResponse ->
                    userPreferences.saveToken(loginResponse.token)
                    userPreferences.saveUserInfo(loginResponse.ownerId.toString(), UserPreferences.USER_TYPE_STORE_OWNER)
                    Result.success(loginResponse)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Login failed: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun createUserFingerprint(deviceInfo: Map<String, String>): String {
        val fingerprint = "${deviceInfo["user_agent"]}_${System.currentTimeMillis()}"
        userPreferences.saveUserFingerprint(fingerprint)
        return fingerprint
    }
    
    suspend fun logout() {
        userPreferences.clearAuthData()
    }
    
    fun isLoggedIn(): Flow<Boolean> = userPreferences.token.map { it != null }
    
    fun getUserType(): Flow<String?> = userPreferences.userType
    
    fun getToken(): Flow<String?> = userPreferences.token
    
    suspend fun getCurrentToken(): String? = userPreferences.token.first()
    
    fun isStoreOwner(): Flow<Boolean> = userPreferences.userType.map { it == UserPreferences.USER_TYPE_STORE_OWNER }
}