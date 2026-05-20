<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    $secret = (string) ($_GET['secret'] ?? '');
    if ($secret === '' || !hash_equals((string) app_config('CRON_SECRET', ''), $secret)) {
        http_response_code(403);
        echo "Forbidden\n";
        exit(1);
    }
}

function cron_log_line(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    echo $line;

    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logDir . '/cron-' . date('Y-m') . '.log', $line, FILE_APPEND);
}

function notification_target_url(array $item): string
{
    $base = rtrim((string) app_config('APP_BASE_URL', './'), '/');

    if ($item['section'] === 'blog') {
        return $base . '/#/article/' . rawurlencode((string) $item['item_key']);
    }

    if ($item['section'] === 'about') {
        return $base . '/#/about';
    }

    return $base . '/#/home';
}

function notification_message(array $item, bool $isNew): array
{
    if ($item['section'] === 'blog') {
        return [
            'title' => $isNew ? 'Nouvel article Pilates Vitalité' : 'Article mis à jour',
            'message' => app_truncate((string) $item['title'], 180),
        ];
    }

    if ($item['section'] === 'about') {
        return [
            'title' => 'Mise à jour - À propos',
            'message' => 'La page À propos de Pilates Vitalité a été mise à jour.',
        ];
    }

    return [
        'title' => 'Mise à jour - Accueil',
        'message' => 'Un contenu important de la page Accueil a été mis à jour.',
    ];
}

try {
    cron_log_line('Starting content check.');

    $config = app_config();
    $database = new Database($config);
    $repository = new SnapshotRepository($database);
    $http = new HttpClient((int) app_config('HTTP_TIMEOUT', 15));
    $scraper = new Scraper($config, $http);
    $push = new PushService($config, $repository);

    $items = $scraper->watchedItems();
    $initialCounts = [];
    foreach (['home', 'about', 'blog'] as $section) {
        $initialCounts[$section] = $repository->countSection($section);
    }

    $changed = 0;
    $notifications = 0;

    foreach ($items as $item) {
        $section = (string) $item['section'];
        $itemKey = (string) $item['item_key'];
        $previous = $repository->latestForItem($section, $itemKey);

        if ($previous !== null && hash_equals((string) $previous['content_hash'], (string) $item['content_hash'])) {
            $repository->touchSnapshot((int) $previous['id']);
            cron_log_line(sprintf('Unchanged: %s/%s', $section, $itemKey));
            continue;
        }

        $repository->insertSnapshot($item);
        $changed++;

        $isNew = $previous === null;
        $isInitialBootstrap = $previous === null && (($initialCounts[$section] ?? 0) === 0) && !app_config('NOTIFY_ON_FIRST_RUN', false);
        $shouldNotify = !$isInitialBootstrap;

        if (!$shouldNotify) {
            cron_log_line(sprintf('Snapshot saved without notification (initial): %s/%s', $section, $itemKey));
            continue;
        }

        if ($repository->notificationExists($section, $itemKey, (string) $item['content_hash'])) {
            cron_log_line(sprintf('Notification already logged: %s/%s', $section, $itemKey));
            continue;
        }

        $copy = notification_message($item, $isNew);
        $result = $push->send($copy['title'], $copy['message'], notification_target_url($item), $section);
        $sent = (int) ($result['sent'] ?? 0);
        $repository->logNotification(
            $section,
            $itemKey,
            (string) $item['content_hash'],
            $copy['title'],
            $copy['message'],
            (string) $item['source_url'],
            $sent
        );
        $notifications++;

        cron_log_line(sprintf(
            'Notification logged: %s/%s sent=%d failed=%d',
            $section,
            $itemKey,
            $sent,
            (int) ($result['failed'] ?? 0)
        ));

        foreach (($result['errors'] ?? []) as $error) {
            cron_log_line('Push warning: ' . $error);
        }
    }

    cron_log_line(sprintf('Finished content check. changed=%d notifications=%d items=%d', $changed, $notifications, count($items)));
    exit(0);
} catch (Throwable $error) {
    cron_log_line('Cron failed: ' . $error->getMessage());
    exit(1);
}
