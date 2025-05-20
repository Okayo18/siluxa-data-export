<?php

namespace Siluxa\DataExport;

use Illuminate\Support\ServiceProvider;
use Siluxa\DataExport\Commands\ExportDataCommand;
use Siluxa\DataExport\Commands\ListExportedFilesCommand;

class DataExportServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publier la configuration
        $this->publishes([
            __DIR__.'/../config/data-export.php' => config_path('data-export.php'),
        ], 'config');

        // Publier les vues
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/data-export'),
        ], 'views');

        // Charger les vues
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'data-export');

        // Enregistrer la commande Artisan
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExportDataCommand::class,
                ListExportedFilesCommand::class,
            ]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/data-export.php', 'data-export');
    }
}