<?php

declare(strict_types=1);

final class SnapshotRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function countSection(string $section): int
    {
        $row = $this->database->fetchOne(
            'SELECT COUNT(*) AS total FROM content_snapshots WHERE section = :section',
            ['section' => $section]
        );

        return (int) ($row['total'] ?? 0);
    }

    public function latestForItem(string $section, string $itemKey): ?array
    {
        return $this->database->fetchOne(
            'SELECT * FROM content_snapshots WHERE section = :section AND item_key = :item_key ORDER BY id DESC LIMIT 1',
            ['section' => $section, 'item_key' => $itemKey]
        );
    }

    public function insertSnapshot(array $item): int
    {
        $this->database->execute(
            'INSERT INTO content_snapshots
                (section, item_key, source_url, content_hash, title, excerpt, image_url, published_at, payload_json, last_seen_at, created_at)
             VALUES
                (:section, :item_key, :source_url, :content_hash, :title, :excerpt, :image_url, :published_at, :payload_json, NOW(), NOW())',
            [
                'section' => $item['section'],
                'item_key' => $item['item_key'],
                'source_url' => $item['source_url'],
                'content_hash' => $item['content_hash'],
                'title' => $item['title'],
                'excerpt' => $item['excerpt'] ?? null,
                'image_url' => $item['image_url'] ?? null,
                'published_at' => $item['published_at'] ?? null,
                'payload_json' => json_encode($item['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );

        return (int) $this->database->pdo()->lastInsertId();
    }

    public function touchSnapshot(int $id): void
    {
        $this->database->execute(
            'UPDATE content_snapshots SET last_seen_at = NOW() WHERE id = :id',
            ['id' => $id]
        );
    }

    public function notificationExists(string $section, string $itemKey, string $hash): bool
    {
        $row = $this->database->fetchOne(
            'SELECT id FROM notification_logs WHERE section = :section AND item_key = :item_key AND content_hash = :content_hash LIMIT 1',
            ['section' => $section, 'item_key' => $itemKey, 'content_hash' => $hash]
        );

        return $row !== null;
    }

    public function logNotification(string $section, string $itemKey, string $hash, string $title, string $message, string $sourceUrl, int $sentCount): void
    {
        $this->database->execute(
            'INSERT INTO notification_logs
                (section, item_key, content_hash, source_url, title, message, sent_count, created_at)
             VALUES
                (:section, :item_key, :content_hash, :source_url, :title, :message, :sent_count, NOW())',
            [
                'section' => $section,
                'item_key' => $itemKey,
                'content_hash' => $hash,
                'source_url' => $sourceUrl,
                'title' => $title,
                'message' => $message,
                'sent_count' => $sentCount,
            ]
        );
    }

    public function activeSubscriptions(): array
    {
        return $this->database->fetchAll(
            'SELECT * FROM push_subscriptions WHERE is_active = 1 ORDER BY id ASC'
        );
    }

    public function deactivateEndpoint(string $endpoint): void
    {
        $this->database->execute(
            'UPDATE push_subscriptions SET is_active = 0, updated_at = NOW() WHERE endpoint_hash = :endpoint_hash',
            ['endpoint_hash' => hash('sha256', $endpoint)]
        );
    }

    public function latestDataFromSnapshots(array $config): ?array
    {
        $rows = $this->database->fetchAll(
            'SELECT cs.*
             FROM content_snapshots cs
             INNER JOIN (
                SELECT section, item_key, MAX(id) AS id
                FROM content_snapshots
                GROUP BY section, item_key
             ) latest ON latest.id = cs.id
             ORDER BY cs.section ASC, cs.id DESC'
        );

        if ($rows === []) {
            return null;
        }

        $home = null;
        $about = null;
        $articles = [];

        foreach ($rows as $row) {
            $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                continue;
            }

            if ($row['section'] === 'home' && $home === null) {
                $home = $payload;
            }

            if ($row['section'] === 'about' && $about === null) {
                $about = $payload;
            }

            if ($row['section'] === 'blog') {
                $articles[] = $payload;
            }
        }

        return [
            'fetchedAt' => date(DATE_ATOM),
            'site' => [
                'name' => 'Pilates Vitalité',
                'tagline' => 'Bien-être physique et mental par le Pilates',
                'fullSiteUrl' => rtrim((string) ($config['SOURCE_BASE_URL'] ?? 'https://pilatesvitalite.webactiondemo.ca'), '/'),
                'finalDomain' => (string) ($config['FINAL_DOMAIN'] ?? 'https://pilatesvitalite.ca'),
            ],
            'home' => $home,
            'about' => $about,
            'blog' => [
                'title' => 'Blogue',
                'sourceUrl' => $config['WATCH_URLS']['blog'] ?? '',
                'articles' => $articles,
            ],
            'contact' => [
                'title' => 'Contact',
                'sourceUrl' => $config['WATCH_URLS']['contact'] ?? '',
                'phone' => '',
                'email' => 'info@pilatesvitalite.ca',
                'address' => '',
            ],
        ];
    }
}
