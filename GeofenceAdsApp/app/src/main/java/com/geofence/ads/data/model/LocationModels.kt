package com.geofence.ads.data.model

import android.os.Parcelable
import kotlinx.parcelize.Parcelize
import com.google.gson.annotations.SerializedName

@Parcelize
data class LocationData(
    val latitude: Double,
    val longitude: Double,
    val accuracy: Float,
    val altitude: Double,
    val speed: Float,
    val bearing: Float,
    val timestamp: Long,
    @SerializedName("is_mock_location") val isMockLocation: Boolean = false
) : Parcelable

@Parcelize
data class GeofenceEvent(
    @SerializedName("store_id") val storeId: Int,
    @SerializedName("user_fingerprint") val userFingerprint: String,
    @SerializedName("event_type") val eventType: String, // 'enter', 'exit', 'dwell'
    val latitude: Double,
    val longitude: Double,
    @SerializedName("distance_to_store_meters") val distanceToStoreMeters: Double,
    @SerializedName("trigger_time") val triggerTime: String
) : Parcelable

data class TrackViewRequest(
    @SerializedName("advertisement_id") val advertisementId: Int,
    @SerializedName("user_fingerprint") val userFingerprint: String,
    val latitude: Double,
    val longitude: Double
)

data class NearbyAdsRequest(
    val latitude: Double,
    val longitude: Double,
    val radius: Int = 1609, // 1 mile in meters
    @SerializedName("user_fingerprint") val userFingerprint: String? = null
)

data class NearbyAdsResponse(
    val advertisements: List<NearbyAdvertisement>,
    val count: Int
)