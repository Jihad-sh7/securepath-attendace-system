<?php

return [
    'duplicate_recognition_window_seconds' => (int) env('DUPLICATE_RECOGNITION_WINDOW_SECONDS', 60),
    'minimum_confidence' => (float) env('RECOGNITION_MINIMUM_CONFIDENCE', 0.85),
    'review_threshold' => (float) env('RECOGNITION_REVIEW_THRESHOLD', 0.75),
];
