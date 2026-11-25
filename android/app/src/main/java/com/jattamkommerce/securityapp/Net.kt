package com.jattamkommerce.securityapp

import java.net.HttpURLConnection
import java.net.URL

/** Plain HTTPS calls (no extra libraries). Returns (HTTP code or -1 when offline, body). */
object Net {
    fun postJson(url: String, json: String, deviceToken: String?, timeoutMs: Int = 15000): Pair<Int, String> =
        request(url, "POST", json, deviceToken, timeoutMs)

    fun get(url: String, timeoutMs: Int = 10000): Pair<Int, String> = request(url, "GET", null, null, timeoutMs)

    private fun request(url: String, method: String, body: String?, deviceToken: String?, timeoutMs: Int): Pair<Int, String> {
        var c: HttpURLConnection? = null
        return try {
            c = URL(url).openConnection() as HttpURLConnection
            c.requestMethod = method
            c.connectTimeout = timeoutMs
            c.readTimeout = timeoutMs
            c.setRequestProperty("Accept", "application/json")
            if (deviceToken != null) c.setRequestProperty("X-Device-Token", deviceToken)
            if (body != null) {
                c.doOutput = true
                c.setRequestProperty("Content-Type", "application/json")
                c.outputStream.use { it.write(body.toByteArray(Charsets.UTF_8)) }
            }
            val code = c.responseCode
            val stream = if (code in 200..299) c.inputStream else c.errorStream
            code to (stream?.bufferedReader()?.use { it.readText() } ?: "")
        } catch (e: Exception) {
            -1 to (e.message ?: "network error")
        } finally {
            c?.disconnect()
        }
    }
}
