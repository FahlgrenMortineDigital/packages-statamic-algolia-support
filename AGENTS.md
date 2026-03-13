# AGENTS.md — Statamic Algolia Support Package

This file gives AI coding agents (Claude Code, Copilot, Cursor, etc.) the context needed to work effectively in this codebase.

---

## What this package does

`fahlgrendigital/packages-statamic-algolia-support` is a Laravel/Statamic addon that provides:

1. **Search transformers** — Convert complex Statamic field types (Bard, dates, taxonomy terms, collections, assets) into Algolia-compatible scalar/array values during indexing.
2. **Index export builder** — Artisan commands that snapshot a Statamic search index to a JSON or CSV file on a filesystem disk.
3. **Computed index builder** — Merges records from multiple Algolia indexes (fetched via the Algolia API) into a single combined JSON file.
4. **Index retrieval API** — A read-only HTTP endpoint that serves the most recently built index file to downstream consumers.

---

## Package structure

```
src/
├── Console/Commands/
│   ├── AlgoliaIndexExportBuilder.php   # artisan algolia:search-index:build-file
│   └── AlgoliaBuildComputedIndexes.php # artisan algolia:build-computed-indexes
├── Http/Controllers/
│   └── AlgoliaIndexConnectorController.php  # GET /api/algolia/indexes/{index}
├── Search/
│   ├── Index/
│   │   ├── IndexAnalysis.php           # Computes min/max/avg record sizes from a JSON file
│   │   └── StringAnalysis.php          # Calculates string size in KB (multibyte-safe)
│   └── Transformers/
│       ├── CollectionTransformer.php   # Collection object → handle string
│       ├── DateTransformer.php         # Carbon → 'Y-m-d H:i:s' string
│       ├── MarkupTransformer.php       # Bard/Textarea → plain text
│       ├── RecordUrlTransformer.php    # Entry/Asset → URL string
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
- `algolia-support.computed_indexes` — map of output index name → `['sources' => [...algolia index names]]`

### Route registration
Routes are registered in `StatamicAlgoliaSupportProvider::configureRoutes()` using the `api` middleware group, prefixed at `/api`. The route is: `GET /api/{api_uri}/{index}`.

---

## Important constraints and gotchas

### TaxonomyTransformer — entry type check
Uses `Statamic\Contracts\Entries\Entry` (the interface) for the `instanceof` check so it works with both flat-file entries (`Statamic\Entries\Entry`) and Eloquent entries (`Statamic\Eloquent\Entries\Entry`). Do **not** change this back to a concrete class import.

### AlgoliaBuildComputedIndexes — Algolia PHP client v3 API
This command uses the v3 API surface:
- `SearchClient::create($appId, $apiKey)`
- `$client->initIndex($indexName)`
- `$_index->browseObjects()`

These APIs do **not** exist in v4. If the Algolia PHP client is upgraded to v4, this command must be rewritten using the new `\Algolia\AlgoliaSearch\Api\SearchClient` and `searchIndex()->browseObjects()` patterns.

### AlgoliaIndexExportBuilder — JSON file format
The command appends one JSON-encoded array per searchable collection chunk using `Storage::disk()->append()`. The resulting file is **not** a single JSON array — it is multiple JSON arrays concatenated, one per chunk. Downstream consumers must handle this format.

### Disk must be defined
The `algolia-support.disk` config value must match a disk name in `config/filesystems.php`. Both commands validate this at startup and exit with a failure code if the disk is missing.

### Memory limit
The builder command sets `memory_limit` from `config('algolia-search.memory_limit')` — note: this is `algolia-search` (with a hyphen after `algolia`), not `algolia-support`. This appears to be a typo/inconsistency in the original code — it should likely be `algolia-support.memory_limit`. Be aware of this if debugging memory issues.

---

## Statamic compatibility

| Statamic | Laravel | PHP  | Status |
|----------|---------|------|--------|
| 5.x      | 11, 12  | 8.3+ | Supported |
| 6.x      | 12      | 8.3+ | Supported (see notes below) |

### Statamic 6 specific notes
- **Carbon 3**: `DateTransformer` uses `Carbon::format()` which is unchanged in Carbon 3. No action needed, but be aware that Statamic 6 dates now default to UTC.
- **`searchables: all` removed**: This package does not set this option. Users upgrading must update their `config/statamic/search.php` to use `'content'` or an explicit list.
- **Algolia PHP client**: Statamic 6's built-in Algolia driver may require client v4. The `AlgoliaBuildComputedIndexes` command would need to be updated if so.

---

## No tests exist

There is no test suite in this package yet. PHPUnit and Orchestra Testbench are listed as dev dependencies, but no test files have been written. When adding tests, use `orchestra/testbench` with a Statamic-aware `TestCase`.

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
