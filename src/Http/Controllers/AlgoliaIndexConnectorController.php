<?php

namespace Fahlgrendigital\PackagesStatamicAlgoliaSupport\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AlgoliaIndexConnectorController extends Controller
{
    public function __invoke($index): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        $exists = array_key_exists($index, config('statamic.search.indexes')) || array_key_exists($index, config('statamic.search.computed_indexes'));
        $index_disk = config('algolia-support.disk');

        if(!$exists) {
            return response()->json(['error' => 'Index does not exist'], 404);
        }

        $most_recent = collect(Storage::disk($index_disk)->files())->filter(function ($file) use($index) {
            $file_name = collect(explode('/', $file))->last();

            return Str::startsWith($file_name, "search-index-$index") && Str::endsWith($file_name, '.json');
        })->sortDesc()->first();

        if(!$most_recent) {
            return response()->json(['error' => 'This index does not have a JSON file yet.'], 404);
        }

        return response()->file(Storage::disk($index_disk)->path($most_recent));
    }
}