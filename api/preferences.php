<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/lib/bootstrap.php';

Response::requireMethod('POST');

$input = Response::input();
$endpoint = trim((string) ($input['endpoint'] ?? ''));
$preferences = null;

if ($endpoint === '' || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
    Response::json(['ok' => false, 'error' => 'Endpoint invalide.'], 422);
}

if (array_key_exists('preferences', $input)) {
    try {
        $preferences = NotificationPreferences::normalizeInput($input['preferences']);
    } catch (InvalidArgumentException $error) {
        Response::json(['ok' => false, 'error' => $error->getMessage()], 422);
    }
}

try {
    $database = new Database(app_config());
    $repository = new SnapshotRepository($database);

    if ($preferences !== null) {
        $database->pdo()->beginTransaction();
        $effectivePreferences = $repository->savePreferencesForEndpoint($endpoint, $preferences);

        if ($effectivePreferences === null) {
            $database->pdo()->rollBack();
            Response::json(['ok' => false, 'error' => 'Abonnement introuvable.'], 404);
        }

        $database->pdo()->commit();
    } else {
        $effectivePreferences = $repository->preferencesForEndpoint($endpoint);
    }

    if ($effectivePreferences === null) {
        Response::json(['ok' => false, 'error' => 'Abonnement introuvable.'], 404);
    }

    Response::json(['ok' => true, 'preferences' => $effectivePreferences]);
} catch (Throwable) {
    if (isset($database) && $database->pdo()->inTransaction()) {
        $database->pdo()->rollBack();
    }

    Response::json([
        'ok' => false,
        'error' => 'Impossible de charger les preferences de notification.',
    ], 500);
}
