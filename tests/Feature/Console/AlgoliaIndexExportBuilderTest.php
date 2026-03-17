<?php

use Illuminate\Support\Facades\Storage;
use Statamic\Contracts\Search\Result;
use Statamic\Contracts\Search\Searchable;
use Statamic\Search\Search as SearchManager;

it('fails when configured disk does not exist', function () {
    config()->set('algolia-support.disk', 'missing-disk');
    config()->set('filesystems.disks.missing-disk', null);
    config()->set('statamic.search.indexes', ['posts' => []]);

    $this->artisan('algolia:search-index:build-file', [
        'index' => 'posts',
        '--file-type' => 'json',
    ])->assertFailed();
});

it('builds a json export file for a valid index', function () {
    Storage::fake('algolia-indexes');
    config()->set('statamic.search.indexes', ['posts' => []]);

    [$index, $searchables] = makeFakeSearchIndexWithSearchables([
        ['objectID' => 'entry::1', 'title' => 'First'],
        ['objectID' => 'entry::2', 'title' => 'Second'],
    ]);

    app()->instance(SearchManager::class, new class($index) {
        public function __construct(private object $index)
        {
        }

        public function index(string $key): object
        {
            return $this->index;
        }
    });

    $this->artisan('algolia:search-index:build-file', [
        'index' => 'posts',
        '--file-type' => 'json',
    ])->assertSuccessful();

    $files = collect(Storage::disk('algolia-indexes')->files())
        ->filter(fn (string $file) => preg_match('/^search-index-posts-\d+\.json$/', $file))
        ->values();

    expect($files)->toHaveCount(1);

    $payload = Storage::disk('algolia-indexes')->get($files->first());
    $decoded = json_decode($payload, true);

    expect($decoded)->toBeArray()
        ->and($decoded)->toHaveCount(2)
        ->and($decoded[0]['objectID'])->toBe('entry::1')
        ->and($decoded[1]['title'])->toBe('Second')
        ->and($searchables->fieldsCalls)->toBeGreaterThanOrEqual(2);
});

it('does not write files in dry run mode', function () {
    Storage::fake('algolia-indexes');
    config()->set('statamic.search.indexes', ['posts' => []]);

    [$index] = makeFakeSearchIndexWithSearchables([
        ['objectID' => 'entry::1', 'title' => 'Dry Run'],
    ]);

    app()->instance(SearchManager::class, new class($index) {
        public function __construct(private object $index)
        {
        }

        public function index(string $key): object
        {
            return $this->index;
        }
    });

    $this->artisan('algolia:search-index:build-file', [
        'index' => 'posts',
        '--file-type' => 'json',
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(Storage::disk('algolia-indexes')->files())->toBeEmpty();
});

function makeFakeSearchIndexWithSearchables(array $documents): array
{
    $items = collect($documents)->map(function (array $document) {
        return new class($document['objectID']) implements Searchable {
            public function __construct(private string $reference)
            {
            }

            public function getSearchValue(string $field)
            {
                return null;
            }

            public function getSearchReference(): string
            {
                return $this->reference;
            }

            public function toSearchResult(): Result
            {
                return Mockery::mock(Result::class);
            }
        };
    });

    $fieldsByObjectId = collect($documents)->mapWithKeys(function (array $document) {
        return [$document['objectID'] => collect($document)->except('objectID')->all()];
    })->all();

    $searchables = new class($items, $fieldsByObjectId) {
        public int $fieldsCalls = 0;

        public function __construct(private \Illuminate\Support\Collection $items, private array $fieldsByObjectId)
        {
        }

        public function lazy()
        {
            return collect([$this->items]);
        }

        public function fields(Searchable $searchable): array
        {
            $this->fieldsCalls++;

            return $this->fieldsByObjectId[$searchable->getSearchReference()] ?? [];
        }
    };

    $index = new class($searchables) {
        public function __construct(private object $searchables)
        {
        }

        public function searchables(): object
        {
            return $this->searchables;
        }
    };

    return [$index, $searchables];
}
