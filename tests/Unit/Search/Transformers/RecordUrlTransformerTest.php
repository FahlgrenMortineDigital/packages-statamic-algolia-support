<?php

use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\RecordUrlTransformer;
use Statamic\Assets\Asset;
use Statamic\Contracts\Entries\Entry as EntryContract;

it('returns entry url for entry searchables', function () {
    $transformer = new RecordUrlTransformer();
    $entry = Mockery::mock(EntryContract::class);

    $entry->shouldReceive('url')->once()->andReturn('/entries/my-entry');

    expect($transformer->handle(null, 'url', $entry))->toBe('/entries/my-entry');
});

it('returns url for an entry that implements the contract without extending the concrete class', function () {
    $transformer = new RecordUrlTransformer();
    $entry = Mockery::mock(EntryContract::class);

    $entry->shouldReceive('url')->once()->andReturn('/custom/entry');

    expect($entry)->not->toBeInstanceOf(\Statamic\Entries\Entry::class)
        ->and($transformer->handle(null, 'url', $entry))->toBe('/custom/entry');
});

it('returns asset url for asset searchables', function () {
    $transformer = new RecordUrlTransformer();
    $asset = Mockery::mock(Asset::class);

    $asset->shouldReceive('url')->once()->andReturn('/assets/file.jpg');

    expect($transformer->handle(null, 'url', $asset))->toBe('/assets/file.jpg');
});

it('returns null for unsupported searchable types', function () {
    $transformer = new RecordUrlTransformer();

    expect($transformer->handle(null, 'url', new stdClass()))->toBeNull();
});
