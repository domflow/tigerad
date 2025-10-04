package com.geofence.ads.location

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.Service
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.IBinder
import androidx.core.app.NotificationCompat
import com.geofence.ads.GeofenceAdsApp
import com.geofence.ads.R
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.*
import timber.log.Timber
import javax.inject.Inject

@AndroidEntryPoint
class GeofenceForegroundService : Service() {
    
    @Inject
    lateinit var geofenceManager: GeofenceManager
    
    @Inject
    lateinit var locationService: GeofenceLocationService
    
    private val serviceScope = CoroutineScope(Dispatchers.IO + SupervisorJob())
    private var locationJob: Job? = null
    
    override fun onCreate() {
        super.onCreate()
        Timber.d("GeofenceForegroundService created")
        startForegroundService()
    }
    
    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        Timber.d("GeofenceForegroundService started")
        
        when (intent?.action) {
            ACTION_START -> startLocationMonitoring()
            ACTION_STOP -> stopLocationMonitoring()
            else -> startLocationMonitoring()
        }
        
        return START_STICKY
    }
    
    override fun onBind(intent: Intent?): IBinder? = null
    
    override fun onDestroy() {
        super.onDestroy()
        Timber.d("GeofenceForegroundService destroyed")
        stopLocationMonitoring()
        serviceScope.cancel()
    }
    
    private fun startForegroundService() {
        val notification = createNotification()
        startForeground(NOTIFICATION_ID, notification)
    }
    
    private fun createNotification(): Notification {
        val channelId = GeofenceAdsApp.CHANNEL_ID_LOCATION
        
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                channelId,
                "Location Service",
                NotificationManager.IMPORTANCE_LOW
            ).apply {
                description = "Monitoring location for geofence detection"
                setShowBadge(false)
            }
            
            val notificationManager = getSystemService(NotificationManager::class.java)
            notificationManager.createNotificationChannel(channel)
        }
        
        return NotificationCompat.Builder(this, channelId)
            .setContentTitle("Geofence Ads Active")
            .setContentText("Monitoring location for nearby advertisements")
            .setSmallIcon(R.drawable.ic_notification)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .setOngoing(true)
            .setSilent(true)
            .build()
    }
    
    private fun startLocationMonitoring() {
        if (locationJob?.isActive == true) {
            Timber.d("Location monitoring already active")
            return
        }
        
        locationJob = serviceScope.launch {
            try {
                Timber.d("Starting location monitoring")
                
                locationService.getCurrentLocation()
                    .collect { location ->
                        location?.let { loc ->
                            if (locationService.isLocationValid(loc)) {
                                Timber.d("Valid location received: ${loc.latitude}, ${loc.longitude}")
                                
                                // Update location in geofence manager
                                // You could also trigger advertisement loading here
                                
                            } else {
                                Timber.w("Invalid location received")
                            }
                        }
                    }
                
            } catch (e: Exception) {
                Timber.e(e, "Error in location monitoring")
            }
        }
    }
    
    private fun stopLocationMonitoring() {
        locationJob?.cancel()
        locationJob = null
        Timber.d("Location monitoring stopped")
    }
    
    companion object {
        private const val NOTIFICATION_ID = 1001
        
        const val ACTION_START = "com.geofence.ads.action.START_LOCATION_MONITORING"
        const val ACTION_STOP = "com.geofence.ads.action.STOP_LOCATION_MONITORING"
        
        fun startService(context: Context) {
            val intent = Intent(context, GeofenceForegroundService::class.java).apply {
                action = ACTION_START
            }
            
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(intent)
            } else {
                context.startService(intent)
            }
        }
        
        fun stopService(context: Context) {
            val intent = Intent(context, GeofenceForegroundService::class.java).apply {
                action = ACTION_STOP
            }
            context.startService(intent)
        }
    }
}