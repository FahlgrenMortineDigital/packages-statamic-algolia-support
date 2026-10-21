<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('algolia-indexes');
    Storage::disk('algolia-indexes')->put('search-index-posts-100.json', '[{"objectID":"1"}]');
    config()->set('statamic.search.indexes', ['posts' => []]);
});

it('is disabled by default', function () {
    expect(config('algolia-support.api_enabled'))->toBeFalse();

    $this->getJson('/api/algolia/indexes/posts')->assertNotFound();
});

it('stays disabled even with a valid token', function () {
    config()->set('algolia-support.api_token', 'secret');

    $this->getJson('/api/algolia/indexes/posts', ['Authorization' => 'Bearer secret'])->assertNotFound();
});

it('rejects requests without a token when enabled', function () {
    config()->set('algolia-support.api_enabled', true);
    config()->set('algolia-support.api_token', 'secret');

    $this->getJson('/api/algolia/indexes/posts')->assertUnauthorized();
});

it('rejects a wrong token when enabled', function () {
    config()->set('algolia-support.api_enabled', true);
    config()->set('algolia-support.api_token', 'secret');

    $this->getJson('/api/algolia/indexes/posts', ['Authorization' => 'Bearer nope'])->assertUnauthorized();
});

it('rejects every request when enabled with no configured token', function () {
    config()->set('algolia-support.api_enabled', true);
    config()->set('algolia-support.api_token', null);

    $this->getJson('/api/algolia/indexes/posts')->assertUnauthorized();
    $this->getJson('/api/algolia/indexes/posts', ['Authorization' => 'Bearer '])->assertUnauthorized();
});

it('serves the latest file with a valid token when enabled', function () {
    config()->set('algolia-support.api_enabled', true);
    config()->set('algolia-support.api_token', 'secret');

    $this->get('/api/algolia/indexes/posts', ['Authorization' => 'Bearer secret'])->assertOk();
});
