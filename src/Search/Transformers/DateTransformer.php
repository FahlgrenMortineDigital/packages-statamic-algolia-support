<?php

namespace Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers;

use Carbon\Carbon;

class DateTransformer
{
    public function handle($value, $field, $searchable): ?string
    {
        if (empty($value)) {
            return null;
        }

        if($value instanceof Carbon) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value;
    }
}
