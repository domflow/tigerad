package com.geofence.ads

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.os.Build
import androidx.hilt.work.HiltWorkerFactory
import androidx.work.Configuration
import com.geofence.ads.location.GeofenceManager
import dagger.hilt.android.HiltAndroidApp
import timber.log.Timber
import javax.inject.Inject

@HiltAndroidApp
class GeofenceAdsApp : Application(), Configuration.Provider {

    @Inject
    lateinit var workerFactory: HiltWorkerFactory

    @Inject
    lateinit var geofenceManager: GeofenceManager

    override fun onCreate() {
        super.onCreate()
        
        // Initialize Timber for logging
        if (BuildConfig.DEBUG) {
            Timber.plant(Timber.DebugTree())
        }
        
        // Create notification channels
        createNotificationChannels()
        
        // Initialize geofence monitoring
        geofenceManager.initializeGeofenceMonitoring()
        
        Timber.d("GeofenceAdsApp initialized")
    }

    override fun getWorkManagerConfiguration(): Configuration {
        return Configuration.Builder()
            .setWorkerFactory(workerFactory)
            .setMinimumLoggingLevel(if (BuildConfig.DEBUG) android.util.Log.DEBUG else android.util.Log.ERROR)
            .build()
    }

    private fun createNotificationChannels() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            // Advertisement notification channel
            val adChannel = NotificationChannel(
                CHANNEL_ID_ADVERTISEMENTS,
                "Advertisements",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "Notifications for nearby advertisements"
                enableVibration(true)
                enableLights(true)
                setShowBadge(true)
            }

            // Location service notification channel
            val locationChannel = NotificationChannel(
                CHANNEL_ID_LOCATION,
                "Location Service",
                NotificationManager.IMPORTANCE_LOW
            ).apply {
                description = "Background location monitoring for geofence detection"
                setShowBadge(false)
            }

            // Payment notification channel
            val paymentChannel = NotificationChannel(
                CHANNEL_ID_PAYMENT,
                "Payment Updates",
                NotificationManager.IMPORTANCE_DEFAULT
            ).apply {
                description = "Payment confirmations and updates"
                enableVibration(true)
            }

            val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            notificationManager.createNotificationChannels(listOf(adChannel, locationChannel, paymentChannel))
        }
    }

    companion object {
        const val CHANNEL_ID_ADVERTISEMENTS = "advertisement_channel"
        const val CHANNEL_ID_LOCATION = "location_channel"
        const val CHANNEL_ID_PAYMENT = "payment_channel"
        
        const val REQUEST_CODE_LOCATION_PERMISSION = 1001
        const val REQUEST_CODE_CAMERA_PERMISSION = 1002
        const val REQUEST_CODE_NOTIFICATION_PERMISSION = 1003
    }
}