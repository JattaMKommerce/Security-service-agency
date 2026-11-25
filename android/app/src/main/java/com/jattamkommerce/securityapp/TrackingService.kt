package com.jattamkommerce.securityapp

import android.Manifest
import android.annotation.SuppressLint
import android.app.Notification
import android.app.Service
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.PackageManager
import android.content.pm.ServiceInfo
import android.location.Location
import android.location.LocationManager
import android.os.BatteryManager
import android.os.Build
import android.os.Handler
import android.os.IBinder
import android.os.Looper
import android.os.PowerManager
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.core.location.LocationManagerCompat
import com.google.android.gms.location.FusedLocationProviderClient
import com.google.android.gms.location.LocationCallback
import com.google.android.gms.location.LocationRequest
import com.google.android.gms.location.LocationResult
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.Priority
import org.json.JSONArray
import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.concurrent.Executors

/**
 * On-duty location sharing that keeps working with the screen off or while other apps are used.
 * Shows a permanent "On duty" notification (Android requires it). GPS points are queued on the
 * phone when there is no internet and sent later. Also tells the control room when the phone is
 * being switched off, the battery is low, or GPS is turned off.
 */
class TrackingService : Service() {

    companion object {
        @Volatile var running = false
        private const val NOTIF_ID = 4201
        private const val MAX_QUEUE = 2000
        private const val BATCH = 100

        /** cfg: { base, token, interval_sec, name } from the website after "Start Duty". */
        fun start(ctx: Context, cfgJson: String) {
            val o = JSONObject(cfgJson)
            Prefs.get(ctx).edit()
                .putBoolean("tracking", true)
                .putString("token", o.getString("token"))
                .putString("base", o.optString("base", BuildConfig.BASE_URL))
                .putInt("interval", o.optInt("interval_sec", 20))
                .putString("name", o.optString("name", ""))
                .apply()
            ContextCompat.startForegroundService(ctx, Intent(ctx, TrackingService::class.java))
        }

        fun stop(ctx: Context) {
            Prefs.get(ctx).edit().putBoolean("tracking", false).apply()
            ctx.stopService(Intent(ctx, TrackingService::class.java))
        }

        fun isTracking(ctx: Context) = running && Prefs.get(ctx).getBoolean("tracking", false)

        /** For the page: { last_sent_ago, pending }. */
        fun info(ctx: Context): JSONObject {
            val p = Prefs.get(ctx)
            val last = p.getLong("last_sent", 0L)
            val ago = if (last == 0L) "" else {
                val s = (System.currentTimeMillis() - last) / 1000
                if (s < 60) "${s}s ago" else "${s / 60}m ago"
            }
            val pending = try { JSONArray(p.getString("queue", "[]")).length() } catch (e: Exception) { 0 }
            return JSONObject().put("last_sent_ago", ago).put("pending", pending)
        }
    }

