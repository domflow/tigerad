package com.geofence.ads.data.repository

import com.geofence.ads.data.model.*
import com.geofence.ads.data.remote.ApiService
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class LocationRepository @Inject constructor(
    private val apiService: ApiService
) {
    
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
            val response = apiService.trackView(advertisementId, userFingerprint, latitude, longitude)
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
    
    suspend fun recordGeofenceEvent(event: GeofenceEvent): Result<MessageResponse> {
        return try {
            val response = apiService.recordGeofenceEvent(event)
            if (response.isSuccessful) {
                response.body()?.let { messageResponse ->
                    Result.success(messageResponse)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to record geofence event: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}