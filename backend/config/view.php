<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | This array of paths tells Laravel where to look for your Blade templates.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | Here is where compiled Blade templates will be cached for performance.
    | Make sure this directory exists and is writable.
    |
    */

    'compiled' => realpath(storage_path('framework/views')),

];
