package com.geofence.ads.data.repository

import com.geofence.ads.data.model.*
import com.geofence.ads.data.remote.ApiService
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class CreditRepository @Inject constructor(
    private val apiService: ApiService
) {
    
    suspend fun getCreditPackages(): Result<List<CreditPackage>> {
        return try {
            val response = apiService.getCreditPackages()
            if (response.isSuccessful) {
                response.body()?.let { packagesResponse ->
                    Result.success(packagesResponse.packages)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to get credit packages: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun purchaseCredits(request: PurchaseCreditsRequest): Result<PurchaseCreditsResponse> {
        return try {
            val response = apiService.purchaseCredits(request)
            if (response.isSuccessful) {
                response.body()?.let { purchaseResponse ->
                    Result.success(purchaseResponse)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to purchase credits: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun refundCredits(storeId: Int, creditsToRefund: Int, refundReason: String): Result<RefundCreditsResponse> {
        return try {
            val response = apiService.refundCredits(RefundCreditsRequest(storeId, creditsToRefund, refundReason))
            if (response.isSuccessful) {
                response.body()?.let { refundResponse ->
                    Result.success(refundResponse)
                } ?: Result.failure(Exception("Empty response body"))
            } else {
                Result.failure(Exception("Failed to refund credits: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}