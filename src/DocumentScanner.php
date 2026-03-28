<?php

namespace lstables\NativeDocumentScanner;

use lstables\NativeDocumentScanner\DTOs\ScanResult;

class DocumentScanner
{
    /**
     * Open the native document scanner and return the result.
     *
     * Returns a ScanResult with page image paths and optional PDF path.
     * Returns null when not running inside NativePHP.
     *
     * @param  int  $maxPages  Maximum pages to scan (0 = unlimited)
     * @param  bool  $allowGalleryImport  Allow importing from photo library (Android only)
     * @param  string  $mode  Scanning mode: 'base', 'filter', or 'full'
     * @param  bool  $outputPdf  Include a PDF of all scanned pages
     * @param  bool  $outputJpegs  Include JPEG paths for each page
     */
    public function scan(
        int $maxPages = 0,
        bool $allowGalleryImport = false,
        string $mode = 'full',
        bool $outputPdf = true,
        bool $outputJpegs = true,
    ): ?ScanResult {
        if (! function_exists('nativephp_call')) {
            return null;
        }

        $params = [
            'maxPages' => $maxPages,
            'allowGalleryImport' => $allowGalleryImport,
            'mode' => $mode,
            'outputPdf' => $outputPdf,
            'outputJpegs' => $outputJpegs,
        ];

        $raw = nativephp_call('DocumentScanner.Scan', json_encode($params));
        $response = json_decode($raw);

        if (! $response || isset($response->error)) {
            return null;
        }

        $data = $response->data ?? $response;

        return new ScanResult(
            pages: (array) ($data->pages ?? []),
            pdf: $data->pdf ?? null,
            pageCount: (int) ($data->pageCount ?? 0),
        );
    }

    /**
     * Convenience: scan and return only the PDF path.
     */
    public function scanToPdf(int $maxPages = 0): ?string
    {
        return $this->scan(
            maxPages: $maxPages,
            outputPdf: true,
            outputJpegs: false,
        )?->pdf;
    }

    /**
     * Convenience: scan and return only JPEG page paths.
     *
     * @return string[]|null
     */
    public function scanToJpegs(int $maxPages = 0): ?array
    {
        return $this->scan(
            maxPages: $maxPages,
            outputPdf: false,
            outputJpegs: true,
        )?->pages;
    }

    /**
     * Convenience: scan a single page and return its JPEG path.
     */
    public function scanSinglePage(): ?string
    {
        $pages = $this->scan(
            maxPages: 1,
            outputPdf: false,
            outputJpegs: true,
        )?->pages;

        return $pages[0] ?? null;
    }
}
