package com.jattamkommerce.securityapp

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.location.LocationManager
import android.net.Uri
import android.os.Build
import android.os.PowerManager
import android.provider.Settings
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.core.location.LocationManagerCompat
import org.json.JSONObject

/** The phone settings background tracking depends on, and shortcuts to fix each one. */
object Setup {
    private fun granted(ctx: Context, p: String) = ContextCompat.checkSelfPermission(ctx, p) == PackageManager.PERMISSION_GRANTED

    fun status(ctx: Context): JSONObject {
        val fine = granted(ctx, Manifest.permission.ACCESS_FINE_LOCATION)
        val background = Build.VERSION.SDK_INT < Build.VERSION_CODES.Q || granted(ctx, Manifest.permission.ACCESS_BACKGROUND_LOCATION)
        val pm = ctx.getSystemService(Context.POWER_SERVICE) as PowerManager
        val lm = ctx.getSystemService(Context.LOCATION_SERVICE) as LocationManager
        return JSONObject()
            .put("location", fine && background)
            .put("notifications", NotificationManagerCompat.from(ctx).areNotificationsEnabled())
            .put("battery", Build.VERSION.SDK_INT < Build.VERSION_CODES.M || pm.isIgnoringBatteryOptimizations(ctx.packageName))
            .put("gps", LocationManagerCompat.isLocationEnabled(lm))
            .put("brand_hint", brandHint())
    }

    /** Extra step for phone brands that close background apps on their own. */
    private fun brandHint(): String = when (Build.MANUFACTURER.lowercase()) {
        "xiaomi", "redmi", "poco" -> "Xiaomi / Redmi / POCO: also open Settings > Apps > Jatta Security > turn ON Autostart, and Battery saver > No restrictions."
        "vivo", "iqoo" -> "Vivo: also open Settings > Battery > Background power consumption > Jatta Security > Allow."
        "oppo", "realme" -> "Oppo / Realme: also open Settings > Apps > Jatta Security > Battery > Allow background activity, and turn ON Auto launch."
        "oneplus" -> "OnePlus: also open Settings > Apps > Jatta Security > Battery > Don't optimise."
        "samsung" -> "Samsung: also open Settings > Battery > Background usage limits and make sure Jatta Security is NOT in 'Sleeping apps'."
        "huawei", "honor" -> "Huawei / Honor: also open Settings > Battery > App launch > Jatta Security > Manage manually (all ON)."
        else -> ""
    }

    @SuppressLint("BatteryLife")
    fun open(a: MainActivity, item: String) {
        val pkg = Uri.parse("package:" + a.packageName)
        try {
            when (item) {
                "location" -> {
                    if (!granted(a, Manifest.permission.ACCESS_FINE_LOCATION)) {
                        a.requestPerms(arrayOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION))
                    } else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                        // Android shows its own page with "Allow all the time".
                        a.requestPerms(arrayOf(Manifest.permission.ACCESS_BACKGROUND_LOCATION))
                    }
                }
                "notifications" -> {
                    if (Build.VERSION.SDK_INT >= 33 && !granted(a, Manifest.permission.POST_NOTIFICATIONS)) {
                        a.requestPerms(arrayOf(Manifest.permission.POST_NOTIFICATIONS))
                    } else {
                        a.startActivity(Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS).putExtra(Settings.EXTRA_APP_PACKAGE, a.packageName))
                    }
                }
                "battery" -> a.startActivity(Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS, pkg))
                "gps" -> a.startActivity(Intent(Settings.ACTION_LOCATION_SOURCE_SETTINGS))
                else -> a.startActivity(Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, pkg))
            }
        } catch (e: Exception) {
            a.startActivity(Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, pkg))
        }
    }
}
