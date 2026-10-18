package com.app.plugins.speedtracker

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.provider.OpenableColumns
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import androidx.fragment.app.Fragment
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.ui.nativerender.NativeElementBridge
import org.json.JSONObject
import java.io.File

/**
 * Backup files and reminders.
 * Namespace: "SpeedTracker.*"
 *
 * Save/open go through the system document picker (Storage Access Framework),
 * so no storage permission is needed. Results come back to PHP as events.
 */
object SpeedTrackerFileFunctions {

    const val FILE_SAVED_EVENT = "App\\SpeedTracker\\Events\\FileSaved"
    const val FILE_PICKED_EVENT = "App\\SpeedTracker\\Events\\FilePicked"

    private const val DIALOG_TAG = "speedtracker-file-dialog"
    private const val REMINDER_CHANNEL_ID = "speed_tracker_reminders"
    private const val REMINDER_NOTIFICATION_ID = 4712

    fun send(event: String, payload: Map<String, Any?>) {
        NativeElementBridge.sendNativeEvent(event, JSONObject(payload).toString())
    }

    /** Hosts the picker in a headless fragment so the result survives rotation. */
    private fun showDialog(activity: FragmentActivity, arguments: Bundle) {
        activity.runOnUiThread {
            val fragments = activity.supportFragmentManager
            fragments.findFragmentByTag(DIALOG_TAG)?.let {
                fragments.beginTransaction().remove(it).commitNowAllowingStateLoss()
            }
            fragments.beginTransaction()
                .add(SpeedTrackerFileDialogFragment().apply { this.arguments = arguments }, DIALOG_TAG)
                .commitAllowingStateLoss()
        }
    }

    class SaveFile(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val path = parameters["path"] as? String
            if (path == null || !File(path).isFile) {
                return mapOf("started" to false)
            }

            showDialog(activity, Bundle().apply {
                putString("mode", "save")
                putString("path", path)
                putString("name", parameters["name"] as? String ?: File(path).name)
            })

            return mapOf("started" to true)
        }
    }

    class PickFile(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            showDialog(activity, Bundle().apply { putString("mode", "pick") })

            return mapOf("started" to true)
        }
    }

    class Notify(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val context = activity.applicationContext

            if (Build.VERSION.SDK_INT >= 33 &&
                ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
            ) {
                return mapOf("posted" to false)
            }

            val manager = context.getSystemService(NotificationManager::class.java)
            manager.createNotificationChannel(
                NotificationChannel(REMINDER_CHANNEL_ID, "Reminders", NotificationManager.IMPORTANCE_DEFAULT),
            )

            val body = parameters["body"] as? String ?: ""
            val openApp = context.packageManager.getLaunchIntentForPackage(context.packageName)?.let {
                PendingIntent.getActivity(context, 0, it, PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT)
            }

            val notification = NotificationCompat.Builder(context, REMINDER_CHANNEL_ID)
                .setSmallIcon(android.R.drawable.ic_dialog_info)
                .setContentTitle(parameters["title"] as? String ?: "Tripometer")
                .setContentText(body)
                .setStyle(NotificationCompat.BigTextStyle().bigText(body))
                .setContentIntent(openApp)
                .setAutoCancel(true)
                .build()

            manager.notify(REMINDER_NOTIFICATION_ID, notification)

            return mapOf("posted" to true)
        }
    }
}

class SpeedTrackerFileDialogFragment : Fragment() {

    private val createDocument = registerForActivityResult(ActivityResultContracts.CreateDocument("application/json")) { uri ->
        finish(SpeedTrackerFileFunctions.FILE_SAVED_EVENT, if (uri == null) cancelled() else save(uri))
    }

    private val openDocument = registerForActivityResult(ActivityResultContracts.OpenDocument()) { uri ->
        finish(SpeedTrackerFileFunctions.FILE_PICKED_EVENT, if (uri == null) cancelled() else copyToCache(uri))
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // Recreated after rotation: the picker is already showing.
        if (savedInstanceState != null) {
            return
        }

        val arguments = requireArguments()
        if (arguments.getString("mode") == "save") {
            createDocument.launch(arguments.getString("name") ?: "tripometer.json")
        } else {
            // Many file managers label .json files as octet-stream or plain text.
            openDocument.launch(arrayOf("application/json", "text/*", "application/octet-stream"))
        }
    }

    private fun cancelled(): Map<String, Any?> = mapOf("cancelled" to true)

    private fun save(uri: Uri): Map<String, Any?> = try {
        val source = File(requireArguments().getString("path") ?: "")
        requireContext().contentResolver.openOutputStream(uri, "wt")?.use { output ->
            source.inputStream().use { it.copyTo(output) }
        } ?: throw IllegalStateException("Could not open the chosen location")

        mapOf("saved" to true)
    } catch (e: Exception) {
        mapOf("saved" to false, "error" to (e.message ?: "Could not save the file"))
    }

    private fun copyToCache(uri: Uri): Map<String, Any?> = try {
        val context = requireContext()
        val target = File(context.cacheDir, "picked-file.json")

        context.contentResolver.openInputStream(uri)?.use { input ->
            target.outputStream().use { input.copyTo(it) }
        } ?: throw IllegalStateException("Could not open the chosen file")

        mapOf("path" to target.absolutePath, "name" to displayName(context, uri))
    } catch (e: Exception) {
        mapOf("error" to (e.message ?: "Could not open the file"))
    }

    private fun displayName(context: Context, uri: Uri): String? =
        context.contentResolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME), null, null, null)?.use {
            if (it.moveToFirst()) it.getString(0) else null
        }

    private fun finish(event: String, payload: Map<String, Any?>) {
        SpeedTrackerFileFunctions.send(event, payload)
        parentFragmentManager.beginTransaction().remove(this).commitAllowingStateLoss()
    }
}
