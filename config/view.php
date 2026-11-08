<?php

return [
    'paths' => [
        resource_path('views'),
    ],

    'compiled' => env('VERCEL')
        ? sys_get_temp_dir()
        : env('VIEW_COMPILED_PATH', realpath(storage_path('framework/views'))),
];
