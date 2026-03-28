<?php

namespace lstables\NativeDocumentScanner\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentScanned
{
    use Dispatchable, SerializesModels;

    /**
     * @param  string[]  $pages  Absolute file paths to each scanned page as JPEG
     * @param  string|null  $pdf  Absolute file path to the combined PDF
     * @param  int  $pageCount  Number of pages scanned
     */
    public function __construct(
        public readonly array $pages,
        public readonly ?string $pdf,
        public readonly int $pageCount,
    ) {}
}
