<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

const EXPECTED_BLOG_COUNT = 28;
const EXPECTED_TRANSLATION_COUNT = 28;
const EXPECTED_INLINE_IMAGE_COUNT = 4;
const EXPECTED_MP3_REFERENCE_COUNT = 4;
const EXPECTED_MP3_UNIQUE_COUNT = 2;
const EXPECTED_EARLIEST_DATE = '2018-12-13';
const EXPECTED_LATEST_DATE = '2023-06-07';
const DEFAULT_LANGUAGE_CODE = 'en';
const CANONICAL_HEALTH_SLUG = 'la-sante-osseuse-pas-quune-affaire-de-femmes';
const INCORRECT_HEALTH_SLUG = 'la-sant-osseuse-pas-quune-affaire-de-femmes';

/**
 * Read-only validation of the WordPress blog import.
 *
 * Run from the Laravel root with:
 *   php ops/audit-blog-import.php
 *
 * Optional environment overrides:
 *   BLOG_AUDIT_BASE_URL=https://pilatesvitalite.webactiondemo.ca
 *   BLOG_AUDIT_LANG_CODE=en
 *   BLOG_AUDIT_HTTP_TIMEOUT=20
 */

/** @param array<int, array<string, mixed>> $checks */
function addCheck(
    array &$checks,
    string $name,
    bool $passed,
    mixed $expected,
    mixed $actual,
    array $details = []
): void {
    $check = [
        'name' => $name,
        'passed' => $passed,
        'expected' => $expected,
        'actual' => $actual,
    ];

    if ($details !== []) {
        $check['details'] = $details;
    }

    $checks[] = $check;
}

/** @param array<int, array<string, mixed>> $checks */
function finish(array $checks, array $context = [], ?string $bootstrapOutput = null): never
{
    $failed = array_values(array_filter(
        $checks,
        static fn (array $check): bool => ($check['passed'] ?? false) !== true
    ));

    $report = [
        'audit' => 'wordpress_blog_import',
        'read_only' => true,
        'generated_at_utc' => gmdate('c'),
        'passed' => $failed === [],
        'summary' => [
            'checks' => count($checks),
            'passed' => count($checks) - count($failed),
            'failed' => count($failed),
        ],
        'context' => $context,
        'checks' => $checks,
        'exit_code' => $failed === [] ? 0 : 1,
    ];

    if ($bootstrapOutput !== null && trim($bootstrapOutput) !== '') {
        $report['bootstrap_output'] = trim($bootstrapOutput);
    }

    $json = json_encode(
        $report,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        $json = "{\n    \"passed\": false,\n    \"error\": \"Unable to encode audit report as JSON\",\n    \"exit_code\": 1\n}";
    }

    fwrite(STDOUT, $json . PHP_EOL);
    exit($failed === [] ? 0 : 1);
}

function lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function isPublishedStatus(mixed $status): bool
{
    if ($status === true || $status === 1 || $status === '1') {
        return true;
    }

    if (!is_string($status)) {
        return false;
    }

    return in_array(lower(trim($status)), ['active', 'publish', 'published'], true);
}

function normalizedDate(mixed $value): ?string
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable((string) $value))->format('Y-m-d');
    } catch (Throwable) {
        return null;
    }
}

function normalizedHost(?string $host): string
{
    $host = lower(trim((string) $host));
    return preg_replace('/^www\./i', '', $host) ?? $host;
}

function encodeUrlPath(string $path): string
{
    $segments = explode('/', str_replace('\\', '/', $path));
    $encoded = array_map(
        static fn (string $segment): string => rawurlencode(rawurldecode($segment)),
        $segments
    );

    return implode('/', $encoded);
}

/**
 * Resolve a database or HTML asset reference against Laravel's public folder.
 *
 * @return array<string, mixed>
 */
