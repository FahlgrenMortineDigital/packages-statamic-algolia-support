<?php

namespace Fahlgrendigital\PackagesStatamicAlgoliaSupport\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IndexBlobCleaner
{
    public static int $window = 3;

    public static function cleanOldFiles(string $index, string $file_type): void
    {
        $disk = config('algolia-support.disk');

        $index_files = collect(Storage::disk($disk)->files())->filter(function ($file) use ($index, $file_type) {
            $file_name = collect(explode('/', $file))->last();
            $pattern = "/^search-index-$index-\d+\.$file_type$/";

            return preg_match($pattern, $file_name);
        })->sortDesc();

        if ($index_files->count() > static::$window) {
            $recent_files = $index_files->take(static::$window);

            $index_files->filter(function ($file) use ($recent_files) {
                return !$recent_files->contains($file);
            })->each(function ($file) use($disk) {
                Storage::disk($disk)->delete($file);
            });
        }
    }
}