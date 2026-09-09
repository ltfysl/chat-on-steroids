<?php

return [
    'driver' => env('TINYVM_DRIVER', 'fake'),
    'gateway' => [
        'url' => env('TINYVM_GATEWAY_URL', 'http://127.0.0.1:8787'),
        'token' => env('TINYVM_GATEWAY_TOKEN'),
        'timeout' => 15,
    ],
    'default' => [
        'vcpu' => (int) env('TINYVM_DEFAULT_VCPU', 2),
        'memory_mb' => (int) env('TINYVM_DEFAULT_MEMORY_MB', 4096),
        'disk_gb' => (int) env('TINYVM_DEFAULT_DISK_GB', 20),
        'image' => env('TINYVM_DEFAULT_IMAGE', 'grokbot-runtime:stable'),
    ],
];