function inspectAssetReference(
    string $reference,
    string $publicRoot,
    string $baseUrl,
    bool $requireStoredRelative = false
): array {
    $original = trim(html_entity_decode($reference, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $baseParts = parse_url($baseUrl) ?: [];
    $parts = parse_url($original);
    $error = null;
    $isAbsolute = false;
    $isSameHost = true;

    if ($original === '' || $parts === false) {
        $error = $original === '' ? 'empty reference' : 'invalid URL';
        $parts = [];
    }

    if ($error === null && (isset($parts['scheme']) || isset($parts['host']))) {
        $isAbsolute = true;
        $scheme = lower((string) ($parts['scheme'] ?? ''));
        $isSameHost = in_array($scheme, ['http', 'https'], true)
            && normalizedHost($parts['host'] ?? '') === normalizedHost($baseParts['host'] ?? '');

        if (!$isSameHost) {
            $error = 'external URL';
        }
    } elseif ($error === null && str_starts_with($original, '//')) {
        $isAbsolute = true;
        $error = 'protocol-relative URL';
    }

    if ($error === null && $requireStoredRelative && ($isAbsolute || str_starts_with($original, '/'))) {
        $error = 'stored path is not relative';
    }

    $urlPath = (string) ($parts['path'] ?? $original);
    $decodedPath = rawurldecode(str_replace('\\', '/', $urlPath));
    $relativePath = ltrim($decodedPath, '/');
    $segments = explode('/', $relativePath);

    if ($error === null && ($relativePath === '' || in_array('..', $segments, true))) {
        $error = $relativePath === '' ? 'empty path' : 'path traversal';
    }

    $publicRootNormalized = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $publicRoot), DIRECTORY_SEPARATOR);
    $localPath = $publicRootNormalized . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $exists = $error === null && is_file($localPath);
    $insidePublicRoot = false;
    $realRoot = realpath($publicRootNormalized);
    $realFile = $exists ? realpath($localPath) : false;

    if ($realRoot !== false && $realFile !== false) {
        $rootPrefix = rtrim(str_replace('\\', '/', $realRoot), '/') . '/';
        $fileValue = str_replace('\\', '/', $realFile);
        $insidePublicRoot = DIRECTORY_SEPARATOR === '\\'
            ? str_starts_with(lower($fileValue), lower($rootPrefix))
            : str_starts_with($fileValue, $rootPrefix);
    }

    if ($exists && !$insidePublicRoot) {
        $error = 'resolved path leaves public directory';
    }

    $publicUrl = rtrim($baseUrl, '/') . '/' . encodeUrlPath($relativePath);

    return [
        'reference' => $original,
        'relative_path' => $relativePath,
        'local_path' => $localPath,
        'public_url' => $publicUrl,
        'is_absolute' => $isAbsolute,
        'is_same_host' => $isSameHost,
        'exists' => $exists,
        'inside_public_root' => $insidePublicRoot,
        'error' => $error,
    ];
}

/**
 * @return array{images: array<int, string>, mp3s: array<int, string>}
 */
