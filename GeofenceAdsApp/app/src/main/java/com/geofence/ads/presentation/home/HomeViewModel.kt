package com.geofence.ads.presentation.home

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.geofence.ads.data.local.UserPreferences
import com.geofence.ads.data.model.LocationData
import com.geofence.ads.data.model.NearbyAdvertisement
import com.geofence.ads.data.repository.LocationRepository
import com.geofence.ads.location.GeofenceLocationService
import dagger.hilt.android.lifecycle.HiltViewModel
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import timber.log.Timber
import javax.inject.Inject

data class HomeUiState(
    val isLoading: Boolean = false,
    val error: String? = null,
    val advertisements: List<NearbyAdvertisement> = emptyList(),
    val currentLocation: LocationData? = null,
    val isLocationUpdatesActive: Boolean = false
)

@HiltViewModel
class HomeViewModel @Inject constructor(
    private val locationRepository: LocationRepository,
    private val locationService: GeofenceLocationService,
    private val userPreferences: UserPreferences,
    @ApplicationContext private val context: Context
) : ViewModel() {
    
    private val _uiState = MutableStateFlow(HomeUiState())
    val uiState: StateFlow<HomeUiState> = _uiState.asStateFlow()
    
    private val _locationFlow = MutableStateFlow<LocationData?>(null)
    
    init {
        observeLocationUpdates()
        loadUserFingerprint()
    }
    
    private fun observeLocationUpdates() {
        viewModelScope.launch {
            locationService.getCurrentLocation()
                .catch { e ->
                    Timber.e(e, "Error observing location updates")
                    _uiState.update { it.copy(error = "Location update error: ${e.message}") }
                }
                .collect { location ->
                    location?.let { loc ->
                        if (locationService.isLocationValid(loc)) {
                            val locationData = locationService.createLocationData(loc)
                            _locationFlow.value = locationData
                            _uiState.update { it.copy(currentLocation = locationData, isLocationUpdatesActive = true) }
                            
                            // Load nearby advertisements when location updates
                            loadNearbyAdvertisements()
                        }
                    }
                }
        }
    }
    
    private fun loadUserFingerprint() {
        viewModelScope.launch {
            val fingerprint = userPreferences.userFingerprint.first()
            if (fingerprint == null) {
                // Create new fingerprint for anonymous user
                val deviceInfo = mapOf(
                    "user_agent" to "Android_${android.os.Build.VERSION.SDK_INT}",
                    "timestamp" to System.currentTimeMillis().toString()
                )
                val newFingerprint = "${deviceInfo["user_agent"]}_${System.currentTimeMillis()}"
                userPreferences.saveUserFingerprint(newFingerprint)
                Timber.d("Created new user fingerprint: $newFingerprint")
            }
        }
    }
    
    fun startLocationUpdates() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLocationUpdatesActive = true) }
        }
    }
    
    fun stopLocationUpdates() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLocationUpdatesActive = false) }
        }
    }
    
    fun loadNearbyAdvertisements() {
        viewModelScope.launch {
            val currentLocation = _locationFlow.value
            if (currentLocation == null) {
                _uiState.update { it.copy(error = "Location not available") }
                return@launch
            }
            
            _uiState.update { it.copy(isLoading = true, error = null) }
            
            try {
                val userFingerprint = userPreferences.userFingerprint.first()
                val result = locationRepository.getNearbyAdvertisements(
                    latitude = currentLocation.latitude,
                    longitude = currentLocation.longitude,
                    radius = 1609, // 1 mile
                    userFingerprint = userFingerprint
                )
                
                result.onSuccess { response ->
                    _uiState.update { 
                        it.copy(
                            advertisements = response.advertisements,
                            isLoading = false,
                            error = null
                        )
                    }
                    Timber.d("Loaded ${response.advertisements.size} nearby advertisements")
                }.onFailure { exception ->
                    _uiState.update { 
                        it.copy(
                            isLoading = false,
                            error = exception.message ?: "Failed to load advertisements"
                        )
                    }
                    Timber.e(exception, "Failed to load nearby advertisements")
                }
                
            } catch (e: Exception) {
                _uiState.update { 
                    it.copy(
                        isLoading = false,
                        error = "Error loading advertisements: ${e.message}"
                    )
                }
                Timber.e(e, "Error loading nearby advertisements")
            }
        }
    }
    
    fun onAdClicked(advertisement: NearbyAdvertisement) {
        viewModelScope.launch {
            val currentLocation = _locationFlow.value ?: return@launch
            val userFingerprint = userPreferences.userFingerprint.first() ?: return@launch
            
            try {
                val result = locationRepository.trackView(
                    advertisementId = advertisement.id,
                    userFingerprint = userFingerprint,
                    latitude = currentLocation.latitude,
                    longitude = currentLocation.longitude
                )
                
                result.onSuccess { response ->
                    Timber.d("Tracked view for advertisement ${advertisement.id}: ${response.message}")
                }.onFailure { exception ->
                    Timber.e(exception, "Failed to track view for advertisement ${advertisement.id}")
                }
                
            } catch (e: Exception) {
                Timber.e(e, "Error tracking view for advertisement ${advertisement.id}")
            }
        }
    }
    
    fun dismissError() {
        _uiState.update { it.copy(error = null) }
    }
    
    fun retryLoading() {
        loadNearbyAdvertisements()
    }
}