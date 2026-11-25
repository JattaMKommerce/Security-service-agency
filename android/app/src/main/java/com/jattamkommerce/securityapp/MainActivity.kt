package com.jattamkommerce.securityapp

import android.Manifest
import android.annotation.SuppressLint
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.provider.MediaStore
import android.webkit.CookieManager
import android.webkit.GeolocationPermissions
import android.webkit.PermissionRequest
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import org.json.JSONObject
import java.io.File

/** Shows the Jatta Security website (admin portal and guard app) inside the Android app. */
class MainActivity : AppCompatActivity() {

    companion object {
        @Volatile var instance: MainActivity? = null
    }

    lateinit var web: WebView
    @Volatile var currentUrl: String = ""
    var pendingTrackingConfig: String? = null

    private val ourHost: String = Uri.parse(BuildConfig.BASE_URL).host ?: ""
    private var fileCallback: ValueCallback<Array<Uri>>? = null
    private var cameraUri: Uri? = null
    private var pendingGeo: Pair<String, GeolocationPermissions.Callback>? = null
    private var pendingWebPermission: PermissionRequest? = null

    private val fileLauncher = registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { res ->
        val cb = fileCallback ?: return@registerForActivityResult
        fileCallback = null
        if (res.resultCode != RESULT_OK) { cb.onReceiveValue(null); return@registerForActivityResult }
        var uris = WebChromeClient.FileChooserParams.parseResult(res.resultCode, res.data)
        if ((uris == null || uris.isEmpty()) && cameraUri != null) uris = arrayOf(cameraUri!!)
        cb.onReceiveValue(uris)
    }

    private val permissionLauncher = registerForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { onPermissionsResult() }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        instance = this
        web = WebView(this)
        setContentView(web)

