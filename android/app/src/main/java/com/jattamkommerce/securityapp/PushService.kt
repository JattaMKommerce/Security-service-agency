package com.jattamkommerce.securityapp

import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage

/**
 * Push messages. When the app is closed, Android shows them by itself (with the channel's sound,
 * e.g. the SOS siren). When the app is open, they arrive here and we show them ourselves.
 */
class PushService : FirebaseMessagingService() {
    override fun onNewToken(token: String) {
        Prefs.get(this).edit().putString("push_token", token).apply()
        MainActivity.instance?.deliverPushToken(token)   // the page sends it to the server
    }

    override fun onMessageReceived(msg: RemoteMessage) {
        val d = msg.data
        val title = d["title"] ?: msg.notification?.title ?: return
        val body = d["body"] ?: msg.notification?.body ?: ""
        Notify.show(this, d["channel"] ?: "alerts", title, body, d["url"] ?: "", d["tag"] ?: "")
    }
}
