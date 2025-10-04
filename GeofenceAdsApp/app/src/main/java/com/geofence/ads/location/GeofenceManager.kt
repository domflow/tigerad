package com.geofence.ads.location

import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import com.geofence.ads.data.model.Store
import com.google.android.gms.location.Geofence
import com.google.android.gms.location.GeofencingClient
import com.google.android.gms.location.GeofencingRequest
import com.google.android.gms.location.LocationServices
import timber.log.Timber
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class GeofenceManager @Inject constructor(
    private val context: Context,
    private val geofencingClient: GeofencingClient,
    private val locationService: GeofenceLocationService,
    private val userPreferences: com.geofence.ads.data.local.UserPreferences
) {
    
    companion object {
        private const val GEOFENCE_RADIUS_METERS = 1609f // 1 mile
        private const val GEOFENCE_EXPIRATION_DURATION = 24 * 60 * 60 * 1000L // 24 hours
        private const val TRIGGER_RADIUS_METERS = 3f // 3 meters
        private const val MAX_GEOFENCES = 100 // Android limit
    }
    
    private var currentGeofences = mutableListOf<Store>()
    
    fun initializeGeofenceMonitoring() {
        Timber.d("Initializing geofence monitoring")
        // Geofence monitoring will be initialized when stores are loaded
    }
    
    suspend fun addGeofencesForStores(stores: List<Store>) {
        if (stores.isEmpty()) {
            Timber.d("No stores to add geofences for")
            return
        }
        
        // Limit to maximum geofences
        val limitedStores = stores.take(MAX_GEOFENCES)
        currentGeofences.clear()
        currentGeofences.addAll(limitedStores)
        
        // Remove existing geofences
        removeAllGeofences()
        
        // Create new geofences
        val geofences = limitedStores.map { store ->
            Geofence.Builder()
                .setRequestId(store.id.toString())
                .setCircularRegion(
                    store.latitude,
                    store.longitude,
                    GEOFENCE_RADIUS_METERS
                )
                .setExpirationDuration(GEOFENCE_EXPIRATION_DURATION)
                .setTransitionTypes(
                    Geofence.GEOFENCE_TRANSITION_ENTER or
                    Geofence.GEOFENCE_TRANSITION_EXIT or
                    Geofence.GEOFENCE_TRANSITION_DWELL
                )
                .setLoiteringDelay(5000) // 5 seconds dwell time
                .build()
        }
        
        if (geofences.isNotEmpty()) {
            val geofencingRequest = GeofencingRequest.Builder()
                .setInitialTrigger(GeofencingRequest.INITIAL_TRIGGER_ENTER or GeofencingRequest.INITIAL_TRIGGER_DWELL)
                .addGeofences(geofences)
                .build()
            
            val pendingIntent = getGeofencePendingIntent()
            
            try {
                geofencingClient.addGeofences(geofencingRequest, pendingIntent)
                    .addOnSuccessListener {
                        Timber.d("Successfully added ${geofences.size} geofences")
                    }
                    .addOnFailureListener { exception ->
                        Timber.e(exception, "Failed to add geofences")
                    }
            } catch (e: SecurityException) {
                Timber.e(e, "Security exception when adding geofences")
            }
        }
    }
    
    fun removeAllGeofences() {
        try {
            geofencingClient.removeGeofences(getGeofencePendingIntent())
                .addOnSuccessListener {
                    Timber.d("Successfully removed all geofences")
                    currentGeofences.clear()
                }
                .addOnFailureListener { exception ->
                    Timber.e(exception, "Failed to remove geofences")
                }
        } catch (e: SecurityException) {
            Timber.e(e, "Security exception when removing geofences")
        }
    }
    
    fun removeGeofence(storeId: Int) {
        try {
            geofencingClient.removeGeofences(listOf(storeId.toString()))
                .addOnSuccessListener {
                    Timber.d("Successfully removed geofence for store $storeId")
                    currentGeofences.removeAll { it.id == storeId }
                }
                .addOnFailureListener { exception ->
                    Timber.e(exception, "Failed to remove geofence for store $storeId")
                }
        } catch (e: SecurityException) {
            Timber.e(e, "Security exception when removing geofence")
        }
    }
    
    private fun getGeofencePendingIntent(): PendingIntent {
        val intent = Intent(context, GeofenceBroadcastReceiver::class.java)
        val flags = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        } else {
            PendingIntent.FLAG_UPDATE_CURRENT
        }
        
        return PendingIntent.getBroadcast(
            context,
            0,
            intent,
            flags
        )
    }
    
    fun getCurrentGeofences(): List<Store> = currentGeofences.toList()
    
    fun getStoreById(storeId: Int): Store? {
        return currentGeofences.find { it.id == storeId }
    }
    
    suspend fun isWithinStoreGeofence(storeId: Int, latitude: Double, longitude: Double): Boolean {
        val store = getStoreById(storeId) ?: return false
        return locationService.calculateDistance(
            latitude,
            longitude,
            store.latitude,
            store.longitude
        ) <= GEOFENCE_RADIUS_METERS
    }
    
    fun isWithinTriggerZone(storeId: Int, latitude: Double, longitude: Double): Boolean {
        val store = getStoreById(storeId) ?: return false
        return locationService.calculateDistance(
            latitude,
            longitude,
            store.latitude,
            store.longitude
        ) <= TRIGGER_RADIUS_METERS
    }
    
    companion object {
        const val ACTION_GEOFENCE_EVENT = "com.geofence.ads.GEOFENCE_EVENT"
        const val EXTRA_GEOFENCE_STORE_ID = "extra_geofence_store_id"
        const val EXTRA_GEOFENCE_TRANSITION = "extra_geofence_transition"
        const val EXTRA_GEOFENCE_LATITUDE = "extra_geofence_latitude"
        const val EXTRA_GEOFENCE_LONGITUDE = "extra_geofence_longitude"
        const val EXTRA_GEOFENCE_DISTANCE = "extra_geofence_distance"
    }
}