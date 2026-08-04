<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Creates a pre-import backup outside public_html without printing credentials.
 * Run from the Laravel project root: php ops/backup-blog-migration.php
 */

$root = dirname(__DIR__);
if (!is_file($root . '/artisan') || !is_file($root . '/bootstrap/app.php')) {
    fwrite(STDERR, "Run this script from the deployed Laravel project root.\n");
    exit(2);
}

require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$backupParent = dirname(dirname(dirname($root))) . '/codex-backups';
$backupDir = $backupParent . '/blog-import-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
$filesDir = $backupDir . '/files';

if (!is_dir($filesDir) && !mkdir($filesDir, 0700, true) && !is_dir($filesDir)) {
    fwrite(STDERR, "Unable to create the backup directory.\n");
    exit(3);
}

function run_process(array $command, ?string $stdoutPath = null, ?string $stderrPath = null, ?array $environment = null): int
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => $stdoutPath === null ? ['pipe', 'w'] : ['file', $stdoutPath, 'wb'],
        2 => $stderrPath === null ? ['pipe', 'w'] : ['file', $stderrPath, 'wb'],
    ];

    $pipes = [];
    $process = proc_open($command, $descriptors, $pipes, null, $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start a required backup process.');
    }

    if (isset($pipes[0]) && is_resource($pipes[0])) {
        fclose($pipes[0]);
    }
    if ($stdoutPath === null && isset($pipes[1]) && is_resource($pipes[1])) {
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
    }
    if ($stderrPath === null && isset($pipes[2]) && is_resource($pipes[2])) {
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
    }

    return proc_close($process);
}

function gzip_file(string $source, string $destination): void
{
    $sourceBytes = filesize($source);
    if ($sourceBytes === false || $sourceBytes === 0) {
        throw new RuntimeException('Database dump is empty before compression.');
    }

    $input = fopen($source, 'rb');
    $output = gzopen($destination, 'wb9');
    if ($input === false || $output === false) {
        throw new RuntimeException('Unable to create compressed database backup.');
    }

    while (!feof($input)) {
        $chunk = fread($input, 1024 * 1024);
        if ($chunk === false) {
            fclose($input);
            gzclose($output);
            throw new RuntimeException('Unable to compress database backup.');
        }

        $offset = 0;
        $length = strlen($chunk);
        while ($offset < $length) {
            $written = gzwrite($output, substr($chunk, $offset));
            if ($written === false || $written === 0) {
                fclose($input);
                gzclose($output);
                throw new RuntimeException('Unable to fully write compressed database backup.');
            }
            $offset += $written;
        }
    }

    fclose($input);
    gzclose($output);

    $gzipError = $destination . '.test.stderr.log';
    $gzipCode = run_process(['/usr/bin/gzip', '-t', $destination], null, $gzipError);
    if ($gzipCode !== 0) {
        throw new RuntimeException('Compressed database backup validation failed.');
    }
    @unlink($gzipError);

    $check = gzopen($destination, 'rb');
    if ($check === false) {
        throw new RuntimeException('Unable to reopen compressed database backup.');
    }
    $uncompressedBytes = 0;
    while (!gzeof($check)) {
        $chunk = gzread($check, 1024 * 1024);
        if ($chunk === false) {
            gzclose($check);
            throw new RuntimeException('Unable to fully read compressed database backup.');
        }
        $uncompressedBytes += strlen($chunk);
    }
    gzclose($check);
    if ($uncompressedBytes !== $sourceBytes) {
        throw new RuntimeException('Compressed database backup size validation failed.');
    }

    if (!unlink($source)) {
        throw new RuntimeException('Unable to remove validated temporary SQL dump.');
    }
}

function dump_database(array $connection, string $destination, string $errorLog): void
{
    $dumpBinary = '/usr/bin/mysqldump';
    if (!is_executable($dumpBinary)) {
        throw new RuntimeException('mysqldump is unavailable.');
    }

    $database = (string) ($connection['database'] ?? '');
    $username = (string) ($connection['username'] ?? '');
    $password = (string) ($connection['password'] ?? '');
    if ($database === '' || $username === '') {
        throw new RuntimeException('Database backup configuration is incomplete.');
    }

    $temporarySql = $destination . '.sql.tmp';
    $command = [
        $dumpBinary,
        '--single-transaction',
        '--quick',
        '--skip-lock-tables',
        '--default-character-set=utf8mb4',
        '--host=' . (string) ($connection['host'] ?? 'localhost'),
        '--port=' . (string) ($connection['port'] ?? 3306),
        '--user=' . $username,
        $database,
    ];

    $environment = [
        'MYSQL_PWD' => $password,
        'PATH' => '/usr/local/bin:/usr/bin:/bin',
        'LANG' => 'C.UTF-8',
    ];
    $exitCode = run_process($command, $temporarySql, $errorLog, $environment);
    if ($exitCode !== 0 || !is_file($temporarySql) || filesize($temporarySql) === 0) {
        @unlink($temporarySql);
        throw new RuntimeException('Database dump failed; inspect the private backup error log.');
    }

    gzip_file($temporarySql, $destination);
    @unlink($errorLog);
}

