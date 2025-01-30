<?php

namespace Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Index;

class StringAnalysis
{
    public static function calculateSizeInKB(string $string): float
    {
        $byteLength = mb_strlen($string, '8bit');
        return $byteLength / 1024;
    }
}
