package com.lstables.plugins.documentscanner

import android.app.Activity
import android.content.Context
import android.content.Intent
import android.graphics.pdf.PdfDocument
import android.os.Handler
import android.os.Looper
import androidx.activity.result.ActivityResult
import androidx.activity.result.IntentSenderRequest
import com.google.mlkit.vision.documentscanner.GmsDocumentScannerOptions
import com.google.mlkit.vision.documentscanner.GmsDocumentScannerOptions.RESULT_FORMAT_JPEG
import com.google.mlkit.vision.documentscanner.GmsDocumentScannerOptions.RESULT_FORMAT_PDF
import com.google.mlkit.vision.documentscanner.GmsDocumentScannerOptions.SCANNER_MODE_BASE
import com.google.mlkit.vision.documentscanner.GmsDocumentScannerOptions.SCANNER_MODE_BASE_WITH_FILTER
import com.google.mlkit.vision.documentscanner.GmsDocumentScannerOptions.SCANNER_MODE_FULL
import com.google.mlkit.vision.documentscanner.GmsDocumentScanning
import com.google.mlkit.vision.documentscanner.GmsDocumentScanningResult
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import com.nativephp.mobile.bridge.NativeActionCoordinator
import org.json.JSONObject
import java.io.File
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit

object DocumentScannerFunctions {

    class Scan : BridgeFunction {

        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val activity = NativeActionCoordinator.currentActivity
                ?: return BridgeResponse.error("No active activity found")

            val maxPages = (parameters["maxPages"] as? Number)?.toInt() ?: 0
            val allowGalleryImport = parameters["allowGalleryImport"] as? Boolean ?: false
            val mode = parameters["mode"] as? String ?: "full"
            val outputPdf = parameters["outputPdf"] as? Boolean ?: true
            val outputJpegs = parameters["outputJpegs"] as? Boolean ?: true

            val scannerMode = when (mode) {
                "base" -> SCANNER_MODE_BASE
                "filter" -> SCANNER_MODE_BASE_WITH_FILTER
                else -> SCANNER_MODE_FULL
            }

            val resultFormats = mutableListOf<Int>().apply {
                if (outputJpegs) add(RESULT_FORMAT_JPEG)
                if (outputPdf) add(RESULT_FORMAT_PDF)
            }

            val optionsBuilder = GmsDocumentScannerOptions.Builder()
                .setScannerMode(scannerMode)
                .setGalleryImportAllowed(allowGalleryImport)

            if (maxPages > 0) {
                optionsBuilder.setPageLimit(maxPages)
            }

            if (resultFormats.isNotEmpty()) {
                optionsBuilder.setResultFormats(*resultFormats.toIntArray())
            }

            val scanner = GmsDocumentScanning.getClient(optionsBuilder.build())

            // Use a latch to block the bridge thread until the async scanner completes
            val latch = CountDownLatch(1)
            var bridgeResult: Map<String, Any> = BridgeResponse.error("Scan timed out")

            Handler(Looper.getMainLooper()).post {
                scanner.getStartScanIntent(activity)
                    .addOnSuccessListener { intentSender ->
                        NativeActionCoordinator.launchActivityForResult(
                            IntentSenderRequest.Builder(intentSender).build()
                        ) { result: ActivityResult ->
                            bridgeResult = handleScanResult(result, activity, outputPdf, outputJpegs)
                            latch.countDown()
                        }
                    }
                    .addOnFailureListener { e ->
                        dispatchFailedEvent(activity, e.message ?: "Scanner initialisation failed")
                        bridgeResult = BridgeResponse.error(e.message ?: "Scanner initialisation failed")
                        latch.countDown()
                    }
            }

            // Wait up to 5 minutes for the user to complete scanning
            latch.await(300, TimeUnit.SECONDS)

            return bridgeResult
        }

        private fun handleScanResult(
            result: ActivityResult,
            activity: Activity,
            outputPdf: Boolean,
            outputJpegs: Boolean,
        ): Map<String, Any> {
            if (result.resultCode == Activity.RESULT_CANCELED) {
                dispatchCancelledEvent(activity)
                return BridgeResponse.success(
                    mapOf(
                        "cancelled" to true,
                        "pageCount" to 0,
                        "pages" to emptyList<String>(),
                        "pdf" to null,
                    )
                )
            }

            if (result.resultCode != Activity.RESULT_OK) {
                val reason = "Scanner returned unexpected result code: ${result.resultCode}"
                dispatchFailedEvent(activity, reason)
                return BridgeResponse.error(reason)
            }

            val scanningResult = GmsDocumentScanningResult.fromActivityResultIntent(result.data)
                ?: run {
                    val reason = "Failed to parse scanning result"
                    dispatchFailedEvent(activity, reason)
                    return BridgeResponse.error(reason)
                }

            val tempDir = File(activity.cacheDir, "NativeDocumentScanner").apply { mkdirs() }
            val jpegPaths = mutableListOf<String>()
            val pageCount = scanningResult.pages?.size ?: 0

            // Copy JPEG pages to our temp dir for stable paths
            if (outputJpegs) {
                scanningResult.pages?.forEachIndexed { index, page ->
                    try {
                        val src = File(page.imageUri.path ?: return@forEachIndexed)
                        val dest = File(tempDir, "page_${index + 1}.jpg")
                        src.copyTo(dest, overwrite = true)
                        jpegPaths.add(dest.absolutePath)
                    } catch (e: Exception) {
                        // Continue — collect as many pages as possible
                    }
                }
            }

            // Copy PDF to our temp dir for stable path
            var pdfPath: String? = null
            if (outputPdf) {
                scanningResult.pdf?.uri?.path?.let { srcPath ->
                    try {
                        val src = File(srcPath)
                        val dest = File(tempDir, "document.pdf")
                        src.copyTo(dest, overwrite = true)
                        pdfPath = dest.absolutePath
                    } catch (e: Exception) {
                        // PDF copy failed — continue without it
                    }
                }
            }

            val payload = mapOf(
                "cancelled" to false,
                "pageCount" to pageCount,
                "pages" to jpegPaths,
                "pdf" to pdfPath,
            )

            dispatchScannedEvent(activity, pageCount, jpegPaths, pdfPath)

            return BridgeResponse.success(payload)
        }

        // MARK: - Event dispatching

        private fun dispatchScannedEvent(
            activity: Activity,
            pageCount: Int,
            pages: List<String>,
            pdf: String?,
        ) {
            val payload = JSONObject().apply {
                put("pageCount", pageCount)
                put("pages", pages)
                put("pdf", pdf ?: JSONObject.NULL)
            }
            Handler(Looper.getMainLooper()).post {
                NativeActionCoordinator.dispatchEvent(
                    activity,
                    "lstables\\NativeDocumentScanner\\Events\\DocumentScanned",
                    payload.toString()
                )
            }
        }

        private fun dispatchCancelledEvent(activity: Activity) {
            Handler(Looper.getMainLooper()).post {
                NativeActionCoordinator.dispatchEvent(
                    activity,
                    "lstables\\NativeDocumentScanner\\Events\\DocumentScanCancelled",
                    JSONObject().toString()
                )
            }
        }

        private fun dispatchFailedEvent(activity: Activity, reason: String) {
            val payload = JSONObject().apply { put("reason", reason) }
            Handler(Looper.getMainLooper()).post {
                NativeActionCoordinator.dispatchEvent(
                    activity,
                    "lstables\\NativeDocumentScanner\\Events\\DocumentScanFailed",
                    payload.toString()
                )
            }
        }
    }
}
