package com.geofence.ads.data.repository

import com.geofence.ads.data.model.*
import com.geofence.ads.data.remote.ApiService
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class StoreRepository @Inject constructor(
    private val apiService: ApiService
) {
    
    suspend fun getStores(): Result<List<Store>> {
        return try {
            val response = apiService.getStores()
            if (response.isSuccessful) {
                response.body()?.let { storesResponse ->
                    Result.success(storesResponse.stores)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to get stores: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getStore(id: Int): Result<Store> {
        return try {
            val response = apiService.getStore(id)
            if (response.isSuccessful) {
                response.body()?.let { store ->
                    Result.success(store)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to get store: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun createStore(request: CreateStoreRequest): Result<CreateResponse> {
        return try {
            val response = apiService.createStore(request)
            if (response.isSuccessful) {
                response.body()?.let { createResponse ->
                    Result.success(createResponse)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to create store: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun updateStore(id: Int, request: UpdateStoreRequest): Result<MessageResponse> {
        return try {
            val response = apiService.updateStore(id, request)
            if (response.isSuccessful) {
                response.body()?.let { messageResponse ->
                    Result.success(messageResponse)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to update store: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}