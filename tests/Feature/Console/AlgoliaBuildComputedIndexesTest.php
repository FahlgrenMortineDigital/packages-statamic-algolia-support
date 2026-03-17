<?php

use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Console\Commands\AlgoliaBuildComputedIndexes;
use Illuminate\Support\Facades\Storage;

it('returns success when no computed indexes are configured', function () {
    Storage::fake('algolia-indexes');
    config()->set('algolia-support.computed_indexes', []);

    $this->artisan('algolia:build-computed-indexes')->assertSuccessful();
    expect(Storage::disk('algolia-indexes')->files())->toBeEmpty();
});

it('builds merged computed index files and applies cleanup window', function () {
    Storage::fake('algolia-indexes');

    config()->set('algolia-support.computed_indexes', [
        'combined_posts' => [
            'sources' => ['posts_en', 'posts_es'],
        ],
    ]);
    config()->set('statamic.search.drivers.algolia.credentials.id', 'fake-app-id');
    config()->set('statamic.search.drivers.algolia.credentials.secret', 'fake-secret');

    Storage::disk('algolia-indexes')->put('search-index-combined_posts-1600000000.json', '[]');
    Storage::disk('algolia-indexes')->put('search-index-combined_posts-1700000000.json', '[]');
    Storage::disk('algolia-indexes')->put('search-index-combined_posts-1710000000.json', '[]');

    $client = new class {
        public function browseObjects(string $indexName): array
        {
            return match ($indexName) {
                'posts_en' => [
                    ['objectID' => 'en-1', 'title' => 'Hello'],
                ],
                'posts_es' => [
                    ['objectID' => 'es-1', 'title' => 'Hola'],
                ],
                default => [],
            };
        }
    };

    $command = new class($client) extends AlgoliaBuildComputedIndexes {
        public function __construct(private object $client)
        {
            parent::__construct();
        }

        protected function makeSearchClient()
        {
            return $this->client;
        }
    };

    app()->instance(AlgoliaBuildComputedIndexes::class, $command);

    $this->artisan('algolia:build-computed-indexes')->assertSuccessful();

    $files = collect(Storage::disk('algolia-indexes')->files())
        ->filter(fn (string $file) => preg_match('/^search-index-combined_posts-\d+\.json$/', $file))
        ->sort()
        ->values();

    expect($files)->toHaveCount(3)
        ->and($files->contains('search-index-combined_posts-1600000000.json'))->toBeFalse();

    $computedFile = $files->first(function (string $file) {
        return str_contains(Storage::disk('algolia-indexes')->get($file), '"en-1"');
    });
    $payload = json_decode(Storage::disk('algolia-indexes')->get($computedFile), true);

    expect($payload)->toBe([
        ['objectID' => 'en-1', 'title' => 'Hello'],
        ['objectID' => 'es-1', 'title' => 'Hola'],
    ]);
});
