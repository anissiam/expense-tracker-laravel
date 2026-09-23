<?php

$frontendUrl = env('FRONTEND_URL');
$statefulDomains = array_filter(array_map('trim', explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', ''))));

// Build explicit origins from FRONTEND_URL + SANCTUM_STATEFUL_DOMAINS so
// Render/Vercel deployments work. Falls back to '*' for local dev.
$corsOrigins = [];
if ($frontendUrl) {
    $corsOrigins[] = rtrim($frontendUrl, '/');
}
foreach ($statefulDomains as $domain) {
    $domain = preg_replace('#^https?://#', '', rtrim($domain, '/'));
    if ($domain !== '') {
        $corsOrigins[] = "https://{$domain}";
        $corsOrigins[] = "http://{$domain}";
    }
}
$corsOrigins = array_values(array_unique($corsOrigins));

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $corsOrigins !== [] ? $corsOrigins : ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];