function copy_backup_file(string $root, string $relative, string $filesDir, bool $required = true): void
{
    $source = $root . '/' . $relative;
    if (!is_file($source)) {
        if ($required) {
            throw new RuntimeException('Required backup source is missing: ' . $relative . '.');
        }
        return;
    }

    $destination = $filesDir . '/' . $relative;
    $directory = dirname($destination);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create a backup subdirectory.');
    }
    if (!copy($source, $destination)) {
        throw new RuntimeException('Unable to back up ' . $relative . '.');
    }
    if (!chmod($destination, 0600)) {
        throw new RuntimeException('Unable to protect backed-up file ' . $relative . '.');
    }
}

try {
    $laravelConnection = (array) config('database.connections.mysql');
    dump_database(
        $laravelConnection,
        $backupDir . '/laravel-database.sql.gz',
        $backupDir . '/laravel-database.stderr.log'
    );

    $pwaConfigPath = $root . '/pwa/backend/config/config.php';
    if (!is_file($pwaConfigPath)) {
        throw new RuntimeException('Private PWA configuration was not found.');
    }
    $pwaConfig = require $pwaConfigPath;
    if (!is_array($pwaConfig)) {
        throw new RuntimeException('Private PWA configuration is invalid.');
    }
    dump_database(
        [
            'host' => $pwaConfig['DB_HOST'] ?? 'localhost',
            'port' => $pwaConfig['DB_PORT'] ?? 3306,
            'database' => $pwaConfig['DB_NAME'] ?? '',
            'username' => $pwaConfig['DB_USER'] ?? '',
            'password' => $pwaConfig['DB_PASS'] ?? '',
        ],
        $backupDir . '/pwa-database.sql.gz',
        $backupDir . '/pwa-database.stderr.log'
    );

    foreach ([
        '.htaccess',
        'app/Http/Controllers/website/WebsiteController.php',
        'pwa/backend/config/config.php',
        'pwa/backend/config/config.example.php',
        'pwa/backend/cron/check-updates.php',
        'pwa/backend/lib/Scraper.php',
    ] as $relative) {
        copy_backup_file($root, $relative, $filesDir);
    }

    $currentImages = Illuminate\Support\Facades\DB::table('blogs')
        ->whereNotNull('image')
        ->pluck('image')
        ->filter(static fn (mixed $image): bool => is_string($image) && trim($image) !== '');
    foreach ($currentImages as $image) {
        $relative = ltrim(str_replace('\\', '/', (string) $image), '/');
        if (str_contains($relative, '..')) {
            throw new RuntimeException('Unsafe existing Blog image path encountered.');
        }
        copy_backup_file($root, 'public/' . $relative, $filesDir);
    }

    $cronFile = $backupDir . '/crontab.txt';
    $cronError = $backupDir . '/crontab.stderr.log';
    $cronCode = run_process(['/usr/bin/crontab', '-l'], $cronFile, $cronError);
    if ($cronCode !== 0) {
        throw new RuntimeException('Unable to capture the current crontab.');
    }
    @unlink($cronError);

    $wordpressUploads = $root . '/public/uploads/wordpress-blog';
    if (is_dir($wordpressUploads)) {
        $tarError = $backupDir . '/uploads.stderr.log';
        $tarCode = run_process(
            ['/usr/bin/tar', '-czf', $backupDir . '/wordpress-blog-uploads.tar.gz', '-C', $root, 'public/uploads/wordpress-blog'],
            null,
            $tarError
        );
        if ($tarCode !== 0) {
            throw new RuntimeException('Unable to archive existing imported blog uploads.');
        }
        @unlink($tarError);
    }

    $manifestFiles = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($backupDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile() || $fileInfo->getFilename() === 'manifest.json') {
            continue;
        }
        $path = $fileInfo->getPathname();
        $manifestFiles[] = [
            'path' => ltrim(str_replace('\\', '/', substr($path, strlen($backupDir))), '/'),
            'bytes' => $fileInfo->getSize(),
            'sha256' => hash_file('sha256', $path),
        ];
    }

    $manifest = [
        'created_at' => gmdate(DATE_ATOM),
        'backup_dir' => $backupDir,
        'files' => $manifestFiles,
    ];
    $manifestPath = $backupDir . '/manifest.json';
    $manifestJson = json_encode(
        $manifest,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
    if (file_put_contents($manifestPath, $manifestJson, LOCK_EX) !== strlen($manifestJson)) {
        throw new RuntimeException('Unable to write the complete backup manifest.');
    }
    if (!chmod($manifestPath, 0600)) {
        throw new RuntimeException('Unable to protect the backup manifest.');
    }
    $decodedManifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decodedManifest) || count($decodedManifest['files'] ?? []) !== count($manifestFiles)) {
        throw new RuntimeException('Backup manifest verification failed.');
    }

    echo json_encode([
        'ok' => true,
        'backup_dir' => $backupDir,
        'artifact_count' => count($manifestFiles),
        'laravel_database_bytes' => filesize($backupDir . '/laravel-database.sql.gz'),
        'pwa_database_bytes' => filesize($backupDir . '/pwa-database.sql.gz'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, "Backup failed: " . $error->getMessage() . "\n");
    exit(1);
}
