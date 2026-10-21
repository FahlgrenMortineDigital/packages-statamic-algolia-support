# AGENTS.md — Statamic Algolia Support Package

This file gives AI coding agents (Claude Code, Copilot, Cursor, etc.) the context needed to work effectively in this codebase.

---

## What this package does

`fahlgrendigital/packages-statamic-algolia-support` is a Laravel/Statamic addon that provides:

1. **Search transformers** — Convert complex Statamic field types (Bard, dates, taxonomy terms, collections, assets) into Algolia-compatible scalar/array values during indexing.
2. **Index export builder** — Artisan commands that snapshot a Statamic search index to a JSON or CSV file on a filesystem disk.
3. **Computed index builder** — Merges records from multiple Algolia indexes (fetched via the Algolia API) into a single combined JSON file.
4. **Index retrieval API** — A read-only HTTP endpoint that serves the most recently built index file to downstream consumers.
5. **Settings applier** — `algolia:apply-settings` applies primary and replica settings from config, with prefixed names.

---

## Package structure

```
src/
├── Console/Commands/
│   ├── AlgoliaApplySettings.php        # artisan algolia:apply-settings
│   ├── AlgoliaIndexExportBuilder.php   # artisan algolia:search-index:build-file
│   └── AlgoliaBuildComputedIndexes.php # artisan algolia:build-computed-indexes
├── Http/
│   ├── Controllers/AlgoliaIndexConnectorController.php  # GET /api/algolia/indexes/{index}
│   └── Middleware/AuthorizeIndexApi.php   # 404 when disabled, bearer token otherwise
├── Search/
│   ├── Index/
│   │   ├── IndexAnalysis.php           # Computes min/max/avg record sizes from a JSON file
│   │   └── StringAnalysis.php          # Calculates string size in KB (multibyte-safe)
│   └── Transformers/
│       ├── CollectionTransformer.php   # Collection object → handle string
│       ├── DateTransformer.php         # Carbon → 'Y-m-d H:i:s' string
│       ├── MarkupTransformer.php       # Bard/Textarea → plain text
│       ├── RecordUrlTransformer.php    # Entry (contract)/Asset → URL string
│       ├── TimestampTransformer.php    # Carbon/date string/null → Unix timestamp int|null
│       └── TaxonomyTransformer.php     # Taxonomy slugs → [{id, slug, title}] array
├── Support/
│   └── IndexBlobCleaner.php            # Deletes all but the 3 newest index files
└── StatamicAlgoliaSupportProvider.php  # Registers commands, routes, config publishing
config/
└── algolia-support.php                 # disk, api_uri, memory_limit, computed_indexes
```

---

## Key conventions

### Transformer contract
All transformers expose a single `handle($value, $field, $searchable)` method — this is the Statamic search transformer signature. Transformers must be pure/stateless except for `TaxonomyTransformer`, which uses Laravel's cache to avoid redundant term lookups.

### Index file naming
Index files follow the pattern: `search-index-{index_key}-{unix_timestamp}.{json|csv}`

The `IndexBlobCleaner` matches this pattern with a regex and keeps only the 3 newest files per index key + file type combination.

### Config keys
- `algolia-support.disk` — filesystem disk name (not a path) for index file storage
- `algolia-support.api_uri` — URI prefix for the retrieval API (leading slash is stripped automatically in the provider)
- `algolia-support.api_enabled` — default `false`. The endpoint returns 404 until enabled.
- `algolia-support.api_token` — bearer token required when enabled. An empty token rejects every request (401).
- `algolia-support.api_middleware` — extra middleware run after the token check.
- `algolia-support.computed_indexes` — map of output index name → `['sources' => [...algolia index names]]`

### Route registration
Routes are registered in `StatamicAlgoliaSupportProvider::configureRoutes()` using the `api` middleware group plus `AuthorizeIndexApi`, prefixed at `/api`. The route is: `GET /api/{api_uri}/{index}`. The route is always registered. The middleware returns 404 when `api_enabled` is false.

---

## Important constraints and gotchas

### TaxonomyTransformer — entry type check
Uses `Statamic\Contracts\Entries\Entry` (the interface) for the `instanceof` check so it works with both flat-file entries (`Statamic\Entries\Entry`) and Eloquent entries (`Statamic\Eloquent\Entries\Entry`). Do **not** change this back to a concrete class import.

### AlgoliaBuildComputedIndexes — Algolia PHP client v4 API
This command uses the v4 API (`Algolia\AlgoliaSearch\Api\SearchClient`):
- `SearchClient::create($appId, $apiKey)` — same as v3
- `$client->browseObjects($indexName)` — replaces v3's `initIndex()->browseObjects()`

