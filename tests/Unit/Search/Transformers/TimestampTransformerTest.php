<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Fahlgrendigital\PackagesStatamicAlgoliaSupport\Search\Transformers\TimestampTransformer;

beforeEach(function () {
    config(['app.timezone' => 'America/New_York']);
    $this->transformer = new TimestampTransformer();
});

it('returns the timestamp of a carbon datetime', function () {
    $date = Carbon::parse('2026-03-16 14:30:00', 'UTC');

    expect($this->transformer->handle($date, 'date', null))->toBe(1773671400);
});

it('reads a date-only string as midnight in the app timezone', function () {
    // 2026-11-04 is EST (UTC-5): midnight local is 05:00 UTC.
    expect($this->transformer->handle('2026-11-04', 'start_date', null))
        ->toBe(Carbon::parse('2026-11-04 05:00:00', 'UTC')->getTimestamp());
});

it('reads a datetime string without offset in the app timezone', function () {
    expect($this->transformer->handle('2026-11-04 10:30:00', 'start_date', null))
        ->toBe(Carbon::parse('2026-11-04 15:30:00', 'UTC')->getTimestamp());
});

it('keeps the offset on a datetime string that has one', function () {
    expect($this->transformer->handle('2026-11-04T10:30:00+00:00', 'start_date', null))
        ->toBe(Carbon::parse('2026-11-04 10:30:00', 'UTC')->getTimestamp());
});

it('returns null for unparseable strings', function (string $value) {
    expect($this->transformer->handle($value, 'start_date', null))->toBeNull();
})->with(['not a date', 'tomorrow', '2026-02-31', '2026-13-45 99:99:99']);

it('returns null for null and empty values', function () {
    expect($this->transformer->handle(null, 'date', null))->toBeNull()
        ->and($this->transformer->handle('', 'date', null))->toBeNull();
});

it('handles midnight across a DST boundary', function () {
    $before = $this->transformer->handle('2026-03-08', 'start_date', null); // EST, 05:00 UTC
    $after = $this->transformer->handle('2026-03-09', 'start_date', null);  // EDT, 04:00 UTC

    expect($before)->toBe(Carbon::parse('2026-03-08 05:00:00', 'UTC')->getTimestamp())
        ->and($after)->toBe(Carbon::parse('2026-03-09 04:00:00', 'UTC')->getTimestamp())
        ->and($after - $before)->toBe(23 * 3600); // spring-forward day is 23 hours
});

it('handles midnight on the fall-back day', function () {
    $before = $this->transformer->handle('2026-11-01', 'start_date', null); // EDT, 04:00 UTC
    $after = $this->transformer->handle('2026-11-02', 'start_date', null);  // EST, 05:00 UTC

    expect($after - $before)->toBe(25 * 3600);
});

it('logs a warning with field, searchable id and raw value for an unparseable value', function () {
    Log::spy();
    $searchable = new class {
        public function id(): string
        {
            return 'entry-123';
        }
    };

    expect($this->transformer->handle('not a date', 'start_date', $searchable))->toBeNull();

    Log::shouldHaveReceived('warning')->once()->with(
        Mockery::type('string'),
        ['field' => 'start_date', 'searchable_id' => 'entry-123', 'value' => 'not a date'],
    );
});

it('does not log for null or empty string', function () {
    Log::spy();

    $this->transformer->handle(null, 'start_date', null);
    $this->transformer->handle('', 'start_date', null);

    Log::shouldNotHaveReceived('warning');
});
