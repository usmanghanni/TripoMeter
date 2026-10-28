package com.app.plugins.speedtracker

import android.annotation.SuppressLint
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Intent
import android.content.pm.ServiceInfo
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.os.Build
import android.os.IBinder
import android.os.Looper
import androidx.core.app.NotificationCompat
import com.nativephp.mobile.ui.nativerender.NativeElementBridge
import org.json.JSONObject
import java.util.Locale
import java.util.UUID

/**
 * Foreground service that keeps GPS running with the screen off and shows the
 * live speed in an ongoing notification (visible on the lock screen).
 */
class SpeedTrackerService : Service(), LocationListener {

    companion object {
        const val DEFAULT_EVENT = "Native\\Mobile\\Events\\Geolocation\\LocationUpdated"

        /** Start a trip natively (widget, driving detection); extra "source". */
        const val ACTION_START = "com.app.plugins.speedtracker.START_TRIP"

        /** Stop the recording trip natively (widget, notification). */
        const val ACTION_STOP = "com.app.plugins.speedtracker.STOP_TRIP"

        private const val CHANNEL_ID = "speed_tracker"
        private const val NOTIFICATION_ID = 4711

        /**
         * Driving detection sometimes fires while walking. An auto-started
         * trip must show this speed (20 km/h) on a few accurate fixes within
         * the confirm window, or it is stopped and discarded.
         */
        private const val AUTO_MIN_SPEED_MPS = 5.56f
        private const val AUTO_CONFIRM_FIXES = 3
        private const val AUTO_CONFIRM_MS = 3 * 60 * 1000L
        private const val MAX_ACCURACY_M = 30f

        /** Display unit for the notification; set from PHP via SpeedTracker.SetUnit. */
        @Volatile
        var useMiles = false
    }

    private var watchId = ""
    private var eventName = DEFAULT_EVENT
    private var distanceMeters = 0.0
    private var lastLocation: Location? = null

    /** Set while an auto-started trip hasn't yet shown driving speed. */
    private var awaitingDrivingSince: Long? = null
    private var drivingFixes = 0

    private val locationManager by lazy { getSystemService(LOCATION_SERVICE) as LocationManager }
    private val notificationManager by lazy { getSystemService(NOTIFICATION_SERVICE) as NotificationManager }

    override fun onBind(intent: Intent?): IBinder? = null

    @SuppressLint("MissingPermission")
    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        SpeedTrackerBuffer.init(this)
        createChannel()

