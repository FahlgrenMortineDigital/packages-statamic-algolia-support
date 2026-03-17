<?php

use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\RecordUrlTransformer;
use Statamic\Assets\Asset;
use Statamic\Entries\Entry;

it('returns entry url for entry searchables', function () {
    $transformer = new RecordUrlTransformer();
    $entry = Mockery::mock(Entry::class);

    $entry->shouldReceive('url')->once()->andReturn('/entries/my-entry');

    expect($transformer->handle(null, 'url', $entry))->toBe('/entries/my-entry');
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
