<?php

namespace Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers;

use Statamic\Contracts\Entries\Entry as EntryContract;

class RecordUrlTransformer
{
    public function handle($value, $field, $searchable): ?string
    {
        return match (true) {
            $searchable instanceof EntryContract => $searchable->url(),
            $searchable instanceof \Statamic\Assets\Asset => $searchable->url(),
            default => null,
        };
    }
}