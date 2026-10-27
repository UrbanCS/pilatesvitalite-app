#!/usr/bin/env php
<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const WP_IMPORT_DEFAULT_SOURCE = 'https://pilatesvitalite.ca';
const WP_IMPORT_EXPECTED_POSTS = 28;
const WP_IMPORT_ADMIN_ID = 1;
const WP_IMPORT_CATEGORY_ID = 1;
const WP_IMPORT_LANGUAGE = 'en';
const WP_IMPORT_UPLOAD_DIRECTORY = 'uploads/wordpress-blog';
const WP_IMPORT_MAX_RESPONSE_BYTES = 25_000_000;
const WP_IMPORT_MAX_MEDIA_BYTES = 150_000_000;
const WP_IMPORT_MAX_TOTAL_MEDIA_BYTES = 750_000_000;
const WP_IMPORT_USER_AGENT = 'PilatesVitaliteWordPressImporter/1.0';

const WP_IMPORT_ALIAS_POST_ID = 2558;
const WP_IMPORT_ALIAS_SLUG = 'la-sant-osseuse-pas-quune-affaire-de-femmes';
const WP_IMPORT_CANONICAL_SLUG = 'la-sante-osseuse-pas-quune-affaire-de-femmes';

final class WordPressImportException extends RuntimeException
{
}

final class RemoteResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
        public string $effectiveUrl,
        public string $contentType
    ) {
    }
}

final class StreamedMedia
{
    public function __construct(
        public string $effectiveUrl,
        public string $contentType,
        public string $sha256,
        public int $bytes
    ) {
    }
}

final class RemoteClient
{
    public function __construct(private int $timeoutSeconds = 45)
    {
        if (!extension_loaded('curl')) {
            throw new WordPressImportException('The PHP cURL extension is required.');
        }
    }

    public function get(string $url, int $maxBytes = WP_IMPORT_MAX_RESPONSE_BYTES): RemoteResponse
    {
        $currentUrl = normalizeTransportUrl($url);

        for ($redirect = 0; $redirect <= 5; $redirect++) {
            assertPublicHttpUrl($currentUrl);
            $response = $this->getOnce($currentUrl, $maxBytes);

            if (!$this->isRedirect($response->status)) {
                return $response;
            }

            $location = $response->headers['location'] ?? '';
            if ($location === '') {
                return $response;
            }

            $resolved = resolveUrl($location, $currentUrl);
            if ($resolved === null) {
                throw new WordPressImportException("Invalid redirect from {$currentUrl}.");
            }
            $currentUrl = normalizeTransportUrl($resolved);
        }

        throw new WordPressImportException("Too many redirects while requesting {$url}.");
    }

    /**
     * @return array<mixed>
     */
    public function getJson(string $url): array
    {
        $response = $this->get($url);
        if ($response->status < 200 || $response->status >= 300) {
            throw new WordPressImportException("HTTP {$response->status} while requesting {$url}.");
        }

        try {
            $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new WordPressImportException("Invalid JSON returned by {$url}: {$error->getMessage()}", 0, $error);
        }

        if (!is_array($decoded)) {
            throw new WordPressImportException("Unexpected JSON document returned by {$url}.");
        }

        return $decoded;
    }

    public function streamMedia(string $url, ?string $temporaryPath): StreamedMedia
    {
        $currentUrl = normalizeTransportUrl($url);

        for ($redirect = 0; $redirect <= 5; $redirect++) {
            assertPublicHttpUrl($currentUrl);
            $result = $this->streamOnce($currentUrl, $temporaryPath);

            if (!$this->isRedirect($result['status'])) {
                if ($result['status'] < 200 || $result['status'] >= 300) {
                    throw new WordPressImportException("HTTP {$result['status']} while downloading {$currentUrl}.");
                }

                return new StreamedMedia(
                    $currentUrl,
                    $result['content_type'],
                    $result['sha256'],
                    $result['bytes']
                );
            }

            $location = $result['headers']['location'] ?? '';
            if ($location === '') {
                throw new WordPressImportException("Redirect without Location while downloading {$currentUrl}.");
            }

            $resolved = resolveUrl($location, $currentUrl);
            if ($resolved === null) {
                throw new WordPressImportException("Invalid media redirect from {$currentUrl}.");
            }
            $currentUrl = normalizeTransportUrl($resolved);
        }

        throw new WordPressImportException("Too many redirects while downloading {$url}.");
    }

    private function getOnce(string $url, int $maxBytes): RemoteResponse
    {
        $headers = [];
        $body = '';
        $bytes = 0;
        $ch = curl_init($url);
        if ($ch === false) {
            throw new WordPressImportException("Unable to initialize cURL for {$url}.");
        }

        $options = [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_USERAGENT => WP_IMPORT_USER_AGENT,
            CURLOPT_HTTPHEADER => ['Accept: application/json, text/html;q=0.9, */*;q=0.8'],
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                collectHeaderLine($headers, $line);
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$bytes, $maxBytes): int {
                $length = strlen($chunk);
                $bytes += $length;
                if ($bytes > $maxBytes) {
                    return 0;
                }
                $body .= $chunk;
                return $length;
            },
        ];
        addCurlProtocolRestrictions($options);
        curl_setopt_array($ch, $options);

        $success = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $contentType = normalizeContentType((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
        curl_close($ch);

        if ($success === false) {
            $reason = $bytes > $maxBytes ? "response exceeded {$maxBytes} bytes" : $curlError;
            throw new WordPressImportException("Request failed for {$url}: {$reason}.");
        }

        return new RemoteResponse($status, $headers, $body, $effectiveUrl ?: $url, $contentType);
    }

    /**
     * @return array{status:int,headers:array<string,string>,content_type:string,sha256:string,bytes:int}
     */
    private function streamOnce(string $url, ?string $temporaryPath): array
    {
        $headers = [];
        $bytes = 0;
        $hash = hash_init('sha256');
        $file = null;

        if ($temporaryPath !== null) {
            $file = fopen($temporaryPath, 'wb');
            if ($file === false) {
                throw new WordPressImportException("Unable to create temporary media file {$temporaryPath}.");
            }
        }

        $ch = curl_init($url);
        if ($ch === false) {
            if (is_resource($file)) {
                fclose($file);
            }
            throw new WordPressImportException("Unable to initialize cURL for {$url}.");
        }

        $options = [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => max($this->timeoutSeconds, 120),
            CURLOPT_USERAGENT => WP_IMPORT_USER_AGENT,
            CURLOPT_HTTPHEADER => ['Accept: image/*, audio/*, video/*, application/pdf;q=0.9, */*;q=0.5'],
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                collectHeaderLine($headers, $line);
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$bytes, $hash, $file): int {
                $length = strlen($chunk);
                $bytes += $length;
                if ($bytes > WP_IMPORT_MAX_MEDIA_BYTES) {
                    return 0;
                }
                hash_update($hash, $chunk);
                if (is_resource($file) && fwrite($file, $chunk) !== $length) {
                    return 0;
                }
                return $length;
            },
        ];
        addCurlProtocolRestrictions($options);
        curl_setopt_array($ch, $options);

        $success = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = normalizeContentType((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
        curl_close($ch);

        if (is_resource($file)) {
            fflush($file);
            fclose($file);
        }

        if ($success === false) {
            $reason = $bytes > WP_IMPORT_MAX_MEDIA_BYTES
                ? 'media exceeded ' . WP_IMPORT_MAX_MEDIA_BYTES . ' bytes'
                : $curlError;
            throw new WordPressImportException("Media download failed for {$url}: {$reason}.");
        }

        return [
            'status' => $status,
            'headers' => $headers,
            'content_type' => $contentType,
            'sha256' => hash_final($hash),
            'bytes' => $bytes,
        ];
    }

    private function isRedirect(int $status): bool
    {
        return in_array($status, [301, 302, 303, 307, 308], true);
    }
}

final class FileMutationJournal
{
    /** @var list<string> */
    private array $createdFiles = [];

    /** @var list<string> */
    private array $createdDirectories = [];

    private bool $active = true;

    public function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }
        if (file_exists($directory)) {
            throw new WordPressImportException("Expected a directory but found a file: {$directory}.");
        }

        $parent = dirname($directory);
        if ($parent !== $directory && !is_dir($parent)) {
            $this->ensureDirectory($parent);
        }

        if (!mkdir($directory, 0755) && !is_dir($directory)) {
            throw new WordPressImportException("Unable to create directory {$directory}.");
        }
        $this->createdDirectories[] = $directory;
    }

