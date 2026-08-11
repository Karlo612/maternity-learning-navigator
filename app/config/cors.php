<?php

$origins = array_values(array_filter(array_map(
    static fn (string $origin): string => trim($origin),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:8080')),
)));

return [
    'paths' => ['api/*', 'graphql'],
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Content-Type', 'Origin', 'X-Requested-With'],
    'exposed_headers' => ['X-Request-Id'],
    'max_age' => 600,
    'supports_credentials' => false,
];
