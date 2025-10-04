package com.geofence.ads.location

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import com.geofence.ads.data.model.GeofenceEvent
import com.geofence.ads.data.repository.LocationRepository
import com.google.android.gms.location.Geofence
import com.google.android.gms.location.GeofenceStatusCodes
import com.google.android.gms.location.GeofencingEvent
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import timber.log.Timber
import javax.inject.Inject

@AndroidEntryPoint
class GeofenceBroadcastReceiver : BroadcastReceiver() {
    
    @Inject
    lateinit var locationRepository: LocationRepository
    
    @Inject
    lateinit var geofenceManager: GeofenceManager
    
    override fun onReceive(context: Context, intent: Intent) {
        Timber.d("Geofence broadcast received")
        
        val geofencingEvent = GeofencingEvent.fromIntent(intent)
        
        if (geofencingEvent == null) {
            Timber.e("GeofencingEvent is null")
            return
        }
        
        if (geofencingEvent.hasError()) {
            val errorMessage = GeofenceStatusCodes.getStatusCodeString(geofencingEvent.errorCode)
            Timber.e("Geofencing error: $errorMessage")
            return
        }
        
        // Get the transition type
        val geofenceTransition = geofencingEvent.geofenceTransition
        
        // Get the geofences that were triggered
        val triggeringGeofences = geofencingEvent.triggeringGeofences
        
        if (triggeringGeofences == null || triggeringGeofences.isEmpty()) {
            Timber.w("No triggering geofences")
            return
        }
        
        // Get the triggering location
        val triggeringLocation = geofencingEvent.triggeringLocation
        
        for (geofence in triggeringGeofences) {
            val storeId = geofence.requestId.toIntOrNull() ?: continue
            
            val eventType = when (geofenceTransition) {
                Geofence.GEOFENCE_TRANSITION_ENTER -> "enter"
                Geofence.GEOFENCE_TRANSITION_EXIT -> "exit"
                Geofence.GEOFENCE_TRANSITION_DWELL -> "dwell"
                else -> continue
            }
            
            // Calculate distance to store
            val store = geofenceManager.getStoreById(storeId)
            val distanceMeters = if (store != null && triggeringLocation != null) {
                geofenceManager.locationService.calculateDistance(
                    triggeringLocation.latitude,
                    triggeringLocation.longitude,
                    store.latitude,
                    store.longitude
                )
            } else {
                0f
            }
            
            // Generate user fingerprint (in real app, this would come from UserPreferences)
            val userFingerprint = generateUserFingerprint(context)
            
            // Create geofence event
            val geofenceEvent = GeofenceEvent(
                storeId = storeId,
                userFingerprint = userFingerprint,
                eventType = eventType,
                latitude = triggeringLocation?.latitude ?: 0.0,
                longitude = triggeringLocation?.longitude ?: 0.0,
                distanceToStoreMeters = distanceMeters.toDouble(),
                triggerTime = System.currentTimeMillis().toString()
            )
            
            // Process the event
            processGeofenceEvent(geofenceEvent, context)
        }
    }
    
    private fun processGeofenceEvent(event: GeofenceEvent, context: Context) {
        CoroutineScope(Dispatchers.IO).launch {
            try {
                Timber.d("Processing geofence event: $event")
                
                // Record the geofence event
                val result = locationRepository.recordGeofenceEvent(event)
                
                result.onSuccess { response ->
                    Timber.d("Successfully recorded geofence event: ${response.message}")
                    
                    // If it's an enter event, load nearby advertisements
                    if (event.eventType == "enter") {
                        loadNearbyAdvertisements(event)
                    }
                    
                    // Show notification for enter events
                    if (event.eventType == "enter") {
                        showGeofenceNotification(context, event)
                    }
                    
                }.onFailure { exception ->
                    Timber.e(exception, "Failed to record geofence event")
                }
                
            } catch (e: Exception) {
                Timber.e(e, "Error processing geofence event")
            }
        }
    }
    
    private fun loadNearbyAdvertisements(event: GeofenceEvent) {
        CoroutineScope(Dispatchers.IO).launch {
            try {
                val result = locationRepository.getNearbyAdvertisements(
                    latitude = event.latitude,
                    longitude = event.longitude,
                    radius = 1609, // 1 mile
                    userFingerprint = event.userFingerprint
                )
                
                result.onSuccess { response ->
                    Timber.d("Loaded ${response.advertisements.size} nearby advertisements for store ${event.storeId}")
                    
                    // Show notification with advertisements
                    if (response.advertisements.isNotEmpty()) {
                        showAdvertisementNotification(response.advertisements.first())
                    }
                    
                }.onFailure { exception ->
                    Timber.e(exception, "Failed to load nearby advertisements")
                }
                
            } catch (e: Exception) {
                Timber.e(e, "Error loading nearby advertisements")
            }
        }
    }
    
    private fun showGeofenceNotification(context: Context, event: GeofenceEvent) {
        val store = geofenceManager.getStoreById(event.storeId)
        val storeName = store?.storeName ?: "Nearby Store"
        
        val notificationTitle = "You're near $storeName!"
        val notificationText = "Check out their latest offers and promotions."
        
        // In a real app, you would use a proper notification system
        // This is a simplified version
        Timber.d("Showing notification: $notificationTitle - $notificationText")
    }
    
    private fun showAdvertisementNotification(advertisement: com.geofence.ads.data.model.NearbyAdvertisement) {
        val notificationTitle = advertisement.title
        val notificationText = "${advertisement.storeName}: ${advertisement.description.take(100)}..."
        
        Timber.d("Showing advertisement notification: $notificationTitle - $notificationText")
    }
    
    private fun generateUserFingerprint(context: Context): String {
        // In a real app, this would come from UserPreferences
        // For now, generate a simple fingerprint
        return "user_${System.currentTimeMillis()}_${context.packageName.hashCode()}"
    }
}