    public function trackCreatedFile(string $path): void
    {
        if (!in_array($path, $this->createdFiles, true)) {
            $this->createdFiles[] = $path;
        }
    }

    public function writeNewFileAtomically(string $path, string $contents): void
    {
        if (file_exists($path)) {
            throw new WordPressImportException("Refusing to overwrite existing file {$path}.");
        }

        $this->ensureDirectory(dirname($path));
        $temporary = $path . '.part-' . bin2hex(random_bytes(4));
        $this->trackCreatedFile($temporary);

        $written = file_put_contents($temporary, $contents, LOCK_EX);
        if ($written === false || $written !== strlen($contents)) {
            throw new WordPressImportException("Unable to write {$temporary}.");
        }
        if (!rename($temporary, $path)) {
            throw new WordPressImportException("Unable to finalize {$path}.");
        }
        $this->trackCreatedFile($path);
    }

    public function commit(): void
    {
        $this->active = false;
    }

    public function rollback(): void
    {
        if (!$this->active) {
            return;
        }
        $this->active = false;

        foreach (array_reverse($this->createdFiles) as $path) {
            if (is_file($path) && !unlink($path)) {
                fwrite(STDERR, "Warning: unable to remove {$path} during rollback.\n");
            }
        }

        foreach (array_reverse($this->createdDirectories) as $directory) {
            if (is_dir($directory)) {
                @rmdir($directory);
            }
        }
    }
}

final class MediaLocalizer
{
    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    /** @var list<array<string, mixed>> */
    private array $records = [];

    private int $totalBytes = 0;
    private int $counter = 0;

    public function __construct(
        private RemoteClient $client,
        private string $laravelRoot,
        private string $sourceHost,
        private string $runId,
        private bool $dryRun,
        private ?FileMutationJournal $journal
    ) {
    }

    public function shouldLocalize(string $absoluteUrl, bool $mediaElement = false): bool
    {
        $parts = parse_url($absoluteUrl);
        $host = normalizeHost((string) ($parts['host'] ?? ''));
        if ($host === '' || $host !== normalizeHost($this->sourceHost)) {
            return false;
        }

        $path = strtolower(rawurldecode((string) ($parts['path'] ?? '')));
        return $mediaElement
            || str_contains($path, '/wp-content/uploads/')
            || hasMediaExtension($path);
    }

