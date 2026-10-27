<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Read-only migration counters for the PWA database. */

try {
    $root = dirname(__DIR__);
    $configPath = $root . '/pwa/backend/config/config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException('Private PWA config.php was not found.');
    }
    $config = require $configPath;
    if (!is_array($config)
        || trim((string) ($config['DB_NAME'] ?? '')) === ''
        || trim((string) ($config['DB_USER'] ?? '')) === ''
    ) {
        throw new RuntimeException('Private PWA config.php is invalid.');
    }

    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            (string) ($config['DB_HOST'] ?? 'localhost'),
            (int) ($config['DB_PORT'] ?? 3306),
            (string) ($config['DB_NAME'] ?? '')
        ),
        (string) ($config['DB_USER'] ?? ''),
        (string) ($config['DB_PASS'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $sections = $pdo->query(
        'SELECT section, COUNT(*) AS snapshots, COUNT(DISTINCT item_key) AS distinct_items, MAX(id) AS latest_id, MAX(last_seen_at) AS last_seen_at
         FROM content_snapshots
         GROUP BY section
         ORDER BY section'
    )->fetchAll();

    $blogKeys = $pdo->query(
        'SELECT item_key, COUNT(*) AS snapshots, MAX(id) AS latest_id, MAX(last_seen_at) AS last_seen_at
         FROM content_snapshots
         WHERE section = \'blog\'
         GROUP BY item_key
         ORDER BY item_key'
    )->fetchAll();

    $notificationStats = $pdo->query(
        'SELECT COUNT(*) AS total, COALESCE(MAX(id), 0) AS latest_id, COALESCE(MAX(created_at), \'\') AS latest_created_at
         FROM notification_logs'
    )->fetch();

    $activeSubscriptions = (int) $pdo->query(
        'SELECT COUNT(*) FROM push_subscriptions WHERE is_active = 1'
    )->fetchColumn();

    echo json_encode([
        'ok' => true,
        'generated_at' => date(DATE_ATOM),
        'sections' => $sections,
        'blog_distinct_items' => count($blogKeys),
        'blog_keys' => $blogKeys,
        'notification_logs' => $notificationStats,
        'active_subscriptions' => $activeSubscriptions,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, "PWA stats failed: " . $error->getMessage() . "\n");
    exit(1);
}
