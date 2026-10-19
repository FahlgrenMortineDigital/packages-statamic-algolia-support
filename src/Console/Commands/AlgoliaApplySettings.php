<?php

namespace Fahlgrendigital\PackagesStatamicAlgoliaSupport\Console\Commands;

use Algolia\AlgoliaSearch\Api\SearchClient;
use Algolia\AlgoliaSearch\Exceptions\NotFoundException;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Statamic\Search\Algolia\Index as AlgoliaIndex;
use Statamic\Search\IndexManager;
use Statamic\Search\Null\NullIndex;

class AlgoliaApplySettings extends Command
{
    /**
     * Settings a virtual replica may carry. Algolia rejects anything else.
     */
    private const VIRTUAL_KEYS = ['customRanking', 'relevancyStrictness'];

    protected $signature = 'algolia:apply-settings
        {index? : Statamic search index handle. Defaults to every algolia index}
        {--dry-run : Print the payload and call nothing}
        {--max-records= : Fail if primary records x (1 + standard replicas) exceeds this number}';

    protected $description = 'Apply Algolia settings to a Statamic index and its replicas from config';

    public function handle(): int
    {
        $maxRecords = $this->option('max-records');

        if ($maxRecords !== null && (! ctype_digit((string) $maxRecords) || (int) $maxRecords < 1)) {
            $this->error('--max-records must be a positive integer.');

            return self::FAILURE;
        }

        try {
            $targets = collect($this->indexes())
                ->map(fn (AlgoliaIndex $index) => $this->desired($index))
                ->values()
                ->all();
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($targets === []) {
            $this->warn('No algolia indexes found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            return $this->dryRun($targets, $maxRecords !== null);
        }

        try {
            if ($maxRecords !== null && ! $this->guardRecords($targets, (int) $maxRecords)) {
                return self::FAILURE;
            }

            foreach ($targets as $target) {
                $this->apply($target);
            }
        } catch (\Throwable $e) {
            $this->error('Algolia request failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function makeSearchClient(array $credentials)
    {
        return SearchClient::create($credentials['id'], $credentials['secret']);
    }

    /**
     * @return array<int, AlgoliaIndex>
     */
    private function indexes(): array
    {
        $handle = $this->argument('index');

        $indexes = collect(app(IndexManager::class)->all())
            ->filter(fn ($index) => $index instanceof AlgoliaIndex)
            ->when($handle, fn ($all) => $all->filter(fn ($index) => $index->handle() === $handle));

        if ($handle && $indexes->isEmpty()) {
            throw new InvalidArgumentException("No algolia index with handle [{$handle}].");
        }

        return $indexes->values()->all();
    }

    /**
     * Build the desired state for one index, with every name resolved.
     */
    private function desired(AlgoliaIndex $index): array
    {
        $config = $index->config();
        $settings = $config['settings'] ?? [];
        $handle = $index->handle();

        if (array_key_exists('replicas', $settings)) {
            throw new InvalidArgumentException(
                "Index [{$handle}]: move 'replicas' out of 'settings' to the index's own 'replicas' key. "
                . 'The driver sends settings to Algolia as written, so replica names would not be prefixed.'
            );
        }

        $names = [];
        $replicas = [];
        $standardCount = 0;

        foreach ($config['replicas'] ?? [] as $key => $value) {
            [$replicaHandle, $virtual, $overrides] = $this->parseReplica($handle, $key, $value);

            if (isset($names[$replicaHandle])) {
                throw new InvalidArgumentException("Index [{$handle}]: replica [{$replicaHandle}] is listed twice.");
            }

            $names[$replicaHandle] = true;

            if ($virtual && ($bad = array_diff(array_keys($overrides), self::VIRTUAL_KEYS))) {
                throw new InvalidArgumentException(
                    "Index [{$handle}]: virtual replica [{$replicaHandle}] only supports "
                    . implode(', ', self::VIRTUAL_KEYS) . '. Found: ' . implode(', ', $bad) . '.'
                );
            }

            $name = (new NullIndex($replicaHandle, [], $index->locale()))->name();

            $replicas[] = [
                'name' => $name,
                'virtual' => $virtual,
                'settings' => $virtual ? $overrides : array_replace($settings, $overrides),
            ];

            $standardCount += $virtual ? 0 : 1;
        }

        $primary = $settings;

        if ($replicas !== []) {
            $primary['replicas'] = array_map(
                fn ($r) => $r['virtual'] ? "virtual({$r['name']})" : $r['name'],
                $replicas
            );
        }

        return [
            'handle' => $handle,
            'name' => $index->name(),
            'credentials' => $config['credentials'] ?? [],
            'primary' => $primary,
            'replicas' => $replicas,
            'standard' => $standardCount,
        ];
    }

    /**
     * @return array{0: string, 1: bool, 2: array}
     */
    private function parseReplica(string $handle, int|string $key, mixed $value): array
    {
        [$label, $overrides] = is_int($key) ? [$value, []] : [$key, $value];

        if (! is_string($label) || ! is_array($overrides)) {
            throw new InvalidArgumentException("Index [{$handle}]: invalid replica entry. Use 'name' or 'name' => [settings].");
        }

        $virtual = preg_match('/^virtual\((.+)\)$/', $label, $m) === 1;

        return [$virtual ? $m[1] : $label, $virtual, $overrides];
    }

    private function dryRun(array $targets, bool $guardRequested): int
    {
        $this->warn('Dry run. No Algolia calls are made.');

        if ($guardRequested) {
            $this->warn('--max-records skipped: it needs a read from Algolia.');
        }

        foreach ($targets as $target) {
            $this->info("> [{$target['name']}] primary");
            $this->line($this->json($target['primary']));

            foreach ($target['replicas'] as $replica) {
                $this->info("> [{$replica['name']}] " . ($replica['virtual'] ? 'virtual' : 'standard') . ' replica');
                $this->line($this->json($replica['settings']));
            }
        }

        return self::SUCCESS;
    }

    private function guardRecords(array $targets, int $max): bool
    {
        $failed = false;

        foreach ($targets as $target) {
            $client = $this->makeSearchClient($target['credentials']);
            $records = $this->recordCount($client, $target['name']);
            $total = $records * (1 + $target['standard']);

            $this->line("> [{$target['name']}] {$records} records x " . (1 + $target['standard']) . " = {$total}");

            if ($total > $max) {
                $failed = true;
                $this->error("[{$target['name']}] would hold {$total} records, over the limit of {$max}. Nothing was changed.");
            }
        }

        return ! $failed;
    }

    private function recordCount(object $client, string $name): int
    {
        try {
            $response = $client->searchSingleIndex($name, ['query' => '', 'hitsPerPage' => 0]);
        } catch (NotFoundException) {
            return 0;
        }

        return (int) ($this->normalize($response)['nbHits'] ?? 0);
    }

    private function apply(array $target): void
    {
        $client = $this->makeSearchClient($target['credentials']);

        $this->info("> [{$target['name']}] primary");
        $this->sync($client, $target['name'], $target['primary']);

        foreach ($target['replicas'] as $replica) {
            $this->info("> [{$replica['name']}] " . ($replica['virtual'] ? 'virtual' : 'standard') . ' replica');
            $this->sync($client, $replica['name'], $replica['settings']);
        }
    }

    private function sync(object $client, string $name, array $desired): void
    {
        $current = $this->currentSettings($client, $name);
        $changed = [];

        foreach ($desired as $key => $value) {
            if (! array_key_exists($key, $current) || $this->canonical($current[$key]) !== $this->canonical($value)) {
                $changed[$key] = $value;
            }
        }

        if ($changed === []) {
            $this->line('  no changes');

            return;
        }

        foreach ($changed as $key => $value) {
            $this->line("  ~ {$key}");
            $this->line('    - ' . (array_key_exists($key, $current) ? $this->json($current[$key]) : '(unset)'));
            $this->line('    + ' . $this->json($value));
        }

        // Algolia applies settings asynchronously. Replicas are created from the
        // primary's task, so the next call must wait for this one to finish.
        $task = $this->normalize($client->setSettings($name, $changed, false));

        if (isset($task['taskID'])) {
            $client->waitForTask($name, $task['taskID']);
        }
    }

    private function currentSettings(object $client, string $name): array
    {
        try {
            return $this->normalize($client->getSettings($name));
        } catch (NotFoundException) {
            return [];
        }
    }

    private function normalize(mixed $value): array
    {
        return (array) json_decode(json_encode($value), true);
    }

    /**
     * Sort map keys, keep list order, so equal settings compare equal.
     */
    private function canonical(mixed $value): string
    {
        $sort = function (mixed $v) use (&$sort) {
            if (! is_array($v)) {
                return $v;
            }

            $v = array_map($sort, $v);

            if (! array_is_list($v)) {
                ksort($v);
            }

            return $v;
        };

        return json_encode($sort($value));
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
