<?php

namespace Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers;

use Statamic\Eloquent\Entries\Entry;
use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;

class TaxonomyTransformer
{
    public function handle($value, $field, $searchable): ?array
    {
        if (!$searchable instanceof Entry) { // only processing Entry instances
            return null;
        }

        $value = $searchable->get($field);

        if (empty($value)) {
            return [];
        }

        return [
            $field => collect($value)->map(function ($item) use($field) {
                return $this->fetchCachedTerm(sprintf("%s::%s", $field, $item));
            })->filter()->map(function (LocalizedTerm $term) {
                return [
                    'id' => $term->id(),
                    'slug' => $term->slug(),
                    'title' => $term->title(),
                ];
            })->values()->all()
        ];
    }

    private function fetchCachedTerm(string $key): ?LocalizedTerm
    {
        return cache()->remember("algolia.taxonomy_term.{$key}", 3600, function () use ($key) {
            return Term::find($key);
        });
    }
}
