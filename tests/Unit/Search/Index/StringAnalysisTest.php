<?php

use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Index\StringAnalysis;

it('calculates size in KB for ascii strings', function () {
    $value = str_repeat('a', 1024);

    expect(StringAnalysis::calculateSizeInKB($value))->toBe(1.0);
});

it('calculates size in KB for multibyte strings using bytes', function () {
    $value = 'こんにちは';

    expect(StringAnalysis::calculateSizeInKB($value))->toBe(15 / 1024);
});