        web.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            setGeolocationEnabled(true)
            mediaPlaybackRequiresUserGesture = false
            mixedContentMode = WebSettings.MIXED_CONTENT_NEVER_ALLOW
            allowFileAccess = false
            userAgentString = "$userAgentString JattaApp/${BuildConfig.VERSION_NAME}"
        }
        CookieManager.getInstance().setAcceptCookie(true)
        web.addJavascriptInterface(JattaBridge(this), "JattaApp")

        web.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView, req: WebResourceRequest): Boolean {
                val u = req.url
                if (u.scheme == "https" && u.host == ourHost) return false
                openExternal(u)
                return true
            }

            override fun onPageStarted(view: WebView, url: String, favicon: Bitmap?) { currentUrl = url }

            override fun onPageFinished(view: WebView, url: String) {
                currentUrl = url
                CookieManager.getInstance().flush()
            }

            override fun onReceivedError(view: WebView, req: WebResourceRequest, err: WebResourceError) {
                if (req.isForMainFrame) showOffline(req.url.toString())
            }
        }

        web.webChromeClient = object : WebChromeClient() {
            override fun onGeolocationPermissionsShowPrompt(origin: String, cb: GeolocationPermissions.Callback) {
                if (Uri.parse(origin).host != ourHost) { cb.invoke(origin, false, false); return }
                if (hasLocation()) cb.invoke(origin, true, false)
                else { pendingGeo = origin to cb; requestLocation() }
            }

            // Camera for the duty selfie (getUserMedia).
            override fun onPermissionRequest(req: PermissionRequest) {
                runOnUiThread {
                    val wantsCamera = req.resources.contains(PermissionRequest.RESOURCE_VIDEO_CAPTURE)
                    if (req.origin.host != ourHost || !wantsCamera) { req.deny(); return@runOnUiThread }
                    if (granted(Manifest.permission.CAMERA)) req.grant(arrayOf(PermissionRequest.RESOURCE_VIDEO_CAPTURE))
                    else { pendingWebPermission = req; permissionLauncher.launch(arrayOf(Manifest.permission.CAMERA)) }
                }
            }

            // <input type="file">: gallery, plus the camera for photos.
            override fun onShowFileChooser(view: WebView, cb: ValueCallback<Array<Uri>>, params: FileChooserParams): Boolean {
                fileCallback?.onReceiveValue(null)
                fileCallback = cb
                cameraUri = null
                val pick = params.createIntent()
                var intent: Intent = pick
                val images = params.acceptTypes.isEmpty() || params.acceptTypes.any { it.isEmpty() || it.startsWith("image") }
                if (images && granted(Manifest.permission.CAMERA)) {
                    val dir = File(cacheDir, "camera").apply { mkdirs() }
                    val uri = FileProvider.getUriForFile(this@MainActivity, "$packageName.files", File(dir, "photo_${System.currentTimeMillis()}.jpg"))
                    cameraUri = uri
                    val cam = Intent(MediaStore.ACTION_IMAGE_CAPTURE)
                        .putExtra(MediaStore.EXTRA_OUTPUT, uri)
                        .addFlags(Intent.FLAG_GRANT_WRITE_URI_PERMISSION or Intent.FLAG_GRANT_READ_URI_PERMISSION)
                    intent = if (params.isCaptureEnabled) cam
                             else Intent.createChooser(pick, "Choose photo").putExtra(Intent.EXTRA_INITIAL_INTENTS, arrayOf(cam))
                }
                return try { fileLauncher.launch(intent); true } catch (e: Exception) { fileCallback = null; false }
            }
        }

        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (web.canGoBack()) web.goBack() else moveTaskToBack(true)
            }
        })

        if (savedInstanceState != null) web.restoreState(savedInstanceState) else web.loadUrl(startUrl(intent))
        App.ensureFirebase(this, null)
        if (Build.VERSION.SDK_INT >= 33 && !granted(Manifest.permission.POST_NOTIFICATIONS)) {
            permissionLauncher.launch(arrayOf(Manifest.permission.POST_NOTIFICATIONS))
        }
    }

    /** Opens the page a notification points to (only paths on our site), otherwise the login / home page. */
    private fun startUrl(i: Intent?): String {
        val path = i?.getStringExtra("url")?.trim()?.takeIf { it.isNotEmpty() && !it.contains(":") && !it.startsWith("//") }?.trimStart('/')
        return BuildConfig.BASE_URL.trimEnd('/') + "/" + (path ?: "login.php")
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        if (!intent.getStringExtra("url").isNullOrBlank()) web.loadUrl(startUrl(intent))
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        web.saveState(outState)
    }

    override fun onResume() {
        super.onResume()
        web.onResume()
        js("window.dispatchEvent(new Event('jattaapp:resume'))")
    }

    override fun onPause() {
        web.onPause()
        CookieManager.getInstance().flush()
        super.onPause()
    }

    override fun onDestroy() {
        if (instance === this) instance = null
        super.onDestroy()
    }

    // ---------- helpers used by the bridge ----------

    fun js(code: String) = runOnUiThread { web.evaluateJavascript(code, null) }

    fun deliverPushToken(token: String) = js("window.__jattaPushToken && window.__jattaPushToken(${JSONObject.quote(token)})")

    fun granted(p: String) = ContextCompat.checkSelfPermission(this, p) == PackageManager.PERMISSION_GRANTED

    fun hasLocation() = granted(Manifest.permission.ACCESS_FINE_LOCATION) || granted(Manifest.permission.ACCESS_COARSE_LOCATION)

    fun requestLocation() = requestPerms(arrayOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION))

    fun requestPerms(perms: Array<String>) = runOnUiThread { permissionLauncher.launch(perms) }

    private fun onPermissionsResult() {
        pendingGeo?.let { (origin, cb) -> cb.invoke(origin, hasLocation(), false) }
        pendingGeo = null
        pendingWebPermission?.let {
            if (granted(Manifest.permission.CAMERA)) it.grant(arrayOf(PermissionRequest.RESOURCE_VIDEO_CAPTURE)) else it.deny()
        }
        pendingWebPermission = null
        pendingTrackingConfig?.let { cfg ->
            pendingTrackingConfig = null
            if (hasLocation()) TrackingService.start(this, cfg)
        }
        js("window.dispatchEvent(new Event('jattaapp:resume'))")
    }

    /** Phone calls, WhatsApp, maps and other websites open outside the app. */
    private fun openExternal(u: Uri) {
        try {
            val i = if (u.scheme == "intent") {
                Intent.parseUri(u.toString(), Intent.URI_INTENT_SCHEME).apply {
                    component = null
                    selector = null
                    addCategory(Intent.CATEGORY_BROWSABLE)
                }
            } else {
                Intent(Intent.ACTION_VIEW, u)
            }
            startActivity(i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
        } catch (e: Exception) {
            Toast.makeText(this, "No app on this phone can open that link.", Toast.LENGTH_SHORT).show()
        }
    }

    private fun showOffline(failedUrl: String) {
        val safe = JSONObject.quote(if (Uri.parse(failedUrl).host == ourHost) failedUrl else BuildConfig.BASE_URL)
        val html = """
            <html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head>
            <body style="font-family:sans-serif;background:#0b1324;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center">
            <div style="padding:24px"><div style="font-size:48px">📶</div><h2>No internet</h2>
            <p style="color:#cbd5e1">Check mobile data or Wi-Fi. If you are on duty, your location is still being saved and will be sent automatically.</p>
            <button onclick='location.replace($safe)' style="background:#f5b301;border:0;border-radius:12px;padding:14px 28px;font-size:16px;font-weight:bold">Try again</button>
            </div></body></html>
        """.trimIndent()
        web.loadDataWithBaseURL(null, html, "text/html", "utf-8", null)
    }
}
