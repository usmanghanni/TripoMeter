package com.app.plugins.speedtracker

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.Intent
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.os.Handler
import android.os.Looper
import android.content.pm.ApplicationInfo
import android.content.pm.PackageManager
import android.media.AudioManager
import android.media.ToneGenerator
import android.os.Build
import android.view.WindowManager
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.ui.nativerender.NativeElementBridge
import org.json.JSONObject
import java.io.File

/**
 * Native side of core's Geolocation facade (watchPosition()->background(),
 * drainWatch, trimWatch, stopBackgroundWatch).
 * Namespace: "Geolocation.*"
 */
object SpeedTrackerFunctions {

    /**
     * True when fine location is granted; otherwise asks for it and reports
     * `permission_required` to PHP so the screen can explain.
     */
    private fun ensureLocationPermission(activity: FragmentActivity, id: String, event: String): Boolean {
        if (ContextCompat.checkSelfPermission(activity, Manifest.permission.ACCESS_FINE_LOCATION)
            == PackageManager.PERMISSION_GRANTED
        ) {
            return true
        }

        val permissions = mutableListOf(
            Manifest.permission.ACCESS_FINE_LOCATION,
            Manifest.permission.ACCESS_COARSE_LOCATION,
        )
        if (Build.VERSION.SDK_INT >= 33) {
            permissions.add(Manifest.permission.POST_NOTIFICATIONS)
        }
        ActivityCompat.requestPermissions(activity, permissions.toTypedArray(), 4711)

        NativeElementBridge.sendNativeEvent(
            event,
            JSONObject(mapOf("success" to false, "error" to "permission_required", "id" to id)).toString(),
        )

        return false
    }

    /** Foreground-only watch for live speed while no trip is recording. */
    class WatchPosition(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val id = parameters["id"] as? String ?: ""
            val event = parameters["event"] as? String ?: SpeedTrackerService.DEFAULT_EVENT

            if (!ensureLocationPermission(activity, id, event)) {
                return mapOf("started" to false)
            }

            SpeedTrackerForegroundWatch.start(
                activity.applicationContext,
                id,
                event,
                (parameters["interval"] as? Number)?.toLong() ?: 1000L,
            )

            return mapOf("started" to true)
        }
    }

    /** Stops the foreground watch only; a recording trip keeps running. */
    class ClearWatch(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            SpeedTrackerForegroundWatch.stop(parameters["id"] as? String)

            return mapOf("cleared" to true)
        }
    }

    class StartBackgroundWatch(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val id = parameters["id"] as? String ?: ""
            val event = parameters["event"] as? String ?: SpeedTrackerService.DEFAULT_EVENT

            if (!ensureLocationPermission(activity, id, event)) {
                return mapOf("started" to false)
            }

            val intent = Intent(activity, SpeedTrackerService::class.java)
                .putExtra("id", id)
                .putExtra("event", event)
                .putExtra("interval", (parameters["interval"] as? Number)?.toLong() ?: 1000L)
                .putExtra("minDistance", (parameters["minDistance"] as? Number)?.toFloat() ?: 0f)

            ContextCompat.startForegroundService(activity, intent)

            return mapOf("started" to true)
        }
    }

    class StopBackgroundWatch(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            activity.stopService(Intent(activity, SpeedTrackerService::class.java))
            SpeedTrackerTrips.clearActive(activity)
            SpeedTrackerBuffer.init(activity)

            if (parameters["clearBuffer"] as? Boolean == true) {
                SpeedTrackerBuffer.clear()
            }

            return mapOf("stopped" to true)
        }
    }

    class DrainWatchBuffer(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val cursor = (parameters["cursor"] as? Number)?.toInt() ?: 0
            SpeedTrackerBuffer.init(activity)
            val (fixes, size) = SpeedTrackerBuffer.drain(cursor)

            return mapOf("fixes" to fixes, "cursor" to size, "size" to size)
        }
    }

    class PlayAlert(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val volume = ((parameters["volume"] as? Number)?.toInt() ?: 100).coerceIn(0, 100)
            if (volume == 0) {
                return mapOf("played" to false)
            }

            val tone = ToneGenerator(AudioManager.STREAM_NOTIFICATION, volume)
            tone.startTone(ToneGenerator.TONE_PROP_BEEP2, 500)
            Handler(Looper.getMainLooper()).postDelayed({ tone.release() }, 700)

            return mapOf("played" to true)
        }
    }

    class KeepScreenOn(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val enabled = parameters["enabled"] as? Boolean ?: true

            activity.runOnUiThread {
                if (enabled) {
                    activity.window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
                } else {
                    activity.window.clearFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
                }
            }

            return mapOf("enabled" to enabled)
        }
    }

    /** The installed build's version, for the About screen and update checks. */
    class AppInfo(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val info = activity.packageManager.getPackageInfo(activity.packageName, 0)
            val code = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) info.longVersionCode else @Suppress("DEPRECATION") info.versionCode.toLong()

            return mapOf(
                "version" to (info.versionName ?: ""),
                "code" to code,
                "debug" to ((activity.applicationInfo.flags and ApplicationInfo.FLAG_DEBUGGABLE) != 0),
            )
        }
    }

    class SetUnit(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            SpeedTrackerService.useMiles = parameters["unit"] == "mph"

            return mapOf("unit" to if (SpeedTrackerService.useMiles) "mph" else "kmh")
        }
    }

    class TrimWatchBuffer(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            SpeedTrackerBuffer.init(activity)
            SpeedTrackerBuffer.trim((parameters["upTo"] as? Number)?.toInt() ?: 0)

            return mapOf("trimmed" to true)
        }
    }
}

