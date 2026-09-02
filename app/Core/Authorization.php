<?php

declare(strict_types=1);

namespace InaudisVote\Core;

use InaudisVote\Auth\AuthService;

final class Authorization
{
    public static function requireLogin(): array
    {
        if (!AuthService::check()) {
            header('Location: /login.php');
            exit;
        }

        return AuthService::user() ?? [];
    }

    public static function requireRoles(array $roles): array
    {
        $user = self::requireLogin();
        if (!in_array($user['role'] ?? null, $roles, true)) {
            http_response_code(403);
            exit('Acceso no autorizado.');
        }

        return $user;
    }

    public static function canManageElection(array $user, int $organizationId): bool
    {
        if (($user['role'] ?? null) === 'admin') {
            return true;
        }

        return ($user['role'] ?? null) === 'organizer'
            && (int) ($user['organization_id'] ?? 0) === $organizationId;
    }
}
