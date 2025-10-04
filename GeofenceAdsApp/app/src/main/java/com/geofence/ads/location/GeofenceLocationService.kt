package com.geofence.ads.location

import android.annotation.SuppressLint
import android.location.Location
import com.google.android.gms.location.FusedLocationProviderClient
import com.google.android.gms.location.LocationCallback
import com.google.android.gms.location.LocationRequest
import com.google.android.gms.location.LocationResult
import com.google.android.gms.location.Priority
import kotlinx.coroutines.channels.awaitClose
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.callbackFlow
import kotlinx.coroutines.tasks.await
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class GeofenceLocationService @Inject constructor(
    private val fusedLocationProviderClient: FusedLocationProviderClient
) {
    
    companion object {
        private const val LOCATION_UPDATE_INTERVAL = 30000L // 30 seconds
        private const val LOCATION_FASTEST_INTERVAL = 15000L // 15 seconds
        private const val LOCATION_SMALLEST_DISPLACEMENT = 10f // 10 meters
    }
    
    private val locationRequest = LocationRequest.Builder(Priority.PRIORITY_HIGH_ACCURACY, LOCATION_UPDATE_INTERVAL)
        .setMinUpdateIntervalMillis(LOCATION_FASTEST_INTERVAL)
        .setMinUpdateDistanceMeters(LOCATION_SMALLEST_DISPLACEMENT)
        .build()
    
    @SuppressLint("MissingPermission")
    fun getCurrentLocation(): Flow<Location?> = callbackFlow {
        val locationCallback = object : LocationCallback() {
            override fun onLocationResult(locationResult: LocationResult) {
                locationResult.lastLocation?.let { location ->
                    trySend(location)
                }
            }
        }
        
        fusedLocationProviderClient.requestLocationUpdates(
            locationRequest,
            locationCallback,
            null
        ).addOnFailureListener { exception ->
            close(exception)
        }
        
        awaitClose {
            fusedLocationProviderClient.removeLocationUpdates(locationCallback)
        }
    }
    
    @SuppressLint("MissingPermission")
    suspend fun getLastLocation(): Location? {
        return try {
            fusedLocationProviderClient.lastLocation.await()
        } catch (e: Exception) {
            null
        }
    }
    
    fun calculateDistance(
        startLat: Double,
        startLon: Double,
        endLat: Double,
        endLon: Double
    ): Float {
        val results = FloatArray(1)
        Location.distanceBetween(startLat, startLon, endLat, endLon, results)
        return results[0]
    }
    
    fun isLocationValid(location: Location): Boolean {
        return location.accuracy < 100 && // Less than 100 meters accuracy
               location.time > System.currentTimeMillis() - 300000 && // Less than 5 minutes old
               !location.isFromMockProvider // Not from mock location
    }
    
    fun createLocationData(location: Location): com.geofence.ads.data.model.LocationData {
        return com.geofence.ads.data.model.LocationData(
            latitude = location.latitude,
            longitude = location.longitude,
            accuracy = location.accuracy,
            altitude = location.altitude,
            speed = location.speed,
            bearing = location.bearing,
            timestamp = location.time,
            isMockLocation = location.isFromMockProvider
        )
    }
}