<?php

return [
    'stateful' => [],
    'guard' => ['web'],
    'expiration' => env('SANCTUM_TOKEN_EXPIRATION', 720),
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
    'middleware' => [],
];