    private lateinit var fused: FusedLocationProviderClient
    private val exec = Executors.newSingleThreadExecutor()
    private val main = Handler(Looper.getMainLooper())
    private val queue = ArrayList<JSONObject>()
    private var intervalSec = 20
    private var sending = false
    private var lastSentAt = 0L
    private var receiverOn = false
    private var wake: PowerManager.WakeLock? = null
    private val clock = SimpleDateFormat("h:mm a", Locale.getDefault())

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onCreate() {
        super.onCreate()
        running = true
        fused = LocationServices.getFusedLocationProviderClient(this)
        loadQueue()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        val p = Prefs.get(this)
        if (!p.getBoolean("tracking", false) || p.getString("token", null) == null) {
            stopSelf()
            return START_NOT_STICKY
        }
        try {
            val n = buildNotification("Starting…")
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                startForeground(NOTIF_ID, n, ServiceInfo.FOREGROUND_SERVICE_TYPE_LOCATION)
            } else {
                startForeground(NOTIF_ID, n)
            }
        } catch (e: Exception) {
            // Android refused (e.g. started from the background without "Allow all the time").
            // The app restarts tracking when it is opened; the control room sees "No signal" meanwhile.
            stopSelf()
            return START_NOT_STICKY
        }
        intervalSec = p.getInt("interval", 20).coerceIn(10, 300)
        requestUpdates()
        registerPhoneEvents()
        if (wake == null) {
            wake = (getSystemService(POWER_SERVICE) as PowerManager)
                .newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "jatta:duty")
                .apply { setReferenceCounted(false); acquire(16 * 3600 * 1000L) }
        }
        main.removeCallbacks(heartbeat)
        main.post(heartbeat)
        return START_STICKY
    }

    override fun onDestroy() {
        running = false
        try { fused.removeLocationUpdates(locationCallback) } catch (e: Exception) { }
        main.removeCallbacksAndMessages(null)
        if (receiverOn) { try { unregisterReceiver(phoneEvents) } catch (e: Exception) { } }
        receiverOn = false
        wake?.let { if (it.isHeld) it.release() }
        wake = null
        exec.shutdown()
        super.onDestroy()
    }

    // ---------- GPS ----------

    private fun hasLocationPermission() =
        ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED ||
        ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED

    private fun gpsState(): String {
        val lm = getSystemService(LOCATION_SERVICE) as LocationManager
        return when {
            !hasLocationPermission() -> "denied"
            !LocationManagerCompat.isLocationEnabled(lm) -> "off"
            else -> "ok"
        }
    }

    @SuppressLint("MissingPermission")
    private fun requestUpdates() {
        if (!hasLocationPermission()) {
            updateNotification("Location permission is off — open the app and allow it")
            return
        }
        try { fused.removeLocationUpdates(locationCallback) } catch (e: Exception) { }
        val req = LocationRequest.Builder(Priority.PRIORITY_HIGH_ACCURACY, intervalSec * 1000L)
            .setMinUpdateIntervalMillis(intervalSec * 500L)
            .setWaitForAccurateLocation(false)
            .build()
        fused.requestLocationUpdates(req, locationCallback, Looper.getMainLooper())
    }

    private val locationCallback = object : LocationCallback() {
        override fun onLocationResult(result: LocationResult) {
            for (l in result.locations) addPoint(l)
            if (System.currentTimeMillis() - lastSentAt >= intervalSec * 800L) send()
        }
    }

    private fun addPoint(l: Location) {
        val o = JSONObject()
            .put("lat", l.latitude).put("lng", l.longitude)
            .put("accuracy", l.accuracy.toDouble()).put("ts", l.time)
        if (l.hasSpeed()) o.put("speed", l.speed.toDouble())
        if (l.hasBearing()) o.put("heading", l.bearing.toDouble())
        battery()?.let { o.put("battery", it) }
        synchronized(queue) {
            queue.add(o)
            while (queue.size > MAX_QUEUE) queue.removeAt(0)
        }
        saveQueue()
    }

    /** Sends queued points; with nothing queued it still tells the server the phone is online (and the GPS state). */
    private val heartbeat = object : Runnable {
        override fun run() {
            send()
            main.postDelayed(this, intervalSec * 1000L)
        }
    }

    private fun send() {
        if (sending) return
        val p = Prefs.get(this)
        val token = p.getString("token", null) ?: return
        val base = (p.getString("base", null) ?: BuildConfig.BASE_URL).trimEnd('/')
        val batch: List<JSONObject> = synchronized(queue) { ArrayList(queue.take(BATCH)) }
        val body = JSONObject().put("points", JSONArray(batch)).put("gps", gpsState()).toString()
        sending = true
        exec.execute {
            val (code, resp) = Net.postJson("$base/api/device/location.php", body, token)
            main.post { onSent(code, resp, batch.size) }
        }
    }

    private fun onSent(code: Int, resp: String, sentCount: Int) {
        sending = false
        when (code) {
            200 -> {
                synchronized(queue) { repeat(minOf(sentCount, queue.size)) { queue.removeAt(0) } }
                saveQueue()
                lastSentAt = System.currentTimeMillis()
                Prefs.get(this).edit().putLong("last_sent", lastSentAt).apply()
                val j = try { JSONObject(resp) } catch (e: Exception) { null }
                if (j != null && !j.optBoolean("on_duty", true)) {
                    endTracking("Duty ended", "Your duty was ended by the control room.")
                    return
                }
                val newInterval = j?.optInt("interval_sec", intervalSec) ?: intervalSec
                if (newInterval in 10..300 && newInterval != intervalSec) {
                    intervalSec = newInterval
                    Prefs.get(this).edit().putInt("interval", newInterval).apply()
                    requestUpdates()
                }
                val gps = gpsState()
                updateNotification(when (gps) {
                    "off" -> "GPS is OFF — turn Location on"
                    "denied" -> "Location permission is off — open the app"
                    else -> "Last sent ${clock.format(Date(lastSentAt))} · works with the screen off"
                })
                if (sentCount == BATCH && synchronized(queue) { queue.isNotEmpty() }) send()
            }
            401 -> endTracking("Location sharing stopped", "You were logged out. Open the app to start duty again.")
            else -> {
                val n = synchronized(queue) { queue.size }
                updateNotification("No internet — $n location(s) saved, will send automatically")
            }
        }
    }

    private fun endTracking(title: String, text: String) {
        Prefs.get(this).edit().putBoolean("tracking", false).apply()
        Notify.show(this, "guard", title, text, "guard/index.php", "duty")
        stopSelf()
    }

    // ---------- phone events: switching off, battery low, GPS on/off ----------

    private val phoneEvents = object : BroadcastReceiver() {
        override fun onReceive(c: Context, i: Intent) {
            when (i.action) {
                Intent.ACTION_SHUTDOWN, "android.intent.action.QUICKBOOT_POWEROFF" -> {
                    // Only a few seconds are left: send right away on a separate thread.
                    val pending = goAsync()
                    Thread { postEvent("phone_off", 3500); pending.finish() }.start()
                }
                Intent.ACTION_BATTERY_LOW -> exec.execute { postEvent("battery_low", 10000) }
                LocationManager.PROVIDERS_CHANGED_ACTION -> {
                    val off = gpsState() == "off"
                    exec.execute { postEvent(if (off) "gps_off" else "gps_on", 10000) }
                    updateNotification(if (off) "GPS is OFF — turn Location on" else "GPS is back on")
                }
            }
        }
    }

    private fun registerPhoneEvents() {
        if (receiverOn) return
        val f = IntentFilter().apply {
            addAction(Intent.ACTION_SHUTDOWN)
            addAction("android.intent.action.QUICKBOOT_POWEROFF")
            addAction(Intent.ACTION_BATTERY_LOW)
            addAction(LocationManager.PROVIDERS_CHANGED_ACTION)
        }
        ContextCompat.registerReceiver(this, phoneEvents, f, ContextCompat.RECEIVER_NOT_EXPORTED)
        receiverOn = true
    }

    private fun postEvent(event: String, timeoutMs: Int) {
        val p = Prefs.get(this)
        val token = p.getString("token", null) ?: return
        val base = (p.getString("base", null) ?: BuildConfig.BASE_URL).trimEnd('/')
        val body = JSONObject().put("event", event)
        battery()?.let { body.put("battery", it) }
        Net.postJson("$base/api/device/event.php", body.toString(), token, timeoutMs)
    }

    private fun battery(): Int? {
        val bm = getSystemService(BATTERY_SERVICE) as BatteryManager
        return bm.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY).takeIf { it in 0..100 }
    }

    // ---------- offline queue ----------

    private fun loadQueue() {
        val arr = try { JSONArray(Prefs.get(this).getString("queue", "[]")) } catch (e: Exception) { JSONArray() }
        synchronized(queue) {
            queue.clear()
            for (i in 0 until arr.length()) arr.optJSONObject(i)?.let { queue.add(it) }
        }
    }

    private fun saveQueue() {
        val s = synchronized(queue) { JSONArray(queue).toString() }
        Prefs.get(this).edit().putString("queue", s).apply()
    }

    // ---------- the permanent "On duty" notification ----------

    private fun buildNotification(text: String): Notification =
        NotificationCompat.Builder(this, "tracking")
            .setSmallIcon(R.drawable.ic_stat_shield)
            .setColor(ContextCompat.getColor(this, R.color.brand))
            .setContentTitle("On duty — location sharing ON")
            .setContentText(text)
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .setCategory(NotificationCompat.CATEGORY_SERVICE)
            .setForegroundServiceBehavior(NotificationCompat.FOREGROUND_SERVICE_IMMEDIATE)
            .setContentIntent(Notify.openAppIntent(this, "guard/index.php", NOTIF_ID))
            .build()

    private fun updateNotification(text: String) {
        try {
            NotificationManagerCompat.from(this).notify(NOTIF_ID, buildNotification(text))
        } catch (e: SecurityException) {
            // notifications not allowed; tracking keeps running
        }
    }
}
