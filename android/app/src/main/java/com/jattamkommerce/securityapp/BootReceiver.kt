package com.jattamkommerce.securityapp

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import androidx.core.content.ContextCompat

/** If the guard was on duty when the phone restarted, start sharing location again. */
class BootReceiver : BroadcastReceiver() {
    override fun onReceive(ctx: Context, intent: Intent) {
        if (!Prefs.get(ctx).getBoolean("tracking", false)) return
        try {
            ContextCompat.startForegroundService(ctx, Intent(ctx, TrackingService::class.java))
        } catch (e: Exception) {
            // Android may block this without "Allow all the time" location; it restarts when the app is opened.
        }
    }
}
