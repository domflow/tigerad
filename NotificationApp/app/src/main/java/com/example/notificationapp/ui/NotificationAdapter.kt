package com.example.notificationapp.ui

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.example.notificationapp.data.model.Notification
import com.example.notificationapp.databinding.ItemNotificationBinding
import java.text.SimpleDateFormat
import java.util.*

class NotificationAdapter(
    private val onNotificationClick: (Notification) -> Unit,
    private val onNotificationDelete: (Notification) -> Unit
) : ListAdapter<Notification, NotificationAdapter.NotificationViewHolder>(NotificationDiffCallback()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): NotificationViewHolder {
        val binding = ItemNotificationBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return NotificationViewHolder(binding)
    }

    override fun onBindViewHolder(holder: NotificationViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class NotificationViewHolder(
        private val binding: ItemNotificationBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        private val dateFormat = SimpleDateFormat("MMM dd, HH:mm", Locale.getDefault())

        fun bind(notification: Notification) {
            with(binding) {
                tvTitle.text = notification.title
                tvMessage.text = notification.message
                tvDate.text = formatDate(notification.createdAt)
                tvType.text = notification.typeName
                tvPriority.text = notification.priority

                // Set read status
                root.alpha = if (notification.isRead) 0.7f else 1.0f
                tvTitle.setTextColor(
                    if (notification.isRead) {
                        android.graphics.Color.GRAY
                    } else {
                        android.graphics.Color.BLACK
                    }
                )

                // Set priority color
                val priorityColor = when (notification.priority.lowercase()) {
                    "urgent" -> android.graphics.Color.RED
                    "high" -> android.graphics.Color.parseColor("#FF9800")
                    "medium" -> android.graphics.Color.parseColor("#4CAF50")
                    "low" -> android.graphics.Color.GRAY
                    else -> android.graphics.Color.GRAY
                }
                tvPriority.setTextColor(priorityColor)

                // Click listeners
                root.setOnClickListener {
                    if (!notification.isRead) {
                        onNotificationClick(notification)
                    }
                }

                btnDelete.setOnClickListener {
                    onNotificationDelete(notification)
                }
            }
        }

        private fun formatDate(dateString: String): String {
            return try {
                val inputFormat = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
                val date = inputFormat.parse(dateString)
                date?.let { dateFormat.format(it) } ?: dateString
            } catch (e: Exception) {
                dateString
            }
        }
    }
}

class NotificationDiffCallback : DiffUtil.ItemCallback<Notification>() {
    override fun areItemsTheSame(oldItem: Notification, newItem: Notification): Boolean {
        return oldItem.id == newItem.id
    }

    override fun areContentsTheSame(oldItem: Notification, newItem: Notification): Boolean {
        return oldItem == newItem
    }
}