    /**
     * @return array{relative:string,public:string,record:array<string,mixed>}
     */
    public function localize(string $url, string $postSlug, string $role): array
    {
        $url = stripUrlFragment($url);
        $cacheKey = $postSlug . "\n" . $url;
        if (isset($this->cache[$cacheKey])) {
            $cached = $this->cache[$cacheKey];
            return [
                'relative' => (string) $cached['local'],
                'public' => '/' . ltrim((string) $cached['local'], '/'),
                'record' => $cached,
            ];
        }

        $temporaryPath = null;
        if (!$this->dryRun) {
            if ($this->journal === null) {
                throw new LogicException('An apply run requires a file mutation journal.');
            }
            $temporaryDirectory = $this->laravelRoot . DIRECTORY_SEPARATOR . 'public'
                . DIRECTORY_SEPARATOR . WP_IMPORT_UPLOAD_DIRECTORY;
            $this->journal->ensureDirectory($temporaryDirectory);
            $temporaryPath = $temporaryDirectory . DIRECTORY_SEPARATOR . '.wp-import-'
                . $this->runId . '-' . (++$this->counter) . '.part';
            $this->journal->trackCreatedFile($temporaryPath);
        }

        $media = $this->client->streamMedia($url, $temporaryPath);
        $this->totalBytes += $media->bytes;
        if ($this->totalBytes > WP_IMPORT_MAX_TOTAL_MEDIA_BYTES) {
            throw new WordPressImportException(
                'Total media download exceeded ' . WP_IMPORT_MAX_TOTAL_MEDIA_BYTES . ' bytes.'
            );
        }

        $extension = mediaExtension($media->contentType, $media->effectiveUrl);
        $sourceName = pathinfo(rawurldecode((string) parse_url($media->effectiveUrl, PHP_URL_PATH)), PATHINFO_FILENAME);
        $safeName = Str::slug($sourceName);
        if ($safeName === '') {
            $safeName = 'media';
        }
        $safeName = substr($safeName, 0, 70);
        $filename = substr($media->sha256, 0, 16) . '-' . $safeName . '.' . $extension;
        $relative = WP_IMPORT_UPLOAD_DIRECTORY . '/' . $postSlug . '/' . $filename;
        $target = $this->laravelRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $reused = false;

        if (!$this->dryRun) {
            if ($this->journal === null || $temporaryPath === null) {
                throw new LogicException('Missing apply-mode file state.');
            }
            $this->journal->ensureDirectory(dirname($target));

            if (is_file($target)) {
                $existingHash = hash_file('sha256', $target);
                if (!is_string($existingHash) || !hash_equals($media->sha256, $existingHash)) {
                    throw new WordPressImportException("Existing media hash mismatch for {$target}.");
                }
                $reused = true;
                if (is_file($temporaryPath) && !unlink($temporaryPath)) {
                    throw new WordPressImportException("Unable to remove temporary file {$temporaryPath}.");
                }
            } else {
                if (!rename($temporaryPath, $target)) {
                    throw new WordPressImportException("Unable to move media into {$target}.");
                }
                $this->journal->trackCreatedFile($target);
            }
        }

        $record = [
            'role' => $role,
            'source' => $url,
            'effective_source' => $media->effectiveUrl,
            'local' => $relative,
            'mime' => $media->contentType,
            'bytes' => $media->bytes,
            'sha256' => $media->sha256,
            'reused' => $reused,
        ];
        $this->cache[$cacheKey] = $record;
        $this->records[] = $record;

        return [
            'relative' => $relative,
            'public' => '/' . $relative,
            'record' => $record,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function records(): array
    {
        return $this->records;
    }
}

final class InternalLinkMapper
{
    /** @var array<string, string> */
    private array $pathMap = [];

    /** @var array<int, string> */
    private array $idMap = [];

    /**
     * @param list<array<string, mixed>> $posts
     */
    public function __construct(private string $sourceHost, array $posts)
    {
        foreach ($posts as $post) {
            $target = '/blog/' . $post['slug'];
            $path = normalizeUrlPath((string) parse_url((string) $post['source_url'], PHP_URL_PATH));
            if ($path !== '') {
                $this->pathMap[$path] = $target;
            }
            $this->idMap[(int) $post['wp_id']] = $target;
        }
    }

    public function rewrite(string $rawUrl, string $baseUrl): string
    {
        $absolute = resolveUrl($rawUrl, $baseUrl);
        if ($absolute === null) {
            return $rawUrl;
        }

        $parts = parse_url($absolute);
        $host = normalizeHost((string) ($parts['host'] ?? ''));
        if ($host !== normalizeHost($this->sourceHost)) {
            return $rawUrl;
        }

        $path = normalizeUrlPath((string) ($parts['path'] ?? '/'));
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        parse_str((string) ($parts['query'] ?? ''), $query);

        if ($path === '/cours-et-tarifs') {
            return '/shop' . $fragment;
        }

        if (isset($query['p']) && ctype_digit((string) $query['p'])) {
            $postId = (int) $query['p'];
            if (isset($this->idMap[$postId])) {
                return $this->idMap[$postId] . $fragment;
            }
        }

        if (isset($this->pathMap[$path])) {
            return $this->pathMap[$path] . $fragment;
        }

        return $rawUrl;
    }
}

final class HtmlContentRewriter
{
    public function __construct(
        private MediaLocalizer $media,
        private InternalLinkMapper $links,
        private string $sourceHost
    ) {
    }

    public function rewrite(string $html, string $baseUrl, string $postSlug): string
    {
        if (trim($html) === '') {
            return '';
        }

        $previousErrors = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $wrapperId = 'wordpress-import-root-' . bin2hex(random_bytes(4));
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="' . $wrapperId . '">' . $html . '</div>',
            LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (!$loaded) {
            throw new WordPressImportException("Unable to parse HTML for {$postSlug}.");
        }

        $xpath = new DOMXPath($document);
        $wrapper = $xpath->query("//*[@id='{$wrapperId}']")?->item(0);
        if (!$wrapper instanceof DOMElement) {
            throw new WordPressImportException("Unable to locate HTML wrapper for {$postSlug}.");
        }

        $nodes = [];
        foreach ($xpath->query('.//*', $wrapper) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $nodes[] = $node;
            }
        }

        foreach ($nodes as $node) {
            $tag = strtolower($node->tagName);

            if ($node->hasAttribute('href') && $tag === 'a') {
                $rawHref = trim($node->getAttribute('href'));
                $absolute = resolveUrl($rawHref, $baseUrl);
                if ($absolute !== null && $this->media->shouldLocalize($absolute, false)) {
                    $role = isAudioUrl($absolute) ? 'inline-audio' : 'inline-media';
                    $localized = $this->media->localize($absolute, $postSlug, $role);
                    $node->setAttribute('href', $localized['public']);
                    $visibleText = trim(html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($visibleText === $rawHref || $visibleText === $absolute) {
                        while ($node->firstChild !== null) {
                            $node->removeChild($node->firstChild);
                        }
                        $node->appendChild($document->createTextNode(
                            $role === 'inline-audio' ? 'Écouter l’enregistrement audio' : 'Télécharger le fichier'
                        ));
                    }
                } else {
                    $node->setAttribute('href', $this->links->rewrite($rawHref, $baseUrl));
                }
            }

            if ($node->hasAttribute('src')) {
                $rawSource = trim($node->getAttribute('src'));
                $absolute = resolveUrl($rawSource, $baseUrl);
                $role = $this->roleForNode($node);
                if ($absolute !== null && $this->media->shouldLocalize($absolute, true)) {
                    $localized = $this->media->localize($absolute, $postSlug, $role);
                    $node->setAttribute('src', $localized['public']);
                }
            }

            if ($node->hasAttribute('poster')) {
                $rawPoster = trim($node->getAttribute('poster'));
                $absolute = resolveUrl($rawPoster, $baseUrl);
                if ($absolute !== null && $this->media->shouldLocalize($absolute, true)) {
                    $localized = $this->media->localize($absolute, $postSlug, 'inline-image');
                    $node->setAttribute('poster', $localized['public']);
                }
            }

            if ($node->hasAttribute('srcset') || $node->hasAttribute('sizes')) {
                $node->removeAttribute('srcset');
                $node->removeAttribute('sizes');
            }

            if ($node->hasAttribute('style')) {
                $style = preg_replace_callback(
                    '/url\(\s*([\'\"]?)(.*?)\1\s*\)/i',
                    function (array $matches) use ($baseUrl, $postSlug): string {
                        $absolute = resolveUrl(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $baseUrl);
                        if ($absolute === null || !$this->media->shouldLocalize($absolute, true)) {
                            return $matches[0];
                        }
                        $localized = $this->media->localize($absolute, $postSlug, 'inline-image');
                        return "url('{$localized['public']}')";
                    },
                    $node->getAttribute('style')
                );
                if (is_string($style)) {
                    $node->setAttribute('style', $style);
                }
            }
        }

        $rewritten = '';
        foreach ($wrapper->childNodes as $child) {
            $rewritten .= $document->saveHTML($child);
        }

        return trim($rewritten);
    }

    private function roleForNode(DOMElement $node): string
    {
        $tag = strtolower($node->tagName);
        $type = strtolower($node->getAttribute('type'));
        if ($tag === 'audio' || str_starts_with($type, 'audio/')) {
            return 'inline-audio';
        }
        if ($tag === 'video' || str_starts_with($type, 'video/')) {
            return 'inline-video';
        }
        if ($tag === 'source' && $node->parentNode instanceof DOMElement) {
            return $this->roleForNode($node->parentNode);
        }
        return 'inline-image';
    }
}

/**
 * @param list<string> $arguments
 * @return array{mode:string,source:string,expected:int}
 */
function parseOptions(array $arguments): array
{
    $mode = null;
    $source = getenv('WORDPRESS_BASE_URL') ?: WP_IMPORT_DEFAULT_SOURCE;
    $expected = WP_IMPORT_EXPECTED_POSTS;

    foreach (array_slice($arguments, 1) as $argument) {
        if ($argument === '--dry-run') {
            if ($mode !== null) {
                throw new WordPressImportException('Choose exactly one of --dry-run or --apply.');
            }
            $mode = 'dry-run';
            continue;
        }
        if ($argument === '--apply') {
            if ($mode !== null) {
                throw new WordPressImportException('Choose exactly one of --dry-run or --apply.');
            }
            $mode = 'apply';
            continue;
        }
        if (str_starts_with($argument, '--source=')) {
            $source = substr($argument, strlen('--source='));
            continue;
        }
        if (str_starts_with($argument, '--expected-count=')) {
            $value = substr($argument, strlen('--expected-count='));
            if (!ctype_digit($value) || (int) $value < 1) {
                throw new WordPressImportException('--expected-count must be a positive integer.');
            }
            $expected = (int) $value;
            continue;
        }
        if ($argument === '--help' || $argument === '-h') {
            printUsage();
            exit(0);
        }
        throw new WordPressImportException("Unknown option: {$argument}.");
    }

    if ($mode === null) {
        throw new WordPressImportException('Choose exactly one of --dry-run or --apply.');
    }

    $source = rtrim(trim($source), '/');
    assertPublicHttpUrl($source);

    return ['mode' => $mode, 'source' => $source, 'expected' => $expected];
}

function printUsage(): void
{
    echo <<<'TEXT'
Usage:
  php ops/import-wordpress-blog.php --dry-run [--source=https://pilatesvitalite.ca] [--expected-count=28]
  php ops/import-wordpress-blog.php --apply   [--source=https://pilatesvitalite.ca] [--expected-count=28]

--dry-run performs network and read-only database checks, but writes no database row or file.
--apply imports inside a database transaction and removes newly created files if the run fails.

TEXT;
}

/**
 * @param array<string, string> $headers
 */
function collectHeaderLine(array &$headers, string $line): void
{
    $trimmed = trim($line);
    if ($trimmed === '') {
        return;
    }
    if (preg_match('/^HTTP\//i', $trimmed)) {
        $headers = [];
        return;
    }
    $position = strpos($trimmed, ':');
    if ($position === false) {
        return;
    }
    $name = strtolower(trim(substr($trimmed, 0, $position)));
    $headers[$name] = trim(substr($trimmed, $position + 1));
}

/**
 * @param array<int, mixed> $options
 */
function addCurlProtocolRestrictions(array &$options): void
{
    if (defined('CURLOPT_PROTOCOLS_STR')) {
        $options[constant('CURLOPT_PROTOCOLS_STR')] = 'http,https';
        $options[constant('CURLOPT_REDIR_PROTOCOLS_STR')] = 'http,https';
        return;
    }
    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
        $protocols = constant('CURLPROTO_HTTP') | constant('CURLPROTO_HTTPS');
        $options[constant('CURLOPT_PROTOCOLS')] = $protocols;
        if (defined('CURLOPT_REDIR_PROTOCOLS')) {
            $options[constant('CURLOPT_REDIR_PROTOCOLS')] = $protocols;
        }
    }
}

function normalizeContentType(string $value): string
{
    return strtolower(trim(explode(';', $value, 2)[0]));
}

function normalizeHost(string $host): string
{
    $host = strtolower(rtrim(trim($host), '.'));
    return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
}

function normalizeTransportUrl(string $url): string
{
    $normalized = preg_replace_callback(
        '/[^\x20-\x7E]/u',
        static fn (array $matches): string => rawurlencode($matches[0]),
        $url
    );
    if (!is_string($normalized)) {
        throw new WordPressImportException("Unable to normalize URL: {$url}.");
    }

    return str_replace(' ', '%20', $normalized);
}

function assertPublicHttpUrl(string $url): void
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new WordPressImportException("Invalid URL: {$url}.");
    }
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = (string) ($parts['host'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        throw new WordPressImportException("Only public HTTP(S) URLs are allowed: {$url}.");
    }
    if (strtolower($host) === 'localhost' || str_ends_with(strtolower($host), '.local')) {
        throw new WordPressImportException("Local host is not allowed: {$host}.");
    }

    $addresses = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $addresses[] = $host;
    } else {
        foreach (dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            if (isset($record['ip'])) {
                $addresses[] = $record['ip'];
            }
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }
    }
    if ($addresses === []) {
        throw new WordPressImportException("Unable to resolve remote host {$host}.");
    }
    foreach (array_unique($addresses) as $address) {
        if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new WordPressImportException("Remote host {$host} resolves to a private or reserved address.");
        }
    }
}

