package com.jattamkommerce.securityapp

import android.app.Application
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.media.AudioAttributes
import android.os.Build
import android.os.Handler
import android.os.Looper
import com.google.firebase.FirebaseApp
import com.google.firebase.FirebaseOptions
import org.json.JSONObject

class App : Application() {
    override fun onCreate() {
        super.onCreate()
        createChannels()
        ensureFirebase(this, null)
    }

    /** Notification channels. "sos" plays the siren at alarm volume. */
    private fun createChannels() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val nm = getSystemService(NotificationManager::class.java)
        val alarm = AudioAttributes.Builder()
            .setUsage(AudioAttributes.USAGE_ALARM)
            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
            .build()
        val sos = NotificationChannel("sos", "SOS emergencies", NotificationManager.IMPORTANCE_HIGH).apply {
            description = "Guard pressed SOS. Plays a siren."
            setSound(Notify.sirenUri(this@App), alarm)
            enableVibration(true)
            vibrationPattern = longArrayOf(0, 800, 300, 800, 300, 800)
            lockscreenVisibility = Notification.VISIBILITY_PUBLIC
            setShowBadge(true)
        }
        val alerts = NotificationChannel("alerts", "Guard alerts", NotificationManager.IMPORTANCE_HIGH).apply {
            description = "Late, left site, no signal, GPS off, battery low, phone switched off"
            lockscreenVisibility = Notification.VISIBILITY_PUBLIC
            setShowBadge(true)
        }
        val requests = NotificationChannel("requests", "Leave & extra hours requests", NotificationManager.IMPORTANCE_HIGH).apply {
            setShowBadge(true)
        }
        val guard = NotificationChannel("guard", "Duty updates", NotificationManager.IMPORTANCE_HIGH).apply {
            description = "Leave / extra hours decisions and handovers"
            setShowBadge(true)
        }
        val tracking = NotificationChannel("tracking", "On-duty location sharing", NotificationManager.IMPORTANCE_LOW).apply {
            description = "Shown while you are on duty"
            setShowBadge(false)
        }
        nm.createNotificationChannels(listOf(sos, alerts, requests, guard, tracking))
    }

    companion object {
        /**
         * Starts Firebase with the public app values from the server (Admin > Settings > Notifications),
         * so no Firebase file has to be built into the app. done(true) when push can be used.
         */
        fun ensureFirebase(ctx: Context, done: ((Boolean) -> Unit)?) {
            val app = ctx.applicationContext
            val main = Handler(Looper.getMainLooper())
            if (initFromPrefs(app)) {
                done?.let { cb -> main.post { cb(true) } }
                Thread { fetchConfig(app) }.start()
                return
            }
            Thread {
                fetchConfig(app)
                val ok = initFromPrefs(app)
                done?.let { cb -> main.post { cb(ok) } }
            }.start()
        }

        private fun fetchConfig(ctx: Context) {
            val (code, body) = Net.get(BuildConfig.BASE_URL.trimEnd('/') + "/api/app/config.php")
            if (code != 200) return
            val fb = try { JSONObject(body).optJSONObject("firebase") } catch (e: Exception) { null } ?: return
            Prefs.get(ctx).edit()
                .putString("fb_project", fb.optString("project_id"))
                .putString("fb_api", fb.optString("api_key"))
                .putString("fb_app", fb.optString("app_id"))
                .putString("fb_sender", fb.optString("sender_id"))
                .apply()
        }

        @Synchronized
        private fun initFromPrefs(ctx: Context): Boolean {
            if (FirebaseApp.getApps(ctx).isNotEmpty()) return true
            val p = Prefs.get(ctx)
            val appId = p.getString("fb_app", "") ?: ""
            if (appId.isEmpty()) return false
            return try {
                val opts = FirebaseOptions.Builder()
                    .setApplicationId(appId)
                    .setApiKey(p.getString("fb_api", "") ?: "")
                    .setProjectId(p.getString("fb_project", "") ?: "")
                    .setGcmSenderId(p.getString("fb_sender", "") ?: "")
                    .build()
                FirebaseApp.initializeApp(ctx, opts)
                true
            } catch (e: Exception) {
                false
            }
        }
    }
}
