<?php

namespace Fewsh\AnkiImporter;

use Illuminate\Support\ServiceProvider;

class AnkiImporterServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish controllers and services
        $this->publishes([
            __DIR__.'/config/anki.php' => config_path('anki.php'),
            __DIR__.'/Controllers/AnkiImportController.php' => app_path('Http/Controllers/AnkiImportController.php'),
            __DIR__.'/Services/AnkiService.php' => app_path('Services/AnkiService.php'),
            __DIR__.'/AnkiImporterServiceProvider.php' => app_path('Providers/AnkiImporterServiceProvider.php'),
        ], 'anki-publishes');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/config/anki.php', 'anki');
    }
}
