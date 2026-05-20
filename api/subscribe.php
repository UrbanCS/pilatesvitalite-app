<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/lib/bootstrap.php';

Response::requireMethod('POST');

$input = Response::input();
$endpoint = trim((string) ($input['endpoint'] ?? ''));
$keys = is_array($input['keys'] ?? null) ? $input['keys'] : [];
$p256dh = trim((string) ($keys['p256dh'] ?? $input['p256dh'] ?? ''));
$auth = trim((string) ($keys['auth'] ?? $input['auth'] ?? ''));
$userAgent = trim((string) ($input['userAgent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));

if ($endpoint === '' || !filter_var($endpoint, FILTER_VALIDATE_URL) || $p256dh === '' || $auth === '') {
    Response::json(['ok' => false, 'error' => 'Abonnement push invalide.'], 422);
}

try {
    $database = new Database(app_config());
    $database->execute(
        'INSERT INTO push_subscriptions
            (endpoint_hash, endpoint, p256dh, auth, user_agent, is_active, created_at, updated_at)
         VALUES
            (:endpoint_hash, :endpoint, :p256dh, :auth, :user_agent, 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            endpoint = VALUES(endpoint),
            p256dh = VALUES(p256dh),
            auth = VALUES(auth),
            user_agent = VALUES(user_agent),
            is_active = 1,
            updated_at = NOW()',
        [
            'endpoint_hash' => hash('sha256', $endpoint),
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
            'user_agent' => app_truncate($userAgent, 500),
        ]
    );

    Response::json(['ok' => true]);
} catch (Throwable $error) {
    Response::json(['ok' => false, 'error' => "Impossible de sauvegarder l'abonnement.", 'detail' => $error->getMessage()], 500);
}
