package com.geofence.ads.data.remote

import com.geofence.ads.data.model.*
import retrofit2.Response
import retrofit2.http.*

interface ApiService {

    // Authentication
    @POST("register-store-owner")
    suspend fun registerStoreOwner(@Body request: StoreOwnerRegistrationRequest): Response<StoreOwnerRegistrationResponse>

    @POST("login")
    suspend fun login(@Body request: LoginRequest): Response<LoginResponse>

    // Stores
    @GET("stores")
    suspend fun getStores(): Response<StoresResponse>

    @GET("stores/{id}")
    suspend fun getStore(@Path("id") id: Int): Response<Store>

    @POST("stores")
    suspend fun createStore(@Body request: CreateStoreRequest): Response<CreateResponse>

    @PUT("stores/{id}")
    suspend fun updateStore(@Path("id") id: Int, @Body request: UpdateStoreRequest): Response<MessageResponse>

    // Advertisements
    @GET("advertisements")
    suspend fun getAdvertisements(
        @Query("page") page: Int = 1,
        @Query("limit") limit: Int = 20,
        @Query("status") status: String? = null
    ): Response<AdvertisementResponse>

    @POST("advertisements")
    suspend fun createAdvertisement(@Body request: CreateAdvertisementRequest): Response<CreateResponse>

    @DELETE("advertisements/{id}")
    suspend fun deleteAdvertisement(@Path("id") id: Int): Response<MessageResponse>

    // Nearby Advertisements (for regular users)
    @GET("nearby-ads")
    suspend fun getNearbyAdvertisements(
        @Query("latitude") latitude: Double,
        @Query("longitude") longitude: Double,
        @Query("radius") radius: Int = 1609,
        @Query("user_fingerprint") userFingerprint: String? = null
    ): Response<NearbyAdsResponse>

    // Location Tracking
    @POST("track-view")
    suspend fun trackView(@Body request: TrackViewRequest): Response<TrackViewResponse>

    @POST("geofence-events")
    suspend fun recordGeofenceEvent(@Body request: GeofenceEvent): Response<MessageResponse>

    // Credit Packages
    @GET("credit-packages")
    suspend fun getCreditPackages(): Response<CreditPackagesResponse>

    @POST("purchase-credits")
    suspend fun purchaseCredits(@Body request: PurchaseCreditsRequest): Response<PurchaseCreditsResponse>

    @POST("refund-credits")
    suspend fun refundCredits(
        @Body request: RefundCreditsRequest
    ): Response<RefundCreditsResponse>

    // Image Upload
    @Multipart
    @POST("upload-image")
    suspend fun uploadImage(
        @Part image: MultipartBody.Part,
        @Part("advertisement_id") advertisementId: Int
    ): Response<ImageUploadResponse>

    // User Profile
    @GET("profile")
    suspend fun getProfile(): Response<StoreOwner>

    companion object {
        const val BASE_URL = BuildConfig.API_BASE_URL
    }
}

// Response wrapper classes
data class StoresResponse(val stores: List<Store>)
data class CreateResponse(val message: String, @SerializedName("store_id") val storeId: Int? = null)
data class MessageResponse(val message: String)
data class CreditPackagesResponse(val packages: List<CreditPackage>)
data class TrackViewResponse(val message: String, @SerializedName("distance_meters") val distanceMeters: Double, @SerializedName("store_name") val storeName: String)
data class ImageUploadResponse(
    val message: String,
    @SerializedName("image_id") val imageId: Int,
    val filename: String,
    val url: String,
    val width: Int,
    val height: Int
)

data class RefundCreditsRequest(
    @SerializedName("store_id") val storeId: Int,
    @SerializedName("credits_to_refund") val creditsToRefund: Int,
    @SerializedName("refund_reason") val refundReason: String
)

data class RefundCreditsResponse(
    val success: Boolean,
    @SerializedName("transaction_id") val transactionId: Int,
    @SerializedName("credits_refunded") val creditsRefunded: Int,
    @SerializedName("refund_amount") val refundAmount: Double
)