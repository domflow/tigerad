package com.geofence.ads.presentation.navigation

import androidx.compose.runtime.Composable
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.geofence.ads.presentation.home.HomeScreen
import com.geofence.ads.presentation.store.StoreOwnerScreen
import com.geofence.ads.presentation.store.CreateStoreScreen
import com.geofence.ads.presentation.store.CreateAdScreen
import com.geofence.ads.presentation.store.PaymentScreen
import com.geofence.ads.presentation.auth.LoginScreen
import com.geofence.ads.presentation.settings.SettingsScreen

@Composable
fun GeofenceAdsNavHost(
    navController: NavHostController = rememberNavController()
) {
    NavHost(
        navController = navController,
        startDestination = Screen.Home.route
    ) {
        // Home Screen (Regular Users)
        composable(Screen.Home.route) {
            HomeScreen(
                onNavigateToStoreOwner = { navController.navigate(Screen.StoreOwner.route) },
                onNavigateToSettings = { navController.navigate(Screen.Settings.route) }
            )
        }
        
        // Store Owner Screens
        composable(Screen.StoreOwner.route) {
            StoreOwnerScreen(
                onNavigateBack = { navController.popBackStack() },
                onNavigateToCreateStore = { navController.navigate(Screen.CreateStore.route) },
                onNavigateToCreateAd = { storeId -> navController.navigate(Screen.CreateAd.createRoute(storeId)) },
                onNavigateToPayment = { navController.navigate(Screen.Payment.route) }
            )
        }
        
        composable(Screen.CreateStore.route) {
            CreateStoreScreen(
                onNavigateBack = { navController.popBackStack() },
                onStoreCreated = { navController.popBackStack() }
            )
        }
        
        composable(
            route = Screen.CreateAd.route,
            arguments = Screen.CreateAd.arguments
        ) { backStackEntry ->
            val storeId = backStackEntry.arguments?.getInt("storeId") ?: 0
            CreateAdScreen(
                storeId = storeId,
                onNavigateBack = { navController.popBackStack() },
                onAdCreated = { navController.popBackStack() }
            )
        }
        
        composable(Screen.Payment.route) {
            PaymentScreen(
                onNavigateBack = { navController.popBackStack() },
                onPaymentCompleted = { navController.popBackStack() }
            )
        }
        
        // Authentication
        composable(Screen.Login.route) {
            LoginScreen(
                onLoginSuccess = { navController.popBackStack() },
                onNavigateBack = { navController.popBackStack() }
            )
        }
        
        // Settings
        composable(Screen.Settings.route) {
            SettingsScreen(
                onNavigateBack = { navController.popBackStack() }
            )
        }
    }
}

sealed class Screen(val route: String) {
    object Home : Screen("home")
    object StoreOwner : Screen("store_owner")
    object CreateStore : Screen("create_store")
    object CreateAd : Screen("create_ad/{storeId}") {
        val arguments = listOf(
            androidx.navigation.navArgument("storeId") { type = androidx.navigation.NavType.IntType }
        )
        fun createRoute(storeId: Int) = "create_ad/$storeId"
    }
    object Payment : Screen("payment")
    object Login : Screen("login")
    object Settings : Screen("settings")
}