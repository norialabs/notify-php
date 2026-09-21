<?php

use NoriaLabs\Send\Send;

return [
    'url' => env('NORIA_SEND_URL', Send::DEFAULT_BASE_URL),

    'key' => env('NORIA_SEND_KEY', ''),

    'timeout' => (int) env('NORIA_SEND_TIMEOUT', 15),

    'retries' => (int) env('NORIA_SEND_RETRIES', 2),

    'fail_on_suppressed' => (bool) env('NORIA_SEND_FAIL_ON_SUPPRESSED', false),

    'webhook_secret' => env('NORIA_SEND_WEBHOOK_SECRET', ''),

    'webhook_tolerance' => (int) env('NORIA_SEND_WEBHOOK_TOLERANCE', 300),
];
