package com.app.plugins.speedtracker

import android.Manifest
import android.annotation.SuppressLint
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.appwidget.AppWidgetProvider
import android.content.BroadcastReceiver
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.SystemClock
import android.view.View
import android.widget.RemoteViews
import androidx.core.app.ActivityCompat
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import com.google.android.gms.location.ActivityRecognition
import com.google.android.gms.location.ActivityTransition
import com.google.android.gms.location.ActivityTransitionRequest
import com.google.android.gms.location.ActivityTransitionResult
import com.google.android.gms.location.DetectedActivity
import com.nativephp.mobile.bridge.BridgeFunction
import org.json.JSONArray
import org.json.JSONObject

/**
 * Trips the native side knows about, persisted so they survive the app being
 * closed: the one recording now ("active") and ones stopped natively (from the
 * widget, the notification or driving detection) that PHP hasn't picked up yet
 * ("finished"). PHP reads them with SpeedTracker.NativeTrips and acknowledges
 * each finished trip once it is saved.
 *
 * Sources: "app" (Start trip button), "widget", "auto" (driving detected).
 */
object SpeedTrackerTrips {

    private const val PREFS = "speedtracker_trips"
    private const val KEY_ACTIVE = "active"
    private const val KEY_FINISHED = "finished"
    private const val KEY_AUTO_RECORD = "auto_record"
    private const val KEY_AUTO_MODE = "auto_record_mode"

    private const val PROMPT_CHANNEL_ID = "speed_tracker_prompts"
    const val PROMPT_NOTIFICATION_ID = 4715

    private fun prefs(context: Context) = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    @Synchronized
    fun active(context: Context): JSONObject? =
        prefs(context).getString(KEY_ACTIVE, null)?.let { runCatching { JSONObject(it) }.getOrNull() }

    @Synchronized
    fun finished(context: Context): JSONArray =
        prefs(context).getString(KEY_FINISHED, null)?.let { runCatching { JSONArray(it) }.getOrNull() } ?: JSONArray()

    /** Record that trip [id] is recording; a different active trip is finished first. */
    @Synchronized
    fun ensureActive(context: Context, id: String, source: String) {
        val current = active(context)
        if (current?.optString("id") == id) {
            return
        }
        if (current != null) {
            finishActive(context)
        }

        prefs(context).edit()
            .putString(KEY_ACTIVE, JSONObject(mapOf("id" to id, "started_at" to System.currentTimeMillis(), "source" to source)).toString())
            .apply()
        SpeedTrackerWidget.refresh(context)
    }

    /** The active trip was stopped natively: keep it for PHP to finish. */
    @Synchronized
    fun finishActive(context: Context) {
        val current = active(context) ?: return
        val finished = finished(context).put(current.put("ended_at", System.currentTimeMillis()))

        prefs(context).edit()
            .remove(KEY_ACTIVE)
            .putString(KEY_FINISHED, finished.toString())
            .apply()
        SpeedTrackerWidget.refresh(context)
    }

    /** PHP stopped the trip itself; nothing to hand back. */
    @Synchronized
    fun clearActive(context: Context) {
        prefs(context).edit().remove(KEY_ACTIVE).apply()
        SpeedTrackerWidget.refresh(context)
    }

    @Synchronized
    fun acknowledge(context: Context, id: String) {
        val remaining = JSONArray()
        val finished = finished(context)
        for (index in 0 until finished.length()) {
            finished.optJSONObject(index)?.takeIf { it.optString("id") != id }?.let { remaining.put(it) }
        }

        prefs(context).edit().putString(KEY_FINISHED, remaining.toString()).apply()
    }

    fun isAutoRecordEnabled(context: Context): Boolean = prefs(context).getBoolean(KEY_AUTO_RECORD, false)

    fun setAutoRecordEnabled(context: Context, enabled: Boolean) {
        prefs(context).edit().putBoolean(KEY_AUTO_RECORD, enabled).apply()
    }

    /** "ask": notify and let the user start/stop; "auto": start and stop by itself. */
    fun autoRecordMode(context: Context): String = prefs(context).getString(KEY_AUTO_MODE, "ask") ?: "ask"

