<?php

declare(strict_types=1);

final class Response
{
    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function requireMethod(string $method): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
            self::json(['ok' => false, 'error' => 'Method not allowed'], 405);
        }
    }

    public static function input(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true);

        return is_array($payload) ? $payload : [];
    }
}

