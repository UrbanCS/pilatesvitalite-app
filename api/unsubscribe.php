<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/lib/bootstrap.php';

Response::requireMethod('POST');

$input = Response::input();
$endpoint = trim((string) ($input['endpoint'] ?? ''));

if ($endpoint === '' || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
    Response::json(['ok' => false, 'error' => 'Endpoint invalide.'], 422);
}

try {
    $database = new Database(app_config());
    $database->execute(
        'UPDATE push_subscriptions SET is_active = 0, updated_at = NOW() WHERE endpoint_hash = :endpoint_hash',
        ['endpoint_hash' => hash('sha256', $endpoint)]
    );

    Response::json(['ok' => true]);
} catch (Throwable $error) {
    Response::json(['ok' => false, 'error' => "Impossible de désactiver l'abonnement.", 'detail' => $error->getMessage()], 500);
}
