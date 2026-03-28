<?php

namespace lstables\NativeDocumentScanner\Facades;

use Illuminate\Support\Facades\Facade;
use lstables\NativeDocumentScanner\DTOs\ScanResult;

/**
 * @method static ScanResult|null scan(int $maxPages = 0, bool $allowGalleryImport = false, string $mode = 'full', bool $outputPdf = true, bool $outputJpegs = true)
 * @method static string|null scanToPdf(int $maxPages = 0)
 * @method static array|null scanToJpegs(int $maxPages = 0)
 * @method static string|null scanSinglePage()
 *
 * @see \lstables\NativeDocumentScanner\DocumentScanner
 */
class DocumentScanner extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'native-document-scanner';
    }
}
