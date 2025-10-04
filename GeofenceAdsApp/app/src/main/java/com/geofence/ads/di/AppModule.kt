package com.geofence.ads.di

import android.content.Context
import com.geofence.ads.BuildConfig
import com.geofence.ads.data.local.UserPreferences
import com.geofence.ads.data.remote.ApiService
import com.geofence.ads.data.remote.AuthInterceptor
import com.geofence.ads.data.remote.RetrofitClient
import com.geofence.ads.data.repository.*
import com.geofence.ads.location.GeofenceLocationService
import com.geofence.ads.location.GeofenceManager
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.FusedLocationProviderClient
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object AppModule {

    @Provides
    @Singleton
    fun provideContext(@ApplicationContext context: Context): Context = context

    @Provides
    @Singleton
    fun provideUserPreferences(@ApplicationContext context: Context): UserPreferences = UserPreferences(context)

    @Provides
    @Singleton
    fun provideAuthInterceptor(userPreferences: UserPreferences): AuthInterceptor = AuthInterceptor(userPreferences)

    @Provides
    @Singleton
    fun provideRetrofitClient(authInterceptor: AuthInterceptor, @ApplicationContext context: Context): RetrofitClient = 
        RetrofitClient(authInterceptor, context)

    @Provides
    @Singleton
    fun provideApiService(retrofitClient: RetrofitClient): ApiService = retrofitClient.apiService

    @Provides
    @Singleton
    fun provideAuthRepository(apiService: ApiService, userPreferences: UserPreferences): AuthRepository = 
        AuthRepository(apiService, userPreferences)

    @Provides
    @Singleton
    fun provideStoreRepository(apiService: ApiService): StoreRepository = 
        StoreRepository(apiService)

    @Provides
    @Singleton
    fun provideAdvertisementRepository(apiService: ApiService): AdvertisementRepository = 
        AdvertisementRepository(apiService)

    @Provides
    @Singleton
    fun provideCreditRepository(apiService: ApiService): CreditRepository = 
        CreditRepository(apiService)

    @Provides
    @Singleton
    fun provideLocationRepository(apiService: ApiService): LocationRepository = 
        LocationRepository(apiService)

    @Provides
    @Singleton
    fun provideFusedLocationProviderClient(@ApplicationContext context: Context): FusedLocationProviderClient = 
        LocationServices.getFusedLocationProviderClient(context)

    @Provides
    @Singleton
    fun provideGeofencingClient(@ApplicationContext context: Context): com.google.android.gms.location.GeofencingClient = 
        LocationServices.getGeofencingClient(context)

    @Provides
    @Singleton
    fun provideGeofenceLocationService(fusedLocationProviderClient: FusedLocationProviderClient): GeofenceLocationService = 
        GeofenceLocationService(fusedLocationProviderClient)

    @Provides
    @Singleton
    fun provideGeofenceManager(
        @ApplicationContext context: Context,
        geofencingClient: com.google.android.gms.location.GeofencingClient,
        locationService: GeofenceLocationService,
        userPreferences: UserPreferences
    ): GeofenceManager = GeofenceManager(context, geofencingClient, locationService, userPreferences)
}