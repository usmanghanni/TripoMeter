package com.app.plugins.speedtracker

import android.Manifest
import android.content.Intent
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

/**
 * Native side of core's Geolocation facade (watchPosition()->background(),
 * drainWatch, trimWatch, stopBackgroundWatch).
 * Namespace: "Geolocation.*"
 */
object SpeedTrackerFunctions {

    class StartBackgroundWatch(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val id = parameters["id"] as? String ?: ""
            val event = parameters["event"] as? String ?: SpeedTrackerService.DEFAULT_EVENT

            if (ContextCompat.checkSelfPermission(activity, Manifest.permission.ACCESS_FINE_LOCATION)
                != PackageManager.PERMISSION_GRANTED
            ) {
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

            if (parameters["clearBuffer"] as? Boolean == true) {
                SpeedTrackerBuffer.clear()
            }

            return mapOf("stopped" to true)
        }
    }

    class DrainWatchBuffer(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val cursor = (parameters["cursor"] as? Number)?.toInt() ?: 0
            val (fixes, size) = SpeedTrackerBuffer.drain(cursor)

            return mapOf("fixes" to fixes, "cursor" to size, "size" to size)
        }
    }

    class PlayAlert(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val tone = ToneGenerator(AudioManager.STREAM_NOTIFICATION, 100)
            tone.startTone(ToneGenerator.TONE_PROP_BEEP2, 500)
            android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({ tone.release() }, 700)

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

    class SetUnit(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            SpeedTrackerService.useMiles = parameters["unit"] == "mph"

            return mapOf("unit" to if (SpeedTrackerService.useMiles) "mph" else "kmh")
        }
    }

    class TrimWatchBuffer(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            SpeedTrackerBuffer.trim((parameters["upTo"] as? Number)?.toInt() ?: 0)

            return mapOf("trimmed" to true)
        }
    }
}

/**
 * In-memory fix buffer so no fix is lost while PHP isn't listening.
 * ponytail: memory only, a process kill loses unsynced fixes; persist to a file if that matters.
 */
object SpeedTrackerBuffer {
    private val fixes = mutableListOf<Map<String, Any?>>()

    @Synchronized
    fun add(fix: Map<String, Any?>) {
        fixes.add(fix)
    }

    @Synchronized
    fun drain(cursor: Int): Pair<List<Map<String, Any?>>, Int> =
        fixes.drop(cursor.coerceAtLeast(0)).toList() to fixes.size

    @Synchronized
    fun trim(upTo: Int) {
        fixes.subList(0, upTo.coerceIn(0, fixes.size)).clear()
    }

    @Synchronized
    fun clear() {
        fixes.clear()
    }
}
