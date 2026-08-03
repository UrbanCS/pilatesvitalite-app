<?php

declare(strict_types=1);

final class NotificationPreferences
{
    private const DEFAULTS = [
        'site_updates' => true,
        'blog' => true,
        'offers' => false,
        'newsletter' => false,
    ];

    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    public static function isKnownCategory(string $category): bool
    {
        return array_key_exists($category, self::DEFAULTS);
    }

    public static function defaultFor(string $category): bool
    {
        if (!self::isKnownCategory($category)) {
            throw new InvalidArgumentException('Categorie de notification inconnue.');
        }

        return self::DEFAULTS[$category];
    }

    public static function normalizeInput(mixed $input): array
    {
        if (!is_array($input)) {
            throw new InvalidArgumentException('Les preferences de notification doivent etre un objet JSON.');
        }

        $normalized = [];
        foreach ($input as $category => $value) {
            if (!is_string($category) || !self::isKnownCategory($category)) {
                throw new InvalidArgumentException('Categorie de notification inconnue.');
            }

            if (is_bool($value)) {
                $normalized[$category] = $value;
                continue;
            }

            if (is_int($value) && ($value === 0 || $value === 1)) {
                $normalized[$category] = $value === 1;
                continue;
            }

            throw new InvalidArgumentException('Chaque preference de notification doit etre un booleen.');
        }

        return $normalized;
    }

    public static function effective(array $rows): array
    {
        $preferences = self::DEFAULTS;

        foreach ($rows as $row) {
            $category = (string) ($row['category'] ?? '');
            if (self::isKnownCategory($category)) {
                $preferences[$category] = (bool) ($row['is_enabled'] ?? false);
            }
        }

        return $preferences;
    }
}