function extractDescriptionAssets(string $html): array
{
    $images = [];
    $mp3s = [];

    if (class_exists(DOMDocument::class)) {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        if ($loaded) {
            foreach ($document->getElementsByTagName('img') as $image) {
                $src = trim($image->getAttribute('src'));
                if ($src !== '') {
                    $images[] = $src;
                }
            }

            $xpath = new DOMXPath($document);
            $nodes = $xpath->query('//*[@href or @src]');
            if ($nodes !== false) {
                foreach ($nodes as $node) {
                    foreach (['href', 'src'] as $attribute) {
                        if (!$node->hasAttribute($attribute)) {
                            continue;
                        }

                        $value = trim($node->getAttribute($attribute));
                        $path = (string) (parse_url(html_entity_decode($value, ENT_QUOTES | ENT_HTML5), PHP_URL_PATH) ?? '');
                        if ($value !== '' && preg_match('/\.mp3$/i', rawurldecode($path)) === 1) {
                            $mp3s[] = $value;
                        }
                    }
                }
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    } else {
        if (preg_match_all('/<img\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1/is', $html, $matches) > 0) {
            $images = array_values(array_filter(array_map('trim', $matches[2])));
        }

        if (preg_match_all('/\b(?:href|src)\s*=\s*(["\'])(.*?\.mp3(?:[?#][^"\']*)?)\1/is', $html, $matches) > 0) {
            $mp3s = array_values(array_filter(array_map('trim', $matches[2])));
        }
    }

    return ['images' => $images, 'mp3s' => $mp3s];
}

/**
 * @param array<int, string> $urls
 * @return array<int, array<string, mixed>>
 */
function httpChecks(array $urls, string $method, int $timeout): array
{
    $urls = array_values(array_unique($urls));
    if ($urls === []) {
        return [];
    }

    if (!function_exists('curl_multi_init')) {
        return streamHttpChecks($urls, $method, $timeout);
    }

    $results = [];
    foreach (array_chunk($urls, 6) as $batch) {
        $multi = curl_multi_init();
        $handles = [];

        foreach ($batch as $url) {
            $handle = curl_init($url);
            $options = [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => 'PilatesVitaliteBlogAudit/1.0',
                CURLOPT_HEADER => false,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_WRITEFUNCTION => static fn ($curl, string $data): int => strlen($data),
            ];

            if ($method === 'HEAD') {
                $options[CURLOPT_NOBODY] = true;
            } else {
                $options[CURLOPT_HTTPGET] = true;
            }

            curl_setopt_array($handle, $options);
            curl_multi_add_handle($multi, $handle);
            $handles[$url] = $handle;
        }

        do {
            $status = curl_multi_exec($multi, $active);
            if ($active && $status === CURLM_OK) {
                $selected = curl_multi_select($multi, 1.0);
                if ($selected === -1) {
                    usleep(10000);
                }
            }
        } while ($active && $status === CURLM_OK);

        foreach ($handles as $url => $handle) {
            $results[$url] = [
                'url' => $url,
                'method' => $method,
                'status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
                'content_type' => curl_getinfo($handle, CURLINFO_CONTENT_TYPE) ?: null,
                'effective_url' => curl_getinfo($handle, CURLINFO_EFFECTIVE_URL) ?: $url,
                'redirects' => (int) curl_getinfo($handle, CURLINFO_REDIRECT_COUNT),
                'error' => curl_error($handle) ?: null,
            ];
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }

        curl_multi_close($multi);
    }

    ksort($results);
    return array_values($results);
}

/**
 * @param array<int, string> $urls
 * @return array<int, array<string, mixed>>
 */
function streamHttpChecks(array $urls, string $method, int $timeout): array
{
    $results = [];
    foreach ($urls as $url) {
        $http_response_header = [];
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => $timeout,
                'ignore_errors' => true,
                'follow_location' => 1,
                'max_redirects' => 5,
                'header' => "User-Agent: PilatesVitaliteBlogAudit/1.0\r\n",
            ],
        ]);

        $handle = @fopen($url, 'rb', false, $context);
        $headers = isset($http_response_header) && is_array($http_response_header)
            ? $http_response_header
            : [];
        if (is_resource($handle)) {
            fclose($handle);
        }

        $status = 0;
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', $header, $match) === 1) {
                $status = (int) $match[1];
            }
        }

        $results[] = [
            'url' => $url,
            'method' => $method,
            'status' => $status,
            'content_type' => null,
            'effective_url' => $url,
            'redirects' => null,
            'error' => $handle === false ? 'HTTP stream request failed' : null,
        ];
    }

    return $results;
}

/**
 * Retry HEAD responses that commonly mean HEAD is unsupported.
 *
 * @param array<int, array<string, mixed>> $results
 * @return array<int, array<string, mixed>>
 */
function retryUnsupportedHead(array $results, int $timeout): array
{
    $retryUrls = [];
    foreach ($results as $result) {
        if (in_array((int) ($result['status'] ?? 0), [0, 405, 501], true)) {
            $retryUrls[] = (string) $result['url'];
        }
    }

    if ($retryUrls === []) {
        return $results;
    }

    $retried = [];
    foreach (httpChecks($retryUrls, 'GET', $timeout) as $result) {
        $retried[$result['url']] = $result;
    }

    foreach ($results as $index => $result) {
        $url = (string) $result['url'];
        if (isset($retried[$url])) {
            $retried[$url]['retried_after_head'] = true;
            $results[$index] = $retried[$url];
        }
    }

    return $results;
}

