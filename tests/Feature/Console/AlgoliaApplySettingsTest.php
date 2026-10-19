<?php

use Algolia\AlgoliaSearch\Exceptions\NotFoundException;
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Console\Commands\AlgoliaApplySettings;
use Statamic\Facades\Site;
use Statamic\Search\Index;
use Statamic\Search\IndexManager;
use Statamic\Sites\Sites;

/**
 * In-memory stand-in for the v4 SearchClient. Mimics Algolia: creating a
 * primary with `replicas` creates standard replicas as a copy of the primary.
 */
class FakeSettingsClient
{
    public array $indexes = [];
    public array $calls = [];
    public int $records = 0;
    private int $lastTask = 0;
    /** @var array<int, string> Index that owns each task still pending. */
    private array $pending = [];

    public function getSettings(string $name): array
    {
        $this->calls[] = ['getSettings', $name];

        return $this->indexes[$name] ?? throw new NotFoundException('Index does not exist', 404);
    }

    public function setSettings(string $name, array $settings, $forwardToReplicas = null): array
    {
        $this->calls[] = ['setSettings', $name, $settings, $forwardToReplicas];

        $isNew = ! isset($this->indexes[$name]);
        $this->indexes[$name] = array_replace($this->indexes[$name] ?? [], $settings);

        $task = ['taskID' => ++$this->lastTask];
        $this->pending[$task['taskID']] = $name;

        if (! $isNew || ! isset($settings['replicas'])) {
            return $task;
        }

        $inherited = array_diff_key($settings, ['replicas' => 1]);

        foreach ($settings['replicas'] as $replica) {
            if (preg_match('/^virtual\((.+)\)$/', $replica, $m)) {
                $this->indexes[$m[1]] ??= [];
            } else {
                $this->indexes[$replica] ??= $inherited;
            }
        }

        return $task;
    }

    public function waitForTask(string $name, int $taskId): array
    {
        $this->calls[] = ['waitForTask', $name, $taskId];

        if (($this->pending[$taskId] ?? null) !== $name) {
            throw new RuntimeException("Task {$taskId} does not belong to {$name}.");
        }

        unset($this->pending[$taskId]);

        return ['status' => 'published'];
    }

    public function pendingTasks(): array
    {
        return $this->pending;
    }

    public function searchSingleIndex(string $name, array $params): array
    {
        $this->calls[] = ['searchSingleIndex', $name];

        return ['nbHits' => $this->records];
    }

    public function writes(): array
    {
        return array_values(array_filter($this->calls, fn ($c) => $c[0] === 'setSettings'));
    }
}

function runApplySettings(FakeSettingsClient $client, array $args = [])
{
    $command = new class($client) extends AlgoliaApplySettings {
        public function __construct(private object $client)
        {
            parent::__construct();
        }

        protected function makeSearchClient(array $credentials)
        {
            return $this->client;
        }
    };

    app()->instance(AlgoliaApplySettings::class, $command);

    return test()->artisan('algolia:apply-settings', $args);
}

function defineContentIndex(array $overrides = []): void
{
    config()->set('statamic.search.indexes.content', array_merge([
        'driver' => 'algolia',
        'searchables' => ['collection:articles'],
        'fields' => ['title'],
        'credentials' => ['id' => 'fake-id', 'secret' => 'fake-secret'],
        'settings' => [
            'searchableAttributes' => ['title', 'summary'],
            'attributesForFaceting' => ['filterOnly(collection)'],
        ],
        'replicas' => [
            'title_asc' => ['customRanking' => ['asc(title)']],
            'virtual(date_desc)' => ['customRanking' => ['desc(date_ts)']],
        ],
    ], $overrides));
}

beforeEach(function () {
    // Statamic's own provider is not loaded in the test app. Statamic registers this as a singleton.
    app()->singleton(IndexManager::class, fn ($app) => new IndexManager($app));
    // Same reason: Sites would read Statamic's site config. Bind a stub before the facade builds the real one.
    $sites = Mockery::mock(Sites::class);
    $sites->shouldReceive('current')->andReturn(new class {
        public function handle(): string
        {
            return 'default';
        }
    });
    app()->instance(Sites::class, $sites);
    Site::clearResolvedInstance(Sites::class);

    defineContentIndex();
});
afterEach(fn () => Index::resolveNameUsing(null));

it('prefixes the primary and replica names through the name resolver', function () {
    Index::resolveNameUsing(fn ($name, $locale) => 'prod_' . $name);
    $client = new FakeSettingsClient;

    runApplySettings($client)->assertSuccessful();

    expect($client->indexes['prod_content']['replicas'])->toBe(['prod_title_asc', 'virtual(prod_date_desc)'])
        ->and($client->indexes)->toHaveKeys(['prod_title_asc', 'prod_date_desc'])
        ->and($client->indexes)->not->toHaveKeys(['content', 'title_asc', 'date_desc']);
});

it('sends standard and virtual replicas in the primary payload', function () {
    $client = new FakeSettingsClient;

    runApplySettings($client)->assertSuccessful();

    $primary = $client->writes()[0];

    expect($primary[1])->toBe('content')
        ->and($primary[2]['replicas'])->toBe(['title_asc', 'virtual(date_desc)'])
        ->and($primary[2]['searchableAttributes'])->toBe(['title', 'summary'])
        ->and($primary[3])->toBeFalse();
});

it('applies each replica its own settings', function () {
    $client = new FakeSettingsClient;

    runApplySettings($client)->assertSuccessful();

    expect($client->indexes['title_asc']['customRanking'])->toBe(['asc(title)'])
        ->and($client->indexes['date_desc']['customRanking'])->toBe(['desc(date_ts)']);
});

