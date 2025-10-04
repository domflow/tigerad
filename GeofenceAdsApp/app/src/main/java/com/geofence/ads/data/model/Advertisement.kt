package com.geofence.ads.data.model

import android.os.Parcelable
import kotlinx.parcelize.Parcelize
import com.google.gson.annotations.SerializedName

@Parcelize
data class Advertisement(
    val id: Int,
    @SerializedName("store_id") val storeId: Int,
    val title: String,
    val description: String,
    val images: List<AdvertisementImage>,
    @SerializedName("call_to_action") val callToAction: String?,
    @SerializedName("link_url") val linkUrl: String?,
    @SerializedName("credits_purchased") val creditsPurchased: Int,
    @SerializedName("views_allocated") val viewsAllocated: Int,
    @SerializedName("views_used") val viewsUsed: Int,
    @SerializedName("views_remaining") val viewsRemaining: Int,
    val status: String,
    @SerializedName("start_date") val startDate: String?,
    @SerializedName("end_date") val endDate: String?,
    @SerializedName("last_sent_at") val lastSentAt: String?,
    @SerializedName("created_at") val createdAt: String,
    @SerializedName("updated_at") val updatedAt: String,
    @SerializedName("store_name") val storeName: String,
    val latitude: Double,
    val longitude: Double
) : Parcelable

@Parcelize
data class AdvertisementImage(
    val url: String,
    @SerializedName("original_filename") val originalFilename: String,
    val width: Int,
    val height: Int
) : Parcelable

data class CreateAdvertisementRequest(
    @SerializedName("store_id") val storeId: Int,
    val title: String,
    val description: String,
    val images: List<String>, // Base64 encoded images or URLs
    @SerializedName("call_to_action") val callToAction: String? = null,
    @SerializedName("link_url") val linkUrl: String? = null,
    val credits: Int,
    @SerializedName("start_date") val startDate: String? = null,
    @SerializedName("end_date") val endDate: String? = null
)

data class AdvertisementResponse(
    val advertisements: List<Advertisement>,
    val total: Int,
    val page: Int,
    val limit: Int,
    val pages: Int
)

data class NearbyAdvertisement(
    val id: Int,
    val title: String,
    val description: String,
    val images: List<AdvertisementImage>,
    @SerializedName("call_to_action") val callToAction: String?,
    @SerializedName("link_url") val linkUrl: String?,
    @SerializedName("store_name") val storeName: String,
    @SerializedName("distance_meters") val distanceMeters: Double,
    val latitude: Double,
    val longitude: Double,
    val category: String?,
    @SerializedName("views_remaining") val viewsRemaining: Int
)