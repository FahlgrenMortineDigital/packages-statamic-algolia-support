<?php

namespace Tests;

use Fahlgrendigital\PackagesStatamicAlgoliaSupport\StatamicAlgoliaSupportProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            StatamicAlgoliaSupportProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('algolia-support.disk', 'algolia-indexes');
        $app['config']->set('algolia-support.api_uri', '/algolia/indexes');
        $app['config']->set('algolia-support.computed_indexes', []);
        $app['config']->set('cache.default', 'array');

        $app['config']->set('filesystems.disks.algolia-indexes', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/algolia-indexes'),
        ]);

        $app['config']->set('statamic.search.indexes', []);
    }
}