        // Must come first: started with startForegroundService, even to stop.
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            startForeground(NOTIFICATION_ID, buildNotification(0f), ServiceInfo.FOREGROUND_SERVICE_TYPE_LOCATION)
        } else {
            startForeground(NOTIFICATION_ID, buildNotification(0f))
        }

        when (intent?.action) {
            ACTION_STOP -> {
                SpeedTrackerTrips.cancelPrompt(this)
                SpeedTrackerTrips.finishActive(this)
                stopSelf()

                return START_NOT_STICKY
            }
            ACTION_START -> {
                SpeedTrackerTrips.cancelPrompt(this)
                watchId = SpeedTrackerTrips.active(this)?.optString("id")?.takeIf { it.isNotEmpty() }
                    ?: UUID.randomUUID().toString()
                SpeedTrackerTrips.ensureActive(this, watchId, intent?.getStringExtra("source") ?: "widget")
            }
            else -> {
                if (intent != null) {
                    watchId = intent.getStringExtra("id") ?: watchId
                    eventName = intent.getStringExtra("event") ?: eventName
                }
                // Restarted by the system after a kill: carry on with the persisted trip.
                if (watchId.isEmpty()) {
                    watchId = SpeedTrackerTrips.active(this)?.optString("id") ?: ""
                }
                if (watchId.isNotEmpty()) {
                    SpeedTrackerTrips.ensureActive(this, watchId, "app")
                }
            }
        }

        // Survives restarts: the window counts from when the trip started.
        val active = SpeedTrackerTrips.active(this)
        awaitingDrivingSince = if (active?.optString("source") == "auto" && drivingFixes < AUTO_CONFIRM_FIXES) {
            active.optLong("started_at", System.currentTimeMillis())
        } else {
            null
        }

        try {
            locationManager.removeUpdates(this)
            locationManager.requestLocationUpdates(
                LocationManager.GPS_PROVIDER,
                intent?.getLongExtra("interval", 1000L) ?: 1000L,
                intent?.getFloatExtra("minDistance", 0f) ?: 0f,
                this,
                Looper.getMainLooper(),
            )
        } catch (e: SecurityException) {
            // Location permission was revoked; nothing can be recorded.
            SpeedTrackerTrips.finishActive(this)
            stopSelf()

            return START_NOT_STICKY
        }

        return START_STICKY
    }

    override fun onLocationChanged(location: Location) {
        lastLocation?.let { distanceMeters += it.distanceTo(location) }
        lastLocation = location

        val speed: Float? = if (location.hasSpeed()) location.speed else null

        if (discardIfNotDriving(speed, location.accuracy)) {
            return
        }
        val fix = mapOf(
            "latitude" to location.latitude,
            "longitude" to location.longitude,
            "accuracy" to location.accuracy.toDouble(),
            "speed" to speed?.toDouble(),
            "heading" to if (location.hasBearing()) location.bearing.toDouble() else null,
            "altitude" to if (location.hasAltitude()) location.altitude else null,
            "timestamp" to location.time,
            "provider" to location.provider,
            "watch_id" to watchId,
        )

        SpeedTrackerBuffer.add(fix)

        val payload = JSONObject(fix + mapOf("success" to true, "id" to watchId))
        NativeElementBridge.sendNativeEvent(eventName, payload.toString())

        notificationManager.notify(NOTIFICATION_ID, buildNotification(speed ?: 0f))
    }

    /**
     * For an auto-started trip still awaiting driving speed: count accurate
     * fixes at driving speed, and give up once the confirm window passes.
     *
     * @return true when the trip was stopped as not driving
     */
    private fun discardIfNotDriving(speed: Float?, accuracy: Float): Boolean {
        val since = awaitingDrivingSince ?: return false

        if (speed != null && speed >= AUTO_MIN_SPEED_MPS && accuracy <= MAX_ACCURACY_M) {
            drivingFixes++
            if (drivingFixes >= AUTO_CONFIRM_FIXES) {
                awaitingDrivingSince = null
            }

            return false
        }

        if (System.currentTimeMillis() - since < AUTO_CONFIRM_MS) {
            return false
        }

        awaitingDrivingSince = null
        locationManager.removeUpdates(this)
        SpeedTrackerTrips.finishActive(this, discard = true)
        stopSelf()

        return true
    }

    override fun onDestroy() {
        locationManager.removeUpdates(this)
        super.onDestroy()
    }

    private fun createChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            notificationManager.createNotificationChannel(
                NotificationChannel(CHANNEL_ID, "Trip recording", NotificationManager.IMPORTANCE_LOW),
            )
        }
    }

    private fun buildNotification(speedMps: Float): Notification {
        val openApp = packageManager.getLaunchIntentForPackage(packageName)?.let {
            PendingIntent.getActivity(this, 0, it, PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT)
        }

        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_menu_mylocation)
            .setContentTitle(
                if (useMiles) String.format(Locale.getDefault(), "%.0f mph", speedMps * 2.236936f)
                else String.format(Locale.getDefault(), "%.0f km/h", speedMps * 3.6f),
            )
            .setContentText(
                if (useMiles) String.format(Locale.getDefault(), "Recording trip · %.2f mi", distanceMeters / 1609.344)
                else String.format(Locale.getDefault(), "Recording trip · %.2f km", distanceMeters / 1000),
            )
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .setSilent(true)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
            .setCategory(NotificationCompat.CATEGORY_NAVIGATION)
            .setContentIntent(openApp)
            .addAction(
                android.R.drawable.ic_media_pause,
                "Stop trip",
                PendingIntent.getForegroundService(
                    this,
                    3,
                    SpeedTrackerTrips.serviceIntent(this, ACTION_STOP),
                    PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT,
                ),
            )
            .build()
    }
}
