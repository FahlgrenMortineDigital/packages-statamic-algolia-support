<?php

return [
    /**
     * ---------------------------------------------------------------
     *  Configure the disk where index exports will be stored
     * ----------------------------------------------------------------
     */
    'disk' => env('ALGOLIA_SUPPORT_DISK', 'algolia-indexes'),

    /**
     * ----------------------------------------------------------------
     * Configure the API URI for the Algolia ingest service to connect.
     * ----------------------------------------------------------------
     */
    'api_uri' => env('ALGOLIA_SUPPORT_API_URI', '/algolia/indexes'),

    /**
     * ----------------------------------------------------------------
     * The index retrieval API is off by default. When enabled, requests
     * need `Authorization: Bearer {api_token}`. An empty token rejects
     * every request. `api_middleware` runs after the token check
     * (for example `['throttle:60,1']`).
     * ----------------------------------------------------------------
     */
    'api_enabled' => env('ALGOLIA_SUPPORT_API_ENABLED', false),
    'api_token' => env('ALGOLIA_SUPPORT_API_TOKEN'),
    'api_middleware' => [],

    /**
     * ----------------------------------------------------------------
     * The memory limit for the Algolia index export builder command.
     * ----------------------------------------------------------------
     */
    'memory_limit' => '2028M',


    /** ----------------------------------------------------------------
     * Configured combined indexes where multiple source indexes
     * provide data for a new index. All indexes must live in the same
     * Algolia application.
     *
     * Example:
     *
     * 'computed_indexes' => [
     *     'combined_products' => ['index_1', 'index_2']
     * ]
     * ----------------------------------------------------------------
     */
    'computed_indexes' => []
];
