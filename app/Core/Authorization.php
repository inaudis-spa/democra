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

    public static function canReviewElection(array $user, int $electionId, int $organizationId): bool
    {
        if (in_array(($user['role'] ?? null), ['admin','organizer'], true)) {
            return self::canManageElection($user, $organizationId, $electionId);
        }
        if (($user['role'] ?? null) !== 'witness') return false;
        $stmt=Database::connection()->prepare('SELECT COUNT(*) FROM election_witnesses WHERE election_id=:eid AND user_id=:uid');
        $stmt->execute(['eid'=>$electionId,'uid'=>(int)($user['id']??0)]);
        return (bool)$stmt->fetchColumn();
    }

    public static function canManageElection(array $user, int $organizationId, int $electionId=0): bool
    {
        if (($user['role'] ?? null) === 'admin') {
            return true;
        }
        if (($user['role'] ?? null) !== 'organizer' || (int) ($user['organization_id'] ?? 0) !== $organizationId) {
            return false;
        }
        if($electionId<=0)$electionId=(int)($_GET['election_id']??$_GET['id']??0);
        if($electionId<=0)return false;
        $stmt=Database::connection()->prepare('SELECT COUNT(*) FROM election_managers WHERE election_id=:eid AND user_id=:uid');
        $stmt->execute(['eid'=>$electionId,'uid'=>(int)($user['id']??0)]);
        return (bool)$stmt->fetchColumn();
    }
}
