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

it('locks the record shape the driver builds from the transformer output', function () {
    $transformer = new TaxonomyTransformer();
    $repository = Mockery::mock(TermRepository::class);
    app()->instance(TermRepository::class, $repository);

    $term = Mockery::mock(LocalizedTerm::class);
    $term->shouldReceive('id')->andReturn('topics_themes::suffrage');
    $term->shouldReceive('slug')->andReturn('suffrage');
    $term->shouldReceive('title')->andReturn('Suffrage');
    $repository->shouldReceive('find')->with('topics_themes::suffrage')->andReturn($term);

    $withTerms = Mockery::mock(EntryContract::class);
    $withTerms->shouldReceive('get')->with('topics_themes')->andReturn(['suffrage']);

    $withoutTerms = Mockery::mock(EntryContract::class);
    $withoutTerms->shouldReceive('get')->with('topics_themes')->andReturn([]);

    // Statamic's Searchables::fields() merges an array return into the record.
    $record = array_merge(['title' => 'A'], $transformer->handle(null, 'topics_themes', $withTerms));
    $empty = array_merge(['title' => 'B'], $transformer->handle(null, 'topics_themes', $withoutTerms));

    expect($record)->toBe([
        'title' => 'A',
        'topics_themes' => [['id' => 'topics_themes::suffrage', 'slug' => 'suffrage', 'title' => 'Suffrage']],
    ])->and($empty)->toBe(['title' => 'B']);
});

it('localizes a base term, as the Eloquent driver returns one', function () {
    $transformer = new TaxonomyTransformer();
    $entry = Mockery::mock(EntryContract::class);
    $repository = Mockery::mock(TermRepository::class);
    app()->instance(TermRepository::class, $repository);

    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('id')->andReturn('tags::news');
    $localized->shouldReceive('slug')->andReturn('news');
    $localized->shouldReceive('title')->andReturn('News');

    $base = Mockery::mock(\Statamic\Taxonomies\Term::class);
    $base->shouldReceive('inDefaultLocale')->once()->andReturn($localized);

    $entry->shouldReceive('get')->once()->with('tags')->andReturn(['news']);
    $repository->shouldReceive('find')->once()->with('tags::news')->andReturn($base);

    expect($transformer->handle(null, 'tags', $entry))->toBe([
        'tags' => [['id' => 'tags::news', 'slug' => 'news', 'title' => 'News']],
    ]);
});