Do **not** revert to the v3 `initIndex()` pattern; it no longer exists in v4, and Statamic 6 conflicts with client versions below 4.32.

### AlgoliaApplySettings — settings and replicas
`php artisan algolia:apply-settings {index?} [--dry-run] [--max-records=N]`. No argument runs every index whose driver is `algolia`. Config shape, per index in `config/statamic/search.php`:

```php
'content' => [
    'driver' => 'algolia',
    'settings' => ['searchableAttributes' => ['title'], 'attributesForFaceting' => ['filterOnly(collection)']],
    'replicas' => [
        'title_asc' => ['ranking' => ['asc(title)', 'typo', 'geo', 'words', 'filters', 'proximity', 'attribute', 'exact', 'custom']],
        'virtual(date_desc)' => ['customRanking' => ['desc(date_ts)']],
        'start_date_asc', // bare string: standard replica, no overrides
    ],
],
```

Rules that are easy to break:
- `replicas` sits **beside** `settings`, never inside it. The driver (`Statamic\Search\Algolia\Index`) sends `settings` to Algolia as written, on index creation and on every `search:update`, so a `replicas` list inside it would go out unprefixed. Statamic ignores the extra `replicas` key. The command rejects `settings.replicas`.
- Names resolve through `Index::resolveNameUsing`. Replica names use `new NullIndex($handle, [], $locale)->name()` so the same callback runs. The callback is protected static, so do not read it directly.
- A standard replica's desired settings are the primary's `settings` merged with its overrides. It copies the primary only at creation, and the command passes `forwardToReplicas: false`. A virtual replica gets only `customRanking` and `relevancyStrictness`.
- `setSettings` runs only when the diff is non-empty. Keep that: idempotency is a test.
- `--dry-run` makes no client calls, so `--max-records` is skipped under it.
- The command resolves `IndexManager` from the container. Tests bind it and stub `Sites`, because Statamic's provider is not loaded in the test app. Tests inject a fake client by overriding `makeSearchClient()`.

### AlgoliaIndexExportBuilder — JSON file format
The command writes one JSON-encoded array per searchable chunk to a temp stream, then stores the file once with `Storage::disk()->put()`. It does not use `append()`. The resulting file is **not** a single JSON array: it is one JSON array per line (newline-separated). Downstream consumers must handle this format. `IndexAnalysis` decodes it line by line and returns `null` for an empty export, so `--json-stats` is skipped.

### Disk must be defined
The `algolia-support.disk` config value must match a disk name in `config/filesystems.php`. Both commands validate this at startup and exit with a failure code if the disk is missing.

### Memory limit
The builder command reads `config('algolia-support.memory_limit')` and applies it via `ini_set`. The default is `2028M` in `config/algolia-support.php`.

---

## Statamic compatibility

| Statamic | Laravel | PHP  | Status |
|----------|---------|------|--------|
| 5.x      | 11, 12  | 8.3+ | Supported |
| 6.x      | 12, 13  | 8.3+ | Supported (see notes below) |

Suite requires PHP 8.3+; runtime supports ^8.3. Suite verified on PHP 8.4 with Laravel 11, 12, and 13.

### Statamic 6 specific notes
- **Carbon 3**: `DateTransformer` uses `Carbon::format()` which is unchanged in Carbon 3. No action needed, but be aware that Statamic 6 dates now default to UTC.
- **`searchables: all` removed**: This package does not set this option. Users upgrading must update their `config/statamic/search.php` to use `'content'` or an explicit list.
- **Algolia PHP client v4**: Statamic 6 conflicts with client versions `<4.32`. This package requires `^4.32` on this branch. `AlgoliaBuildComputedIndexes` has been updated to the v4 API.

---

## Tests

Pest tests live in `tests/Unit` and `tests/Feature`, on Orchestra Testbench (`tests/TestCase.php`). Run them with `vendor/bin/pest`. Add a test with every change.

---

## Common tasks

**Add a new transformer:**
1. Create `src/Search/Transformers/MyTransformer.php` with a `handle($value, $field, $searchable)` method.
2. Document the return type and what searchable types it supports.
3. No registration needed — users reference the class directly in their search config.

**Change the number of retained index files:**
Edit `IndexBlobCleaner::$window` (default: 3). This is a static property.

**Add a new artisan command:**
1. Create the command in `src/Console/Commands/`.
2. Register it in `StatamicAlgoliaSupportProvider::configureConsole()`.
