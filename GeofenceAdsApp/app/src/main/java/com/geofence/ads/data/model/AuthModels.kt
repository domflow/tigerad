package com.geofence.ads.data.model

import com.google.gson.annotations.SerializedName

data class StoreOwnerRegistrationRequest(
    @SerializedName("business_name") val businessName: String,
    @SerializedName("owner_name") val ownerName: String,
    val email: String,
    val phone: String,
    val password: String
)

data class StoreOwnerRegistrationResponse(
    val message: String,
    @SerializedName("owner_id") val ownerId: Int,
    val token: String,
    @SerializedName("api_key") val apiKey: String
)

data class LoginRequest(
    val email: String,
    val password: String
)

data class LoginResponse(
    val message: String,
    val token: String,
    @SerializedName("owner_id") val ownerId: Int,
    @SerializedName("verification_status") val verificationStatus: String
)

data class StoreOwner(
    val id: Int,
    @SerializedName("business_name") val businessName: String,
    @SerializedName("owner_name") val ownerName: String,
    val email: String,
    val phone: String,
    @SerializedName("verification_status") val verificationStatus: String,
    @SerializedName("created_at") val createdAt: String,
    @SerializedName("updated_at") val updatedAt: String
)