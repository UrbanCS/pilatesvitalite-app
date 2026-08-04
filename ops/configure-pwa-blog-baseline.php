<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Guarded editor for the deployed private PWA config.
 *
 * Usage from the Laravel root:
 *   php ops/configure-pwa-blog-baseline.php --dry-run-on
 *   php ops/configure-pwa-blog-baseline.php --on
 *   php ops/configure-pwa-blog-baseline.php --dry-run-off
 *   php ops/configure-pwa-blog-baseline.php --off
 */

$mode = $argv[1] ?? '--dry-run-on';
$allowed = ['--dry-run-on', '--on', '--dry-run-off', '--off'];
if (!in_array($mode, $allowed, true)) {
    fwrite(STDERR, "Unknown mode.\n");
    exit(2);
}

$root = dirname(__DIR__);
$target = $root . '/pwa/backend/config/config.php';
if (!is_file($target) || !is_readable($target)) {
    fwrite(STDERR, "Private PWA config.php was not found.\n");
    exit(2);
}

$lockHandle = fopen($target, 'r');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Private PWA config.php is already being edited.\n");
    exit(2);
}
register_shutdown_function(static function () use ($lockHandle): void {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
});

$originalPermissions = fileperms($target);
if ($originalPermissions === false) {
    fwrite(STDERR, "Unable to read private PWA config permissions.\n");
    exit(2);
}
$originalPermissions &= 0777;

$original = file_get_contents($target);
if ($original === false) {
    fwrite(STDERR, "Unable to read private PWA config.php.\n");
    exit(2);
}

$updated = $original;

$itemsPattern = "/^[ \\t]*'MAX_BLOG_ITEMS'[ \\t]*=>[ \\t]*\\d+[ \\t]*,/m";
$pagesPattern = "/^[ \\t]*'MAX_BLOG_PAGES'[ \\t]*=>[ \\t]*\\d+[ \\t]*,/m";
$baselinePattern = "/^[ \\t]*'BLOG_BASELINE_MODE'[ \\t]*=>[ \\t]*(?:true|false)[ \\t]*,/m";
$notifyPattern = "/^[ \\t]*'NOTIFY_ON_FIRST_RUN'[ \\t]*=>/m";

if (preg_match_all($itemsPattern, $updated) !== 1) {
    fwrite(STDERR, "MAX_BLOG_ITEMS is missing or ambiguous.\n");
    exit(3);
}
$updated = preg_replace(
    $itemsPattern,
    "    'MAX_BLOG_ITEMS' => 50,",
    $updated,
    1
) ?? $updated;

$pagesCount = preg_match_all($pagesPattern, $updated);
if ($pagesCount === 1) {
    $updated = preg_replace(
        $pagesPattern,
        "    'MAX_BLOG_PAGES' => 10,",
        $updated,
        1
    ) ?? $updated;
} elseif ($pagesCount === 0 && preg_match_all($itemsPattern, $updated) === 1) {
    $updated = str_replace(
        "    'MAX_BLOG_ITEMS' => 50,",
        "    'MAX_BLOG_ITEMS' => 50,\n    'MAX_BLOG_PAGES' => 10,",
        $updated
    );
} else {
    fwrite(STDERR, "Unable to insert MAX_BLOG_PAGES safely.\n");
    exit(3);
}

$desired = str_contains($mode, 'on') ? 'true' : 'false';
$baselineCount = preg_match_all($baselinePattern, $updated);
$notifyCount = preg_match_all($notifyPattern, $updated);
if ($baselineCount === 1) {
    $updated = preg_replace(
        $baselinePattern,
        "    'BLOG_BASELINE_MODE' => {$desired},",
        $updated,
        1
    ) ?? $updated;
} elseif ($baselineCount === 0 && $notifyCount === 1) {
    $updated = preg_replace(
        $notifyPattern,
        "    'BLOG_BASELINE_MODE' => {$desired},\n    'NOTIFY_ON_FIRST_RUN' =>",
        $updated
    ) ?? $updated;
} else {
    fwrite(STDERR, "Unable to insert BLOG_BASELINE_MODE safely.\n");
    exit(3);
}

if (preg_match_all("/^[ \\t]*'MAX_BLOG_ITEMS'[ \\t]*=>[ \\t]*50[ \\t]*,/m", $updated) !== 1
    || preg_match_all("/^[ \\t]*'MAX_BLOG_PAGES'[ \\t]*=>[ \\t]*10[ \\t]*,/m", $updated) !== 1
    || preg_match_all("/^[ \\t]*'BLOG_BASELINE_MODE'[ \\t]*=>[ \\t]*{$desired}[ \\t]*,/m", $updated) !== 1
) {
    fwrite(STDERR, "Post-edit validation failed.\n");
    exit(3);
}

$dryRun = str_contains($mode, 'dry-run');
$changed = !hash_equals(hash('sha256', $original), hash('sha256', $updated));
$result = [
    'ok' => true,
    'mode' => ltrim($mode, '-'),
    'changed' => $changed,
    'max_blog_items' => 50,
    'max_blog_pages' => 10,
    'blog_baseline_mode' => $desired === 'true',
    'sha256_before' => hash('sha256', $original),
    'sha256_after' => hash('sha256', $updated),
];

if (!$dryRun && $changed) {
    if (!hash_equals(hash('sha256', $original), hash_file('sha256', $target))) {
        fwrite(STDERR, "Private PWA config.php changed during this operation.\n");
        exit(4);
    }
    $temporary = $target . '.codex-tmp-' . getmypid();
    if (file_put_contents($temporary, $updated, LOCK_EX) === false) {
        fwrite(STDERR, "Unable to write temporary PWA config.\n");
        exit(4);
    }
    if (!chmod($temporary, $originalPermissions)) {
        @unlink($temporary);
        fwrite(STDERR, "Unable to preserve private PWA config permissions.\n");
        exit(4);
    }

    $lintOutput = [];
    $lintCode = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($temporary) . ' 2>&1', $lintOutput, $lintCode);
    if ($lintCode !== 0) {
        @unlink($temporary);
        fwrite(STDERR, "PHP lint failed for private PWA config.\n");
        exit(4);
    }

    if (!rename($temporary, $target)) {
        @unlink($temporary);
        fwrite(STDERR, "Unable to atomically replace private PWA config.\n");
        exit(4);
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
