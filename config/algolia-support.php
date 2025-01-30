<?php

return [
    /** ---------------------------------------------------------------
     *  Configure the disk where index exports will be stored
     * ----------------------------------------------------------------
     */
    'disk' => env('ALGOLIA_SUPPORT_DISK', 'algolia-indexes'),

    /** ----------------------------------------------------------------
     * Configure the API URI for the Algolia ingest service to connect.
     * ----------------------------------------------------------------
     */
    'api_uri' => env('ALGOLIA_SUPPORT_API_URI', '/algolia/indexes'),

    /** ----------------------------------------------------------------
     * The memory limit for the Algolia index export builder command.
     * ----------------------------------------------------------------
     */
    'memory_limit' => '2028M'
];
