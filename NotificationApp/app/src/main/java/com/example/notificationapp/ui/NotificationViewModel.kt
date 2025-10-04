package com.example.notificationapp.ui

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.example.notificationapp.data.model.NotificationResponse
import com.example.notificationapp.data.repository.AuthRepository
import com.example.notificationapp.data.repository.NotificationRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class NotificationViewModel @Inject constructor(
    private val notificationRepository: NotificationRepository,
    private val authRepository: AuthRepository
) : ViewModel() {

    private val _notifications = MutableLiveData<Result<NotificationResponse>>()
    val notifications: LiveData<Result<NotificationResponse>> = _notifications

    private val _loading = MutableLiveData<Boolean>()
    val loading: LiveData<Boolean> = _loading

    private val _createResult = MutableLiveData<Result<Int>>()
    val createResult: LiveData<Result<Int>> = _createResult

    private val _markAsReadResult = MutableLiveData<Result<String>>()
    val markAsReadResult: LiveData<Result<String>> = _markAsReadResult

    private val _deleteResult = MutableLiveData<Result<String>>()
    val deleteResult: LiveData<Result<String>> = _deleteResult

    fun loadNotifications(page: Int = 1, limit: Int = 20, unreadOnly: Boolean = false) {
        viewModelScope.launch {
            _loading.value = true
            val result = notificationRepository.getNotifications(page, limit, unreadOnly)
            _notifications.postValue(result)
            _loading.value = false
        }
    }

    fun createNotification(title: String, message: String): LiveData<Result<Int>> {
        viewModelScope.launch {
            _loading.value = true
            val result = notificationRepository.createNotification(title, message)
            _createResult.postValue(result)
            _loading.value = false
        }
        return _createResult
    }

    fun markAsRead(notificationId: Int): LiveData<Result<String>> {
        viewModelScope.launch {
            _loading.value = true
            val result = notificationRepository.markAsRead(notificationId)
            _markAsReadResult.postValue(result)
            _loading.value = false
        }
        return _markAsReadResult
    }

    fun deleteNotification(notificationId: Int): LiveData<Result<String>> {
        viewModelScope.launch {
            _loading.value = true
            val result = notificationRepository.deleteNotification(notificationId)
            _deleteResult.postValue(result)
            _loading.value = false
        }
        return _deleteResult
    }

    fun logout() {
        authRepository.logout()
    }
}