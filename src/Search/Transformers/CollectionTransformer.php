<?php

namespace Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers;


class CollectionTransformer
{
    public function handle($value, $field, $searchable): ?string
    {
        if (empty($value)) {
            return null;
        }

        if(is_array($value)) {
            return $value['handle'] ?? null;
        }

        return $value;
    }
}
