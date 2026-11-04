<?php

return [
    'face_ai' => [
        'url' => env('FACE_AI_SERVICE_URL'),
        'token' => env('FACE_AI_SERVICE_TOKEN'),
        'timeout' => (int) env('FACE_AI_SERVICE_TIMEOUT', 45),
    ],
];
