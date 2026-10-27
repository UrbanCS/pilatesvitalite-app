<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Suspends and restores only the Pilates Vitalité PWA content-check cron.
 * Run from the Laravel project root.
 */

$mode = $argv[1] ?? '--status';
if (!in_array($mode, ['--status', '--suspend', '--resume'], true)) {
    fwrite(STDERR, "Usage: php ops/manage-pwa-cron.php [--status|--suspend|--resume]\n");
    exit(2);
}

$root = dirname(__DIR__);
$cronTarget = $root . '/pwa/backend/cron/check-updates.php';
$stateDir = $root . '/storage/app/codex-blog-import';
$stateFile = $stateDir . '/pwa-cron.line';

function crontab_read(): array
{
    $output = [];
    $code = 0;
    exec('/usr/bin/crontab -l 2>/dev/null', $output, $code);
    if ($code !== 0) {
        throw new RuntimeException('Unable to read the current crontab.');
    }

    return $output;
}

function crontab_write(array $lines): void
{
    $temporary = tempnam(sys_get_temp_dir(), 'pilates-cron-');
    if ($temporary === false) {
        throw new RuntimeException('Unable to create a temporary crontab file.');
    }

    $payload = $lines === [] ? '' : implode(PHP_EOL, $lines) . PHP_EOL;
    if (file_put_contents($temporary, $payload, LOCK_EX) === false) {
        @unlink($temporary);
        throw new RuntimeException('Unable to write the temporary crontab.');
    }

    $output = [];
    $code = 0;
    exec('/usr/bin/crontab ' . escapeshellarg($temporary) . ' 2>&1', $output, $code);
    @unlink($temporary);
    if ($code !== 0) {
        throw new RuntimeException('Unable to install the updated crontab.');
    }
}

function is_active_target_cron(string $line, string $target): bool
{
    $trimmed = ltrim($line);

    return $trimmed !== '' && !str_starts_with($trimmed, '#') && str_contains($trimmed, $target);
}

function saved_cron_line(string $stateFile, string $target): ?string
{
    if (!is_file($stateFile)) {
        return null;
    }

    $saved = trim((string) file_get_contents($stateFile));

    return $saved !== '' && !str_contains($saved, "\n") && is_active_target_cron($saved, $target)
        ? $saved
        : null;
}

try {
    $lines = crontab_read();
    $matching = array_values(array_filter(
        $lines,
        static fn (string $line): bool => is_active_target_cron($line, $cronTarget)
    ));

    if ($mode === '--status') {
        echo json_encode([
            'ok' => true,
            'scheduled' => count($matching) === 1,
            'matching_lines' => count($matching),
            'saved_for_resume' => is_file($stateFile),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(count($matching) <= 1 ? 0 : 3);
    }

    if ($mode === '--suspend') {
        if ($matching === []) {
            if (saved_cron_line($stateFile, $cronTarget) === null) {
                throw new RuntimeException('PWA cron is absent and no valid saved definition exists.');
            }
            echo json_encode(['ok' => true, 'scheduled' => false, 'changed' => false], JSON_PRETTY_PRINT) . PHP_EOL;
            exit(0);
        }
        if (count($matching) !== 1) {
            throw new RuntimeException('Expected exactly one PWA content-check cron line before suspension.');
        }
        if (!is_dir($stateDir) && !mkdir($stateDir, 0750, true) && !is_dir($stateDir)) {
            throw new RuntimeException('Unable to create the cron state directory.');
        }
        if (file_put_contents($stateFile, $matching[0] . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Unable to save the PWA cron definition.');
        }
        chmod($stateFile, 0600);

        $remaining = array_values(array_filter(
            $lines,
            static fn (string $line): bool => !is_active_target_cron($line, $cronTarget)
        ));
        crontab_write($remaining);

        $after = array_values(array_filter(
            crontab_read(),
            static fn (string $line): bool => is_active_target_cron($line, $cronTarget)
        ));
        if ($after !== []) {
            throw new RuntimeException('PWA cron suspension verification failed.');
        }

        echo json_encode(['ok' => true, 'scheduled' => false], JSON_PRETTY_PRINT) . PHP_EOL;
        exit(0);
    }

    if (count($matching) === 1) {
        $saved = saved_cron_line($stateFile, $cronTarget);
        if ($saved !== null && !hash_equals($saved, $matching[0])) {
            throw new RuntimeException('Scheduled PWA cron differs from the saved definition.');
        }
        if ($saved !== null && !unlink($stateFile)) {
            throw new RuntimeException('Unable to clear the restored PWA cron state.');
        }
        echo json_encode(['ok' => true, 'scheduled' => true, 'changed' => false], JSON_PRETTY_PRINT) . PHP_EOL;
        exit(0);
    }
    if ($matching !== [] || !is_file($stateFile)) {
        throw new RuntimeException('Cannot restore the PWA cron from an unambiguous saved state.');
    }

    $saved = saved_cron_line($stateFile, $cronTarget);
    if ($saved === null) {
        throw new RuntimeException('Saved PWA cron definition is invalid.');
    }
    $lines[] = $saved;
    crontab_write($lines);

    $after = array_values(array_filter(
        crontab_read(),
        static fn (string $line): bool => is_active_target_cron($line, $cronTarget)
    ));
    if (count($after) !== 1 || !hash_equals($saved, $after[0])) {
        throw new RuntimeException('PWA cron restoration verification failed.');
    }
    if (!unlink($stateFile)) {
        throw new RuntimeException('Unable to clear the restored PWA cron state.');
    }

    echo json_encode(['ok' => true, 'scheduled' => true, 'changed' => true], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, "Cron operation failed: " . $error->getMessage() . "\n");
    exit(1);
}
