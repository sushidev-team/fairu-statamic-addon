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
    'url_proxy' => env('FAIRU_URL_PROXY', 'https://files.fairu.app'),

    /*
    |--------------------------------------------------------------------------
    | Previous Assets System
    |--------------------------------------------------------------------------
    |
    | Should the old or default asset system provided by statamic
    | be deactivated in the frontend?
    |
    */

    'deactivate_old' => env('FAIRU_DEACTIVATE_OLD', false),

    /*
    |--------------------------------------------------------------------------
    | Migration
    |--------------------------------------------------------------------------
    |
    | Settings that can be defined for migration of statamic assets
    | to fairu
    |
    */

    'migration' => [

        // Define the field that will contain the caption
        'caption' => env('FAIRU_MIGRATION_FIELD_CAPTION', 'caption'),

        // Define the field that will contain the description
        'description' => env('FAIRU_MIGRATION_FIELD_DESCRIPTION', 'description'),

        // Define the field that will contain the copyright
        'copyright' => env('FAIRU_MIGRATION_FIELD_COPYRIGHT', 'copyright'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Define how long meta data should be cached
    |
    */

    'caching_meta' => [60, 120]
];