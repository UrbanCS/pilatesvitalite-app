<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/lib/bootstrap.php';

$config = app_config();
$nextCourseCache = new NextCourseCache();

try {
    $http = new HttpClient((int) app_config('HTTP_TIMEOUT', 15));
    $scraper = new Scraper($config, $http);
    $latest = $scraper->latest();
    $latest['nextCourse'] = $nextCourseCache->read();
    $latest['ok'] = true;
    $latest['source'] = 'live';

    Response::json($latest);
} catch (Throwable $liveError) {
    try {
        $repository = new SnapshotRepository(new Database($config));
        $fallback = $repository->latestDataFromSnapshots($config);

        if ($fallback !== null && $fallback['home'] !== null && $fallback['about'] !== null) {
            $fallback['nextCourse'] = $nextCourseCache->read();
            $fallback['ok'] = true;
            $fallback['source'] = 'database';
            Response::json($fallback);
        }
    } catch (Throwable) {
        // The live error below is more useful for the caller.
    }

    Response::json([
        'ok' => false,
        'error' => 'Impossible de charger les contenus publics du site.',
        'detail' => $liveError->getMessage(),
    ], 502);
}