    fun setAutoRecordMode(context: Context, mode: String) {
        prefs(context).edit().putString(KEY_AUTO_MODE, if (mode == "auto") "auto" else "ask").apply()
    }

    private fun hasNotificationPermission(context: Context): Boolean =
        Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED

    /**
     * Heads-up notification asking to start (or stop) the trip, with the
     * action as a button. Tapping the button starts/stops the service directly.
     */
    @SuppressLint("MissingPermission")
    fun showPrompt(context: Context, start: Boolean) {
        if (!hasNotificationPermission(context)) {
            return
        }

        val manager = context.getSystemService(NotificationManager::class.java)
        manager.createNotificationChannel(
            NotificationChannel(PROMPT_CHANNEL_ID, "Driving detected", NotificationManager.IMPORTANCE_HIGH),
        )

        val action = PendingIntent.getForegroundService(
            context,
            if (start) 11 else 12,
            serviceIntent(context, if (start) SpeedTrackerService.ACTION_START else SpeedTrackerService.ACTION_STOP, "auto"),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT,
        )
        val dismiss = PendingIntent.getBroadcast(
            context,
            13,
            Intent(context, SpeedTrackerDrivingReceiver::class.java).setAction(SpeedTrackerDrivingReceiver.ACTION_DISMISS),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT,
        )
        val openApp = context.packageManager.getLaunchIntentForPackage(context.packageName)?.let {
            PendingIntent.getActivity(context, 0, it, PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT)
        }

        val notification = NotificationCompat.Builder(context, PROMPT_CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_menu_mylocation)
            .setContentTitle(if (start) "Looks like you're driving" else "Looks like you've stopped driving")
            .setContentText(if (start) "Record this trip?" else "Stop recording this trip?")
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_RECOMMENDATION)
            .setAutoCancel(true)
            .setContentIntent(openApp)
            .addAction(0, if (start) "Start trip" else "Stop trip", action)
            .addAction(0, if (start) "Not now" else "Keep recording", dismiss)
            // A start prompt is stale after a few minutes of driving.
            .setTimeoutAfter(if (start) 10 * 60 * 1000L else 30 * 60 * 1000L)
            .build()

