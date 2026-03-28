import Foundation
import VisionKit
import UIKit
import PDFKit

enum DocumentScannerFunctions {

    class Scan: NSObject, BridgeFunction, VNDocumentCameraViewControllerDelegate {

        // Held strongly during the async scan session
        private var continuation: CheckedContinuation<[String: Any], Error>?
        private var options: ScanOptions = ScanOptions()

        struct ScanOptions {
            var maxPages: Int = 0
            var outputPdf: Bool = true
            var outputJpegs: Bool = true
        }

        func execute(parameters: [String: Any]) throws -> [String: Any] {
            options = ScanOptions(
                maxPages: parameters["maxPages"] as? Int ?? 0,
                outputPdf: parameters["outputPdf"] as? Bool ?? true,
                outputJpegs: parameters["outputJpegs"] as? Bool ?? true
            )

            guard VNDocumentCameraViewController.isSupported else {
                return BridgeResponse.error(message: "Document scanning is not supported on this device")
            }

            // Run the UI presentation on the main thread and await the async result
            let result = try runOnMainThreadSync {
                try await self.presentScanner()
            }

            return result
        }

        // MARK: - Scanner presentation

        private func presentScanner() async throws -> [String: Any] {
            return try await withCheckedThrowingContinuation { continuation in
                self.continuation = continuation

                let scanner = VNDocumentCameraViewController()
                scanner.delegate = self

                DispatchQueue.main.async {
                    guard let rootVC = UIApplication.shared.connectedScenes
                        .compactMap({ $0 as? UIWindowScene })
                        .flatMap({ $0.windows })
                        .first(where: { $0.isKeyWindow })?
                        .rootViewController else {
                        continuation.resume(throwing: ScannerError.noPresentingViewController)
                        return
                    }

                    var topVC = rootVC
                    while let presented = topVC.presentedViewController {
                        topVC = presented
                    }

                    topVC.present(scanner, animated: true)
                }
            }
        }

        // MARK: - VNDocumentCameraViewControllerDelegate

        func documentCameraViewController(
            _ controller: VNDocumentCameraViewController,
            didFinishWith scan: VNDocumentCameraScan
        ) {
            controller.dismiss(animated: true) {
                do {
                    let result = try self.buildResult(from: scan)
                    self.dispatchScannedEvent(result: result, scan: scan)
                    self.continuation?.resume(returning: BridgeResponse.success(data: result))
                } catch {
                    self.dispatchFailedEvent(reason: error.localizedDescription)
                    self.continuation?.resume(returning: BridgeResponse.error(message: error.localizedDescription))
                }
                self.continuation = nil
            }
        }

        func documentCameraViewControllerDidCancel(_ controller: VNDocumentCameraViewController) {
            controller.dismiss(animated: true) {
                self.dispatchCancelledEvent()
                self.continuation?.resume(returning: BridgeResponse.success(data: [
                    "cancelled": true,
                    "pageCount": 0,
                    "pages": [],
                    "pdf": NSNull()
                ]))
                self.continuation = nil
            }
        }

        func documentCameraViewController(
            _ controller: VNDocumentCameraViewController,
            didFailWithError error: Error
        ) {
            controller.dismiss(animated: true) {
                self.dispatchFailedEvent(reason: error.localizedDescription)
                self.continuation?.resume(returning: BridgeResponse.error(message: error.localizedDescription))
                self.continuation = nil
            }
        }

        // MARK: - Result building

        private func buildResult(from scan: VNDocumentCameraScan) throws -> [String: Any] {
            let tempDir = FileManager.default.temporaryDirectory
                .appendingPathComponent("NativeDocumentScanner", isDirectory: true)

            try? FileManager.default.createDirectory(at: tempDir, withIntermediateDirectories: true)

            let pageCount = options.maxPages > 0
                ? min(scan.pageCount, options.maxPages)
                : scan.pageCount

            var jpegPaths: [String] = []
            var images: [UIImage] = []

            // Collect page images
            for i in 0..<pageCount {
                let image = scan.imageOfPage(at: i)
                images.append(image)

                if options.outputJpegs {
                    guard let data = image.jpegData(compressionQuality: 0.92) else {
                        throw ScannerError.jpegConversionFailed
                    }
                    let path = tempDir.appendingPathComponent("page_\(i + 1).jpg")
                    try data.write(to: path)
                    jpegPaths.append(path.path)
                }
            }

            // Build PDF if requested
            var pdfPath: Any = NSNull()
            if options.outputPdf && !images.isEmpty {
                let pdfData = buildPdf(from: images)
                let pdfFile = tempDir.appendingPathComponent("document.pdf")
                try pdfData.write(to: pdfFile)
                pdfPath = pdfFile.path
            }

            return [
                "cancelled": false,
                "pageCount": pageCount,
                "pages": jpegPaths,
                "pdf": pdfPath
            ]
        }

        private func buildPdf(from images: [UIImage]) -> Data {
            let pdfDocument = PDFDocument()
            for (index, image) in images.enumerated() {
                if let page = PDFPage(image: image) {
                    pdfDocument.insert(page, at: index)
                }
            }
            return pdfDocument.dataRepresentation() ?? Data()
        }

        // MARK: - Event dispatching

        private func dispatchScannedEvent(result: [String: Any], scan: VNDocumentCameraScan) {
            let payload: [String: Any] = [
                "pageCount": result["pageCount"] ?? 0,
                "pages": result["pages"] ?? [],
                "pdf": result["pdf"] ?? NSNull()
            ]
            LaravelBridge.shared.send?(
                "lstables\\NativeDocumentScanner\\Events\\DocumentScanned",
                payload
            )
        }

        private func dispatchCancelledEvent() {
            LaravelBridge.shared.send?(
                "lstables\\NativeDocumentScanner\\Events\\DocumentScanCancelled",
                [:]
            )
        }

        private func dispatchFailedEvent(reason: String) {
            LaravelBridge.shared.send?(
                "lstables\\NativeDocumentScanner\\Events\\DocumentScanFailed",
                ["reason": reason]
            )
        }

        // MARK: - Helpers

        /// Run an async block synchronously — bridge functions must return synchronously.
        private func runOnMainThreadSync<T>(_ block: @escaping () async throws -> T) throws -> T {
            var result: Result<T, Error>?
            let semaphore = DispatchSemaphore(value: 0)

            Task { @MainActor in
                do {
                    result = .success(try await block())
                } catch {
                    result = .failure(error)
                }
                semaphore.signal()
            }

            semaphore.wait()

            switch result! {
            case .success(let value): return value
            case .failure(let error): throw error
            }
        }
    }
}

// MARK: - Errors

private enum ScannerError: LocalizedError {
    case noPresentingViewController
    case jpegConversionFailed

    var errorDescription: String? {
        switch self {
        case .noPresentingViewController:
            return "Could not find a view controller to present the scanner from"
        case .jpegConversionFailed:
            return "Failed to convert scanned page to JPEG"
        }
    }
}
