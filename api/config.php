<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/lib/bootstrap.php';

$urls = app_config('WATCH_URLS', []);

Response::json([
    'ok' => true,
    'vapidPublicKey' => (string) app_config('VAPID_PUBLIC_KEY', ''),
    'appBaseUrl' => (string) app_config('APP_BASE_URL', ''),
    'finalDomain' => (string) app_config('FINAL_DOMAIN', 'https://pilatesvitalite.ca'),
    'sourceUrls' => [
        'home' => $urls['home'] ?? '',
        'about' => $urls['about'] ?? '',
        'blog' => $urls['blog'] ?? '',
        'contact' => $urls['contact'] ?? '',
    ],
]);

