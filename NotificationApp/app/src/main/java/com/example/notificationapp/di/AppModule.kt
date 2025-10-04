package com.example.notificationapp.di

import android.content.Context
import com.example.notificationapp.data.remote.ApiService
import com.example.notificationapp.data.remote.RetrofitClient
import com.example.notificationapp.data.remote.TokenProvider
import com.example.notificationapp.data.repository.AuthRepository
import com.example.notificationapp.data.repository.NotificationRepository
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
    fun provideTokenProvider(): TokenProvider = TokenProvider()

    @Provides
    @Singleton
    fun provideRetrofitClient(
        tokenProvider: TokenProvider
    ): RetrofitClient = RetrofitClient(AuthInterceptor(tokenProvider))

    @Provides
    @Singleton
    fun provideApiService(retrofitClient: RetrofitClient): ApiService = retrofitClient.apiService

    @Provides
    @Singleton
    fun provideAuthRepository(
        apiService: ApiService,
        tokenProvider: TokenProvider
    ): AuthRepository = AuthRepository(apiService, tokenProvider)

    @Provides
    @Singleton
    fun provideNotificationRepository(
        apiService: ApiService
    ): NotificationRepository = NotificationRepository(apiService)
}