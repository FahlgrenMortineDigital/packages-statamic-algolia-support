<?php

namespace Fahlgrendigital\PackagesStatamicAlgoliaSupport;

use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Console\Commands\AlgoliaBuildComputedIndexes;
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Console\Commands\AlgoliaIndexExportBuilder;
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Http\Controllers\AlgoliaIndexConnectorController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class StatamicAlgoliaSupportProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureConsole();
        $this->configureRoutes();
        $this->publishConfig();
    }

    private function configureConsole(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AlgoliaIndexExportBuilder::class,
                AlgoliaBuildComputedIndexes::class
            ]);
        }
    }

    private function configureRoutes(): void
    {
        $api_uri = '/' . ltrim(config('algolia-support.api_uri'), '/');

        Route::middleware('api')
             ->prefix('api')
             ->group(function () use ($api_uri) {
                 Route::get("$api_uri/{index}", AlgoliaIndexConnectorController::class);
             });
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/algolia-support.php' => config_path('algolia-support.php'),
        ], 'statamic-algolia-support-config');
    }
}