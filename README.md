# NativePHP Mobile Document Scanner

Native document scanning for NativePHP Mobile apps — powered by **VisionKit** on iOS and **ML Kit** on Android.

Scan physical documents from the camera with automatic edge detection, perspective correction, multi-page support, and PDF output. The same scanner engine used by Apple Notes and Google Drive, wrapped in a clean Laravel facade.

---

## Requirements

- PHP 8.2+
- NativePHP Mobile 3.x
- iOS 13.0+ (VisionKit — zero additional dependencies)
- Android API 21+ with at least 1.7 GB RAM (ML Kit via Google Play Services)

---

## Installation

```bash
composer require lstables/nativephp-mobile-document-scanner
```

Register the plugin with NativePHP:

```bash
php artisan native:plugin:register lstables/nativephp-mobile-document-scanner
```

---

## Usage

### Basic scan

```php
use lstables\NativeDocumentScanner\Facades\DocumentScanner;

$result = DocumentScanner::scan();

if ($result && $result->hasPages()) {
    // Array of absolute file paths to JPEG pages
    foreach ($result->pages as $path) {
        // upload, store, process...
    }

    // Combined PDF path (if outputPdf was true)
    if ($result->hasPdf()) {
        Storage::put('scans/document.pdf', file_get_contents($result->pdf));
    }
}
```

### Scan to PDF only

```php
$pdfPath = DocumentScanner::scanToPdf();

if ($pdfPath) {
    Storage::put('receipts/scan.pdf', file_get_contents($pdfPath));
}
```

### Scan to JPEG pages only

```php
$pages = DocumentScanner::scanToJpegs(maxPages: 3);
```

### Scan a single page

```php
$imagePath = DocumentScanner::scanSinglePage();
```

### Full options

```php
$result = DocumentScanner::scan(
    maxPages: 10,           // 0 = unlimited
    allowGalleryImport: true,  // Android only — allow importing from photo library
    mode: 'full',           // 'base' | 'filter' | 'full'
    outputPdf: true,        // Include a combined PDF
    outputJpegs: true,      // Include per-page JPEGs
);
```

**Scanning modes:**

| Mode | What it includes |
|---|---|
| `base` | Crop, rotate, reorder pages |
| `filter` | + Greyscale, auto-enhancement filters |
| `full` | + ML-powered stain/shadow/finger removal (default) |

---

## Listening to events

Events fire whether you use the facade or the JS bridge, and are received by your Livewire components:

```php
use lstables\NativeDocumentScanner\Events\DocumentScanned;
use lstables\NativeDocumentScanner\Events\DocumentScanCancelled;
use lstables\NativeDocumentScanner\Events\DocumentScanFailed;

#[OnNative(DocumentScanned::class)]
public function onScanned(int $pageCount, array $pages, ?string $pdf): void
{
    // Store, upload, or process scanned files
}

#[OnNative(DocumentScanCancelled::class)]
public function onCancelled(): void
{
    // User tapped Cancel
}

#[OnNative(DocumentScanFailed::class)]
public function onFailed(string $reason): void
{
    // Something went wrong
}
```

---

## JavaScript usage

For Livewire + Alpine or SPA setups, use the JS bridge directly:

```js
import { scan, scanToPdf, scanToJpegs, scanSinglePage } from 'vendor/lstables/nativephp-mobile-document-scanner/resources/js/documentScanner'

// Full scan
const result = await scan({ maxPages: 5, mode: 'full' })
if (result && !result.cancelled) {
    console.log(result.pages)     // ['/path/to/page_1.jpg', ...]
    console.log(result.pdf)       // '/path/to/document.pdf'
    console.log(result.pageCount) // 5
}

// Just a PDF
const pdfPath = await scanToPdf()

// Just JPEG pages
const pages = await scanToJpegs(3)

// Single page
const page = await scanSinglePage()
```

---

## ScanResult

| Property | Type | Description |
|---|---|---|
| `pages` | `string[]` | Absolute paths to JPEG files, one per page |
| `pdf` | `string\|null` | Absolute path to combined PDF, or null |
| `pageCount` | `int` | Number of pages scanned |
| `hasPages()` | `bool` | Whether any pages were captured |
| `hasPdf()` | `bool` | Whether a PDF was generated |
| `firstPage()` | `string\|null` | Path to the first page, or null |
| `toArray()` | `array` | Serialise to plain array |

---

## File locations

Scanned files are written to the device's temporary/cache directory under `NativeDocumentScanner/`. They persist until the OS clears the cache. Copy them to permanent storage (Laravel's `Storage` facade, or a file upload) before the session ends.

```php
$result = DocumentScanner::scanToPdf();

// Move to Laravel storage
Storage::disk('local')->put(
    'documents/scan_' . now()->timestamp . '.pdf',
    file_get_contents($result)
);
```

---

## Platform notes

### iOS
- Uses `VNDocumentCameraViewController` from VisionKit — the same scanner as Apple Notes
- No additional dependencies or CocoaPods required
- UI is Apple's — not customisable (this is a system constraint, not a plugin limitation)
- No gallery import available on iOS (camera-only)

### Android
- Uses Google ML Kit Document Scanner API via Google Play Services
- ML models are downloaded centrally — minimal impact on app bundle size
- No camera permission required — handled by Play Services
- Gallery import can be enabled via `allowGalleryImport: true`
- Requires a minimum device RAM of 1.7 GB

---

## Changelog

### 1.0.0
- Initial release
- VisionKit scanner (iOS)
- ML Kit Document Scanner (Android)
- Facade with `scan()`, `scanToPdf()`, `scanToJpegs()`, `scanSinglePage()`
- `DocumentScanned`, `DocumentScanCancelled`, `DocumentScanFailed` events
- JavaScript bridge library
- Full Pest test suite

---

## License

MIT
