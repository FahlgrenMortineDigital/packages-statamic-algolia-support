<?php

namespace Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Index;

use Illuminate\Support\Facades\Storage;

class IndexAnalysis
{
    /**
     * The export holds one JSON array per line (one per searchable provider).
     * Returns null when the file holds no records.
     */
    public static function analyzeJsonFile(string $path, string $disk): ?array
    {
        $fileContent = Storage::disk($disk)->get($path);
        $records = [];

        foreach (preg_split('/\R/', (string) $fileContent) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $chunk = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

            foreach ($chunk as $record) {
                $records[] = $record;
            }
        }

        if ($records === []) {
            return null;
        }

        $sizes = array_map([StringAnalysis::class, 'calculateSizeInKB'], array_map('json_encode', $records));

        return [
            'maxSize (kb)' => max($sizes),
            'minSize (kb)' => min($sizes),
            'avgSize (kb)' => array_sum($sizes) / count($sizes),
        ];
    }
}
