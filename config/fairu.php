<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fairu Basis configuration
    |--------------------------------------------------------------------------
    |
    |
    */

    'connections' => [
        'default' => [
            'tenant' => env('FAIRU_TENANT'),
            'tenant_secret' => env('FAIRU_TENANT_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fairu Endpoints
    |--------------------------------------------------------------------------
    |
    | If you have an enterprise version of fairu you can provide a differnt url
    | here. Let the url as it is for the default endpoint.
    |
    */
    'url' => env('FAIRU_URL', 'https://fairu.app'),

];