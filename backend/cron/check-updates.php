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

$storageDir = __DIR__ . '/../storage';
if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
    echo "Unable to create cron storage directory.\n";
    exit(1);
}

$lockHandle = fopen($storageDir . '/check-updates.lock', 'c');
if ($lockHandle === false) {
    echo "Unable to open cron lock.\n";
    exit(1);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fclose($lockHandle);
    echo "Content check already running.\n";
    exit(0);
}

register_shutdown_function(static function () use ($lockHandle): void {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
});

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
    $section = (string) ($item['section'] ?? '');

    if ($section === 'blog') {
        return $base . '/#/article/' . rawurlencode((string) $item['item_key']);
    }

    if ($section === 'about') {
        return $base . '/#/about';
    }

    if (in_array($section, ['course', 'package'], true)) {
        $sourceUrl = (string) ($item['source_url'] ?? '');
        if (filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            return $sourceUrl;
        }
    }

    return $base . '/#/home';
}

function notification_category(string $section): string
{
    return match ($section) {
        'home', 'about' => 'site_updates',
        'blog' => 'blog',
        'course', 'package' => 'offers',
        'newsletter' => 'newsletter',
        default => throw new InvalidArgumentException('Unknown notification section: ' . $section),
    };
}

function notification_message(array $item, bool $isNew): array
{
    $section = (string) ($item['section'] ?? '');

    if ($section === 'blog') {
        return [
            'title' => $isNew ? 'Nouvel article Pilates Vitalité' : 'Article mis à jour',
            'message' => app_truncate((string) $item['title'], 180),
        ];
    }

    if ($section === 'about') {
        return [
            'title' => 'Mise à jour - À propos',
            'message' => 'La page À propos de Pilates Vitalité a été mise à jour.',
        ];
    }

    if ($section === 'course') {
        return [
            'title' => 'Nouveau cours Pilates Vitalité',
            'message' => app_truncate((string) $item['title'], 180),
        ];
    }

    if ($section === 'package') {
        return [
            'title' => 'Nouveau forfait Pilates Vitalité',
            'message' => app_truncate((string) $item['title'], 180),
        ];
    }

    if ($section === 'newsletter') {
        return [
            'title' => 'Nouvelle infolettre Pilates Vitalité',
            'message' => app_truncate((string) $item['title'], 180),
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
    $blogBaselineMode = (bool) ($config['BLOG_BASELINE_MODE'] ?? false);
    if ($blogBaselineMode) {
        cron_log_line('Blog baseline mode enabled: blog snapshots will be saved without notifications.');
    }

    $database = new Database($config);
    $repository = new SnapshotRepository($database);
    $http = new HttpClient((int) app_config('HTTP_TIMEOUT', 15));
    $scraper = new Scraper($config, $http);
    $push = new PushService($config, $repository);

    try {
        $nextCourse = $scraper->nextCourse();
        (new NextCourseCache())->write($nextCourse);
        cron_log_line($nextCourse === null
            ? 'Next course cache refreshed: no upcoming studio course.'
            : sprintf(
                'Next course cache refreshed: date=%s id=%s',
                (string) $nextCourse['date'],
                (string) $nextCourse['id']
            ));
    } catch (Throwable $nextCourseError) {
        cron_log_line('Next course cache warning: ' . $nextCourseError->getMessage());
    }

    $latest = $scraper->latest();
    try {
        (new LatestContentCache())->write($latest);
        cron_log_line(sprintf(
            'Latest-content cache refreshed: articles=%d.',
            count($latest['blog']['articles'] ?? [])
        ));
    } catch (Throwable $cacheError) {
        cron_log_line('Latest-content cache warning: ' . $cacheError->getMessage());
    }

    $items = $scraper->watchedItems($latest);
    try {
        $newsletterItems = (new SourceNewsletterRepository($config))->sentCampaignItems();
        $items = array_merge($items, $newsletterItems);
    } catch (Throwable $newsletterError) {
        cron_log_line('Newsletter source warning: ' . $newsletterError->getMessage());
    }

    $initialCounts = [];
    foreach ($items as $item) {
        $section = (string) ($item['section'] ?? '');
        if ($section !== '' && !array_key_exists($section, $initialCounts)) {
            $initialCounts[$section] = $repository->countSection($section);
        }
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
        $onlyNotifyWhenNew = in_array($section, ['course', 'package', 'newsletter'], true);
        $isBlogBaseline = $blogBaselineMode && $section === 'blog';
        $shouldNotify = !$isBlogBaseline && !$isInitialBootstrap && (!$onlyNotifyWhenNew || $isNew);

        if (!$shouldNotify) {
            $reason = $isBlogBaseline ? 'blog baseline' : ($isInitialBootstrap ? 'initial' : 'update ignored');
            cron_log_line(sprintf('Snapshot saved without notification (%s): %s/%s', $reason, $section, $itemKey));
            continue;
        }

        if ($repository->notificationExists($section, $itemKey, (string) $item['content_hash'])) {
            cron_log_line(sprintf('Notification already logged: %s/%s', $section, $itemKey));
            continue;
        }

        $copy = notification_message($item, $isNew);
        $category = notification_category($section);
        $result = $push->send($copy['title'], $copy['message'], notification_target_url($item), $section, $category);
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