function resolveUrl(string $url, string $baseUrl): ?string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '' || str_starts_with($url, '#') || preg_match('/^(?:data|blob|mailto|tel|javascript):/i', $url)) {
        return null;
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }

    $base = parse_url($baseUrl);
    if (!is_array($base) || empty($base['scheme']) || empty($base['host'])) {
        return null;
    }
    if (str_starts_with($url, '//')) {
        return $base['scheme'] . ':' . $url;
    }

    $origin = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');
    if (str_starts_with($url, '/')) {
        return $origin . normalizeRelativeUrl($url);
    }

    $basePath = (string) ($base['path'] ?? '/');
    $directory = str_ends_with($basePath, '/') ? $basePath : dirname($basePath) . '/';
    return $origin . normalizeRelativeUrl($directory . $url);
}

function normalizeRelativeUrl(string $url): string
{
    $fragment = '';
    if (($position = strpos($url, '#')) !== false) {
        $fragment = substr($url, $position);
        $url = substr($url, 0, $position);
    }
    $query = '';
    if (($position = strpos($url, '?')) !== false) {
        $query = substr($url, $position);
        $url = substr($url, 0, $position);
    }

    $segments = [];
    foreach (explode('/', $url) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);
            continue;
        }
        $segments[] = $segment;
    }

    return '/' . implode('/', $segments) . $query . $fragment;
}

function stripUrlFragment(string $url): string
{
    $position = strpos($url, '#');
    return $position === false ? $url : substr($url, 0, $position);
}

function normalizeUrlPath(string $path): string
{
    $path = '/' . trim(rawurldecode($path), '/');
    return $path === '/' ? '/' : rtrim($path, '/');
}

function hasMediaExtension(string $path): bool
{
    $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
    return in_array($extension, [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif',
        'mp3', 'm4a', 'aac', 'wav', 'ogg', 'oga',
        'mp4', 'm4v', 'webm', 'mov', 'pdf',
    ], true);
}

function isAudioUrl(string $url): bool
{
    $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    return in_array($extension, ['mp3', 'm4a', 'aac', 'wav', 'ogg', 'oga'], true);
}

function mediaExtension(string $contentType, string $url): string
{
    $mimeMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/aac' => 'aac',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/ogg' => 'ogg',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'application/pdf' => 'pdf',
    ];
    if (isset($mimeMap[$contentType])) {
        return $mimeMap[$contentType];
    }

    $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    if (($contentType === '' || $contentType === 'application/octet-stream') && hasMediaExtension('/file.' . $extension)) {
        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    throw new WordPressImportException("Unsupported media type '{$contentType}' for {$url}.");
}

/**
 * @return list<array<string, mixed>>
 */
function fetchWordPressPosts(RemoteClient $client, string $sourceBase, int $expected): array
{
    $endpoint = $sourceBase . '/wp-json/wp/v2/posts';
    $posts = [];
    $page = 1;
    $reportedTotal = null;

    do {
        $query = http_build_query([
            'per_page' => 100,
            'page' => $page,
            'status' => 'publish',
            'orderby' => 'date',
            'order' => 'asc',
            '_embed' => 1,
        ], '', '&', PHP_QUERY_RFC3986);
        $response = $client->get($endpoint . '?' . $query);
        if ($response->status < 200 || $response->status >= 300) {
            throw new WordPressImportException("WordPress posts endpoint returned HTTP {$response->status}.");
        }
        try {
            $pagePosts = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new WordPressImportException('WordPress posts endpoint returned invalid JSON.', 0, $error);
        }
        if (!is_array($pagePosts)) {
            throw new WordPressImportException('WordPress posts endpoint did not return an array.');
        }
        if (isset($response->headers['x-wp-total']) && ctype_digit($response->headers['x-wp-total'])) {
            $reportedTotal = (int) $response->headers['x-wp-total'];
        }
        foreach ($pagePosts as $post) {
            if (!is_array($post)) {
                throw new WordPressImportException('WordPress returned a malformed post entry.');
            }
            $posts[] = $post;
        }
        $page++;
    } while (count($pagePosts) === 100);

    if ($reportedTotal !== null && $reportedTotal !== $expected) {
        throw new WordPressImportException("WordPress reports {$reportedTotal} posts; expected {$expected}.");
    }
    if (count($posts) !== $expected) {
        throw new WordPressImportException('Received ' . count($posts) . " WordPress posts; expected {$expected}.");
    }

    $ids = array_map(static fn (array $post): int => (int) ($post['id'] ?? 0), $posts);
    if (in_array(0, $ids, true) || count(array_unique($ids)) !== count($ids)) {
        throw new WordPressImportException('WordPress post IDs are missing or duplicated.');
    }

    return $posts;
}

/**
 * @param array<string, mixed> $post
 * @return array<string, mixed>
 */
