<?php

namespace Fahlgrendigital\PackagesStatamicAlgoliaSupport\Console\Commands;

use Algolia\AlgoliaSearch\SearchClient;
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Support\IndexBlobCleaner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AlgoliaBuildComputedIndexes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'algolia:build-computed-indexes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build computed indexes for Algolia';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = config('algolia-support.disk');

        // Get the computed indexes from the search config
        $computedIndexes = config('algolia-support.computed_indexes', []);

        // Loop through each computed index
        foreach ($computedIndexes as $index => $config) {
            $this->info("> Building [{$index}]");

            $client = SearchClient::create(
                config('statamic.search.drivers.algolia.credentials.id'),
                config('statamic.search.drivers.algolia.credentials.secret')
            );
            $fileName = "search-index-$index-" . now()->timestamp . ".json";
            $handle = Storage::disk($disk)->path($fileName);
            $file = fopen($handle, 'w');

            fwrite($file, "[");

            $first = true;

            Storage::disk($disk)->put($fileName, "[");

            foreach($config['sources'] as $indexName) {
                $this->info("> Fetching data from [{$indexName}]");
                $_index = $client->initIndex($indexName);

                foreach ($_index->browseObjects() as $hit) {
                    if (!$first) {
                        fwrite($file, ","); // Add comma for JSON structure
                    }

                    fwrite($file, json_encode($hit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                    $first = false;
                }
            }

            fwrite($file, "]");

            fclose($file);

            $this->newLine();
            $this->info("> Cleaning up old index files");

            IndexBlobCleaner::cleanOldFiles($index, 'json');
        }

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
}