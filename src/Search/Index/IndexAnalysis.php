<?php

namespace Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Index;

use Illuminate\Support\Facades\Storage;

class IndexAnalysis
{
    public static function analyzeJsonFile(string $path, string $disk): array
    {
        $fileContent = Storage::disk($disk)->get($path);
        $records = json_decode($fileContent, true);

        $sizes = array_map([StringAnalysis::class, 'calculateSizeInKB'], array_map('json_encode', $records));
        $maxSize = max($sizes);
        $minSize = min($sizes);
        $avgSize = array_sum($sizes) / count($sizes);

        return [
            'maxSize (kb)' => $maxSize,
            'minSize (kb)' => $minSize,
            'avgSize (kb)' => $avgSize,
        ];
    }
}
