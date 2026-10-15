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

/**
 * Foreground service that keeps GPS running with the screen off and shows the
 * live speed in an ongoing notification (visible on the lock screen).
 */
class SpeedTrackerService : Service(), LocationListener {

    companion object {
        const val DEFAULT_EVENT = "Native\\Mobile\\Events\\Geolocation\\LocationUpdated"
        private const val CHANNEL_ID = "speed_tracker"
        private const val NOTIFICATION_ID = 4711

        /** Display unit for the notification; set from PHP via SpeedTracker.SetUnit. */
        @Volatile
        var useMiles = false
    }

    private var watchId = ""
    private var eventName = DEFAULT_EVENT
    private var distanceMeters = 0.0
    private var lastLocation: Location? = null

    private val locationManager by lazy { getSystemService(LOCATION_SERVICE) as LocationManager }
    private val notificationManager by lazy { getSystemService(NOTIFICATION_SERVICE) as NotificationManager }

    override fun onBind(intent: Intent?): IBinder? = null

    @SuppressLint("MissingPermission")
    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (intent != null) {
            watchId = intent.getStringExtra("id") ?: watchId
            eventName = intent.getStringExtra("event") ?: eventName
        }

        createChannel()

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            startForeground(NOTIFICATION_ID, buildNotification(0f), ServiceInfo.FOREGROUND_SERVICE_TYPE_LOCATION)
        } else {
            startForeground(NOTIFICATION_ID, buildNotification(0f))
        }

        locationManager.removeUpdates(this)
        locationManager.requestLocationUpdates(
            LocationManager.GPS_PROVIDER,
            intent?.getLongExtra("interval", 1000L) ?: 1000L,
            intent?.getFloatExtra("minDistance", 0f) ?: 0f,
            this,
            Looper.getMainLooper(),
        )

        return START_STICKY
    }

    override fun onLocationChanged(location: Location) {
        lastLocation?.let { distanceMeters += it.distanceTo(location) }
        lastLocation = location

        val speed: Float? = if (location.hasSpeed()) location.speed else null
        val fix = mapOf(
            "latitude" to location.latitude,
            "longitude" to location.longitude,
            "accuracy" to location.accuracy.toDouble(),
            "speed" to speed?.toDouble(),
            "heading" to if (location.hasBearing()) location.bearing.toDouble() else null,
            "timestamp" to location.time,
            "provider" to location.provider,
        )

        SpeedTrackerBuffer.add(fix)

        val payload = JSONObject(fix + mapOf("success" to true, "id" to watchId))
        NativeElementBridge.sendNativeEvent(eventName, payload.toString())

        notificationManager.notify(NOTIFICATION_ID, buildNotification(speed ?: 0f))
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
            .build()
    }
}
