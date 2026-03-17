<?php

use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Http\Controllers\AlgoliaIndexConnectorController;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

it('returns 404 json when requested index is not configured', function () {
    config()->set('statamic.search.indexes', []);
    config()->set('algolia-support.computed_indexes', []);

    $response = app(AlgoliaIndexConnectorController::class)('posts');

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true))->toBe(['error' => 'Index does not exist']);
});

it('returns 404 json when configured index has no json file yet', function () {
    config()->set('statamic.search.indexes', ['posts' => []]);
    Storage::fake('algolia-indexes');

    $response = app(AlgoliaIndexConnectorController::class)('posts');

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true))->toBe(['error' => 'This index does not have a JSON file yet.']);
});

it('returns the most recent index json file for a configured index', function () {
    config()->set('statamic.search.indexes', ['posts' => []]);
    Storage::fake('algolia-indexes');

    Storage::disk('algolia-indexes')->put('search-index-posts-100.json', '[{"old":true}]');
    Storage::disk('algolia-indexes')->put('search-index-posts-200.json', '[{"new":true}]');

    $response = app(AlgoliaIndexConnectorController::class)('posts');

    expect($response)->toBeInstanceOf(BinaryFileResponse::class)
        ->and($response->getFile()->getFilename())->toBe('search-index-posts-200.json');
});
