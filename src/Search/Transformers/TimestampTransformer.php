<?php

namespace Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class TimestampTransformer
{
    /**
     * Convert a date value to a Unix timestamp (UTC seconds) that Algolia can filter numerically.
     *
     * Accepts a Carbon/DateTime instance, a date or datetime string, or null.
     * Anything empty, unparseable, or of another type returns null. A bad value
     * must not abort an indexing batch. A non-empty value that cannot be parsed
     * logs a warning with the field handle, the searchable id, and the raw value.
     * Null and empty values return null silently.
     *
     * A date-only string ("2026-11-04") means midnight in the app timezone
     * (config 'app.timezone'), converted to UTC seconds. A datetime string without
     * an offset is read in the app timezone too. A string with an offset keeps it.
     */
    public function handle($value, $field, $searchable): ?int
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        $timestamp = is_string($value) ? $this->parse(trim($value)) : null;

        if ($timestamp === null) {
            Log::warning('TimestampTransformer could not parse a date value.', [
                'field' => $field,
                'searchable_id' => is_object($searchable) && method_exists($searchable, 'id') ? $searchable->id() : null,
                'value' => $value,
            ]);
        }

        return $timestamp;
    }

    private function parse(string $value): ?int
    {
        $timezone = config('app.timezone', 'UTC');

        // Reject relative strings such as "tomorrow" or "now". Stored dates start with Y-m-d.
        if (! preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $date = Carbon::createFromFormat('!Y-m-d', $value, $timezone);

                // createFromFormat rolls "2026-02-31" over to March. Treat that as invalid.
                return $date && $date->format('Y-m-d') === $value ? $date->getTimestamp() : null;
            }

            // Carbon::parse rolls impossible dates over and reads "+1 day" as an offset. Reject both.
            $parts = date_parse($value);

            if ($parts['warning_count'] > 0 || $parts['error_count'] > 0 || isset($parts['relative'])) {
                return null;
            }

            return Carbon::parse($value, $timezone)->getTimestamp();
        } catch (Throwable) {
            return null;
        }
    }
}