function normalizeWordPressPost(array $post, string $sourceBase): array
{
    $wpId = (int) ($post['id'] ?? 0);
    $rawSlug = rawurldecode((string) ($post['slug'] ?? ''));
    $slug = Str::slug($rawSlug);
    if ($wpId < 1 || $slug === '') {
        throw new WordPressImportException("Post {$wpId} has an invalid slug.");
    }
    if ($wpId === WP_IMPORT_ALIAS_POST_ID && $slug !== WP_IMPORT_CANONICAL_SLUG) {
        throw new WordPressImportException(
            'WordPress post 2558 no longer has the expected canonical slug; aborting alias correction.'
        );
    }

    $title = decodeRenderedText((string) ($post['title']['rendered'] ?? ''));
    if ($title === '') {
        throw new WordPressImportException("Post {$wpId} has an empty title.");
    }
    $content = (string) ($post['content']['rendered'] ?? '');
    $excerpt = decodeRenderedText((string) ($post['excerpt']['rendered'] ?? ''));
    $publishedAt = wordpressDateToSql((string) ($post['date'] ?? ''), "post {$wpId} publication date");
    $updatedAt = wordpressDateToSql(
        (string) ($post['modified'] ?? $post['date'] ?? ''),
        "post {$wpId} modification date"
    );
    $sourceUrl = resolveUrl((string) ($post['link'] ?? ''), $sourceBase . '/');
    if ($sourceUrl === null) {
        throw new WordPressImportException("Post {$wpId} has an invalid source URL.");
    }

    $yoast = is_array($post['yoast_head_json'] ?? null) ? $post['yoast_head_json'] : [];
    $seoTitle = decodeRenderedText((string) ($yoast['title'] ?? $title));
    $seoDescription = decodeRenderedText((string) ($yoast['description'] ?? $excerpt));
    if ($seoDescription === '') {
        $seoDescription = Str::limit(decodeRenderedText($content), 160, '');
    }

    $tagNames = [];
    foreach (($post['_embedded']['wp:term'] ?? []) as $termGroup) {
        if (!is_array($termGroup)) {
            continue;
        }
        foreach ($termGroup as $term) {
            if (is_array($term) && ($term['taxonomy'] ?? '') === 'post_tag') {
                $name = decodeRenderedText((string) ($term['name'] ?? ''));
                if ($name !== '') {
                    $tagNames[] = $name;
                }
            }
        }
    }

    $tagNames = array_values(array_unique($tagNames));

    return [
        'wp_id' => $wpId,
        'raw' => $post,
        'slug' => $slug,
        'title' => $title,
        'content' => $content,
        'excerpt' => $excerpt,
        'seo_title' => $seoTitle ?: $title,
        'seo_description' => $seoDescription,
        'tags' => $tagNames === []
            ? null
            : json_encode($tagNames, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'published_at' => $publishedAt,
        'updated_at' => $updatedAt,
        'source_url' => $sourceUrl,
        'target_url' => '/blog/' . $slug,
    ];
}

/**
 * @param list<array<string, mixed>> $posts
 * @return list<array<string, mixed>>
 */
function assignHomepagePosts(array $posts): array
{
    $ranked = $posts;
    usort($ranked, static function (array $left, array $right): int {
        $dateComparison = strcmp((string) $right['published_at'], (string) $left['published_at']);
        return $dateComparison !== 0
            ? $dateComparison
            : ((int) $right['wp_id'] <=> (int) $left['wp_id']);
    });
    $homepageIds = array_flip(array_map(
        static fn (array $post): int => (int) $post['wp_id'],
        array_slice($ranked, 0, 3)
    ));

    foreach ($posts as &$post) {
        $post['show_homepage'] = isset($homepageIds[(int) $post['wp_id']]) ? 1 : 0;
    }
    unset($post);

    return $posts;
}

function decodeRenderedText(string $html): string
{
    $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(strip_tags($decoded));
    return preg_replace('/\s+/u', ' ', $text) ?? $text;
}

function wordpressDateToSql(string $value, string $label): string
{
    $value = trim($value);
    if ($value === '') {
        throw new WordPressImportException("Missing {$label}.");
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        throw new WordPressImportException("Invalid {$label}: {$value}.");
    }
    return $date->format('Y-m-d H:i:s');
}

/**
 * @param array<string, mixed> $post
 * @return array{url:?string,method:string,rest_status:?int,warnings:list<string>}
 */
function resolveFeaturedImage(array $post, RemoteClient $client, string $sourceBase): array
{
    $featuredId = (int) ($post['raw']['featured_media'] ?? 0);
    if ($featuredId < 1) {
        return ['url' => null, 'method' => 'none', 'rest_status' => null, 'warnings' => []];
    }

    $warnings = [];
    $restUrl = $sourceBase . '/wp-json/wp/v2/media/' . $featuredId;
    $restStatus = null;
    try {
        $response = $client->get($restUrl);
        $restStatus = $response->status;
        if ($response->status >= 200 && $response->status < 300) {
            $media = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
            $candidate = is_array($media) ? (string) ($media['source_url'] ?? '') : '';
            $resolved = resolveUrl($candidate, $sourceBase . '/');
            if ($resolved !== null) {
                return ['url' => $resolved, 'method' => 'rest', 'rest_status' => $restStatus, 'warnings' => []];
            }
        }
        $warnings[] = "Featured media REST returned HTTP {$response->status} for post {$post['wp_id']}.";
    } catch (Throwable $error) {
        $warnings[] = "Featured media REST failed for post {$post['wp_id']}: {$error->getMessage()}";
    }

    $ogImage = fetchOgImage($client, (string) $post['source_url']);
    if ($ogImage !== null) {
        return ['url' => $ogImage, 'method' => 'og:image', 'rest_status' => $restStatus, 'warnings' => $warnings];
    }

    $embedded = (string) ($post['raw']['_embedded']['wp:featuredmedia'][0]['source_url'] ?? '');
    $embeddedUrl = resolveUrl($embedded, $sourceBase . '/');
    if ($embeddedUrl !== null) {
        $warnings[] = "Using embedded featured media as final fallback for post {$post['wp_id']}.";
        return ['url' => $embeddedUrl, 'method' => 'embedded', 'rest_status' => $restStatus, 'warnings' => $warnings];
    }

    throw new WordPressImportException("Unable to resolve featured image for WordPress post {$post['wp_id']}.");
}

function fetchOgImage(RemoteClient $client, string $postUrl): ?string
{
    try {
        $response = $client->get($postUrl);
    } catch (Throwable) {
        return null;
    }
    if ($response->status < 200 || $response->status >= 300) {
        return null;
    }

    $previousErrors = libxml_use_internal_errors(true);
    $document = new DOMDocument('1.0', 'UTF-8');
    $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $response->body, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrors);
    if (!$loaded) {
        return null;
    }
    $xpath = new DOMXPath($document);
    foreach ([
        "//meta[translate(@property,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='og:image']/@content",
        "//meta[translate(@name,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='twitter:image']/@content",
    ] as $query) {
        $node = $xpath->query($query)?->item(0);
        $candidate = $node ? trim((string) $node->nodeValue) : '';
        $resolved = resolveUrl($candidate, $postUrl);
        if ($resolved !== null) {
            return $resolved;
        }
    }
    return null;
}

/**
 * @param list<array<string, mixed>> $posts
 * @return list<array<string, mixed>>
 */
function preparePosts(
    array $posts,
    RemoteClient $client,
    MediaLocalizer $media,
    InternalLinkMapper $links,
    string $sourceBase
): array {
    $rewriter = new HtmlContentRewriter($media, $links, (string) parse_url($sourceBase, PHP_URL_HOST));
    $prepared = [];

    foreach ($posts as $post) {
        $featured = resolveFeaturedImage($post, $client, $sourceBase);
        $image = '';
        if ($featured['url'] !== null) {
            try {
                $localized = $media->localize($featured['url'], (string) $post['slug'], 'featured');
                $image = $localized['relative'];
            } catch (Throwable $error) {
                if ($featured['method'] === 'og:image') {
                    throw $error;
                }
                $ogImage = fetchOgImage($client, (string) $post['source_url']);
                if ($ogImage === null) {
                    throw $error;
                }
                $localized = $media->localize($ogImage, (string) $post['slug'], 'featured-og-fallback');
                $image = $localized['relative'];
                $featured['method'] = 'og:image';
                $featured['url'] = $ogImage;
                $featured['warnings'][] = 'Primary featured media download failed; og:image fallback was used.';
            }
        }

        $description = $rewriter->rewrite(
            (string) $post['content'],
            (string) $post['source_url'],
            (string) $post['slug']
        );
        $post['image'] = $image;
        $post['description'] = $description;
        $post['featured'] = $featured;
        $post['content_sha256'] = hash('sha256', $description);
        unset($post['raw'], $post['content']);
        $prepared[] = $post;
    }

    return $prepared;
}

