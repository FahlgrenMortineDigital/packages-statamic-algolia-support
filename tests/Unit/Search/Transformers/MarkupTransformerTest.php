<?php

use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\MarkupTransformer;

it('returns null for empty values', function () {
    $transformer = new MarkupTransformer();

    expect($transformer->handle(null, 'content', null))->toBeNull();
});

it('strips html and trims string values', function () {
    $transformer = new MarkupTransformer();

    expect($transformer->handle('  <p>Hello <strong>world</strong></p>  ', 'content', null))
        ->toBe('Hello world');
});

it('returns null when stripped markup has no content', function () {
    $transformer = new MarkupTransformer();

    expect($transformer->handle('<p>   </p>', 'content', null))->toBeNull();
});
