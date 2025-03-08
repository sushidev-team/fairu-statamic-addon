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

    /*
    |--------------------------------------------------------------------------
    | Previous Assets System
    |--------------------------------------------------------------------------
    |
    | Should the old or default asset system provided by statamic
    | be deactivated in the frontend?
    |
    */

    'deactivate_old' => env('FAIRU_DEACTIVATE_OLD', true),

];