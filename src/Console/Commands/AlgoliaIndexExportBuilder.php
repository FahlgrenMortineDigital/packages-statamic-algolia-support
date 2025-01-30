<?php

namespace Fahlgrendigital\PackagesStatamicAlgoliaSupport\Console\Commands;

use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Index\IndexAnalysis;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Writer;
use Statamic\Contracts\Search\Searchable;
use Statamic\Facades\Search;
use Statamic\Search\Index;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class AlgoliaIndexExportBuilder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search-index:build-file
    {index : target index}
    {--T|file-type= : specify the file type [json/csv]}
    {--D|dry-run : only do a dry run of the command}
    {--json-stats : output stats for json files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build search index file';

    private array $file_types = ['json', 'csv'];

    /**
     * The number of index files to keep for a given index. Includes the most recently generated file.
     * @var int
     */
    private int $history_count = 3;

    private string $disk = 'algolia-indexes';

    public function handle(): int
    {
        @ini_set('memory_limit', config('algolia-search.memory_limit'));

        $index_key  = $this->argument('index');
        $file_type  = $this->option('file-type');
        $is_dry_run = $this->option('dry-run');
        $json_stats = $this->option('json-stats');
        $indexes    = array_keys(config('statamic.search.indexes'));
        $path       = Storage::disk($this->disk)->path('');
        $writer     = null;

        while (!in_array($file_type, $this->file_types)) {
            $file_type = $this->choice('File export type', $this->file_types);
        }

        while (!in_array($index_key, $indexes)) {
            $index_key = $this->choice('Choose index', $indexes);
        }

        $file_name = "search-index-$index_key-" . now()->timestamp . ".$file_type";
        $path      = sprintf("%s%s", $path, $file_name);

        if ($file_type === 'csv' && !$is_dry_run) {
            $writer = Writer::createFromPath($path, 'w');
        }

        $this->info("> Augmenting searchable document list...");

        /** @var Index $index */
        $index              = Search::index($index_key);
        $searchables_master = $index->searchables()->lazy();
        $count              = $searchables_master->reduce(function ($carry, $collection) {
            return $carry + $collection->count();
        }, 0);
        $bar                = $this->output->createProgressBar($count);

        if ($file_type === 'csv' && !$is_dry_run) {
            $writer->insertOne(
                array_merge(
                    ['objectID'],
                    array_keys($index->searchables()->fields($searchables_master->first()->first()))
                )
            );
        }

        $this->info("> Building export file...\n");

        $bar->start();

        $searchables_master->each(function ($collection) use ($index, $bar, $file_type, $path, $is_dry_run, $writer, $file_name) {
            $documents = $collection->map(function (Searchable $item) use ($index, $bar) {
                $data             = $index->searchables()->fields($item);
                $data['objectID'] = $item->getSearchReference();

                $bar->advance();

                return $data;
            });

            if ($file_type === 'json' && !$is_dry_run) {
                Storage::disk($this->disk)->append($file_name, json_encode($documents->all()));
            } else if ($file_type === 'csv' && !$is_dry_run) {
                $documents->each(function ($values) use ($writer) {
                    $writer->insertOne($values);
                });
            }
        });

        $bar->finish();
        $this->output->newLine();
        $this->output->newLine();

        if ($is_dry_run) {
            return SymfonyCommand::SUCCESS;
        }

        $this->info("> Cleaning up old index files");
        $this->clearOldFiles($index_key, $file_type);

        if($json_stats && $file_type === 'json') {
            $this->output->newLine();
            $this->info("> Analyzing JSON file");
            $data = IndexAnalysis::analyzeJsonFile($file_name, $this->disk);
            $this->table(array_keys($data), [array_values($data)]);
        }

        return SymfonyCommand::SUCCESS;
    }

    private function clearOldFiles(string $index, string $file_type): void
    {
        $index_files = collect(Storage::disk($this->disk)->files())->filter(function ($file) use ($index, $file_type) {
            $file_name = collect(explode('/', $file))->last();

            return Str::startsWith($file_name, "search-index-$index") && Str::endsWith($file_name, ".$file_type");
        })->sortDesc();

        if ($index_files->count() > $this->history_count) {
            //removes and returns the first {history_count}
            $recent_files = $index_files->take($this->history_count);

            $this->info("> Cleaning up " . $index_files->count() - $recent_files->count() . " index files");

            $index_files->filter(function ($file) use ($recent_files) {
                return !$recent_files->contains($file);
            })->each(function ($file) {
                Storage::disk($this->disk)->delete($file);
            });
        }
    }
}