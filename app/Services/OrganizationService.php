<?php

declare(strict_types=1);

namespace InaudisVote\Services;

use InaudisVote\Core\Audit;
use InaudisVote\Core\Database;
use PDO;

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

        $stmt = $pdo->prepare(
            'INSERT INTO organizations (name, tax_id, slug, status) VALUES (:name, :tax_id, :slug, :status)'
        );
        $stmt->execute([
            'name' => $name,
            'tax_id' => trim((string) ($data['tax_id'] ?? '')) ?: null,
            'slug' => strtolower(preg_replace('/[^a-z0-9-]+/i', '-', $slug) ?? $slug),
            'status' => in_array(($data['status'] ?? 'active'), ['active', 'inactive'], true) ? $data['status'] : 'active',
        ]);

        $id = (int) $pdo->lastInsertId();
        Audit::log('organization.created', 'organization', $id, ['name' => $name]);
        return $id;
    }
}
