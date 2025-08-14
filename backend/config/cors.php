<?php

return [
    'paths' => [
        'api/*',
        'login',
        'logout',
        #'register',
        'sanctum/csrf-cookie',
    ],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:5173',
    ],
    'allowed_headers' => ['*'],
    'supports_credentials' => true,
];

