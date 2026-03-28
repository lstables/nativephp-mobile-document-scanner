<?php

namespace lstables\NativeDocumentScanner\DTOs;

class ScanResult
{
    /**
     * @param  string[]  $pages  Absolute file paths to each scanned page as JPEG
     * @param  string|null  $pdf  Absolute file path to the combined PDF, if requested
     * @param  int  $pageCount  Number of pages scanned
     */
    public function __construct(
        public readonly array $pages,
        public readonly ?string $pdf,
        public readonly int $pageCount,
    ) {}

    /**
     * Whether any pages were scanned successfully.
     */
    public function hasPages(): bool
    {
        return $this->pageCount > 0;
    }

    /**
     * Whether a PDF was generated.
     */
    public function hasPdf(): bool
    {
        return $this->pdf !== null;
    }

    /**
     * Return the first scanned page path, or null.
     */
    public function firstPage(): ?string
    {
        return $this->pages[0] ?? null;
    }

    /**
     * Convert to a plain array for easy JSON serialisation.
     */
    public function toArray(): array
    {
        return [
            'pages' => $this->pages,
            'pdf' => $this->pdf,
            'pageCount' => $this->pageCount,
        ];
    }
}
