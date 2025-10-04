package com.example.notificationapp.ui

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.example.notificationapp.data.model.LoginResponse
import com.example.notificationapp.data.model.RegisterResponse
import com.example.notificationapp.data.repository.AuthRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class AuthViewModel @Inject constructor(
    private val authRepository: AuthRepository
) : ViewModel() {

    private val _loginResult = MutableLiveData<Result<LoginResponse>>()
    val loginResult: LiveData<Result<LoginResponse>> = _loginResult

    private val _registerResult = MutableLiveData<Result<RegisterResponse>>()
    val registerResult: LiveData<Result<RegisterResponse>> = _registerResult

    fun login(username: String, password: String): LiveData<Result<LoginResponse>> {
        viewModelScope.launch {
            val result = authRepository.login(username, password)
            _loginResult.postValue(result)
        }
        return _loginResult
    }

    fun register(username: String, email: String, password: String): LiveData<Result<RegisterResponse>> {
        viewModelScope.launch {
            val result = authRepository.register(username, email, password)
            _registerResult.postValue(result)
        }
        return _registerResult
    }

    fun isLoggedIn(): Boolean = authRepository.isLoggedIn()

    fun logout() {
        authRepository.logout()
    }
}