/** @param array<int, array<string, mixed>> $results */
function allHttp200(array $results, int $expectedCount): bool
{
    if (count($results) !== $expectedCount) {
        return false;
    }

    foreach ($results as $result) {
        if ((int) ($result['status'] ?? 0) !== 200 || ($result['error'] ?? null) !== null) {
            return false;
        }
    }

    return true;
}

$checks = [];
$context = [];

try {
    $laravelRoot = dirname(__DIR__);
    $autoloadPath = $laravelRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    $bootstrapPath = $laravelRoot . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';

    if (!is_file($autoloadPath) || !is_file($bootstrapPath)) {
        addCheck(
            $checks,
            'laravel.bootstrap_files',
            false,
            ['vendor/autoload.php' => true, 'bootstrap/app.php' => true],
            [
                'vendor/autoload.php' => is_file($autoloadPath),
                'bootstrap/app.php' => is_file($bootstrapPath),
            ],
            ['laravel_root' => $laravelRoot]
        );
        $bootstrapOutput = ob_get_clean();
        finish($checks, ['laravel_root' => $laravelRoot], $bootstrapOutput ?: null);
    }

    require $autoloadPath;
    $app = require $bootstrapPath;
    $app->make(Kernel::class)->bootstrap();

    $bootstrapOutput = ob_get_clean();
    $languageCode = trim((string) (getenv('BLOG_AUDIT_LANG_CODE') ?: DEFAULT_LANGUAGE_CODE));
    $baseUrl = rtrim(trim((string) (getenv('BLOG_AUDIT_BASE_URL') ?: config('app.url'))), '/');
    $timeout = (int) (getenv('BLOG_AUDIT_HTTP_TIMEOUT') ?: 20);
    $timeout = max(5, min(120, $timeout));
    $publicRoot = public_path();

    $context = [
        'laravel_root' => $laravelRoot,
        'public_root' => $publicRoot,
        'base_url' => $baseUrl,
        'language_code' => $languageCode,
        'http_timeout_seconds' => $timeout,
    ];

    $baseUrlValid = filter_var($baseUrl, FILTER_VALIDATE_URL) !== false
        && in_array(lower((string) parse_url($baseUrl, PHP_URL_SCHEME)), ['http', 'https'], true);
    addCheck(
        $checks,
        'configuration.public_base_url',
        $baseUrlValid,
        'absolute HTTP(S) URL',
        $baseUrl
    );

    $schema = DB::connection()->getSchemaBuilder();
    $requiredColumns = [
        'blogs' => [
            'id', 'slug', 'image', 'status', 'created_at',
        ],
        'blog_translations' => [
            'id', 'blog_id', 'lang_code', 'title', 'description',
        ],
    ];
    $schemaDetails = [];
    $schemaPassed = true;

    foreach ($requiredColumns as $table => $columns) {
        $exists = $schema->hasTable($table);
        $actualColumns = $exists ? $schema->getColumnListing($table) : [];
        $missingColumns = array_values(array_diff($columns, $actualColumns));
        $schemaDetails[$table] = [
            'exists' => $exists,
            'missing_columns' => $missingColumns,
        ];
        $schemaPassed = $schemaPassed && $exists && $missingColumns === [];
    }

    addCheck(
        $checks,
        'database.schema',
        $schemaPassed,
        $requiredColumns,
        $schemaDetails
    );

    if (!$schemaPassed) {
        finish($checks, $context, $bootstrapOutput ?: null);
    }

    // Every database operation below is a SELECT. The script never calls insert,
    // update, delete, migrations, cache writes, storage writes, or model save.
    $blogs = DB::table('blogs')
        ->select(['id', 'slug', 'image', 'status', 'created_at'])
        ->orderBy('id')
        ->get();
    $translations = DB::table('blog_translations')
        ->where('lang_code', $languageCode)
        ->select(['id', 'blog_id', 'lang_code', 'title', 'description'])
        ->orderBy('blog_id')
        ->get();
    $allTranslationCount = DB::table('blog_translations')->count();

    addCheck(
        $checks,
        'database.blog_count',
        $blogs->count() === EXPECTED_BLOG_COUNT,
        EXPECTED_BLOG_COUNT,
        $blogs->count()
    );
    addCheck(
        $checks,
        'database.target_translation_count',
        $translations->count() === EXPECTED_TRANSLATION_COUNT,
        EXPECTED_TRANSLATION_COUNT,
        $translations->count(),
        ['all_languages_count' => $allTranslationCount, 'target_language' => $languageCode]
    );

    $blogIds = $blogs->map(static fn ($blog): int => (int) $blog->id)->all();
    $translationBlogIds = $translations->map(static fn ($translation): int => (int) $translation->blog_id)->all();
    $translationCounts = array_count_values($translationBlogIds);
    $missingTranslationBlogIds = array_values(array_diff($blogIds, array_keys($translationCounts)));
    $duplicateTranslationBlogIds = [];
    foreach ($translationCounts as $blogId => $count) {
        if ($count !== 1) {
            $duplicateTranslationBlogIds[] = ['blog_id' => (int) $blogId, 'count' => $count];
        }
    }
    $orphanTranslationBlogIds = array_values(array_diff(array_keys($translationCounts), $blogIds));
    $oneToOne = $missingTranslationBlogIds === []
        && $duplicateTranslationBlogIds === []
        && $orphanTranslationBlogIds === [];

    addCheck(
        $checks,
        'database.target_translations_one_to_one',
        $oneToOne,
        'exactly one target translation for every blog',
        $oneToOne ? 'one-to-one' : 'mismatch',
        [
            'missing_blog_ids' => $missingTranslationBlogIds,
            'duplicate_blog_ids' => $duplicateTranslationBlogIds,
            'orphan_blog_ids' => $orphanTranslationBlogIds,
        ]
    );

    $slugMap = [];
    $emptySlugBlogIds = [];
    foreach ($blogs as $blog) {
        $slug = trim((string) $blog->slug);
        if ($slug === '') {
            $emptySlugBlogIds[] = (int) $blog->id;
            continue;
        }
        $slugMap[lower($slug)][] = (int) $blog->id;
    }
    $duplicateSlugs = [];
    foreach ($slugMap as $slug => $ids) {
        if (count($ids) > 1) {
            $duplicateSlugs[] = ['slug' => $slug, 'blog_ids' => $ids];
        }
    }
    $slugsUnique = $emptySlugBlogIds === [] && $duplicateSlugs === [];

    addCheck(
        $checks,
        'database.slugs_unique',
        $slugsUnique,
        '28 non-empty unique slugs (case-insensitive)',
        count($slugMap) . ' unique non-empty slugs',
        ['empty_blog_ids' => $emptySlugBlogIds, 'duplicates' => $duplicateSlugs]
    );

    $dates = [];
    $invalidDateBlogIds = [];
    foreach ($blogs as $blog) {
        $date = normalizedDate($blog->created_at);
        if ($date === null) {
            $invalidDateBlogIds[] = (int) $blog->id;
        } else {
            $dates[] = $date;
        }
    }
    sort($dates);
    $earliestDate = $dates[0] ?? null;
    $latestDate = $dates === [] ? null : $dates[count($dates) - 1];
    $dateRangePassed = $invalidDateBlogIds === []
        && $earliestDate === EXPECTED_EARLIEST_DATE
        && $latestDate === EXPECTED_LATEST_DATE;

    addCheck(
        $checks,
        'database.created_at_range',
        $dateRangePassed,
        ['earliest' => EXPECTED_EARLIEST_DATE, 'latest' => EXPECTED_LATEST_DATE],
        ['earliest' => $earliestDate, 'latest' => $latestDate],
        ['invalid_blog_ids' => $invalidDateBlogIds]
    );

    $unpublished = [];
    $statusDistribution = [];
    foreach ($blogs as $blog) {
        $statusKey = is_scalar($blog->status) || $blog->status === null
            ? var_export($blog->status, true)
            : get_debug_type($blog->status);
        $statusDistribution[$statusKey] = ($statusDistribution[$statusKey] ?? 0) + 1;
        if (!isPublishedStatus($blog->status)) {
            $unpublished[] = ['blog_id' => (int) $blog->id, 'slug' => (string) $blog->slug, 'status' => $blog->status];
        }
    }

    addCheck(
        $checks,
        'database.all_blogs_published',
        $unpublished === [],
        'all 28 statuses published/active/1',
        $statusDistribution,
        ['unpublished' => $unpublished]
    );

    $canonicalMatches = $blogs->filter(
        static fn ($blog): bool => lower(trim((string) $blog->slug)) === CANONICAL_HEALTH_SLUG
    )->count();
    $incorrectMatches = $blogs->filter(
        static fn ($blog): bool => lower(trim((string) $blog->slug)) === INCORRECT_HEALTH_SLUG
    )->count();

    addCheck(
        $checks,
        'database.canonical_health_article',
        $canonicalMatches === 1 && $incorrectMatches === 0,
        ['canonical_count' => 1, 'incorrect_alias_count' => 0],
        ['canonical_count' => $canonicalMatches, 'incorrect_alias_count' => $incorrectMatches]
    );

    $featuredAssets = [];
    $invalidFeaturedAssets = [];
    foreach ($blogs as $blog) {
        $inspection = inspectAssetReference(
            (string) $blog->image,
            $publicRoot,
            $baseUrl,
            true
        );
        $relativePath = (string) $inspection['relative_path'];
        $allowedDirectory = str_starts_with($relativePath, 'uploads/website-images/')
            || str_starts_with($relativePath, 'uploads/wordpress-blog/');
        $hasLegacyWordPressPath = stripos((string) $blog->image, 'wp-content/uploads') !== false;
        $valid = $inspection['error'] === null
            && $inspection['exists'] === true
            && $inspection['inside_public_root'] === true
            && $allowedDirectory
            && !$hasLegacyWordPressPath;

        $entry = [
            'blog_id' => (int) $blog->id,
            'slug' => (string) $blog->slug,
            'image' => (string) $blog->image,
            'relative_path' => $relativePath,
            'exists' => $inspection['exists'],
            'error' => $inspection['error'],
            'allowed_directory' => $allowedDirectory,
        ];
        $featuredAssets[] = $inspection + ['blog_id' => (int) $blog->id, 'slug' => (string) $blog->slug];
        if (!$valid) {
            $invalidFeaturedAssets[] = $entry;
        }
    }

    addCheck(
        $checks,
        'filesystem.featured_images',
        $invalidFeaturedAssets === [] && count($featuredAssets) === EXPECTED_BLOG_COUNT,
        '28 relative image paths under uploads/website-images or uploads/wordpress-blog, all existing',
        count($featuredAssets) . ' inspected, ' . count($invalidFeaturedAssets) . ' invalid',
        ['invalid' => $invalidFeaturedAssets]
    );

    $legacyImageRows = [];
    foreach ($blogs as $blog) {
        if (stripos((string) $blog->image, 'wp-content/uploads') !== false) {
            $legacyImageRows[] = ['blog_id' => (int) $blog->id, 'image' => (string) $blog->image];
        }
    }
    $legacyDescriptionRows = [];
    foreach ($translations as $translation) {
        if (stripos((string) $translation->description, 'wp-content/uploads') !== false) {
            $legacyDescriptionRows[] = ['translation_id' => (int) $translation->id, 'blog_id' => (int) $translation->blog_id];
        }
    }

    addCheck(
        $checks,
        'content.no_legacy_wp_upload_urls',
        $legacyImageRows === [] && $legacyDescriptionRows === [],
        'zero wp-content/uploads references in blogs.image and target descriptions',
        [
            'image_rows' => count($legacyImageRows),
            'description_rows' => count($legacyDescriptionRows),
        ],
        ['images' => $legacyImageRows, 'descriptions' => $legacyDescriptionRows]
    );

    $slugByBlogId = [];
    foreach ($blogs as $blog) {
        $slugByBlogId[(int) $blog->id] = (string) $blog->slug;
    }

    $inlineImageReferences = [];
    $mp3References = [];
    foreach ($translations as $translation) {
        $assets = extractDescriptionAssets((string) $translation->description);
        foreach ($assets['images'] as $source) {
            $inlineImageReferences[] = [
                'translation_id' => (int) $translation->id,
                'blog_id' => (int) $translation->blog_id,
                'slug' => $slugByBlogId[(int) $translation->blog_id] ?? null,
                'reference' => $source,
            ];
        }
        foreach ($assets['mp3s'] as $source) {
            $mp3References[] = [
                'translation_id' => (int) $translation->id,
                'blog_id' => (int) $translation->blog_id,
                'slug' => $slugByBlogId[(int) $translation->blog_id] ?? null,
                'reference' => $source,
            ];
        }
    }

    $inlineInspections = [];
    $invalidInlineAssets = [];
    foreach ($inlineImageReferences as $reference) {
        $inspection = inspectAssetReference((string) $reference['reference'], $publicRoot, $baseUrl);
        $relocated = str_starts_with((string) $inspection['relative_path'], 'uploads/wordpress-blog/');
        $valid = $inspection['error'] === null
            && $inspection['exists'] === true
            && $inspection['inside_public_root'] === true
            && $relocated;
        $entry = $reference + $inspection + ['relocated' => $relocated];
        $inlineInspections[] = $entry;
        if (!$valid) {
            $invalidInlineAssets[] = $entry;
        }
    }

    $uniqueInlinePaths = array_values(array_unique(array_map(
        static fn (array $entry): string => (string) $entry['relative_path'],
        $inlineInspections
    )));
    $inlinePassed = count($inlineImageReferences) === EXPECTED_INLINE_IMAGE_COUNT
        && count($uniqueInlinePaths) === EXPECTED_INLINE_IMAGE_COUNT
        && $invalidInlineAssets === [];

    addCheck(
        $checks,
        'content.inline_images_relocated',
        $inlinePassed,
        [
            'occurrences' => EXPECTED_INLINE_IMAGE_COUNT,
            'unique_files' => EXPECTED_INLINE_IMAGE_COUNT,
            'directory' => 'uploads/wordpress-blog/',
            'all_files_exist' => true,
        ],
        [
            'occurrences' => count($inlineImageReferences),
            'unique_files' => count($uniqueInlinePaths),
            'invalid_files' => count($invalidInlineAssets),
        ],
        ['assets' => $inlineInspections]
    );

    $mp3Inspections = [];
    $invalidMp3Assets = [];
    foreach ($mp3References as $reference) {
        $inspection = inspectAssetReference((string) $reference['reference'], $publicRoot, $baseUrl);
        $relocated = str_starts_with((string) $inspection['relative_path'], 'uploads/wordpress-blog/');
        $valid = $inspection['error'] === null
            && $inspection['exists'] === true
            && $inspection['inside_public_root'] === true
            && $relocated;
        $entry = $reference + $inspection + ['relocated' => $relocated];
        $mp3Inspections[] = $entry;
        if (!$valid) {
            $invalidMp3Assets[] = $entry;
        }
    }

    $uniqueMp3Paths = array_values(array_unique(array_map(
        static fn (array $entry): string => (string) $entry['relative_path'],
        $mp3Inspections
    )));
    $mp3Passed = count($mp3References) === EXPECTED_MP3_REFERENCE_COUNT
        && count($uniqueMp3Paths) === EXPECTED_MP3_UNIQUE_COUNT
        && $invalidMp3Assets === [];

    addCheck(
        $checks,
        'content.mp3_files_relocated',
        $mp3Passed,
        [
            'occurrences' => EXPECTED_MP3_REFERENCE_COUNT,
            'unique_files' => EXPECTED_MP3_UNIQUE_COUNT,
            'directory' => 'uploads/wordpress-blog/',
            'all_files_exist' => true,
        ],
        [
            'occurrences' => count($mp3References),
            'unique_files' => count($uniqueMp3Paths),
            'invalid_files' => count($invalidMp3Assets),
        ],
        ['assets' => $mp3Inspections]
    );

    if ($baseUrlValid) {
        $blogUrls = [];
        foreach ($blogs as $blog) {
            $slug = trim((string) $blog->slug);
            if ($slug !== '') {
                $blogUrls[] = $baseUrl . '/blog/' . rawurlencode($slug);
            }
        }
        $blogUrls = array_values(array_unique($blogUrls));
        // HEAD verifies the public route without intentionally registering a
        // page view in applications that count views only for GET requests.
        $blogHttpResults = httpChecks($blogUrls, 'HEAD', $timeout);

        addCheck(
            $checks,
            'http.public_blog_urls',
            allHttp200($blogHttpResults, EXPECTED_BLOG_COUNT),
            ['count' => EXPECTED_BLOG_COUNT, 'method' => 'HEAD', 'status' => 200],
            [
                'count' => count($blogHttpResults),
                'status_distribution' => array_count_values(array_map(
                    static fn (array $result): int => (int) $result['status'],
                    $blogHttpResults
                )),
            ],
            ['results' => $blogHttpResults]
        );

        $imageUrls = [];
        foreach ($featuredAssets as $asset) {
            if (($asset['error'] ?? null) === null) {
                $imageUrls[] = (string) $asset['public_url'];
            }
        }
        foreach ($inlineInspections as $asset) {
            if (($asset['error'] ?? null) === null) {
                $imageUrls[] = (string) $asset['public_url'];
            }
        }
        $imageUrls = array_values(array_unique($imageUrls));
        $imageHttpResults = retryUnsupportedHead(httpChecks($imageUrls, 'HEAD', $timeout), $timeout);

        addCheck(
            $checks,
            'http.public_images',
            allHttp200($imageHttpResults, count($imageUrls)) && $imageUrls !== [],
            ['all_discovered_image_urls_status' => 200],
            [
                'count' => count($imageHttpResults),
                'status_distribution' => array_count_values(array_map(
                    static fn (array $result): int => (int) $result['status'],
                    $imageHttpResults
                )),
            ],
            ['results' => $imageHttpResults]
        );

        $mp3Urls = array_values(array_unique(array_map(
            static fn (array $asset): string => (string) $asset['public_url'],
            array_filter($mp3Inspections, static fn (array $asset): bool => ($asset['error'] ?? null) === null)
        )));
        $mp3HttpResults = retryUnsupportedHead(httpChecks($mp3Urls, 'HEAD', $timeout), $timeout);

        addCheck(
            $checks,
            'http.public_mp3_files',
            allHttp200($mp3HttpResults, EXPECTED_MP3_UNIQUE_COUNT),
            ['count' => EXPECTED_MP3_UNIQUE_COUNT, 'status' => 200],
            [
                'count' => count($mp3HttpResults),
                'status_distribution' => array_count_values(array_map(
                    static fn (array $result): int => (int) $result['status'],
                    $mp3HttpResults
                )),
            ],
            ['results' => $mp3HttpResults]
        );
    } else {
        foreach (['http.public_blog_urls', 'http.public_images', 'http.public_mp3_files'] as $name) {
            addCheck($checks, $name, false, 'valid base URL before HTTP checks', 'skipped');
        }
    }

    finish($checks, $context, $bootstrapOutput ?: null);
} catch (Throwable $exception) {
    $bufferedOutput = ob_get_level() > 0 ? ob_get_clean() : '';
    addCheck(
        $checks,
        'runtime.completed_without_exception',
        false,
        'no exception',
        get_class($exception),
        ['message' => $exception->getMessage()]
    );
    finish($checks, $context, $bufferedOutput ?: null);
}
