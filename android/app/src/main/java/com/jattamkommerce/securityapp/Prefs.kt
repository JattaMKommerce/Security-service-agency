package com.jattamkommerce.securityapp

import android.content.Context
import android.content.SharedPreferences

/** Small key/value store for duty tracking state, the offline GPS queue and the Firebase app values. */
object Prefs {
    fun get(ctx: Context): SharedPreferences = ctx.applicationContext.getSharedPreferences("jatta", Context.MODE_PRIVATE)
}
