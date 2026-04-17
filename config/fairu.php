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
    | Define how long meta data should be cached.
    |
    | Note on fetchMeta modes (used by {{ fairu:image }}, {{ fairu:images }}, {{ fairu }}):
    |   - fetchMeta="true"  → lean API call (/api/files/meta): id, name, focal_point,
    |                          width, height, alt, description, is_image, mime.
    |                          Default; significantly faster than "full".
    |   - fetchMeta="full"  → heavy API call (/api/files/list): includes licenses,
    |                          copyrights and block status. Use only when template
    |                          logic depends on license/block info.
    |
    */

    'caching_meta' => [60, 120],

    /*
    |--------------------------------------------------------------------------
    | Meta Coalescing
    |--------------------------------------------------------------------------
    |
    | When enabled (default), every {{ fairu:image }} and {{ fairu:url ... fetchMeta="true" }}
    | tag on a page emits a lightweight placeholder during render. A response
    | middleware then fetches meta for every queued id in one batched call to
    | /api/files/meta and rewrites the placeholders in-place. This collapses N
    | serial backend calls into a single request regardless of how deeply the
    | tags are nested in components.
    |
    | Note: if you wrap fairu tags inside Statamic's {{ cache }} block, the
    | placeholder will be cached and subsequent renders will render an unknown
    | placeholder. Use response-level static caching instead.
    |
    */

    'coalesce_meta' => env('FAIRU_COALESCE_META', true),
];