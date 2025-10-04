package com.geofence.ads.data.local

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import javax.inject.Inject
import javax.inject.Singleton

private val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "user_preferences")

@Singleton
class UserPreferences @Inject constructor(
    private val context: Context
) {
    private val TOKEN_KEY = stringPreferencesKey("auth_token")
    private val USER_ID_KEY = stringPreferencesKey("user_id")
    private val USER_TYPE_KEY = stringPreferencesKey("user_type")
    private val USER_FINGERPRINT_KEY = stringPreferencesKey("user_fingerprint")
    private val LAST_LOCATION_KEY = stringPreferencesKey("last_location")
    private val NOTIFICATIONS_ENABLED_KEY = stringPreferencesKey("notifications_enabled")

    // Authentication
    val token: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[TOKEN_KEY]
    }

    val userId: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[USER_ID_KEY]
    }

    val userType: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[USER_TYPE_KEY]
    }

    suspend fun saveToken(token: String) {
        context.dataStore.edit { preferences ->
            preferences[TOKEN_KEY] = token
        }
    }

    suspend fun saveUserInfo(userId: String, userType: String) {
        context.dataStore.edit { preferences ->
            preferences[USER_ID_KEY] = userId
            preferences[USER_TYPE_KEY] = userType
        }
    }

    suspend fun clearAuthData() {
        context.dataStore.edit { preferences ->
            preferences.remove(TOKEN_KEY)
            preferences.remove(USER_ID_KEY)
            preferences.remove(USER_TYPE_KEY)
        }
    }

    fun getToken(): String? {
        return context.dataStore.data.map { preferences ->
            preferences[TOKEN_KEY]
        }.let { flow ->
            var token: String? = null
            flow.collect { token = it }
            token
        }
    }

    // User Fingerprint (for anonymous users)
    val userFingerprint: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[USER_FINGERPRINT_KEY]
    }

    suspend fun saveUserFingerprint(fingerprint: String) {
        context.dataStore.edit { preferences ->
            preferences[USER_FINGERPRINT_KEY] = fingerprint
        }
    }

    // Location Data
    val lastLocation: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[LAST_LOCATION_KEY]
    }

    suspend fun saveLastLocation(locationJson: String) {
        context.dataStore.edit { preferences ->
            preferences[LAST_LOCATION_KEY] = locationJson
        }
    }

    // Notification Settings
    val notificationsEnabled: Flow<Boolean> = context.dataStore.data.map { preferences ->
        preferences[NOTIFICATIONS_ENABLED_KEY] ?: "true"
    }.map { it.toBoolean() }

    suspend fun setNotificationsEnabled(enabled: Boolean) {
        context.dataStore.edit { preferences ->
            preferences[NOTIFICATIONS_ENABLED_KEY] = enabled.toString()
        }
    }

    // Clear all data
    suspend fun clearAll() {
        context.dataStore.edit { preferences ->
            preferences.clear()
        }
    }

    companion object {
        const val USER_TYPE_REGULAR = "regular"
        const val USER_TYPE_STORE_OWNER = "store_owner"
    }
}