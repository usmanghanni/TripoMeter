import Foundation
import UIKit
import UniformTypeIdentifiers
import UserNotifications

// MARK: - Document picker

/// Save/open through the Files picker; results go back to PHP as events.
final class SpeedTrackerDocuments: NSObject, UIDocumentPickerDelegate {
    static let shared = SpeedTrackerDocuments()

    static let fileSavedEvent = "App\\SpeedTracker\\Events\\FileSaved"
    static let filePickedEvent = "App\\SpeedTracker\\Events\\FilePicked"

    private var isSaving = false

    func save(path: String, name: String) {
        DispatchQueue.main.async {
            // Copy under the requested name so Files suggests it.
            let source = URL(fileURLWithPath: path)
            let named = FileManager.default.temporaryDirectory.appendingPathComponent(name)
            try? FileManager.default.removeItem(at: named)

            do {
                try FileManager.default.copyItem(at: source, to: named)
            } catch {
                self.send(Self.fileSavedEvent, ["saved": false, "error": error.localizedDescription])
                return
            }

            self.isSaving = true
            self.present(UIDocumentPickerViewController(forExporting: [named], asCopy: true))
        }
    }

    func pick() {
        DispatchQueue.main.async {
            self.isSaving = false
            self.present(UIDocumentPickerViewController(forOpeningContentTypes: [.json, .plainText, .data], asCopy: true))
        }
    }

    func documentPicker(_ controller: UIDocumentPickerViewController, didPickDocumentsAt urls: [URL]) {
        if isSaving {
            send(Self.fileSavedEvent, ["saved": true])
            return
        }

        guard let url = urls.first else {
            send(Self.filePickedEvent, ["cancelled": true])
            return
        }

        let target = FileManager.default.temporaryDirectory.appendingPathComponent("picked-file.json")

        do {
            try? FileManager.default.removeItem(at: target)
            try FileManager.default.copyItem(at: url, to: target)
            send(Self.filePickedEvent, ["path": target.path, "name": url.lastPathComponent])
        } catch {
            send(Self.filePickedEvent, ["error": error.localizedDescription])
        }
    }

    func documentPickerWasCancelled(_ controller: UIDocumentPickerViewController) {
        send(isSaving ? Self.fileSavedEvent : Self.filePickedEvent, ["cancelled": true])
    }

    private func present(_ picker: UIDocumentPickerViewController) {
        picker.delegate = self

        guard let presenter = Self.topViewController() else {
            send(isSaving ? Self.fileSavedEvent : Self.filePickedEvent, ["error": "No window to show the file picker"])
            return
        }

        presenter.present(picker, animated: true)
    }

    private static func topViewController() -> UIViewController? {
        let window = UIApplication.shared.connectedScenes
            .compactMap { $0 as? UIWindowScene }
            .flatMap { $0.windows }
            .first { $0.isKeyWindow }

        var top = window?.rootViewController
        while let presented = top?.presentedViewController {
            top = presented
        }

        return top
    }

    private func send(_ event: String, _ payload: [String: Any]) {
        guard let data = try? JSONSerialization.data(withJSONObject: payload),
              let json = String(data: data, encoding: .utf8) else { return }

        NativeElementBridge.sendNativeEvent(eventName: event, payloadJson: json)
    }
}

// MARK: - SpeedTracker.* file and reminder bridge functions

enum SpeedTrackerFileFunctions {

    class SaveFile: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let path = parameters["path"] as? String, FileManager.default.fileExists(atPath: path) else {
                return ["started": false]
            }

            SpeedTrackerDocuments.shared.save(
                path: path,
                name: parameters["name"] as? String ?? URL(fileURLWithPath: path).lastPathComponent
            )

            return ["started": true]
        }
    }

    class PickFile: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            SpeedTrackerDocuments.shared.pick()

            return ["started": true]
        }
    }

    class Notify: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let content = UNMutableNotificationContent()
            content.title = parameters["title"] as? String ?? "Tripometer"
            content.body = parameters["body"] as? String ?? ""
            content.sound = .default

            let center = UNUserNotificationCenter.current()
            center.requestAuthorization(options: [.alert, .sound]) { granted, _ in
                guard granted else { return }

                center.add(UNNotificationRequest(identifier: "speedtracker-reminder", content: content, trigger: nil))
            }

            return ["posted": true]
        }
    }
}
