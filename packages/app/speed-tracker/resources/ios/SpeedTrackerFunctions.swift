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

// MARK: - Geolocation.* bridge functions

enum SpeedTrackerFunctions {

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

    class TrimWatchBuffer: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            SpeedTrackerLocation.shared.trim(upTo: parameters["upTo"] as? Int ?? 0)

            return ["trimmed": true]
        }
    }
}
