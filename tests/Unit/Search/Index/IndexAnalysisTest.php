<?php

use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Index\IndexAnalysis;
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Index\StringAnalysis;
use Illuminate\Support\Facades\Storage;

it('analyzes json file and returns min max and avg record sizes', function () {
    Storage::fake('algolia-indexes');

    $records = [
        ['title' => 'alpha'],
        ['title' => 'a much longer value'],
        ['title' => 'mid'],
    ];

    Storage::disk('algolia-indexes')->put('records.json', json_encode($records));

    $result = IndexAnalysis::analyzeJsonFile('records.json', 'algolia-indexes');

    $sizes = array_map(
        fn ($record) => StringAnalysis::calculateSizeInKB(json_encode($record)),
        $records
    );

    expect($result['maxSize (kb)'])->toBe(max($sizes))
        ->and($result['minSize (kb)'])->toBe(min($sizes))
        ->and($result['avgSize (kb)'])->toBe(array_sum($sizes) / count($sizes));
});

it('reads one json array per line across multiple providers', function () {
    Storage::fake('algolia-indexes');

    $first = [['title' => 'alpha'], ['title' => 'a much longer value']];
    $second = [['title' => 'mid']];

    Storage::disk('algolia-indexes')->put('multi.json', json_encode($first) . PHP_EOL . json_encode($second));

    $result = IndexAnalysis::analyzeJsonFile('multi.json', 'algolia-indexes');

    $sizes = array_map(
        fn ($record) => StringAnalysis::calculateSizeInKB(json_encode($record)),
        array_merge($first, $second)
    );

    expect($result['maxSize (kb)'])->toBe(max($sizes))
        ->and($result['minSize (kb)'])->toBe(min($sizes))
        ->and($result['avgSize (kb)'])->toBe(array_sum($sizes) / count($sizes));
});

it('returns null for an empty export', function () {
    Storage::fake('algolia-indexes');

    Storage::disk('algolia-indexes')->put('empty.json', '');
    Storage::disk('algolia-indexes')->put('empty-array.json', '[]');

    expect(IndexAnalysis::analyzeJsonFile('empty.json', 'algolia-indexes'))->toBeNull()
        ->and(IndexAnalysis::analyzeJsonFile('empty-array.json', 'algolia-indexes'))->toBeNull();
});
