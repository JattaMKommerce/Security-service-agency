package com.jattamkommerce.securityapp

import android.net.Uri
import android.print.PrintManager
import android.content.Context
import android.webkit.JavascriptInterface
import com.google.firebase.messaging.FirebaseMessaging
import org.json.JSONObject

/**
 * window.JattaApp in the website. Only answers pages from our own site
 * (external links open in the browser, never inside the app).
 */
class JattaBridge(private val a: MainActivity) {
    private val ourHost = Uri.parse(BuildConfig.BASE_URL).host

    private fun ours(): Boolean = Uri.parse(a.currentUrl).let { it.scheme == "https" && it.host == ourHost }

    @JavascriptInterface fun isApp(): Boolean = true

    @JavascriptInterface fun appVersion(): String = BuildConfig.VERSION_NAME

    @JavascriptInterface fun startTracking(cfgJson: String) {
        if (!ours()) return
        a.runOnUiThread {
            if (a.hasLocation()) TrackingService.start(a, cfgJson)
            else { a.pendingTrackingConfig = cfgJson; a.requestLocation() }
        }
    }

    @JavascriptInterface fun stopTracking() {
        if (!ours()) return
        a.runOnUiThread { TrackingService.stop(a) }
    }

    @JavascriptInterface fun isTracking(): Boolean = TrackingService.isTracking(a)

    @JavascriptInterface fun trackingInfo(): String = TrackingService.info(a).toString()

    @JavascriptInterface fun setupStatus(): String = Setup.status(a).toString()

    @JavascriptInterface fun openSetup(item: String) {
        if (!ours()) return
        a.runOnUiThread { Setup.open(a, item) }
    }

    /** Calls window[callback](token) when the push token is ready (only if push is set up on the server). */
    @JavascriptInterface fun getPushToken(callback: String) {
        if (!ours() || !Regex("^[A-Za-z_$][A-Za-z0-9_$]*$").matches(callback)) return
        a.runOnUiThread {
            App.ensureFirebase(a) { ready ->
                if (!ready) return@ensureFirebase
                try {
                    FirebaseMessaging.getInstance().token.addOnSuccessListener { t ->
                        Prefs.get(a).edit().putString("push_token", t).apply()
                        a.js("window.$callback && window.$callback(${JSONObject.quote(t)})")
                    }
                } catch (e: Exception) { }
            }
        }
    }

    /** window.print() does nothing inside an app; this opens Android's print / save-as-PDF screen. */
    @JavascriptInterface fun printPage(title: String) {
        if (!ours()) return
        a.runOnUiThread {
            val pm = a.getSystemService(Context.PRINT_SERVICE) as PrintManager
            pm.print(title.ifBlank { "Document" }, a.web.createPrintDocumentAdapter(title), null)
        }
    }
}
