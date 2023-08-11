<?php

declare(strict_types=1);

namespace App\app\Services\EmailValidation;

/**
 * @see https://help.debounce.io/kb/understanding-results/result-codes
 */
enum ResultCodeEnum: int
{
    case SYNTAX = 1; // Not a valid email string

    case SPAM_TRAP = 2;

    case DISPOSABLE = 3;

    case ACCEPT_ALL = 4; // Catch-all domain

    case DELIVERABLE = 5;

    case INVALID = 6; // Verified Bounce

    case UNKNOWN = 7; // Unreachable server

    case ROLE = 8;

    public static function enumToValues(array $enums): array
    {
        return array_map(fn ($case) => $case->value, $enums);
    }

    public static function defaults(): array
    {
        return self::enumToValues([self::DELIVERABLE, self::ACCEPT_ALL, self::UNKNOWN, self::ROLE]);
    }

    public static function subscriber(): array
    {
        return self::enumToValues([self::DELIVERABLE, self::ACCEPT_ALL]);
    }

    public static function participant(): array
    {
        return self::enumToValues([self::DELIVERABLE, self::ACCEPT_ALL, self::UNKNOWN]);
    }

    public static function parent(): array
    {
        return self::enumToValues([self::DELIVERABLE, self::ACCEPT_ALL]);
    }

    public static function customer(): array
    {
        return self::enumToValues([self::DELIVERABLE, self::ACCEPT_ALL, self::UNKNOWN]);
    }
}
