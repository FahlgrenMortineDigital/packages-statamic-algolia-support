<?php

return [
    'disk' => env('ALGOLIA_SUPPORT_DISK', 'algolia-indexes'),
    'api_uri' => env('ALGOLIA_SUPPORT_API_URI', '/algolia/indexes'),
    'memory_limit' => '2028M'
];