/**
 * Fix buffer so no fix is lost while PHP isn't listening. Mirrored to a JSON
 * lines file so trips recorded while the app is closed (widget, driving
 * detection) survive the process being killed.
 */
object SpeedTrackerBuffer {
    private val fixes = mutableListOf<Map<String, Any?>>()
    private var file: File? = null

    @Synchronized
    fun init(context: Context) {
        if (file != null) {
            return
        }

        val buffer = File(context.filesDir, "speedtracker-buffer.jsonl")
        file = buffer

        if (fixes.isEmpty() && buffer.isFile) {
            buffer.forEachLine { line ->
                runCatching { JSONObject(line) }.getOrNull()?.let { json ->
                    fixes.add(json.keys().asSequence().associateWith { key -> json.get(key).takeUnless { it == JSONObject.NULL } })
                }
            }
        }
    }

    @Synchronized
    fun add(fix: Map<String, Any?>) {
        fixes.add(fix)
        runCatching { file?.appendText(JSONObject(fix).toString() + "\n") }
    }

    @Synchronized
    fun drain(cursor: Int): Pair<List<Map<String, Any?>>, Int> =
        fixes.drop(cursor.coerceAtLeast(0)).toList() to fixes.size

    @Synchronized
    fun trim(upTo: Int) {
        fixes.subList(0, upTo.coerceIn(0, fixes.size)).clear()
        runCatching { file?.writeText(fixes.joinToString("") { JSONObject(it).toString() + "\n" }) }
    }

    @Synchronized
    fun clear() {
        fixes.clear()
        runCatching { file?.delete() }
    }
}

/** GPS listener bound to the app process, without a service or notification. */
object SpeedTrackerForegroundWatch : LocationListener {
    private var locationManager: LocationManager? = null
    private var watchId = ""
    private var eventName = SpeedTrackerService.DEFAULT_EVENT

    @SuppressLint("MissingPermission")
    fun start(context: Context, id: String, event: String, intervalMs: Long) {
        val manager = locationManager ?: (context.getSystemService(Context.LOCATION_SERVICE) as LocationManager)
        locationManager = manager
        watchId = id
        eventName = event

        Handler(Looper.getMainLooper()).post {
            manager.removeUpdates(this)
            manager.requestLocationUpdates(LocationManager.GPS_PROVIDER, intervalMs, 0f, this, Looper.getMainLooper())
        }
    }

    fun stop(id: String?) {
        if (id != null && id != watchId) {
            return
        }

        Handler(Looper.getMainLooper()).post { locationManager?.removeUpdates(this) }
    }

    override fun onLocationChanged(location: Location) {
        val payload = mapOf(
            "success" to true,
            "id" to watchId,
            "latitude" to location.latitude,
            "longitude" to location.longitude,
            "accuracy" to location.accuracy.toDouble(),
            "speed" to if (location.hasSpeed()) location.speed.toDouble() else null,
            "heading" to if (location.hasBearing()) location.bearing.toDouble() else null,
            "altitude" to if (location.hasAltitude()) location.altitude else null,
            "timestamp" to location.time,
            "provider" to location.provider,
        )

        NativeElementBridge.sendNativeEvent(eventName, JSONObject(payload).toString())
    }
}
