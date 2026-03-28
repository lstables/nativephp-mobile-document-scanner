<?php

namespace lstables\NativeDocumentScanner\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentScanFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $reason,
    ) {}
}
