<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*'],
    'allowed_origins' => ['http://localhost:8080', 'http://localhost:8081'],
    'allowed_methods' => ['*'],
    'allowed_headers' => ['*'],    

];
