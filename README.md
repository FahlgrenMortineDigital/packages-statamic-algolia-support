# Statamic Algolia Support

Add helpful support classes for working with the Algolia driver in Statamic applications.

**Compatibility:** Statamic 5.x and 6.x | Laravel 11.x and 12.x | PHP 8.3+

# Installation

```shell
$ composer require fahlgrendigital/packages-statamic-algolia-support
```

### Publish config

```shell
$ php artisan vendor:publish --tag=statamic-algolia-support-config
```

# Usage

This package focuses on three areas of support for the Algolia driver in Statamic applications:

* [Transformers](#transformers)
* [Index building](#indexes)
* [Index importing (API)](#index-api)

---

## Transformers

Transformers are Statamic [search transformers](https://statamic.dev/search#transforming-fields) that convert complex Statamic field types into Algolia-compatible formats. Register them in your `config/statamic/search.php` under a field's `transformer` key.

### CollectionTransformer

Extracts the `handle` string from a collection object. Useful for configuring Algolia facets that filter by collection.

```php
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\CollectionTransformer;

// In config/statamic/search.php
'fields' => [
    'collection' => ['transformer' => CollectionTransformer::class],
],
```

**Returns:** `string|null` — the collection handle, or `null` if empty.

---

### DateTransformer

Converts a Carbon date object into a formatted date string (`Y-m-d H:i:s`). Compatible with Carbon 2.x and 3.x (Statamic 6).

```php
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\DateTransformer;

'fields' => [
    'date' => ['transformer' => DateTransformer::class],
],
```

**Returns:** `string|null` — formatted date string, or passes through non-Carbon values unchanged.

> **Statamic 6 note:** Statamic 6 requires Carbon 3. Dates now convert to UTC at runtime. If your app uses a non-UTC timezone, configure `'localize_dates_in_modifiers' => true` in `config/statamic/system.php`.

---

### MarkupTransformer

Strips HTML from Bard and Textarea fields, returning plain searchable text. For Bard fields, it uses Statamic's `bardText` modifier to properly handle the ProseMirror document structure.

```php
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\MarkupTransformer;

'fields' => [
    'content' => ['transformer' => MarkupTransformer::class], // Bard or Textarea
],
```

**Returns:** `string|null` — plain text content, or `null` if empty.

---

### TaxonomyTransformer

Resolves taxonomy term references (slugs stored on an entry) into structured arrays containing `id`, `slug`, and `title`. Results are cached for 1 hour per term to avoid repeated lookups during large index builds.

Works with both flat-file entries and Eloquent entries.

```php
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\TaxonomyTransformer;

'fields' => [
    'tags' => ['transformer' => TaxonomyTransformer::class],
],
```

**Returns:** `array|null` — `['tags' => [['id' => ..., 'slug' => ..., 'title' => ...], ...]]`, or `null` for non-entry searchables, or `[]` for entries with no terms.

---

### RecordUrlTransformer

Generates the URL for an Entry or Asset searchable. Returns `null` for other searchable types (e.g. taxonomy terms).

```php
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\RecordUrlTransformer;

'fields' => [
    'url' => ['transformer' => RecordUrlTransformer::class],
],
```

**Returns:** `string|null` — the URL for entries and assets, `null` otherwise.

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
| `--json-stats` | Print min/max/avg record sizes after building (JSON only) |

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

> **Note:** `AlgoliaBuildComputedIndexes` uses the Algolia PHP client v3 API. Upgrading to client v4 will require rewriting this command for the new API surface.

---

## Index API

The package registers a read-only API endpoint that serves the most recently built index file:

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
| Algolia PHP client v3 | Pending | `AlgoliaBuildComputedIndexes` uses v3-specific APIs. Monitor whether Statamic 6 or its Algolia driver requires client v4 and update accordingly. |
| Entry status queries | None | This package does not use `->where('status', ...)`. No action needed. |
| Vue 3 / Control Panel | None | This package has no Control Panel components. |