it('is idempotent', function () {
    $client = new FakeSettingsClient;

    runApplySettings($client)->assertSuccessful();
    $client->calls = [];

    runApplySettings($client)->expectsOutputToContain('no changes')->assertSuccessful();

    expect($client->writes())->toBeEmpty();
});

it('prints a diff of current against desired settings', function () {
    $client = new FakeSettingsClient;
    $client->indexes['content'] = ['searchableAttributes' => ['title']];

    runApplySettings($client)
        ->expectsOutputToContain('~ searchableAttributes')
        ->expectsOutputToContain('- ["title"]')
        ->expectsOutputToContain('+ ["title","summary"]')
        ->assertSuccessful();
});

it('carries primary changes to standard replicas and keeps their own ranking', function () {
    $client = new FakeSettingsClient;
    runApplySettings($client)->assertSuccessful();

    defineContentIndex(['settings' => [
        'searchableAttributes' => ['title', 'summary'],
        'attributesForFaceting' => ['filterOnly(collection)', 'topics.slug'],
    ]]);
    app()->forgetInstance(IndexManager::class); // drop cached index instances so the new config is read
    $client->calls = [];

    runApplySettings($client)->assertSuccessful();

    expect($client->indexes['title_asc']['attributesForFaceting'])->toBe(['filterOnly(collection)', 'topics.slug'])
        ->and($client->indexes['title_asc']['customRanking'])->toBe(['asc(title)'])
        ->and($client->indexes['date_desc'])->toBe(['customRanking' => ['desc(date_ts)']]);
});

it('makes no client calls on a dry run and prints the payload', function () {
    $client = new FakeSettingsClient;

    runApplySettings($client, ['--dry-run' => true])
        ->expectsOutputToContain('"replicas":["title_asc","virtual(date_desc)"]')
        ->assertSuccessful();

    expect($client->calls)->toBeEmpty();
});

it('fails the record guard before writing anything', function () {
    $client = new FakeSettingsClient;
    $client->records = 600; // 600 x (1 + 1 standard) = 1200

    runApplySettings($client, ['--max-records' => 1000])
        ->expectsOutputToContain('would hold 1200 records, over the limit of 1000')
        ->assertFailed();

    expect($client->writes())->toBeEmpty();
});

it('passes the record guard at the limit and ignores virtual replicas', function () {
    $client = new FakeSettingsClient;
    $client->records = 500; // 500 x 2 = 1000, the virtual replica adds nothing

    runApplySettings($client, ['--max-records' => 1000])->assertSuccessful();

    expect($client->writes())->not->toBeEmpty();
});

it('rejects replicas placed inside settings', function () {
    defineContentIndex(['settings' => ['replicas' => ['title_asc']]]);
    $client = new FakeSettingsClient;

    runApplySettings($client)->expectsOutputToContain("move 'replicas' out of 'settings'")->assertFailed();

    expect($client->calls)->toBeEmpty();
});

it('rejects unsupported settings on a virtual replica', function () {
    defineContentIndex(['replicas' => ['virtual(date_desc)' => ['ranking' => ['asc(title)']]]]);

    runApplySettings(new FakeSettingsClient)
        ->expectsOutputToContain('only supports customRanking, relevancyStrictness')
        ->assertFailed();
});

it('targets one index by handle and fails on an unknown handle', function () {
    config()->set('statamic.search.indexes.other', [
        'driver' => 'algolia', 'searchables' => [], 'fields' => ['title'],
        'credentials' => ['id' => 'x', 'secret' => 'y'],
        'settings' => ['searchableAttributes' => ['title']],
    ]);
    $client = new FakeSettingsClient;

    runApplySettings($client, ['index' => 'other'])->assertSuccessful();
    expect($client->indexes)->toHaveKey('other')->and($client->indexes)->not->toHaveKey('content');

    runApplySettings(new FakeSettingsClient, ['index' => 'missing'])
        ->expectsOutputToContain('No algolia index with handle [missing]')
        ->assertFailed();
});

it('skips non-algolia indexes when no argument is given', function () {
    config()->set('statamic.search.indexes.local_one', [
        'driver' => 'local', 'searchables' => [], 'fields' => ['title'],
    ]);
    $client = new FakeSettingsClient;

    runApplySettings($client)->assertSuccessful();

    expect($client->indexes)->not->toHaveKey('local_one');
});

it('waits for each setSettings task before the next call', function () {
    defineContentIndex();
    $client = new FakeSettingsClient();

    runApplySettings($client, ['index' => 'content'])->assertSuccessful();

    $sequence = array_values(array_filter(
        $client->calls,
        fn ($c) => in_array($c[0], ['setSettings', 'waitForTask'], true)
    ));

    // setSettings is always followed by the wait for its own task: primary, then each replica.
    expect($sequence)->not->toBeEmpty();
    expect(count($sequence) % 2)->toBe(0);

    foreach (array_chunk($sequence, 2) as [$write, $wait]) {
        expect($write[0])->toBe('setSettings');
        expect($wait[0])->toBe('waitForTask');
        expect($wait[1])->toBe($write[1]);
    }

    expect(array_column(array_column(array_chunk($sequence, 2), 0), 1))
        ->toBe(['content', 'title_asc', 'date_desc']);
    expect($client->pendingTasks())->toBe([]);
});

it('does not wait when nothing changed', function () {
    defineContentIndex();
    $client = new FakeSettingsClient();

    runApplySettings($client, ['index' => 'content'])->assertSuccessful();
    $client->calls = [];
    runApplySettings($client, ['index' => 'content'])->assertSuccessful();

    expect(array_column($client->calls, 0))->not->toContain('waitForTask');
});
