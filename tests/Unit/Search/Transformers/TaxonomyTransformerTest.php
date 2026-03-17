<?php

use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\TaxonomyTransformer;
use Illuminate\Support\Facades\Cache;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\TermRepository;
use Statamic\Taxonomies\LocalizedTerm;

beforeEach(function () {
    Cache::flush();
});

it('returns null for non-entry searchables', function () {
    $transformer = new TaxonomyTransformer();

    expect($transformer->handle(null, 'tags', new stdClass()))->toBeNull();
});

it('returns an empty array when entry has no taxonomy values', function () {
    $transformer = new TaxonomyTransformer();
    $entry = Mockery::mock(EntryContract::class);

    $entry->shouldReceive('get')->once()->with('tags')->andReturn([]);

    expect($transformer->handle(null, 'tags', $entry))->toBe([]);
});

it('maps taxonomy terms to id slug and title', function () {
    $transformer = new TaxonomyTransformer();
    $entry = Mockery::mock(EntryContract::class);
    $term = Mockery::mock(LocalizedTerm::class);
    $repository = Mockery::mock(TermRepository::class);

    app()->instance(TermRepository::class, $repository);

    $entry->shouldReceive('get')->once()->with('tags')->andReturn(['news']);
    $repository->shouldReceive('find')->once()->with('tags::news')->andReturn($term);

    $term->shouldReceive('id')->andReturn('tags::news');
    $term->shouldReceive('slug')->andReturn('news');
    $term->shouldReceive('title')->andReturn('News');

    expect($transformer->handle(null, 'tags', $entry))->toBe([
        'tags' => [
            [
                'id' => 'tags::news',
                'slug' => 'news',
                'title' => 'News',
            ],
        ],
    ]);
});

it('uses cache to avoid repeat term lookups', function () {
    $transformer = new TaxonomyTransformer();
    $entry = Mockery::mock(EntryContract::class);
    $term = Mockery::mock(LocalizedTerm::class);
    $repository = Mockery::mock(TermRepository::class);

    app()->instance(TermRepository::class, $repository);

    $entry->shouldReceive('get')->twice()->with('tags')->andReturn(['news']);
    $repository->shouldReceive('find')->once()->with('tags::news')->andReturn($term);

    $term->shouldReceive('id')->andReturn('tags::news');
    $term->shouldReceive('slug')->andReturn('news');
    $term->shouldReceive('title')->andReturn('News');

    $transformer->handle(null, 'tags', $entry);
    $transformer->handle(null, 'tags', $entry);
});
