<?php

declare(strict_types=1);

date_default_timezone_set('America/Toronto');

$configPath = __DIR__ . '/../config/config.php';
$exampleConfigPath = __DIR__ . '/../config/config.example.php';
$config = file_exists($configPath) ? require $configPath : require $exampleConfigPath;

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/Scraper.php';
require_once __DIR__ . '/SnapshotRepository.php';
require_once __DIR__ . '/PushService.php';

function app_config(?string $key = null, mixed $default = null): mixed
{
    global $config;

    if ($key === null) {
        return $config;
    }

    return $config[$key] ?? $default;
}

function app_clean_text(string $value): string
{
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/\x{00a0}/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}

function app_truncate(string $value, int $length = 220): string
{
    $value = app_clean_text($value);

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($value, 'UTF-8') <= $length) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $length - 1, 'UTF-8')) . '...';
    }

    if (strlen($value) <= $length) {
        return $value;
    }

    return rtrim(substr($value, 0, $length - 1)) . '...';
}

function app_absolute_url(string $url, string $baseUrl): string
{
    if ($url === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }

    $base = parse_url($baseUrl);
    $scheme = $base['scheme'] ?? 'https';
    $host = $base['host'] ?? '';

    if (str_starts_with($url, '//')) {
        return $scheme . ':' . $url;
    }

    if (str_starts_with($url, '/')) {
        return $scheme . '://' . $host . $url;
    }

    $path = $base['path'] ?? '/';
    $directory = rtrim(dirname($path), '/\\');

    return $scheme . '://' . $host . ($directory ? '/' . ltrim($directory, '/') : '') . '/' . ltrim($url, '/');
}

function app_slug_from_url(string $url): string
{
    $path = (string) parse_url($url, PHP_URL_PATH);
    $slug = basename(trim($path, '/'));
    $slug = preg_replace('/[^a-zA-Z0-9\-_]+/', '-', $slug) ?? $slug;

    return trim($slug, '-') ?: hash('sha256', $url);
}

function app_json_hash(mixed $value): string
{
    return hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

