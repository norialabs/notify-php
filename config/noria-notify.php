<?php

return [
    'url' => env('NORIA_NOTIFY_URL', 'http://localhost:4800'),

    'key' => env('NORIA_NOTIFY_KEY', ''),

    'timeout' => (int) env('NORIA_NOTIFY_TIMEOUT', 15),

    'retries' => (int) env('NORIA_NOTIFY_RETRIES', 2),

    'fail_on_suppressed' => (bool) env('NORIA_NOTIFY_FAIL_ON_SUPPRESSED', false),

    'webhook_secret' => env('NORIA_NOTIFY_WEBHOOK_SECRET', ''),

    'webhook_tolerance' => (int) env('NORIA_NOTIFY_WEBHOOK_TOLERANCE', 300),
];
