package com.geofence.ads.data.repository

import com.geofence.ads.data.model.*
import com.geofence.ads.data.remote.ApiService
import okhttp3.MultipartBody
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class AdvertisementRepository @Inject constructor(
    private val apiService: ApiService
) {
    
    suspend fun getAdvertisements(page: Int = 1, limit: Int = 20, status: String? = null): Result<AdvertisementResponse> {
        return try {
            val response = apiService.getAdvertisements(page, limit, status)
            if (response.isSuccessful) {
                response.body()?.let { adsResponse ->
                    Result.success(adsResponse)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to get advertisements: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun createAdvertisement(request: CreateAdvertisementRequest): Result<CreateResponse> {
        return try {
            val response = apiService.createAdvertisement(request)
            if (response.isSuccessful) {
                response.body()?.let { createResponse ->
                    Result.success(createResponse)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to create advertisement: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun deleteAdvertisement(id: Int): Result<MessageResponse> {
        return try {
            val response = apiService.deleteAdvertisement(id)
            if (response.isSuccessful) {
                response.body()?.let { messageResponse ->
                    Result.success(messageResponse)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to delete advertisement: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun uploadImage(imagePart: MultipartBody.Part, advertisementId: Int): Result<ImageUploadResponse> {
        return try {
            val response = apiService.uploadImage(imagePart, advertisementId)
            if (response.isSuccessful) {
                response.body()?.let { uploadResponse ->
                    Result.success(uploadResponse)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to upload image: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getNearbyAdvertisements(latitude: Double, longitude: Double, radius: Int = 1609, userFingerprint: String? = null): Result<NearbyAdsResponse> {
        return try {
            val response = apiService.getNearbyAdvertisements(latitude, longitude, radius, userFingerprint)
            if (response.isSuccessful) {
                response.body()?.let { nearbyResponse ->
                    Result.success(nearbyResponse)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to get nearby advertisements: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun trackView(advertisementId: Int, userFingerprint: String, latitude: Double, longitude: Double): Result<TrackViewResponse> {
        return try {
            val response = apiService.trackView(TrackViewRequest(advertisementId, userFingerprint, latitude, longitude))
            if (response.isSuccessful) {
                response.body()?.let { trackResponse ->
                    Result.success(trackResponse)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to track view: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}