        manager.notify(PROMPT_NOTIFICATION_ID, notification)
    }

    fun cancelPrompt(context: Context) {
        context.getSystemService(NotificationManager::class.java).cancel(PROMPT_NOTIFICATION_ID)
    }

    fun serviceIntent(context: Context, action: String, source: String? = null): Intent =
        Intent(context, SpeedTrackerService::class.java).setAction(action).apply {
            source?.let { putExtra("source", it) }
        }

    fun startFromBackground(context: Context, source: String) {
        ContextCompat.startForegroundService(context, serviceIntent(context, SpeedTrackerService.ACTION_START, source))
    }

    fun stopFromBackground(context: Context) {
        finishActive(context)
        context.stopService(Intent(context, SpeedTrackerService::class.java))
    }

    fun hasLocationPermission(context: Context): Boolean =
        ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED

    private fun hasActivityPermission(context: Context): Boolean =
        Build.VERSION.SDK_INT < Build.VERSION_CODES.Q ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.ACTIVITY_RECOGNITION) == PackageManager.PERMISSION_GRANTED

    private fun hasBackgroundLocationPermission(context: Context): Boolean =
        Build.VERSION.SDK_INT < Build.VERSION_CODES.Q ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_BACKGROUND_LOCATION) == PackageManager.PERMISSION_GRANTED

    /** Driving detection: Google Play services activity transitions for IN_VEHICLE. */
    @SuppressLint("MissingPermission")
    fun registerDrivingDetection(context: Context): Boolean {
        if (!hasActivityPermission(context) || !hasLocationPermission(context)) {
            return false
        }

        val transitions = listOf(ActivityTransition.ACTIVITY_TRANSITION_ENTER, ActivityTransition.ACTIVITY_TRANSITION_EXIT).map {
            ActivityTransition.Builder()
                .setActivityType(DetectedActivity.IN_VEHICLE)
                .setActivityTransition(it)
                .build()
        }

        ActivityRecognition.getClient(context)
            .requestActivityTransitionUpdates(ActivityTransitionRequest(transitions), drivingIntent(context))

        return true
    }

    @SuppressLint("MissingPermission")
    fun unregisterDrivingDetection(context: Context) {
        runCatching { ActivityRecognition.getClient(context).removeActivityTransitionUpdates(drivingIntent(context)) }
    }

    private fun drivingIntent(context: Context): PendingIntent {
        // Activity recognition fills in the intent, so it must be mutable.
        val mutable = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) PendingIntent.FLAG_MUTABLE else 0

        return PendingIntent.getBroadcast(
            context,
            0,
            Intent(context, SpeedTrackerDrivingReceiver::class.java).setAction(SpeedTrackerDrivingReceiver.ACTION),
            PendingIntent.FLAG_UPDATE_CURRENT or mutable,
        )
    }

    // MARK: - Bridge functions

    class NativeTrips(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val finished = finished(activity)

            return mapOf(
                "active" to (active(activity)?.let { toMap(it) } ?: false),
                "finished" to (0 until finished.length()).mapNotNull { index -> finished.optJSONObject(index)?.let { toMap(it) } },
                "auto_record" to isAutoRecordEnabled(activity),
            )
        }
    }

    class AcknowledgeNativeTrip(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            (parameters["id"] as? String)?.let { acknowledge(activity, it) }

            return mapOf("acknowledged" to true)
        }
    }

    /**
     * Turn driving detection on or off. With `request` true, missing
     * permissions are asked for and the status says which step is pending.
     */
    class SetAutoRecord(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val enabled = parameters["enabled"] as? Boolean ?: false
            val request = parameters["request"] as? Boolean ?: true
            val mode = parameters["mode"] as? String ?: "ask"
            setAutoRecordMode(activity, mode)

            if (!enabled) {
                setAutoRecordEnabled(activity, false)
                unregisterDrivingDetection(activity)

                return mapOf("status" to "disabled")
            }

            val missing = mutableListOf<String>()
            if (!hasLocationPermission(activity)) {
                missing += listOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION)
            }
            if (!hasActivityPermission(activity)) {
                missing += Manifest.permission.ACTIVITY_RECOGNITION
            }
            // "Ask me" works through a notification.
            if (mode != "auto" && !hasNotificationPermission(activity)) {
                missing += Manifest.permission.POST_NOTIFICATIONS
            }
            if (missing.isNotEmpty()) {
                if (request) {
                    ActivityCompat.requestPermissions(activity, missing.toTypedArray(), 4713)
                }

                return mapOf("status" to "permission_required")
            }

            // Asked separately: Android only offers "Allow all the time" on its own.
            if (!hasBackgroundLocationPermission(activity)) {
                if (request) {
                    ActivityCompat.requestPermissions(activity, arrayOf(Manifest.permission.ACCESS_BACKGROUND_LOCATION), 4714)
                }

                return mapOf("status" to "background_location_required")
            }

            return try {
                registerDrivingDetection(activity)
                setAutoRecordEnabled(activity, true)
                mapOf("status" to "enabled")
            } catch (e: Exception) {
                mapOf("status" to "unavailable", "error" to (e.message ?: "Google Play services unavailable"))
            }
        }
    }

    private fun toMap(json: JSONObject): Map<String, Any> =
        json.keys().asSequence().associateWith { json.get(it) }
}

/**
 * IN_VEHICLE enter/exit. In "ask" mode it notifies with Start/Stop buttons;
 * in "auto" mode it starts and stops the trip itself. Only trips that driving
 * detection started (source "auto") are ever stopped by it.
 */
class SpeedTrackerDrivingReceiver : BroadcastReceiver() {

