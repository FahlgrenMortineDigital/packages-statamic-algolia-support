<?php

namespace Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers;

class RecordUrlTransformer
{
    public function handle($value, $field, $searchable): ?string
    {
        return match (true) {
            $searchable instanceof \Statamic\Entries\Entry => $searchable->url(),
            $searchable instanceof \Statamic\Assets\Asset => $searchable->url(),
            default => null,
        };
    }
}