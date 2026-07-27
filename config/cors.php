<?php

return [
    /*
     * Izinkan API CR eksternal dipanggil dari website lain.
     * Karena autentikasi memakai header API key, kita tidak butuh cookie/credential cross-site.
     */
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
