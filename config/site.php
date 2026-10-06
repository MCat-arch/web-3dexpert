<?php

return [
    /*
    |---------------------------------------------------------------------------
    | Locales
    |---------------------------------------------------------------------------
    | Legacy served Indonesian from index.php and English from index-en.php --
    | a near-duplicate file with ~406 differing lines, so every content change
    | had to be made twice. Only the homepage was ever duplicated; the rest of
    | the site had no English version at all.
    |
    | Here the locale is a route prefix and the content is translatable, so
    | there is exactly one set of views.
    */
    'locales' => [
        'id' => ['name' => 'Indonesia', 'native' => 'Bahasa Indonesia'],
        'en' => ['name' => 'English', 'native' => 'English'],
    ],

    // The default locale is served WITHOUT a prefix, so "/" stays Indonesian
    // and the old homepage URL keeps its meaning.
    'default_locale' => 'id',

    /*
    |---------------------------------------------------------------------------
    | Media
    |---------------------------------------------------------------------------
    | Uploads MUST NOT live under public/. Legacy wrote uploads straight into
    | the web-served assets/images/ with no extension check on the main image
    | (edit.php: uniqid().'_'.$_FILES['gambar_utama']['name']), so a .php
    | upload became executable server-side.
    |
    | Files go to a private disk and are served through App\Http\Controllers\
    | MediaController, which sets its own Content-Type.
    */
    'media_disk' => env('MEDIA_DISK', 'media'),

    'uploads' => [
        'image' => [
            'max_kb' => 5120,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        ],
        'document' => [
            'max_kb' => 20480,
            'mimes' => ['pdf'],
            'mime_types' => ['application/pdf'],
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Pagination
    |---------------------------------------------------------------------------
    */
    'per_page' => [
        'portfolio' => 12,
        'careers' => 15,
    ],

    /*
    |---------------------------------------------------------------------------
    | Legacy database connection
    |---------------------------------------------------------------------------
    | Used only by `php artisan app:migrate-legacy-data`. Leave unset in
    | production once the migration is done.
    */
    'legacy_connection' => env('LEGACY_DB_CONNECTION', 'legacy'),
];
