<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/lib/bootstrap.php';

Response::requireMethod('POST');

$input = Response::input();
$headers = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
$secret = (string) ($input['secret'] ?? $_GET['secret'] ?? $headers['x-notify-test-secret'] ?? '');

if ($secret === '' || !hash_equals((string) app_config('NOTIFY_TEST_SECRET', ''), $secret)) {
    Response::json(['ok' => false, 'error' => 'Secret invalide.'], 403);
}

$title = app_truncate((string) ($input['title'] ?? 'Pilates Vitalité'), 120);
$message = app_truncate((string) ($input['message'] ?? 'Notification test envoyée avec succès.'), 240);
$url = (string) ($input['url'] ?? app_config('APP_BASE_URL', './'));

try {
    $database = new Database(app_config());
    $repository = new SnapshotRepository($database);
    $push = new PushService(app_config(), $repository);
    $result = $push->send($title, $message, $url, 'test');

    Response::json(['ok' => true, 'result' => $result]);
} catch (Throwable $error) {
    Response::json(['ok' => false, 'error' => 'Impossible d envoyer la notification test.', 'detail' => $error->getMessage()], 500);
}
