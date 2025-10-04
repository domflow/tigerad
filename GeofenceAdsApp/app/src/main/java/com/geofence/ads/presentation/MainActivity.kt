package com.geofence.ads.presentation

import android.Manifest
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import com.geofence.ads.presentation.navigation.GeofenceAdsNavHost
import com.geofence.ads.presentation.theme.GeofenceAdsTheme
import com.google.accompanist.permissions.ExperimentalPermissionsApi
import com.google.accompanist.permissions.isGranted
import com.google.accompanist.permissions.rememberPermissionState
import com.google.accompanist.permissions.shouldShowRationale
import dagger.hilt.android.AndroidEntryPoint
import timber.log.Timber

@AndroidEntryPoint
class MainActivity : ComponentActivity() {

    private val locationPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { permissions ->
        val allGranted = permissions.all { it.value }
        Timber.d("Location permissions granted: $allGranted")
        if (!allGranted) {
            // Handle permission denied
            Timber.w("Location permissions denied by user")
        }
    }

    private val cameraPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { isGranted ->
        Timber.d("Camera permission granted: $isGranted")
        if (!isGranted) {
            Timber.w("Camera permission denied by user")
        }
    }

    private val notificationPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { isGranted ->
        Timber.d("Notification permission granted: $isGranted")
        if (!isGranted) {
            Timber.w("Notification permission denied by user")
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        installSplashScreen()
        super.onCreate(savedInstanceState)
        
        // Request permissions
        requestPermissions()
        
        setContent {
            GeofenceAdsTheme {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    GeofenceAdsNavHost()
                }
            }
        }
    }

    private fun requestPermissions() {
        // Request location permissions
        locationPermissionLauncher.launch(
            arrayOf(
                Manifest.permission.ACCESS_FINE_LOCATION,
                Manifest.permission.ACCESS_COARSE_LOCATION,
                Manifest.permission.ACCESS_BACKGROUND_LOCATION
            )
        )
        
        // Request camera permission
        cameraPermissionLauncher.launch(Manifest.permission.CAMERA)
        
        // Request notification permission (Android 13+)
        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.TIRAMISU) {
            notificationPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
        }
    }
}

@OptIn(ExperimentalPermissionsApi::class)
@Composable
fun PermissionRequestDialog(
    permission: String,
    permissionText: String,
    onPermissionResult: (Boolean) -> Unit
) {
    val permissionState = rememberPermissionState(permission)
    
    if (!permissionState.status.isGranted && permissionState.status.shouldShowRationale) {
        AlertDialog(
            onDismissRequest = { onPermissionResult(false) },
            title = { Text("Permission Required") },
            text = { Text(permissionText) },
            confirmButton = {
                Button(onClick = { permissionState.launchPermissionRequest() }) {
                    Text("Grant Permission")
                }
            },
            dismissButton = {
                TextButton(onClick = { onPermissionResult(false) }) {
                    Text("Cancel")
                }
            }
        )
    }
}

fun openAppSettings(context: android.content.Context) {
    val intent = Intent(android.provider.Settings.ACTION_APPLICATION_DETAILS_SETTINGS).apply {
        data = Uri.fromParts("package", context.packageName, null)
        flags = Intent.FLAG_ACTIVITY_NEW_TASK
    }
    context.startActivity(intent)
}