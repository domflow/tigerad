package com.geofence.ads.data.model

import android.os.Parcelable
import kotlinx.parcelize.Parcelize
import com.google.gson.annotations.SerializedName

@Parcelize
data class Store(
    val id: Int,
    @SerializedName("store_name") val storeName: String,
    val address: String,
    val latitude: Double,
    val longitude: Double,
    @SerializedName("geofence_radius_meters") val geofenceRadiusMeters: Int,
    @SerializedName("trigger_radius_meters") val triggerRadiusMeters: Int,
    @SerializedName("is_active") val isActive: Boolean,
    @SerializedName("business_hours") val businessHours: String?,
    val phone: String?,
    val website: String?,
    val category: String?,
    @SerializedName("total_credits") val totalCredits: Int,
    @SerializedName("available_credits") val availableCredits: Int,
    @SerializedName("created_at") val createdAt: String,
    @SerializedName("updated_at") val updatedAt: String
) : Parcelable

data class CreateStoreRequest(
    @SerializedName("store_name") val storeName: String,
    val address: String,
    val latitude: Double,
    val longitude: Double,
    @SerializedName("geofence_radius_meters") val geofenceRadiusMeters: Int = 1609,
    @SerializedName("trigger_radius_meters") val triggerRadiusMeters: Int = 3,
    val phone: String? = null,
    val website: String? = null,
    val category: String? = null
)

data class UpdateStoreRequest(
    @SerializedName("store_name") val storeName: String? = null,
    val address: String? = null,
    val phone: String? = null,
    val website: String? = null,
    val category: String? = null,
    @SerializedName("is_active") val isActive: Boolean? = null
)