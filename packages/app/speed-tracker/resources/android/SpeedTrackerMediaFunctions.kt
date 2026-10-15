package com.app.plugins.speedtracker

import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.media.MediaMetadata
import android.media.session.MediaController
import android.media.session.MediaSessionManager
import android.media.session.PlaybackState
import android.provider.Settings
import android.service.notification.NotificationListenerService
import androidx.core.app.NotificationManagerCompat
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction

/**
 * Exists only so the user can grant "Notification access", which is what
 * Android requires before an app may see other apps' media sessions. It does
 * not read or store notifications.
 */
class SpeedTrackerMediaListener : NotificationListenerService()

/**
 * Now playing + transport controls for whichever app owns the active media
 * session (Spotify, YouTube Music, ...).
 * Namespace: "SpeedTracker.*"
 */
object SpeedTrackerMediaFunctions {

    private fun hasAccess(context: Context): Boolean =
        NotificationManagerCompat.getEnabledListenerPackages(context).contains(context.packageName)

    /** The playing session if there is one, else the most recent session. */
    private fun activeController(context: Context): MediaController? {
        if (!hasAccess(context)) {
            return null
        }

        return try {
            val manager = context.getSystemService(MediaSessionManager::class.java)
            val sessions = manager.getActiveSessions(ComponentName(context, SpeedTrackerMediaListener::class.java))

            sessions.firstOrNull { it.playbackState?.state == PlaybackState.STATE_PLAYING } ?: sessions.firstOrNull()
        } catch (e: SecurityException) {
            null
        }
    }

    class NowPlaying(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            if (!hasAccess(activity)) {
                return mapOf("supported" to true, "access" to false, "active" to false)
            }

            val controller = activeController(activity)
                ?: return mapOf("supported" to true, "access" to true, "active" to false)

            val metadata = controller.metadata
            val appName = try {
                activity.packageManager.getApplicationLabel(
                    activity.packageManager.getApplicationInfo(controller.packageName, 0),
                ).toString()
            } catch (e: Exception) {
                controller.packageName
            }

            return mapOf(
                "supported" to true,
                "access" to true,
                "active" to true,
                "playing" to (controller.playbackState?.state == PlaybackState.STATE_PLAYING),
                "title" to (metadata?.getString(MediaMetadata.METADATA_KEY_TITLE) ?: ""),
                "artist" to (
                    metadata?.getString(MediaMetadata.METADATA_KEY_ARTIST)
                        ?: metadata?.getString(MediaMetadata.METADATA_KEY_ALBUM_ARTIST)
                        ?: ""
                    ),
                "app" to appName,
            )
        }
    }

    class MediaCommand(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val controller = activeController(activity) ?: return mapOf("ok" to false)
            val controls = controller.transportControls
            val isPlaying = controller.playbackState?.state == PlaybackState.STATE_PLAYING

            when (parameters["command"] as? String) {
                "play" -> controls.play()
                "pause" -> controls.pause()
                "toggle" -> if (isPlaying) controls.pause() else controls.play()
                "next" -> controls.skipToNext()
                "previous" -> controls.skipToPrevious()
                else -> return mapOf("ok" to false)
            }

            return mapOf("ok" to true)
        }
    }

    class OpenMediaAccessSettings(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            activity.startActivity(
                Intent(Settings.ACTION_NOTIFICATION_LISTENER_SETTINGS).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
            )

            return mapOf("opened" to true)
        }
    }
}
