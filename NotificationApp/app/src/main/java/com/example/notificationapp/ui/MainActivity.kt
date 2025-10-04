package com.example.notificationapp.ui

import android.content.Intent
import android.os.Bundle
import android.view.Menu
import android.view.MenuItem
import android.widget.Toast
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import com.example.notificationapp.R
import com.example.notificationapp.databinding.ActivityMainBinding
import com.example.notificationapp.worker.NotificationWorker
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.launch

@AndroidEntryPoint
class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private val viewModel: NotificationViewModel by viewModels()
    private lateinit var notificationAdapter: NotificationAdapter

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        setupUI()
        observeViewModel()
        loadNotifications()
    }

    private fun setupUI() {
        setSupportActionBar(binding.toolbar)
        
        // Setup RecyclerView
        notificationAdapter = NotificationAdapter(
            onNotificationClick = { notification ->
                markNotificationAsRead(notification.id)
            },
            onNotificationDelete = { notification ->
                deleteNotification(notification.id)
            }
        )

        binding.recyclerView.apply {
            layoutManager = LinearLayoutManager(this@MainActivity)
            adapter = notificationAdapter
        }

        // Setup swipe refresh
        binding.swipeRefresh.setOnRefreshListener {
            loadNotifications()
        }

        // Setup FAB
        binding.fab.setOnClickListener {
            showCreateNotificationDialog()
        }
    }

    private fun observeViewModel() {
        viewModel.notifications.observe(this) { result ->
            result.onSuccess { notificationResponse ->
                notificationAdapter.submitList(notificationResponse.notifications)
                binding.tvEmpty.visibility = 
                    if (notificationResponse.notifications.isEmpty()) android.view.View.VISIBLE 
                    else android.view.View.GONE
            }.onFailure { exception ->
                Toast.makeText(
                    this,
                    "Failed to load notifications: ${exception.message}",
                    Toast.LENGTH_LONG
                ).show()
            }
            binding.swipeRefresh.isRefreshing = false
        }

        viewModel.loading.observe(this) { isLoading ->
            binding.progressBar.visibility = 
                if (isLoading) android.view.View.VISIBLE 
                else android.view.View.GONE
        }
    }

    private fun loadNotifications() {
        lifecycleScope.launch {
            viewModel.loadNotifications()
        }
    }

    private fun markNotificationAsRead(notificationId: Int) {
        lifecycleScope.launch {
            viewModel.markAsRead(notificationId).observe(this@MainActivity) { result ->
                result.onSuccess { message ->
                    // Refresh notifications
                    loadNotifications()
                }.onFailure { exception ->
                    Toast.makeText(
                        this@MainActivity,
                        "Failed to mark as read: ${exception.message}",
                        Toast.LENGTH_LONG
                    ).show()
                }
            }
        }
    }

    private fun deleteNotification(notificationId: Int) {
        lifecycleScope.launch {
            viewModel.deleteNotification(notificationId).observe(this@MainActivity) { result ->
                result.onSuccess { message ->
                    // Refresh notifications
                    loadNotifications()
                }.onFailure { exception ->
                    Toast.makeText(
                        this@MainActivity,
                        "Failed to delete notification: ${exception.message}",
                        Toast.LENGTH_LONG
                    ).show()
                }
            }
        }
    }

    private fun showCreateNotificationDialog() {
        val dialog = CreateNotificationDialog { title, message ->
            createNotification(title, message)
        }
        dialog.show(supportFragmentManager, "CreateNotificationDialog")
    }

    private fun createNotification(title: String, message: String) {
        lifecycleScope.launch {
            viewModel.createNotification(title, message).observe(this@MainActivity) { result ->
                result.onSuccess { notificationId ->
                    Toast.makeText(
                        this@MainActivity,
                        "Notification created successfully",
                        Toast.LENGTH_SHORT
                    ).show()
                    loadNotifications()
                }.onFailure { exception ->
                    Toast.makeText(
                        this@MainActivity,
                        "Failed to create notification: ${exception.message}",
                        Toast.LENGTH_LONG
                    ).show()
                }
            }
        }
    }

    override fun onCreateOptionsMenu(menu: Menu): Boolean {
        menuInflater.inflate(R.menu.main_menu, menu)
        return true
    }

    override fun onOptionsItemSelected(item: MenuItem): Boolean {
        return when (item.itemId) {
            R.id.action_refresh -> {
                loadNotifications()
                true
            }
            R.id.action_logout -> {
                logout()
                true
            }
            else -> super.onOptionsItemSelected(item)
        }
    }

    private fun logout() {
        viewModel.logout()
        NotificationWorker.cancel(this)
        
        // Navigate to login
        startActivity(Intent(this, LoginActivity::class.java))
        finish()
    }
}