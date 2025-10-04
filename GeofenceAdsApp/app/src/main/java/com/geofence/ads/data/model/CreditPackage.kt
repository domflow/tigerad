package com.geofence.ads.data.model

import android.os.Parcelable
import kotlinx.parcelize.Parcelize
import com.google.gson.annotations.SerializedName

@Parcelize
data class CreditPackage(
    val id: Int,
    val name: String,
    val price: Double,
    val credits: Int,
    @SerializedName("views_per_credit") val viewsPerCredit: Int,
    @SerializedName("total_views") val totalViews: Int,
    @SerializedName("is_active") val isActive: Boolean,
    @SerializedName("sort_order") val sortOrder: Int
) : Parcelable

data class PurchaseCreditsRequest(
    @SerializedName("store_id") val storeId: Int,
    @SerializedName("package_id") val packageId: Int,
    @SerializedName("payment_method") val paymentMethod: String, // 'stripe' or 'google_wallet'
    @SerializedName("payment_token") val paymentToken: String
)

data class PurchaseCreditsResponse(
    val success: Boolean,
    @SerializedName("transaction_id") val transactionId: Int,
    @SerializedName("credits_purchased") val creditsPurchased: Int,
    @SerializedName("amount_paid") val amountPaid: Double
)

data class CreditBalance(
    @SerializedName("total_credits") val totalCredits: Int,
    @SerializedName("available_credits") val availableCredits: Int,
    @SerializedName("used_credits") val usedCredits: Int,
    @SerializedName("pending_refund_credits") val pendingRefundCredits: Int
)

data class CreditTransaction(
    val id: Int,
    @SerializedName("store_id") val storeId: Int,
    @SerializedName("transaction_type") val transactionType: String, // 'purchase', 'refund', 'usage', 'expiry'
    val credits: Int,
    val amount: Double,
    @SerializedName("payment_method") val paymentMethod: String?,
    @SerializedName("payment_reference") val paymentReference: String?,
    val status: String,
    @SerializedName("refund_reason") val refundReason: String?,
    @SerializedName("created_at") val createdAt: String
)