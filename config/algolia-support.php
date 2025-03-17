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