function localizedImportPath(string $reference, bool $databaseRelative): ?string
{
    $reference = trim(html_entity_decode($reference, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $parts = parse_url($reference);
    if (
        $reference === ''
        || $parts === false
        || isset($parts['scheme'])
        || isset($parts['host'])
        || isset($parts['query'])
        || isset($parts['fragment'])
    ) {
        return null;
    }

    $hasLeadingSlash = str_starts_with($reference, '/');
    if (($databaseRelative && $hasLeadingSlash) || (!$databaseRelative && !$hasLeadingSlash)) {
        return null;
    }

    $relative = ltrim(rawurldecode((string) ($parts['path'] ?? '')), '/');
    if (
        !str_starts_with($relative, WP_IMPORT_UPLOAD_DIRECTORY . '/')
        || in_array('..', explode('/', $relative), true)
    ) {
        return null;
    }

    return $relative;
}

/**
 * Assert the exact media relationships expected from the audited source before
 * opening the database transaction. Physical files may be shared across roles.
 *
 * @param list<array<string, mixed>> $posts
 * @return array<string, int>
 */
function validatePreparedMediaState(array $posts, string $laravelRoot, bool $dryRun): array
{
    if (count($posts) !== WP_IMPORT_EXPECTED_POSTS) {
        throw new WordPressImportException('Prepared media validation requires exactly 28 posts.');
    }

    $featuredPaths = [];
    $inlinePaths = [];
    $mp3Paths = [];
    $postSlugs = [];

    foreach ($posts as $post) {
        $slug = (string) ($post['slug'] ?? '');
        if ($slug === '' || isset($postSlugs[$slug])) {
            throw new WordPressImportException('Prepared media validation found a missing or duplicate post slug.');
        }
        $postSlugs[$slug] = true;

        $featuredPath = localizedImportPath((string) ($post['image'] ?? ''), true);
        if ($featuredPath === null) {
            throw new WordPressImportException("Post {$slug} does not have one relative localized featured image.");
        }
        $featuredPaths[] = $featuredPath;

        $description = (string) ($post['description'] ?? '');
        if (stripos(rawurldecode($description), 'wp-content/uploads') !== false) {
            throw new WordPressImportException("Post {$slug} still contains a WordPress uploads URL.");
        }

        $previousErrors = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $description . '</div>',
            LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        if (!$loaded) {
            throw new WordPressImportException("Unable to validate prepared HTML for {$slug}.");
        }

        foreach ($document->getElementsByTagName('img') as $image) {
            if ($image->hasAttribute('srcset') || $image->hasAttribute('sizes')) {
                throw new WordPressImportException("Post {$slug} still contains img srcset/sizes attributes.");
            }
            $path = localizedImportPath($image->getAttribute('src'), false);
            if ($path === null) {
                throw new WordPressImportException("Post {$slug} contains a non-local inline image.");
            }
            $inlinePaths[] = $path;
        }

        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//*[@href or @src]') ?: [] as $node) {
            foreach (['href', 'src'] as $attribute) {
                if (!$node instanceof DOMElement || !$node->hasAttribute($attribute)) {
                    continue;
                }
                $reference = trim($node->getAttribute($attribute));
                $path = rawurldecode((string) (parse_url($reference, PHP_URL_PATH) ?? ''));
                if (preg_match('/\.mp3$/i', $path) !== 1) {
                    continue;
                }
                $localized = localizedImportPath($reference, false);
                if ($localized === null) {
                    throw new WordPressImportException("Post {$slug} contains a non-local MP3 reference.");
                }
                $mp3Paths[] = $localized;
            }
        }
    }

    $uniqueInlinePaths = array_values(array_unique($inlinePaths));
    $uniqueMp3Paths = array_values(array_unique($mp3Paths));
    $uniqueFeaturedPaths = array_values(array_unique($featuredPaths));
    if (count($featuredPaths) !== 28 || count($uniqueFeaturedPaths) !== 28) {
        throw new WordPressImportException(
            'Expected exactly 28 distinct per-article featured-image paths; found '
            . count($featuredPaths) . ' references and ' . count($uniqueFeaturedPaths) . ' unique paths.'
        );
    }
    if (count($inlinePaths) !== 4 || count($uniqueInlinePaths) !== 4) {
        throw new WordPressImportException(
            'Expected exactly four unique localized img src references; found '
            . count($inlinePaths) . ' references and ' . count($uniqueInlinePaths) . ' unique paths.'
        );
    }
    if (count($mp3Paths) !== 4 || count($uniqueMp3Paths) !== 2) {
        throw new WordPressImportException(
            'Expected exactly four localized MP3 attribute references to two unique files; found '
            . count($mp3Paths) . ' references and ' . count($uniqueMp3Paths) . ' unique paths.'
        );
    }

    $allPaths = array_values(array_unique(array_merge($featuredPaths, $uniqueInlinePaths, $uniqueMp3Paths)));
    if (!$dryRun) {
        foreach ($allPaths as $relative) {
            $path = $laravelRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (!is_file($path)) {
                throw new WordPressImportException("Localized media file is missing before transaction: {$path}.");
            }
        }
    }

    return [
        'featured_references' => count($featuredPaths),
        'featured_unique_files' => count($uniqueFeaturedPaths),
        'inline_images' => count($inlinePaths),
        'inline_unique_files' => count($uniqueInlinePaths),
        'inline_audio' => count($uniqueMp3Paths),
        'inline_audio_references' => count($mp3Paths),
        'audio_unique_files' => count($uniqueMp3Paths),
        'physical_files' => count($allPaths),
    ];
}

function validateLaravelSchema(): void
{
    $schema = DB::connection()->getSchemaBuilder();
    $required = [
        'blogs' => [
            'id', 'admin_id', 'blog_category_id', 'slug', 'image', 'views', 'show_homepage',
            'is_popular', 'tags', 'status', 'created_at', 'updated_at',
        ],
        'blog_translations' => [
            'id', 'blog_id', 'lang_code', 'title', 'description', 'seo_title', 'seo_description',
            'created_at', 'updated_at',
        ],
    ];

    foreach ($required as $table => $columns) {
        if (!$schema->hasTable($table)) {
            throw new WordPressImportException("Missing Laravel table {$table}.");
        }
        $available = $schema->getColumnListing($table);
        $missing = array_values(array_diff($columns, $available));
        if ($missing !== []) {
            throw new WordPressImportException("Table {$table} is missing columns: " . implode(', ', $missing) . '.');
        }
    }
}

/**
 * Accept only the known one-row state before the first import or the exact
 * 28-row state produced by a previous successful import.
 *
 * @param list<string> $sourceSlugs
 * @return 'initial'|'final'
 */
function validateDatabaseStateBeforeImport(array $sourceSlugs): string
{
    $sourceSlugs = array_values(array_unique(array_map('strval', $sourceSlugs)));
    sort($sourceSlugs, SORT_STRING);
    if (
        count($sourceSlugs) !== WP_IMPORT_EXPECTED_POSTS
        || !in_array(WP_IMPORT_CANONICAL_SLUG, $sourceSlugs, true)
        || in_array(WP_IMPORT_ALIAS_SLUG, $sourceSlugs, true)
    ) {
        throw new WordPressImportException(
            'Preflight refused: source must contain exactly 28 unique canonical slugs, including post 2558.'
        );
    }

    $blogs = DB::table('blogs')->select(['id', 'slug'])->orderBy('id')->get();
    $translations = DB::table('blog_translations')
        ->select(['blog_id', 'lang_code'])
        ->orderBy('blog_id')
        ->get();

    if ($blogs->count() === 1) {
        $blog = $blogs->first();
        $translation = $translations->count() === 1 ? $translations->first() : null;
        if (
            $blog === null
            || !in_array((string) $blog->slug, [WP_IMPORT_ALIAS_SLUG, WP_IMPORT_CANONICAL_SLUG], true)
            || $translation === null
            || (int) $translation->blog_id !== (int) $blog->id
            || (string) $translation->lang_code !== WP_IMPORT_LANGUAGE
        ) {
            throw new WordPressImportException(
                'Preflight refused: initial state must be the sole health article alias/canonical row '
                . 'with exactly one 1:1 en translation.'
            );
        }

        return 'initial';
    }

    if ($blogs->count() !== WP_IMPORT_EXPECTED_POSTS) {
        throw new WordPressImportException(
            'Preflight refused: database must be either the exact one-row initial state or the exact '
            . WP_IMPORT_EXPECTED_POSTS . '-row final state; found ' . $blogs->count() . ' blogs.'
        );
    }

    $storedSlugs = $blogs->map(static fn ($blog): string => (string) $blog->slug)->all();
    sort($storedSlugs, SORT_STRING);
    if (
        $storedSlugs !== $sourceSlugs
        || in_array(WP_IMPORT_ALIAS_SLUG, $storedSlugs, true)
        || count(array_keys($storedSlugs, WP_IMPORT_CANONICAL_SLUG, true)) !== 1
    ) {
        throw new WordPressImportException(
            'Preflight refused: final blogs state does not exactly match the 28 canonical source slugs.'
        );
    }

    if ($translations->count() !== WP_IMPORT_EXPECTED_POSTS) {
        throw new WordPressImportException(
            'Preflight refused: final state must contain exactly 28 translations and no foreign rows.'
        );
    }

    $translationCounts = [];
    $knownBlogIds = [];
    foreach ($blogs as $blog) {
        $knownBlogIds[(int) $blog->id] = true;
    }
    foreach ($translations as $translation) {
        $blogId = (int) $translation->blog_id;
        if (
            !isset($knownBlogIds[$blogId])
            || (string) $translation->lang_code !== WP_IMPORT_LANGUAGE
        ) {
            throw new WordPressImportException(
                'Preflight refused: final translations must all be 1:1 en rows for the 28 source blogs.'
            );
        }
        $translationCounts[$blogId] = ($translationCounts[$blogId] ?? 0) + 1;
    }
    foreach (array_keys($knownBlogIds) as $blogId) {
        if (($translationCounts[$blogId] ?? 0) !== 1) {
            throw new WordPressImportException(
                'Preflight refused: final translations are not exactly 1:1 with blogs.'
            );
        }
    }

    return 'final';
}

/**
 * @param list<string> $expectedSlugs
 */
function validateImportedDatabaseState(array $expectedSlugs): void
{
    $expectedSlugs = array_values(array_unique($expectedSlugs));
    sort($expectedSlugs, SORT_STRING);
    if (count($expectedSlugs) !== WP_IMPORT_EXPECTED_POSTS) {
        throw new WordPressImportException(
            'Transactional validation expected exactly ' . WP_IMPORT_EXPECTED_POSTS . ' unique source slugs.'
        );
    }

    $storedSlugs = DB::table('blogs')->orderBy('slug')->pluck('slug')
        ->map(static fn ($slug): string => (string) $slug)
        ->all();
    sort($storedSlugs, SORT_STRING);
    if (count($storedSlugs) !== WP_IMPORT_EXPECTED_POSTS || $storedSlugs !== $expectedSlugs) {
        throw new WordPressImportException(
            'Transactional validation failed: blogs must contain exactly the 28 canonical WordPress slugs.'
        );
    }

    $duplicateSlugs = DB::table('blogs')
        ->select('slug')
        ->groupBy('slug')
        ->havingRaw('COUNT(*) <> 1')
        ->pluck('slug');
    if ($duplicateSlugs->isNotEmpty()) {
        throw new WordPressImportException('Transactional validation found duplicate Laravel slugs.');
    }
    if (
        DB::table('blogs')->where('slug', WP_IMPORT_ALIAS_SLUG)->exists()
        || DB::table('blogs')->where('slug', WP_IMPORT_CANONICAL_SLUG)->count() !== 1
    ) {
        throw new WordPressImportException('Transactional validation failed for the post 2558 slug correction.');
    }

    $translationTotal = DB::table('blog_translations')->count();
    $englishTotal = DB::table('blog_translations')->where('lang_code', WP_IMPORT_LANGUAGE)->count();
    if ($translationTotal !== WP_IMPORT_EXPECTED_POSTS || $englishTotal !== WP_IMPORT_EXPECTED_POSTS) {
        throw new WordPressImportException(
            'Transactional validation failed: blog_translations must contain exactly 28 en rows and no foreign rows.'
        );
    }

    $invalidTranslationLinks = DB::table('blogs as b')
        ->leftJoin('blog_translations as bt', static function ($join): void {
            $join->on('bt.blog_id', '=', 'b.id')
                ->where('bt.lang_code', '=', WP_IMPORT_LANGUAGE);
        })
        ->select('b.id')
        ->groupBy('b.id')
        ->havingRaw('COUNT(bt.id) <> 1')
        ->get();
    if ($invalidTranslationLinks->isNotEmpty()) {
        throw new WordPressImportException('Transactional validation failed: translations are not 1:1 with blogs.');
    }

    if (DB::table('blogs')->where('show_homepage', 1)->count() !== 3) {
        throw new WordPressImportException('Transactional validation failed: exactly three blogs must show on homepage.');
    }
}

/**
 * @param array<string, mixed> $post
 * @return array{row:?object,alias_row:?object,canonical_row:?object,alias_correction:bool}
 */
function locateBlogRow(array $post, bool $lock): array
{
    $query = DB::table('blogs')->where('slug', (string) $post['slug']);
    if ($lock) {
        $query->lockForUpdate();
    }
    $canonicalRows = $query->limit(2)->get();
    if ($canonicalRows->count() > 1) {
        throw new WordPressImportException("Multiple Laravel rows use slug {$post['slug']}.");
    }
    $canonical = $canonicalRows->first();
    $alias = null;

    if ((int) $post['wp_id'] === WP_IMPORT_ALIAS_POST_ID) {
        $aliasQuery = DB::table('blogs')->where('slug', WP_IMPORT_ALIAS_SLUG);
        if ($lock) {
            $aliasQuery->lockForUpdate();
        }
        $aliasRows = $aliasQuery->limit(2)->get();
        if ($aliasRows->count() > 1) {
            throw new WordPressImportException('Multiple Laravel rows use the known typo slug.');
        }
        $alias = $aliasRows->first();
        if ($canonical !== null && $alias !== null && (int) $canonical->id !== (int) $alias->id) {
            throw new WordPressImportException(
                'Both canonical and typo slug rows exist for WordPress post 2558; refusing to create or merge implicitly.'
            );
        }
    }

    $row = $canonical ?? $alias;
    return [
        'row' => $row,
        'alias_row' => $alias,
        'canonical_row' => $canonical,
        'alias_correction' => $canonical === null && $alias !== null,
    ];
}

/**
 * @param array<string, mixed> $post
 * @return array<string, mixed>
 */
function importOnePost(array $post, bool $apply): array
{
    $located = locateBlogRow($post, $apply);
    $row = $located['row'];
    $blogValues = [
        'admin_id' => WP_IMPORT_ADMIN_ID,
        'blog_category_id' => WP_IMPORT_CATEGORY_ID,
        'slug' => $post['slug'],
        'image' => $post['image'],
        'show_homepage' => $post['show_homepage'],
        'tags' => $post['tags'],
        'status' => 1,
        'created_at' => $post['published_at'],
        'updated_at' => $post['updated_at'],
    ];
    $translationValues = [
        'lang_code' => WP_IMPORT_LANGUAGE,
        'title' => $post['title'],
        'description' => $post['description'],
        'seo_title' => $post['seo_title'],
        'seo_description' => $post['seo_description'],
        'created_at' => $post['published_at'],
        'updated_at' => $post['updated_at'],
    ];

    if ($row === null) {
        $action = 'create';
        $blogId = null;
        if ($apply) {
            $blogId = (int) DB::table('blogs')->insertGetId($blogValues + [
                'views' => 0,
                'is_popular' => 0,
            ]);
            DB::table('blog_translations')->insert($translationValues + ['blog_id' => $blogId]);
        }

        return [
            'wp_id' => $post['wp_id'],
            'slug' => $post['slug'],
            'blog_id' => $blogId,
            'action' => $action,
            'translation_action' => 'create',
            'alias_corrected' => false,
        ];
    }

    $blogId = (int) $row->id;
    $translationQuery = DB::table('blog_translations')
        ->where('blog_id', $blogId)
        ->where('lang_code', WP_IMPORT_LANGUAGE);
    if ($apply) {
        $translationQuery->lockForUpdate();
    }
    $translations = $translationQuery->limit(2)->get();
    if ($translations->count() > 1) {
        throw new WordPressImportException("Blog {$blogId} has duplicate '{$post['slug']}' translations.");
    }
    $translation = $translations->first();

    $blogChanged = rowDiffers($row, $blogValues);
    $translationChanged = $translation === null || rowDiffers($translation, $translationValues);
    $aliasCorrected = (bool) $located['alias_correction'];
    $action = $aliasCorrected ? 'update-alias' : (($blogChanged || $translationChanged) ? 'update' : 'unchanged');

    if ($apply) {
        if ($blogChanged || $aliasCorrected) {
            DB::table('blogs')->where('id', $blogId)->update($blogValues);
        }
        if ($translation === null) {
            DB::table('blog_translations')->insert($translationValues + ['blog_id' => $blogId]);
        } elseif ($translationChanged) {
            DB::table('blog_translations')->where('id', (int) $translation->id)->update($translationValues);
        }
    }

    return [
        'wp_id' => $post['wp_id'],
        'slug' => $post['slug'],
        'blog_id' => $blogId,
        'action' => $action,
        'translation_action' => $translation === null ? 'create' : ($translationChanged ? 'update' : 'unchanged'),
        'alias_corrected' => $aliasCorrected,
    ];
}

/**
 * @param array<string, mixed> $values
 */
function rowDiffers(object $row, array $values): bool
{
    foreach ($values as $column => $value) {
        $existing = property_exists($row, $column) ? $row->{$column} : null;
        if ((string) ($existing ?? '') !== (string) ($value ?? '')) {
            return true;
        }
    }
    return false;
}

/**
 * @param list<array<string, mixed>> $results
 * @param list<array<string, mixed>> $media
 * @return array<string, int>
 */
function summarize(array $results, array $media): array
{
    $summary = [
        'posts_total' => count($results),
        'posts_create' => 0,
        'posts_update' => 0,
        'posts_update_alias' => 0,
        'posts_unchanged' => 0,
        'translations_create' => 0,
        'translations_update' => 0,
        'translations_unchanged' => 0,
        'media_total' => count($media),
        'media_downloaded' => 0,
        'media_reused' => 0,
        'featured_og_fallbacks' => 0,
        'inline_images' => 0,
        'inline_audio' => 0,
        'media_bytes' => 0,
    ];

    foreach ($results as $result) {
        $action = str_replace('-', '_', (string) $result['action']);
        $key = 'posts_' . $action;
        if (isset($summary[$key])) {
            $summary[$key]++;
        }
        $translationKey = 'translations_' . str_replace('-', '_', (string) $result['translation_action']);
        if (isset($summary[$translationKey])) {
            $summary[$translationKey]++;
        }
    }

    foreach ($media as $item) {
        $summary['media_bytes'] += (int) ($item['bytes'] ?? 0);
        if (($item['reused'] ?? false) === true) {
            $summary['media_reused']++;
        } else {
            $summary['media_downloaded']++;
        }
        $role = (string) ($item['role'] ?? '');
        if ($role === 'featured-og-fallback') {
            $summary['featured_og_fallbacks']++;
        }
        if ($role === 'inline-image') {
            $summary['inline_images']++;
        }
        if ($role === 'inline-audio') {
            $summary['inline_audio']++;
        }
    }

    return $summary;
}

/**
 * @param array<string, int> $summary
 */
function printSummary(string $mode, array $summary, ?string $manifestPath): void
{
    echo "\nWordPress blog import summary ({$mode})\n";
    echo str_repeat('-', 48) . "\n";
    foreach ($summary as $key => $value) {
        echo str_pad($key, 30) . ': ' . $value . "\n";
    }
    if ($manifestPath !== null) {
        echo "Manifest: {$manifestPath}\n";
    } else {
        echo "Manifest: emitted below only; dry-run wrote no file or database row.\n";
    }
}

/**
 * @param list<string> $arguments
 */
function runImporter(array $arguments): int
{
    $options = parseOptions($arguments);
    $dryRun = $options['mode'] === 'dry-run';
    $laravelRoot = dirname(__DIR__);
    $autoloadPath = $laravelRoot . '/vendor/autoload.php';
    $bootstrapPath = $laravelRoot . '/bootstrap/app.php';
    if (!is_file($autoloadPath) || !is_file($bootstrapPath)) {
        throw new WordPressImportException(
            'Place this script at ops/import-wordpress-blog.php inside the Laravel root before running it.'
        );
    }

    require $autoloadPath;
    $app = require $bootstrapPath;
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    validateLaravelSchema();

    $runId = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $startedAt = gmdate(DATE_ATOM);
    $client = new RemoteClient();
    $rawPosts = fetchWordPressPosts($client, $options['source'], $options['expected']);
    $posts = array_map(
        static fn (array $post): array => normalizeWordPressPost($post, $options['source']),
        $rawPosts
    );
    $posts = assignHomepagePosts($posts);

    $slugs = array_column($posts, 'slug');
    if (count(array_unique($slugs)) !== count($slugs)) {
        throw new WordPressImportException('Canonical WordPress slugs are not unique.');
    }
    $wp2558 = array_values(array_filter(
        $posts,
        static fn (array $post): bool => (int) $post['wp_id'] === WP_IMPORT_ALIAS_POST_ID
    ));
    if (count($wp2558) !== 1) {
        throw new WordPressImportException('Expected exactly one WordPress post with ID 2558.');
    }
    $databasePreflightState = validateDatabaseStateBeforeImport($slugs);

    $journal = $dryRun ? null : new FileMutationJournal();
    $completed = false;
    register_shutdown_function(static function () use (&$journal, &$completed): void {
        if (!$completed && $journal instanceof FileMutationJournal) {
            $journal->rollback();
        }
    });

    try {
        $sourceHost = (string) parse_url($options['source'], PHP_URL_HOST);
        $links = new InternalLinkMapper($sourceHost, $posts);
        $media = new MediaLocalizer(
            $client,
            $laravelRoot,
            $sourceHost,
            $runId,
            $dryRun,
            $journal
        );
        $preparedPosts = preparePosts($posts, $client, $media, $links, $options['source']);
        $mediaValidation = validatePreparedMediaState($preparedPosts, $laravelRoot, $dryRun);
        $results = [];
        $transactionOpen = false;

        if (!$dryRun) {
            DB::beginTransaction();
            $transactionOpen = true;
        }

        try {
            foreach ($preparedPosts as $post) {
                $results[] = importOnePost($post, !$dryRun);
            }

            if (!$dryRun) {
                validateImportedDatabaseState(array_column($preparedPosts, 'slug'));
            }

            $mediaRecords = $media->records();
            $summary = summarize($results, $mediaRecords);
            $summary['featured_images'] = $mediaValidation['featured_references'];
            $summary['featured_unique_files'] = $mediaValidation['featured_unique_files'];
            $summary['inline_images'] = $mediaValidation['inline_images'];
            $summary['inline_audio'] = $mediaValidation['inline_audio'];
            $summary['inline_audio_references'] = $mediaValidation['inline_audio_references'];
            $featuredFallbacks = 0;
            foreach ($preparedPosts as $post) {
                if (($post['featured']['method'] ?? '') === 'og:image') {
                    $featuredFallbacks++;
                }
            }
            $summary['featured_og_fallbacks'] = $featuredFallbacks;

            $manifest = [
                'run_id' => $runId,
                'mode' => $options['mode'],
                'source' => $options['source'],
                'database_preflight_state' => $databasePreflightState,
                'started_at' => $startedAt,
                'finished_at' => gmdate(DATE_ATOM),
                'expected_posts' => $options['expected'],
                'summary' => $summary,
                'media_validation' => $mediaValidation,
                'redirects' => array_map(
                    static fn (array $post): array => [
                        'wp_id' => $post['wp_id'],
                        'source' => $post['source_url'],
                        'target' => $post['target_url'],
                    ],
                    $preparedPosts
                ),
                'posts' => array_map(
                    static function (array $post, array $result): array {
                        return [
                            'wp_id' => $post['wp_id'],
                            'source_url' => $post['source_url'],
                            'slug' => $post['slug'],
                            'target_url' => $post['target_url'],
                            'title' => $post['title'],
                            'published_at' => $post['published_at'],
                            'updated_at' => $post['updated_at'],
                            'show_homepage' => $post['show_homepage'],
                            'image' => $post['image'],
                            'featured' => $post['featured'],
                            'content_sha256' => $post['content_sha256'],
                            'database' => $result,
                        ];
                    },
                    $preparedPosts,
                    $results
                ),
                'media' => $mediaRecords,
            ];
            $manifestJson = json_encode(
                $manifest,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . "\n";
            $manifestPath = null;

            if ($dryRun) {
                printSummary($options['mode'], $summary, null);
                echo "\n--- BEGIN DRY-RUN MANIFEST JSON ---\n";
                echo $manifestJson;
                echo "--- END DRY-RUN MANIFEST JSON ---\n";
            } else {
                if (!$journal instanceof FileMutationJournal) {
                    throw new LogicException('Missing apply-mode journal.');
                }
                $manifestPath = $laravelRoot . '/storage/app/imports/wordpress-blog-' . $runId . '.json';
                $journal->writeNewFileAtomically($manifestPath, $manifestJson);
                DB::commit();
                $transactionOpen = false;
                $journal->commit();
                $completed = true;
                printSummary($options['mode'], $summary, $manifestPath);
            }
        } catch (Throwable $error) {
            if ($transactionOpen) {
                DB::rollBack();
            }
            throw $error;
        }

        if ($dryRun) {
            $completed = true;
        }

        return 0;
    } catch (Throwable $error) {
        if ($journal instanceof FileMutationJournal) {
            $journal->rollback();
        }
        throw $error;
    }
}

try {
    exit(runImporter($argv));
} catch (Throwable $error) {
    fwrite(STDERR, "Import aborted: {$error->getMessage()}\n");
    exit(1);
}
