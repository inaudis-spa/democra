<?php

declare(strict_types=1);

namespace InaudisVote\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['_csrf'];
    }

    public static function validate(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['_csrf'])
            && hash_equals((string) $_SESSION['_csrf'], $token);
    }

    public static function input(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
