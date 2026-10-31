<?php

return [
    'username' => env('SOLOINVENTORY_USERNAME', ''),
    'password_hash' => env('SOLOINVENTORY_PASSWORD_HASH', ''),
    'photo_max_kb' => 12288,
    'trusted_proxies' => env('SOLOINVENTORY_TRUSTED_PROXIES'),
];
