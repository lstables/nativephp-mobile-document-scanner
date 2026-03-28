# Changelog

All notable changes to this package will be documented here.

## [1.0.0] — 2026-03-28

### Added
- Initial release
- iOS support via VisionKit `VNDocumentCameraViewController`
- Android support via Google ML Kit Document Scanner API
- `DocumentScanner::scan()` — full options
- `DocumentScanner::scanToPdf()` — convenience method
- `DocumentScanner::scanToJpegs()` — convenience method
- `DocumentScanner::scanSinglePage()` — convenience method
- `ScanResult` DTO with `hasPages()`, `hasPdf()`, `firstPage()`, `toArray()`
- `DocumentScanned` event
- `DocumentScanCancelled` event
- `DocumentScanFailed` event
- JavaScript bridge library (`resources/js/documentScanner.js`)
- Pest test suite