    companion object {
        const val ACTION = "com.app.plugins.speedtracker.DRIVING_TRANSITION"
        const val ACTION_DISMISS = "com.app.plugins.speedtracker.DISMISS_PROMPT"
    }

    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action == ACTION_DISMISS) {
            SpeedTrackerTrips.cancelPrompt(context)

            return
        }

        if (!SpeedTrackerTrips.isAutoRecordEnabled(context) || !ActivityTransitionResult.hasResult(intent)) {
            return
        }

        val result = ActivityTransitionResult.extractResult(intent) ?: return
        val last = result.transitionEvents.lastOrNull { it.activityType == DetectedActivity.IN_VEHICLE } ?: return
        val active = SpeedTrackerTrips.active(context)
        val asks = SpeedTrackerTrips.autoRecordMode(context) != "auto"

        if (last.transitionType == ActivityTransition.ACTIVITY_TRANSITION_ENTER && active == null) {
            if (asks) {
                SpeedTrackerTrips.showPrompt(context, start = true)
            } else {
                runCatching { SpeedTrackerTrips.startFromBackground(context, "auto") }
            }
        } else if (last.transitionType == ActivityTransition.ACTIVITY_TRANSITION_EXIT) {
            when {
                active?.optString("source") != "auto" -> SpeedTrackerTrips.cancelPrompt(context)
                asks -> SpeedTrackerTrips.showPrompt(context, start = false)
                else -> SpeedTrackerTrips.stopFromBackground(context)
            }
        }
    }
}

/** Driving detection registrations don't survive a reboot or an app update. */
class SpeedTrackerBootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (SpeedTrackerTrips.isAutoRecordEnabled(context)) {
            runCatching { SpeedTrackerTrips.registerDrivingDetection(context) }
        }
        SpeedTrackerWidget.refresh(context)
    }
}

/**
 * Home screen widget: recording state, elapsed time and a Start/Stop button.
 * Its layout and provider XML are installed by the plugin's build hook, so
 * resources are looked up by name.
 */
class SpeedTrackerWidget : AppWidgetProvider() {

    override fun onUpdate(context: Context, appWidgetManager: AppWidgetManager, appWidgetIds: IntArray) {
        refresh(context)
    }

    companion object {
        fun refresh(context: Context) {
            val manager = AppWidgetManager.getInstance(context)
            val ids = manager.getAppWidgetIds(ComponentName(context, SpeedTrackerWidget::class.java))
            val layout = id(context, "speedtracker_widget", "layout")

            if (ids.isEmpty() || layout == 0) {
                return
            }

            val active = SpeedTrackerTrips.active(context)
            val views = RemoteViews(context.packageName, layout)
            val status = id(context, "speedtracker_widget_status", "id")
            val timer = id(context, "speedtracker_widget_timer", "id")
            val button = id(context, "speedtracker_widget_button", "id")
            val root = id(context, "speedtracker_widget_root", "id")

            if (active != null) {
                val elapsed = System.currentTimeMillis() - active.optLong("started_at", System.currentTimeMillis())
                views.setTextViewText(status, if (active.optString("source") == "auto") "Recording (auto)" else "Recording")
                views.setViewVisibility(timer, View.VISIBLE)
                views.setChronometer(timer, SystemClock.elapsedRealtime() - elapsed, null, true)
                views.setTextViewText(button, "Stop")
            } else {
                views.setTextViewText(status, "Not recording")
                views.setViewVisibility(timer, View.GONE)
                views.setChronometer(timer, SystemClock.elapsedRealtime(), null, false)
                views.setTextViewText(button, "Start")
            }

            val action = if (active != null) SpeedTrackerService.ACTION_STOP else SpeedTrackerService.ACTION_START
            views.setOnClickPendingIntent(
                button,
                PendingIntent.getForegroundService(
                    context,
                    if (active != null) 2 else 1,
                    SpeedTrackerTrips.serviceIntent(context, action, "widget"),
                    PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT,
                ),
            )

            context.packageManager.getLaunchIntentForPackage(context.packageName)?.let {
                views.setOnClickPendingIntent(
                    root,
                    PendingIntent.getActivity(context, 0, it, PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT),
                )
            }

            manager.updateAppWidget(ids, views)
        }

        private fun id(context: Context, name: String, type: String): Int =
            context.resources.getIdentifier(name, type, context.packageName)
    }
}
