<?php

return [

    'server_url' => rtrim(env('PLATFORM_SERVER_URL', 'https://uticms.ru'), '/'),

    'server_public_key' => env('PLATFORM_SERVER_PUBLIC_KEY', ''),

    'server_public_key_previous' => env('PLATFORM_SERVER_PUBLIC_KEY_PREVIOUS'),

    'registration_key' => env('PLATFORM_KEY'),

    'environment_type' => env('PLATFORM_ENV', 'production'),

    'channel' => env('PLATFORM_CHANNEL', 'stable'),

    'storage_path' => storage_path('app/platform'),

    'heartbeat_interval_hours' => (int) env('PLATFORM_HEARTBEAT_INTERVAL_HOURS', 24),

    'offline_grace_days' => (int) env('PLATFORM_OFFLINE_GRACE_DAYS', 14),

    'api_timeout_seconds' => (int) env('PLATFORM_API_TIMEOUT_SECONDS', 15),

    'certificate_renew_before_days' => (int) env('PLATFORM_CERTIFICATE_RENEW_BEFORE_DAYS', 7),

    'client_version' => env('PLATFORM_CLIENT_VERSION', 'uticms-platform/1.0.0'),

    'core_version' => env('PLATFORM_CORE_VERSION', '1.0.0'),

];
