<?php

namespace lstables\NativeDocumentScanner;

use Illuminate\Support\ServiceProvider;

class DocumentScannerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('native-document-scanner', function () {
            return new DocumentScanner;
        });
    }

    public function boot(): void
    {
        //
    }
}
