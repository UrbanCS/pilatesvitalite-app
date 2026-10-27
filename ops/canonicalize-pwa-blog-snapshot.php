<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Renames the one historical misspelled PWA blog key before the import baseline.
 * Run from the Laravel root with --dry-run, then --apply.
 */

$mode = $argv[1] ?? '--dry-run';
if (!in_array($mode, ['--dry-run', '--apply'], true)) {
    fwrite(STDERR, "Usage: php ops/canonicalize-pwa-blog-snapshot.php [--dry-run|--apply]\n");
    exit(2);
}

$root = dirname(__DIR__);
$configPath = $root . '/pwa/backend/config/config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Private PWA config.php was not found.\n");
    exit(2);
}
$config = require $configPath;
if (!is_array($config)
    || trim((string) ($config['DB_NAME'] ?? '')) === ''
    || trim((string) ($config['DB_USER'] ?? '')) === ''
) {
    fwrite(STDERR, "Private PWA config.php is invalid.\n");
    exit(2);
}

$oldKey = 'la-sant-osseuse-pas-quune-affaire-de-femmes';
$canonicalKey = 'la-sante-osseuse-pas-quune-affaire-de-femmes';

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        (string) ($config['DB_HOST'] ?? 'localhost'),
        (int) ($config['DB_PORT'] ?? 3306),
        (string) ($config['DB_NAME'] ?? '')
    );
    $pdo = new PDO(
        $dsn,
        (string) ($config['DB_USER'] ?? ''),
        (string) ($config['DB_PASS'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $count = static function (PDO $pdo, string $key): int {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM content_snapshots WHERE section = :section AND item_key = :item_key'
        );
        $statement->execute(['section' => 'blog', 'item_key' => $key]);

        return (int) $statement->fetchColumn();
    };

    if ($mode === '--apply') {
        $pdo->beginTransaction();
        $lock = $pdo->prepare(
            'SELECT id FROM content_snapshots WHERE section = :section AND item_key IN (:old_key, :canonical) FOR UPDATE'
        );
        $lock->execute(['section' => 'blog', 'old_key' => $oldKey, 'canonical' => $canonicalKey]);
        $lock->fetchAll();
    }

    $oldCount = $count($pdo, $oldKey);
    $canonicalCount = $count($pdo, $canonicalKey);
    $changed = false;

    if ($oldCount === 0 && $canonicalCount === 0) {
        throw new RuntimeException('Neither the historical nor canonical Blog snapshot key exists.');
    }
    if ($oldCount > 0 && $canonicalCount > 0) {
        throw new RuntimeException('Both historical and canonical Blog snapshot keys exist; refusing an ambiguous merge.');
    }

    if ($mode === '--apply' && $oldCount > 0) {
        $statement = $pdo->prepare(
            'UPDATE content_snapshots SET item_key = :canonical WHERE section = :section AND item_key = :old_key'
        );
        $statement->execute([
            'canonical' => $canonicalKey,
            'section' => 'blog',
            'old_key' => $oldKey,
        ]);
        if ($statement->rowCount() !== $oldCount) {
            $pdo->rollBack();
            throw new RuntimeException('Snapshot key update count did not match the audited count.');
        }
        $changed = true;

        $oldCount = $count($pdo, $oldKey);
        $canonicalCount = $count($pdo, $canonicalKey);
        if ($oldCount !== 0 || $canonicalCount === 0) {
            throw new RuntimeException('Snapshot key verification failed after migration.');
        }
    }

    if ($mode === '--apply') {
        $pdo->commit();
    }

    echo json_encode([
        'ok' => true,
        'mode' => ltrim($mode, '-'),
        'changed' => $changed,
        'misspelled_rows' => $oldCount,
        'canonical_rows' => $canonicalCount,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "PWA snapshot canonicalization failed: " . $error->getMessage() . "\n");
    exit(1);
}
