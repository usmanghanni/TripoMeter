import Foundation
import CoreLocation
import AudioToolbox
import UIKit

// MARK: - Location engine

/// Keeps CoreLocation running in the background (UIBackgroundModes: location,
/// started while in use) and buffers fixes until PHP drains them.
/// ponytail: memory-only buffer, a kill loses unsynced fixes; persist to a file if that matters.
final class SpeedTrackerLocation: NSObject, CLLocationManagerDelegate {
    static let shared = SpeedTrackerLocation()

    private let manager = CLLocationManager()
    private let lock = NSLock()
    private var fixes: [[String: Any]] = []
    private var watchId = ""
    private var eventName = "Native\\Mobile\\Events\\Geolocation\\LocationUpdated"

    func start(id: String, event: String) {
        DispatchQueue.main.async {
            self.watchId = id
            self.eventName = event
            self.manager.delegate = self
            self.manager.desiredAccuracy = kCLLocationAccuracyBestForNavigation
            self.manager.activityType = .otherNavigation
            self.manager.pausesLocationUpdatesAutomatically = false
            self.manager.allowsBackgroundLocationUpdates = true
            self.manager.showsBackgroundLocationIndicator = true

            if self.manager.authorizationStatus == .notDetermined {
                self.manager.requestWhenInUseAuthorization()
            }

            self.manager.startUpdatingLocation()
        }
    }

    func stop(clearBuffer: Bool) {
        DispatchQueue.main.async { self.manager.stopUpdatingLocation() }

        if clearBuffer {
            lock.lock(); fixes.removeAll(); lock.unlock()
        }
    }

    func drain(cursor: Int) -> (fixes: [[String: Any]], size: Int) {
        lock.lock(); defer { lock.unlock() }

        return (Array(fixes.dropFirst(max(0, cursor))), fixes.count)
    }

    func trim(upTo: Int) {
        lock.lock(); defer { lock.unlock() }
        fixes.removeFirst(min(max(0, upTo), fixes.count))
    }

    func locationManager(_ manager: CLLocationManager, didUpdateLocations locations: [CLLocation]) {
        for location in locations {
            let fix: [String: Any] = [
                "latitude": location.coordinate.latitude,
                "longitude": location.coordinate.longitude,
                "accuracy": location.horizontalAccuracy,
                "speed": location.speed >= 0 ? location.speed : NSNull(),
                "heading": location.course >= 0 ? location.course : NSNull(),
                "altitude": location.verticalAccuracy >= 0 ? location.altitude : NSNull(),
                "timestamp": Int(location.timestamp.timeIntervalSince1970 * 1000),
                "provider": "gps",
            ]

            lock.lock(); fixes.append(fix); lock.unlock()

            send(fix.merging(["success": true, "id": watchId]) { _, new in new })
        }
    }

    func locationManager(_ manager: CLLocationManager, didFailWithError error: Error) {
        send(["success": false, "error": error.localizedDescription, "id": watchId])
    }

    func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
        if manager.authorizationStatus == .denied || manager.authorizationStatus == .restricted {
            send(["success": false, "error": "permission_denied", "id": watchId])
        }
    }

    private func send(_ payload: [String: Any]) {
        guard let data = try? JSONSerialization.data(withJSONObject: payload),
              let json = String(data: data, encoding: .utf8) else { return }

        NativeElementBridge.sendNativeEvent(eventName: eventName, payloadJson: json)
    }
}

// MARK: - Foreground watch

/// Live speed while no trip is recording: no background updates, stops with the screen.
final class SpeedTrackerForegroundWatch: NSObject, CLLocationManagerDelegate {
    static let shared = SpeedTrackerForegroundWatch()

    private let manager = CLLocationManager()
    private var watchId = ""
    private var eventName = "Native\\Mobile\\Events\\Geolocation\\LocationUpdated"

    func start(id: String, event: String) {
        DispatchQueue.main.async {
            self.watchId = id
            self.eventName = event
            self.manager.delegate = self
            self.manager.desiredAccuracy = kCLLocationAccuracyBestForNavigation
            self.manager.activityType = .otherNavigation

            if self.manager.authorizationStatus == .notDetermined {
                self.manager.requestWhenInUseAuthorization()
            }

            self.manager.startUpdatingLocation()
        }
    }

    func stop(id: String?) {
        DispatchQueue.main.async {
            if id == nil || id == self.watchId {
                self.manager.stopUpdatingLocation()
            }
        }
    }

