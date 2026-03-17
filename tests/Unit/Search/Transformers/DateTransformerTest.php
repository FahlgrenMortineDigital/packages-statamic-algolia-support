<?php

use Carbon\Carbon;
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\DateTransformer;

it('returns null for empty date values', function () {
    $transformer = new DateTransformer();

    expect($transformer->handle(null, 'date', null))->toBeNull();
});

it('formats carbon values', function () {
    $transformer = new DateTransformer();
    $date = Carbon::parse('2026-03-16 14:30:00');

    expect($transformer->handle($date, 'date', null))->toBe('2026-03-16 14:30:00');
});

it('passes through non-carbon values unchanged', function () {
    $transformer = new DateTransformer();

    expect($transformer->handle('2026-03-16', 'date', null))->toBe('2026-03-16');
});
