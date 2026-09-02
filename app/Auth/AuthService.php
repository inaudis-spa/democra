<?php

declare(strict_types=1);

namespace InaudisVote\Auth;

use InaudisVote\Core\Database;

final class AuthService
{
    public static function attempt(string $email, string $password): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, organization_id, name, email, password_hash, role, status
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => mb_strtolower(trim($email))]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active') {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'organization_id' => $user['organization_id'] !== null ? (int) $user['organization_id'] : null,
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];

        $audit = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, event_type, entity_type, entity_id, metadata, ip_address, created_at)
             VALUES (:user_id, :event_type, :entity_type, NULL, :metadata, :ip_address, NOW())'
        );
        $audit->execute([
            'user_id' => (int) $user['id'],
            'event_type' => 'auth.login_success',
            'entity_type' => 'user',
            'metadata' => json_encode(['email' => $user['email']], JSON_UNESCAPED_UNICODE),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        return true;
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        session_destroy();
    }
}