    func locationManager(_ manager: CLLocationManager, didUpdateLocations locations: [CLLocation]) {
        for location in locations {
            send([
                "success": true,
                "id": watchId,
                "latitude": location.coordinate.latitude,
                "longitude": location.coordinate.longitude,
                "accuracy": location.horizontalAccuracy,
                "speed": location.speed >= 0 ? location.speed : NSNull(),
                "heading": location.course >= 0 ? location.course : NSNull(),
                "altitude": location.verticalAccuracy >= 0 ? location.altitude : NSNull(),
                "timestamp": Int(location.timestamp.timeIntervalSince1970 * 1000),
                "provider": "gps",
            ])
        }
    }

    func locationManager(_ manager: CLLocationManager, didFailWithError error: Error) {
        send(["success": false, "error": error.localizedDescription, "id": watchId])
    }

    func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
        if manager.authorizationStatus == .denied || manager.authorizationStatus == .restricted {
            send(["success": false, "error": "permission_denied", "id": watchId])
        }
    }

    private func send(_ payload: [String: Any]) {
        guard let data = try? JSONSerialization.data(withJSONObject: payload),
              let json = String(data: data, encoding: .utf8) else { return }

        NativeElementBridge.sendNativeEvent(eventName: eventName, payloadJson: json)
    }
}

// MARK: - Geolocation.* bridge functions

enum SpeedTrackerFunctions {

    class WatchPosition: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            SpeedTrackerForegroundWatch.shared.start(
                id: parameters["id"] as? String ?? "",
                event: parameters["event"] as? String ?? "Native\\Mobile\\Events\\Geolocation\\LocationUpdated"
            )

            return ["started": true]
        }
    }

    /// Stops the foreground watch only; a recording trip keeps running.
    class ClearWatch: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            SpeedTrackerForegroundWatch.shared.stop(id: parameters["id"] as? String)

            return ["cleared": true]
        }
    }

    class StartBackgroundWatch: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            SpeedTrackerLocation.shared.start(
                id: parameters["id"] as? String ?? "",
                event: parameters["event"] as? String ?? "Native\\Mobile\\Events\\Geolocation\\LocationUpdated"
            )

            return ["started": true]
        }
    }

    class StopBackgroundWatch: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            SpeedTrackerLocation.shared.stop(clearBuffer: parameters["clearBuffer"] as? Bool ?? false)

            return ["stopped": true]
        }
    }

    class DrainWatchBuffer: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let result = SpeedTrackerLocation.shared.drain(cursor: parameters["cursor"] as? Int ?? 0)

            return ["fixes": result.fixes, "cursor": result.size, "size": result.size]
        }
    }

    class PlayAlert: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            // 1005 = system "alarm" tone; plays alongside the location background mode.
            AudioServicesPlaySystemSound(1005)

            return ["played": true]
        }
    }

    class KeepScreenOn: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let enabled = parameters["enabled"] as? Bool ?? true

            // Only affects the foreground; iOS ignores it once the app is backgrounded.
            DispatchQueue.main.async { UIApplication.shared.isIdleTimerDisabled = enabled }

            return ["enabled": enabled]
        }
    }

    /// No-op on iOS: there is no trip notification; kept so the PHP call is portable.
    class SetUnit: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            return ["unit": parameters["unit"] as? String ?? "kmh"]
        }
    }

    /// iOS doesn't expose other apps' playback (e.g. Spotify) to third-party apps.
    class NowPlaying: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            return ["supported": false, "access": false, "active": false]
        }
    }

    class MediaCommand: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            return ["ok": false]
        }
    }

    class AppInfo: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let info = Bundle.main.infoDictionary ?? [:]
            #if DEBUG
            let debug = true
            #else
            let debug = false
            #endif

            return [
                "version": info["CFBundleShortVersionString"] as? String ?? "",
                "code": Int(info["CFBundleVersion"] as? String ?? "") ?? 0,
                "debug": debug,
            ]
        }
    }

    /// Android only: the widget and driving detection start trips natively.
    class NativeTrips: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            return ["active": false, "finished": [], "auto_record": false, "supported": false]
        }
    }

    class AcknowledgeNativeTrip: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            return ["acknowledged": true]
        }
    }

    class SetAutoRecord: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            return ["status": "unavailable", "error": "Automatic recording is only available on Android"]
        }
    }

    /// iOS doesn't let apps change the system volume; use the side buttons.
    class AdjustVolume: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            return ["supported": false]
        }
    }

    class OpenMediaAccessSettings: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            return ["opened": false]
        }
    }

    class TrimWatchBuffer: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            SpeedTrackerLocation.shared.trim(upTo: parameters["upTo"] as? Int ?? 0)

            return ["trimmed": true]
        }
    }
}
