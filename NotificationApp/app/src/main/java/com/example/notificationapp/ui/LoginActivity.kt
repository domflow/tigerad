package com.example.notificationapp.ui

import android.content.Intent
import android.os.Bundle
import android.widget.Toast
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.example.notificationapp.R
import com.example.notificationapp.databinding.ActivityLoginBinding
import com.example.notificationapp.worker.NotificationWorker
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.launch

@AndroidEntryPoint
class LoginActivity : AppCompatActivity() {

    private lateinit var binding: ActivityLoginBinding
    private val viewModel: AuthViewModel by viewModels()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityLoginBinding.inflate(layoutInflater)
        setContentView(binding.root)

        setupUI()
        observeViewModel()
    }

    private fun setupUI() {
        binding.btnLogin.setOnClickListener {
            val username = binding.etUsername.text.toString().trim()
            val password = binding.etPassword.text.toString().trim()

            if (validateInput(username, password)) {
                login(username, password)
            }
        }

        binding.btnRegister.setOnClickListener {
            val username = binding.etUsername.text.toString().trim()
            val email = binding.etEmail.text.toString().trim()
            val password = binding.etPassword.text.toString().trim()

            if (validateInput(username, password, email)) {
                register(username, email, password)
            }
        }

        binding.tvToggleMode.setOnClickListener {
            toggleMode()
        }
    }

    private fun validateInput(username: String, password: String, email: String? = null): Boolean {
        if (username.isEmpty()) {
            binding.etUsername.error = "Username is required"
            return false
        }

        if (password.isEmpty()) {
            binding.etPassword.error = "Password is required"
            return false
        }

        if (password.length < 6) {
            binding.etPassword.error = "Password must be at least 6 characters"
            return false
        }

        email?.let {
            if (it.isEmpty()) {
                binding.etEmail.error = "Email is required"
                return false
            }
            if (!android.util.Patterns.EMAIL_ADDRESS.matcher(it).matches()) {
                binding.etEmail.error = "Invalid email format"
                return false
            }
        }

        return true
    }

    private fun login(username: String, password: String) {
        setLoading(true)
        lifecycleScope.launch {
            viewModel.login(username, password).observe(this@LoginActivity) { result ->
                result.onSuccess { response ->
                    Toast.makeText(
                        this@LoginActivity,
                        "Login successful!",
                        Toast.LENGTH_SHORT
                    ).show()
                    
                    // Schedule notification polling
                    NotificationWorker.schedule(this@LoginActivity)
                    
                    // Navigate to main activity
                    startActivity(Intent(this@LoginActivity, MainActivity::class.java))
                    finish()
                }.onFailure { exception ->
                    Toast.makeText(
                        this@LoginActivity,
                        "Login failed: ${exception.message}",
                        Toast.LENGTH_LONG
                    ).show()
                }
                setLoading(false)
            }
        }
    }

    private fun register(username: String, email: String, password: String) {
        setLoading(true)
        lifecycleScope.launch {
            viewModel.register(username, email, password).observe(this@LoginActivity) { result ->
                result.onSuccess { response ->
                    Toast.makeText(
                        this@LoginActivity,
                        "Registration successful!",
                        Toast.LENGTH_SHORT
                    ).show()
                    
                    // Schedule notification polling
                    NotificationWorker.schedule(this@LoginActivity)
                    
                    // Navigate to main activity
                    startActivity(Intent(this@LoginActivity, MainActivity::class.java))
                    finish()
                }.onFailure { exception ->
                    Toast.makeText(
                        this@LoginActivity,
                        "Registration failed: ${exception.message}",
                        Toast.LENGTH_LONG
                    ).show()
                }
                setLoading(false)
            }
        }
    }

    private fun toggleMode() {
        val isLoginMode = binding.btnLogin.visibility == android.view.View.VISIBLE
        
        if (isLoginMode) {
            // Switch to register mode
            binding.btnLogin.visibility = android.view.View.GONE
            binding.btnRegister.visibility = android.view.View.VISIBLE
            binding.etEmail.visibility = android.view.View.VISIBLE
            binding.tvToggleMode.text = "Already have an account? Login"
            title = "Register"
        } else {
            // Switch to login mode
            binding.btnLogin.visibility = android.view.View.VISIBLE
            binding.btnRegister.visibility = android.view.View.GONE
            binding.etEmail.visibility = android.view.View.GONE
            binding.tvToggleMode.text = "Don't have an account? Register"
            title = "Login"
        }
    }

    private fun setLoading(loading: Boolean) {
        binding.btnLogin.isEnabled = !loading
        binding.btnRegister.isEnabled = !loading
        binding.progressBar.visibility = if (loading) android.view.View.VISIBLE else android.view.View.GONE
    }

    private fun observeViewModel() {
        // Observe any additional view model states if needed
    }
}