<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // 1. Especifica tu origen para mayor seguridad
   'allowed_origins' => ['http://localhost:4200', 'http://localhost:4201'],
'allowed_headers' => ['*'],
'allowed_methods' => ['*'],
'supports_credentials' => true,
'allowed_headers' => ['*'], // Permite que el Interceptor envíe el Token
'allowed_methods' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // 2. CAMBIA ESTO A TRUE (Es vital para Sanctum)
    'supports_credentials' => true, 
];