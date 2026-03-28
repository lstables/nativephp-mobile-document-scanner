<?php

use lstables\NativeDocumentScanner\DocumentScanner;
use lstables\NativeDocumentScanner\DTOs\ScanResult;

describe('DocumentScanner', function () {

    it('returns null when nativephp_call is not defined', function () {
        $scanner = new DocumentScanner;
        expect($scanner->scan())->toBeNull();
    });

    it('returns null from scanToPdf when not in native context', function () {
        $scanner = new DocumentScanner;
        expect($scanner->scanToPdf())->toBeNull();
    });

    it('returns null from scanToJpegs when not in native context', function () {
        $scanner = new DocumentScanner;
        expect($scanner->scanToJpegs())->toBeNull();
    });

    it('returns null from scanSinglePage when not in native context', function () {
        $scanner = new DocumentScanner;
        expect($scanner->scanSinglePage())->toBeNull();
    });

    it('returns a ScanResult when nativephp_call returns a valid response', function () {
        // Mock nativephp_call in the global namespace
        if (! function_exists('nativephp_call')) {
            eval('function nativephp_call(string $method, string $params): string {
                return json_encode([
                    "data" => [
                        "cancelled" => false,
                        "pageCount" => 2,
                        "pages" => ["/tmp/page_1.jpg", "/tmp/page_2.jpg"],
                        "pdf" => "/tmp/document.pdf",
                    ]
                ]);
            }');
        }

        $scanner = new DocumentScanner;
        $result = $scanner->scan();

        expect($result)->toBeInstanceOf(ScanResult::class)
            ->and($result->pageCount)->toBe(2)
            ->and($result->pages)->toHaveCount(2)
            ->and($result->pdf)->toBe('/tmp/document.pdf')
            ->and($result->hasPages())->toBeTrue()
            ->and($result->hasPdf())->toBeTrue()
            ->and($result->firstPage())->toBe('/tmp/page_1.jpg');
    });
});

describe('ScanResult', function () {

    it('reports hasPages correctly', function () {
        $result = new ScanResult(pages: [], pdf: null, pageCount: 0);
        expect($result->hasPages())->toBeFalse();

        $result = new ScanResult(pages: ['/tmp/p.jpg'], pdf: null, pageCount: 1);
        expect($result->hasPages())->toBeTrue();
    });

    it('reports hasPdf correctly', function () {
        $result = new ScanResult(pages: [], pdf: null, pageCount: 0);
        expect($result->hasPdf())->toBeFalse();

        $result = new ScanResult(pages: [], pdf: '/tmp/doc.pdf', pageCount: 0);
        expect($result->hasPdf())->toBeTrue();
    });

    it('returns null from firstPage when pages is empty', function () {
        $result = new ScanResult(pages: [], pdf: null, pageCount: 0);
        expect($result->firstPage())->toBeNull();
    });

    it('serialises to array', function () {
        $result = new ScanResult(
            pages: ['/tmp/page_1.jpg'],
            pdf: '/tmp/doc.pdf',
            pageCount: 1
        );

        expect($result->toArray())->toBe([
            'pages' => ['/tmp/page_1.jpg'],
            'pdf' => '/tmp/doc.pdf',
            'pageCount' => 1,
        ]);
    });
});
