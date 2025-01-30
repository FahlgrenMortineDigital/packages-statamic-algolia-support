<?php

namespace Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers;

use Statamic\Modifiers\CoreModifiers;

class MarkupTransformer
{
    public function handle($value, $field, $searchable): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (is_string($value)) {
            $content = trim(strip_tags($value));
        } else {
            $modifier = new CoreModifiers();
            $value    = $searchable->augmentedValue($field);
            $content  = $modifier->bardText($value);
        }

        if (empty($content)) {
            return null;
        }

        return $content;
    }
}
