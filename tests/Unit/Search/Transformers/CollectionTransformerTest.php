<?php

use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\CollectionTransformer;

it('returns null for empty collection value', function () {
    $transformer = new CollectionTransformer();

    expect($transformer->handle(null, 'collection', null))->toBeNull();
});

it('extracts the handle when value is an array', function () {
    $transformer = new CollectionTransformer();

    expect($transformer->handle(['handle' => 'blog'], 'collection', null))->toBe('blog');
});

it('passes through scalar values unchanged', function () {
    $transformer = new CollectionTransformer();

    expect($transformer->handle('news', 'collection', null))->toBe('news');
});
