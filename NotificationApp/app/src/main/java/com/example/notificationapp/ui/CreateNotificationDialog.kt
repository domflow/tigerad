package com.example.notificationapp.ui

import android.app.AlertDialog
import android.app.Dialog
import android.os.Bundle
import android.view.LayoutInflater
import androidx.fragment.app.DialogFragment
import com.example.notificationapp.databinding.DialogCreateNotificationBinding

class CreateNotificationDialog(
    private val onNotificationCreated: (title: String, message: String) -> Unit
) : DialogFragment() {

    private lateinit var binding: DialogCreateNotificationBinding

    override fun onCreateDialog(savedInstanceState: Bundle?): Dialog {
        binding = DialogCreateNotificationBinding.inflate(LayoutInflater.from(context))

        val dialog = AlertDialog.Builder(requireActivity())
            .setTitle("Create Notification")
            .setView(binding.root)
            .setPositiveButton("Create") { _, _ ->
                val title = binding.etTitle.text.toString().trim()
                val message = binding.etMessage.text.toString().trim()
                
                if (title.isNotEmpty() && message.isNotEmpty()) {
                    onNotificationCreated(title, message)
                }
            }
            .setNegativeButton("Cancel", null)
            .create()

        return dialog
    }
}