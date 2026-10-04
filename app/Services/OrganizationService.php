<?php

declare(strict_types=1);

namespace InaudisVote\Services;

use InaudisVote\Core\Audit;
use InaudisVote\Core\Database;
use PDO;
use PDOException;

final class OrganizationService
{
    public static function all(): array
    {
        return Database::connection()->query(
            'SELECT id, name, tax_id, slug, status, created_at FROM organizations ORDER BY created_at DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $name = trim((string) ($data['name'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));

        if ($name === '' || $slug === '') {
            throw new \InvalidArgumentException('Nombre y slug son obligatorios.');
        }

        $asciiSlug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug) ?: $slug;
        $normalizedSlug = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $asciiSlug) ?? ''), '-');
        if ($normalizedSlug === '') {
            throw new \InvalidArgumentException('El identificador web debe contener letras o números.');
        }
        if (mb_strlen($name) > 150 || strlen($normalizedSlug) > 120) {
            throw new \InvalidArgumentException('El nombre o identificador web es demasiado largo.');
        }
        $status = in_array(($data['status'] ?? 'active'), ['active', 'inactive'], true)
            ? (string) ($data['status'] ?? 'active')
            : 'active';
        $stmt = $pdo->prepare(
            'INSERT INTO organizations (name, tax_id, slug, status) VALUES (:name, :tax_id, :slug, :status)'
        );
        try {
            $stmt->execute([
                'name' => $name,
                'tax_id' => trim((string) ($data['tax_id'] ?? '')) ?: null,
                'slug' => $normalizedSlug,
                'status' => $status,
            ]);
        } catch (PDOException $exception) {
            if ((string)$exception->getCode() === '23000') {
                throw new \InvalidArgumentException('El identificador web ya está siendo utilizado por otra organización.');
            }
            throw $exception;
        }

        $id = (int) $pdo->lastInsertId();
        Audit::log('organization.created', 'organization', $id, ['name' => $name]);
        return $id;
    }
}
