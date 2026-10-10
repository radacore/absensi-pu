<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Halaman Inertia
    |--------------------------------------------------------------------------
    |
    | Direktori halaman memakai huruf kapital (resources/js/Pages).
    | Default paket adalah resource_path('js/pages') (huruf kecil) yang
    | hanya cocok di filesystem case-insensitive (macOS) — di Linux,
    | asersi component() pada belasan tes gagal dengan
    | "Inertia page component file [...] does not exist".
    |
    | Nilai lain disalin apa adanya dari default paket
    | (vendor/inertiajs/inertia-laravel/config/inertia.php) karena
    | mergeConfigFrom menggabung dangkal per kunci teratas.
    |
    */

    'pages' => [

        'ensure_pages_exist' => false,

        'paths' => [

            resource_path('js/Pages'),

        ],

        'extensions' => [

            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',

        ],

    ],

];
