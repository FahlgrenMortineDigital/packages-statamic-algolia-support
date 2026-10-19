# Statamic Algolia Support

Add helpful support classes for working with the Algolia driver in Statamic applications.

**Compatibility:** Statamic 5.x and 6.x | Laravel 11.x, 12.x and 13.x | PHP 8.3+

# Installation

```shell
$ composer require fahlgrendigital/packages-statamic-algolia-support
```

### Publish config

```shell
$ php artisan vendor:publish --tag=statamic-algolia-support-config
```

# Usage

This package focuses on four areas of support for the Algolia driver in Statamic applications:

* [Transformers](#transformers)
* [Applying settings](#applying-settings)
* [Index building](#indexes)
* [Index importing (API)](#index-api)

---

## Transformers

Transformers are Statamic [search transformers](https://statamic.dev/search#transforming-fields) that convert complex Statamic field types into Algolia-compatible formats. Register them in your `config/statamic/search.php`. Statamic reads `fields` as a flat list of field handles and `transformers` as a map of field handle to class.

### CollectionTransformer

Extracts the `handle` string from a collection object. Useful for configuring Algolia facets that filter by collection.

```php
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\CollectionTransformer;

// In config/statamic/search.php
'fields' => ['collection'],
'transformers' => [
    'collection' => CollectionTransformer::class,
],
```

**Returns:** `string|null` — the collection handle, or `null` if empty.

---

### DateTransformer

Converts a Carbon date object into a formatted date string (`Y-m-d H:i:s`). Compatible with Carbon 2.x and 3.x (Statamic 6).

```php
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\DateTransformer;

'fields' => ['date'],
'transformers' => [
    'date' => DateTransformer::class,
],
```

**Returns:** `string|null` — formatted date string, or passes through non-Carbon values unchanged.

> **Statamic 6 note:** Statamic 6 requires Carbon 3. Dates now convert to UTC at runtime. If your app uses a non-UTC timezone, configure `'localize_dates_in_modifiers' => true` in `config/statamic/system.php`.

---

### TimestampTransformer

Converts a date value into a Unix timestamp (integer seconds, UTC) so Algolia can filter and sort on it numerically. Use it where `DateTransformer` is not enough: Algolia cannot filter a `Y-m-d H:i:s` string numerically.

```php
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\TimestampTransformer;

'transformers' => [
    'date' => TimestampTransformer::class,       // Carbon from Entry::date()
    'start_date' => TimestampTransformer::class, // stored string, e.g. "2026-11-04"
],
```

| Input | Output |
|---|---|
| Carbon / DateTime instance | `getTimestamp()` |
| Date-only string (`2026-11-04`) | Midnight in the app timezone (`config('app.timezone')`), converted to UTC seconds |
| Datetime string without offset | Read in the app timezone, converted to UTC seconds |
| Datetime string with offset | Offset respected |
| `null`, empty string | `null` |
| Unparseable string, impossible date (`2026-02-31`), relative string (`tomorrow`), other types | `null` |

**Returns:** `int|null`.

Unparseable values return `null` instead of throwing. One bad value must not abort an indexing batch. A non-empty unparseable value logs a warning with the field handle, the searchable id, and the raw value. `null` and empty strings do not log. The record gets no usable timestamp, so filter on a companion boolean if you need to find those records.

Midnight follows the app timezone's offset on that date, so a date on either side of a DST change differs by 23 or 25 hours, not 24.

---

### MarkupTransformer

Strips HTML from Bard and Textarea fields, returning plain searchable text. For Bard fields, it uses Statamic's `bardText` modifier to properly handle the ProseMirror document structure.

```php
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\MarkupTransformer;

'fields' => ['content'],
'transformers' => [
    'content' => MarkupTransformer::class, // Bard or Textarea
],
```

**Returns:** `string|null` — plain text content, or `null` if empty.

---

### TaxonomyTransformer

Resolves taxonomy term references (slugs stored on an entry) into structured arrays containing `id`, `slug`, and `title`. Results are cached for 1 hour per term to avoid repeated lookups during large index builds.

Works with both flat-file entries and Eloquent entries.

```php
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\TaxonomyTransformer;

'fields' => ['tags'],
'transformers' => [
    'tags' => TaxonomyTransformer::class,
],
```

**Returns:** `array|null` — `['tags' => [['id' => ..., 'slug' => ..., 'title' => ...], ...]]`, or `null` for non-entry searchables. Entries with no terms return `[]`, which Statamic spreads into the record, so the record gets no attribute.

---

### RecordUrlTransformer

Generates the URL for an Entry or Asset searchable. Entries are matched on the `Statamic\Contracts\Entries\Entry` contract, so flat-file, Eloquent and custom entry classes all work. Returns `null` for other searchable types (e.g. taxonomy terms).

```php
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\RecordUrlTransformer;

'fields' => ['url'],
'transformers' => [
    'url' => RecordUrlTransformer::class,
],
```

**Returns:** `string|null` — the URL for entries and assets, `null` otherwise.

---

## Applying settings

Statamic's Algolia driver sends an index's `settings` key to the primary index only. It never sets a replica's own ranking, and it never prefixes replica names. `algolia:apply-settings` closes both gaps from config.

```shell
php artisan algolia:apply-settings {index?} [--dry-run] [--max-records=N]
```

| Option | Description |
|---|---|
| `{index}` | Statamic index handle (the key in `config/statamic/search.php`). Omit to run every index whose driver is `algolia`. |
| `--dry-run` | Print the payload for each index and replica. Makes no Algolia calls, so the diff and `--max-records` are skipped. |
| `--max-records=N` | Read the primary's record count, multiply by `1 + standard replicas`, and fail before any write if the result exceeds `N`. Virtual replicas are not counted. |

### Config

Put `replicas` beside `settings` in the index config. Do not put it inside `settings`: the driver sends `settings` to Algolia as written, so replica names would go out unprefixed.

```php
// config/statamic/search.php
'content' => [
    'driver' => 'algolia',
    'searchables' => ['collection:articles'],
    'fields' => ['title', 'date'],
    'settings' => [                     // primary settings. The driver also sends these.
        'searchableAttributes' => ['title', 'summary'],
        'attributesForFaceting' => ['filterOnly(collection)'],
        'customRanking' => ['desc(date_ts)'],
    ],
    'replicas' => [                     // read only by algolia:apply-settings
        // Standard replica: its own settings, keyed by unprefixed name.
        'title_asc' => ['ranking' => ['asc(title)', 'typo', 'geo', 'words', 'filters', 'proximity', 'attribute', 'exact', 'custom']],
        // Virtual replica: customRanking and relevancyStrictness only.
        'virtual(date_desc)' => ['customRanking' => ['desc(date_ts)']],
        // A bare string is a standard replica with no overrides.
        'start_date_asc',
    ],
],
```

For each index the command:

1. Resolves the primary and every replica name through `Index::resolveNameUsing`, so a prefix applies to both.
2. Sends the primary `settings` plus the prefixed `replicas` list, in Algolia's `virtual(name)` syntax for virtual replicas.
3. Applies each replica's settings. A standard replica gets the primary's `settings` merged with its own overrides (overrides win), because Algolia copies the primary only when it creates a replica. A virtual replica gets only its own overrides and inherits the rest.
4. Prints a current-vs-desired diff per setting key and calls `setSettings` only when something differs. A second run changes nothing. The command compares only the keys you configure, and it always replaces the primary's `replicas` list with the configured one.

It fails, before any call, when `settings` contains `replicas`, a virtual replica carries other keys, or a replica is listed twice. The command uses the Algolia client v4 and the credentials from the index config.

> **Driver behavior:** `search:update` clears the index, then re-sends `settings` (without `replicas`). Algolia treats that as a partial update, so replicas survive. Standard replicas are emptied and refilled with the primary. Run `algolia:apply-settings` after changing `settings` or `replicas`.

---

## Indexes

The index builder exports Statamic search indexes to JSON or CSV files on a configured filesystem disk. This is useful for:

- Bulk-replacing an Algolia index without hitting record-level API limits
- Keeping index snapshots across environments
- Feeding downstream services via the [Index API](#index-api)

### Filesystem disk configuration

Define a disk in `config/filesystems.php`:

```php
'algolia-indexes' => [
    'driver' => 'local',
    'root'   => storage_path('app/algolia-indexes'),
],
```

Then set `ALGOLIA_SUPPORT_DISK=algolia-indexes` in your `.env` (or publish and edit `config/algolia-support.php`).

### Commands

**Build a single index export:**

```shell
php artisan algolia:search-index:build-file {index} -T json/csv [-D] [--json-stats]
```

| Option | Description |
|---|---|
| `{index}` | The Statamic search index key (must exist in `config/statamic/search.php`) |
| `-T, --file-type` | Export format: `json` or `csv` |
| `-D, --dry-run` | Run without writing any files |
| `--json-stats` | Print min/max/avg record sizes after building (JSON only). Reads one JSON array per line. Skipped when no records were exported. |

Files are named `search-index-{index}-{timestamp}.{ext}`. Only the 3 most recent files per index are kept; older files are deleted automatically.

**Build computed (combined) indexes:**

```shell
php artisan algolia:build-computed-indexes
```

Merges records from multiple Algolia indexes (fetched via the Algolia API) into a single JSON file. Configure sources in `config/algolia-support.php`:

```php
'computed_indexes' => [
    'combined_products' => [
        'sources' => ['products_en', 'products_es'],
    ],
],
```

> **Note:** `AlgoliaBuildComputedIndexes` uses the Algolia PHP client v4 API (`^4.32`, per `composer.json`).

---

## Index API

The package registers a read-only API endpoint that serves the most recently built index file. **It is disabled by default** and returns 404 until you enable it:

```dotenv
ALGOLIA_SUPPORT_API_ENABLED=true
ALGOLIA_SUPPORT_API_TOKEN=a-long-random-string
```

Requests must send `Authorization: Bearer {token}`. A missing, wrong or unset token returns 401. Add `api_middleware` in `config/algolia-support.php` (for example `['throttle:60,1']`) to run more middleware after the token check.

```
GET /api/algolia/indexes/{index}
```

Returns the latest `search-index-{index}-*.json` file from the configured disk as a binary file response. Returns 404 if the index doesn't exist or no file has been built yet.

The URI path is configurable via `ALGOLIA_SUPPORT_API_URI` (default: `/algolia/indexes`).

---

## Configuration reference

```php
// config/algolia-support.php
return [
    // Filesystem disk name where index exports are stored
    'disk'             => env('ALGOLIA_SUPPORT_DISK', 'algolia-indexes'),

    // URI prefix for the index retrieval API
    'api_uri'          => env('ALGOLIA_SUPPORT_API_URI', '/algolia/indexes'),

    // Index retrieval API: off by default, bearer token required when on
    'api_enabled'      => env('ALGOLIA_SUPPORT_API_ENABLED', false),
    'api_token'        => env('ALGOLIA_SUPPORT_API_TOKEN'),
    'api_middleware'   => [],

    // PHP memory limit for the index builder command
    'memory_limit'     => '2028M',

    // Computed indexes: each key is the output index name,
    // 'sources' lists Algolia index names to merge from
    'computed_indexes' => [],
];
```

---

## Statamic 5 → 6 upgrade notes

| Area | Impact | Action |
|---|---|---|
| `statamic/cms` constraint | — | Package now supports `^5.0\|^6.0`. Run `composer update`. |
| Carbon 3 | Low | `DateTransformer` uses `->format()` which is compatible with Carbon 3. Review UTC timezone behavior (see note above). |
| Search `searchables: all` removed | Medium | Removed in Statamic 6. Replace with `'content'` or an explicit list in your `config/statamic/search.php`. |
| Algolia PHP client v4 | Done | `AlgoliaBuildComputedIndexes` uses the v4 API. Statamic 6 conflicts with client versions below 4.32, so the package requires `^4.32`. |
| Entry status queries | None | This package does not use `->where('status', ...)`. No action needed. |
| Vue 3 / Control Panel | None | This package has no Control Panel components. |
