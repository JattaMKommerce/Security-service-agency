package com.jattamkommerce.securityapp

import android.app.Notification
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.net.Uri
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat

/** Shows a notification (used for push messages while the app is open, and duty messages). */
object Notify {
    fun sirenUri(ctx: Context): Uri = Uri.parse("android.resource://${ctx.packageName}/${R.raw.siren}")

    fun openAppIntent(ctx: Context, url: String, requestCode: Int): PendingIntent {
        val i = Intent(ctx, MainActivity::class.java)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP)
            .putExtra("url", url)
        return PendingIntent.getActivity(ctx, requestCode, i, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
    }

    fun show(ctx: Context, channel: String, title: String, body: String, url: String, tag: String) {
        val nm = NotificationManagerCompat.from(ctx)
        if (!nm.areNotificationsEnabled()) return
        val id = (tag.ifEmpty { title + System.currentTimeMillis() }).hashCode()
        val sos = channel == "sos"
        val b = NotificationCompat.Builder(ctx, channel)
            .setSmallIcon(R.drawable.ic_stat_shield)
            .setColor(ContextCompat.getColor(ctx, R.color.brand))
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setAutoCancel(true)
            .setContentIntent(openAppIntent(ctx, url, id))
            .setPriority(if (sos) NotificationCompat.PRIORITY_MAX else NotificationCompat.PRIORITY_HIGH)
            .setCategory(if (sos) NotificationCompat.CATEGORY_ALARM else NotificationCompat.CATEGORY_MESSAGE)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
        if (sos) b.setSound(sirenUri(ctx)).setVibrate(longArrayOf(0, 800, 300, 800, 300, 800))
        val n = b.build()
        if (sos) n.flags = n.flags or Notification.FLAG_INSISTENT   // siren repeats until the admin opens it
        try {
            nm.notify(tag.ifEmpty { null }, id, n)
        } catch (e: SecurityException) {
            // notification permission was removed
        }
    }
}
