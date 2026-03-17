<?php

use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Support\IndexBlobCleaner;
use Illuminate\Support\Facades\Storage;

it('keeps only the newest three matching files per index and type', function () {
    Storage::fake('algolia-indexes');

    Storage::disk('algolia-indexes')->put('search-index-posts-100.json', '[]');
    Storage::disk('algolia-indexes')->put('search-index-posts-200.json', '[]');
    Storage::disk('algolia-indexes')->put('search-index-posts-300.json', '[]');
    Storage::disk('algolia-indexes')->put('search-index-posts-400.json', '[]');

    Storage::disk('algolia-indexes')->put('search-index-posts-050.csv', 'id');
    Storage::disk('algolia-indexes')->put('other-file.json', '[]');

    IndexBlobCleaner::cleanOldFiles('posts', 'json');

    expect(Storage::disk('algolia-indexes')->exists('search-index-posts-400.json'))->toBeTrue()
        ->and(Storage::disk('algolia-indexes')->exists('search-index-posts-300.json'))->toBeTrue()
        ->and(Storage::disk('algolia-indexes')->exists('search-index-posts-200.json'))->toBeTrue()
        ->and(Storage::disk('algolia-indexes')->exists('search-index-posts-100.json'))->toBeFalse()
        ->and(Storage::disk('algolia-indexes')->exists('search-index-posts-050.csv'))->toBeTrue()
        ->and(Storage::disk('algolia-indexes')->exists('other-file.json'))->toBeTrue();
});
