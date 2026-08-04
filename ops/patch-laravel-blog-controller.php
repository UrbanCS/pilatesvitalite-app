<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * One-off, guarded patch for the Laravel website blog ordering/pagination.
 *
 * Usage from the Laravel project root:
 *   php ops/patch-laravel-blog-controller.php --dry-run
 *   php ops/patch-laravel-blog-controller.php --apply
 */

$mode = $argv[1] ?? '--dry-run';
if (!in_array($mode, ['--dry-run', '--apply'], true)) {
    fwrite(STDERR, "Usage: php ops/patch-laravel-blog-controller.php [--dry-run|--apply]\n");
    exit(2);
}

$root = dirname(__DIR__);
$target = $root . '/app/Http/Controllers/website/WebsiteController.php';
if (!is_file($target) || !is_readable($target)) {
    fwrite(STDERR, "WebsiteController.php was not found beside the deployed ops directory.\n");
    exit(2);
}

$lockHandle = fopen($target, 'r');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "WebsiteController.php is already being edited.\n");
    exit(2);
}
register_shutdown_function(static function () use ($lockHandle): void {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
});

$originalPermissions = fileperms($target);
if ($originalPermissions === false) {
    fwrite(STDERR, "Unable to read WebsiteController.php permissions.\n");
    exit(2);
}
$originalPermissions &= 0777;

$original = file_get_contents($target);
if ($original === false) {
    fwrite(STDERR, "Unable to read WebsiteController.php.\n");
    exit(2);
}

$updated = $original;
$changes = [];

$homeOldPattern = <<<'REGEX'
~(\$blogs\s*=\s*Blog::with\(\[.*?\]\))\s*->orderBy\('id', 'desc'\)->where\(\['status' => 1\]\)->where\('show_homepage', 1\)->get\(\)->take\(3\);~s
REGEX;
$homeNewPattern = <<<'REGEX'
~(\$blogs\s*=\s*Blog::with\(\[.*?\]\))\s*->orderBy\('created_at', 'desc'\)->where\(\['status' => 1\]\)->where\('show_homepage', 1\)->get\(\)->take\(3\);~s
REGEX;
$homeOldCount = preg_match_all($homeOldPattern, $updated);
$homeNewCount = preg_match_all($homeNewPattern, $updated);
if ($homeOldCount === 4 && $homeNewCount === 0) {
    $updated = preg_replace_callback(
        $homeOldPattern,
        static fn (array $matches): string => $matches[1]
            . "->orderBy('created_at', 'desc')->where(['status' => 1])->where('show_homepage', 1)->get()->take(3);",
        $updated,
        -1,
        $homeReplacements
    ) ?? $updated;
    if ($homeReplacements !== 4) {
        fwrite(STDERR, "Guard failed while patching homepage Blog queries.\n");
        exit(3);
    }
    $changes['homepage_chronological_queries'] = 4;
} elseif ($homeOldCount === 0 && $homeNewCount === 4) {
    $changes['homepage_chronological_queries'] = 0;
} else {
    fwrite(STDERR, "Guard failed: homepage Blog queries are missing, mixed, or ambiguous.\n");
    exit(3);
}

$listNeedle = "Blog::with('translation', 'category.translation')->orderBy('id', 'desc')->where(['status' => 1])->take(3);";
$listReplacement = "Blog::with('translation', 'category.translation')->orderBy('created_at', 'desc')->where(['status' => 1]);";
$listOldCount = substr_count($updated, $listNeedle);
$listNewCount = substr_count($updated, $listReplacement);
if ($listOldCount === 1 && $listNewCount === 0) {
    $updated = str_replace($listNeedle, $listReplacement, $updated);
    $changes['blog_list_query'] = 1;
} elseif ($listOldCount === 0 && $listNewCount === 1) {
    $changes['blog_list_query'] = 0;
} else {
    fwrite(STDERR, "Guard failed: unexpected Blog list query.\n");
    exit(3);
}

$navigationReplacements = [
    "Blog::where('id', '<', \$blog->id)->orderBy('id', 'desc')->first();" =>
        "Blog::where('created_at', '<', \$blog->created_at)->orderBy('created_at', 'desc')->first();",
    "Blog::where('id', '>', \$blog->id)->orderBy('id', 'desc')->first();" =>
        "Blog::where('created_at', '>', \$blog->created_at)->orderBy('created_at', 'asc')->first();",
];

$navigationChanges = 0;
foreach ($navigationReplacements as $needle => $replacement) {
    $oldCount = substr_count($updated, $needle);
    $newCount = substr_count($updated, $replacement);
    if ($oldCount === 1 && $newCount === 0) {
        $updated = str_replace($needle, $replacement, $updated);
        $navigationChanges++;
        continue;
    }

    if ($oldCount === 0 && $newCount === 1) {
        continue;
    }

    fwrite(STDERR, "Guard failed: unexpected previous/next article query.\n");
    exit(3);
}
$changes['navigation_queries'] = $navigationChanges;

$navigationNewCount = array_sum(array_map(
    static fn (string $replacement): int => substr_count($updated, $replacement),
    array_values($navigationReplacements)
));
if (preg_match_all($homeOldPattern, $updated) !== 0
    || preg_match_all($homeNewPattern, $updated) !== 4
    || substr_count($updated, $listNeedle) !== 0
    || substr_count($updated, $listReplacement) !== 1
    || $navigationNewCount !== 2
) {
    fwrite(STDERR, "Guard failed: patched Blog queries did not pass exact post-validation.\n");
    exit(3);
}

$changed = !hash_equals(hash('sha256', $original), hash('sha256', $updated));
$result = [
    'ok' => true,
    'mode' => ltrim($mode, '-'),
    'changed' => $changed,
    'target' => $target,
    'changes' => $changes,
    'sha256_before' => hash('sha256', $original),
    'sha256_after' => hash('sha256', $updated),
];

if ($mode === '--dry-run' || !$changed) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$backupDir = $root . '/storage/app/codex-blog-import';
if (!is_dir($backupDir) && !mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Unable to create the controller backup directory.\n");
    exit(4);
}

$backup = $backupDir . '/WebsiteController.php.before-' . gmdate('Ymd-His') . '.bak';
if (!copy($target, $backup)) {
    fwrite(STDERR, "Unable to create the controller backup.\n");
    exit(4);
}

$temporary = $target . '.codex-tmp-' . getmypid();
if (file_put_contents($temporary, $updated, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write the temporary controller.\n");
    exit(4);
}
if (!chmod($temporary, $originalPermissions)) {
    @unlink($temporary);
    fwrite(STDERR, "Unable to preserve WebsiteController.php permissions.\n");
    exit(4);
}

$lintOutput = [];
$lintCode = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($temporary) . ' 2>&1', $lintOutput, $lintCode);
if ($lintCode !== 0) {
    @unlink($temporary);
    fwrite(STDERR, "PHP lint failed: " . implode("\n", $lintOutput) . "\n");
    exit(4);
}

if (!hash_equals(hash('sha256', $original), (string) hash_file('sha256', $target))) {
    @unlink($temporary);
    fwrite(STDERR, "WebsiteController.php changed during this operation.\n");
    exit(4);
}

if (!rename($temporary, $target)) {
    @unlink($temporary);
    fwrite(STDERR, "Unable to atomically replace WebsiteController.php.\n");
    exit(4);
}

$result['backup'] = $backup;
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
