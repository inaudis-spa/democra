<?php

declare(strict_types=1);

namespace InaudisVote\Services;

use InaudisVote\Core\Audit;
use InaudisVote\Core\Database;
use PDO;

final class CensusService
{
    public static function list(int $electionId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM census_members WHERE election_id=:id ORDER BY name');
        $stmt->execute(['id'=>$electionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function add(int $electionId, array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Nombre y email válido son obligatorios.');
        }
        $stmt = Database::connection()->prepare('INSERT INTO census_members (election_id,rut,name,email,phone,vote_weight,segment,status) VALUES (:election_id,:rut,:name,:email,:phone,:weight,:segment,:status)');
        $stmt->execute([
            'election_id'=>$electionId,
            'rut'=>trim((string)($data['rut'] ?? '')) ?: null,
            'name'=>$name,
            'email'=>$email,
            'phone'=>trim((string)($data['phone'] ?? '')) ?: null,
            'weight'=>max(0.0001,(float)($data['vote_weight'] ?? 1)),
            'segment'=>trim((string)($data['segment'] ?? '')) ?: null,
            'status'=>'eligible',
        ]);
        $id=(int)Database::connection()->lastInsertId();
        Audit::log('census.member_added','census_member',$id,['election_id'=>$electionId,'email'=>$email]);
        return $id;
    }

    public static function importCsv(int $electionId, string $csvContent): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent)) ?: [];
        $created = 0; $errors = [];
        foreach ($lines as $index => $line) {
            if (trim($line) === '') continue;
            $row = str_getcsv($line, ';');
            if (count($row) < 3) $row = str_getcsv($line, ',');
            if ($index === 0 && isset($row[0]) && preg_match('/rut|nombre|name/i', implode(' ', $row))) continue;
            try {
                self::add($electionId, [
                    'rut'=>$row[0] ?? null,
                    'name'=>$row[1] ?? '',
                    'email'=>$row[2] ?? '',
                    'phone'=>$row[3] ?? null,
                    'vote_weight'=>$row[4] ?? 1,
                    'segment'=>$row[5] ?? null,
                ]);
                $created++;
            } catch (\Throwable $e) {
                $errors[]='Fila '.($index+1).': '.$e->getMessage();
            }
        }
        Audit::log('census.csv_imported','election',$electionId,['created'=>$created,'errors'=>count($errors)]);
        return ['created'=>$created,'errors'=>$errors];
    }

    public static function generateInvitation(int $memberId): string
    {
        $pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT id,election_id,has_voted,status FROM census_members WHERE id=:id');
        $stmt->execute(['id'=>$memberId]);
        $member=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$member || $member['status']!=='eligible' || (int)$member['has_voted']===1) {
            throw new \RuntimeException('Votante no habilitado.');
        }
        $raw=bin2hex(random_bytes(32));
        $hash=hash('sha256',$raw);
        $pdo->prepare('DELETE FROM voter_tokens WHERE census_member_id=:id AND consumed_at IS NULL')->execute(['id'=>$memberId]);
        $pdo->prepare('INSERT INTO voter_tokens (election_id,census_member_id,token_hash,created_at) VALUES (:election_id,:member_id,:token_hash,NOW())')->execute([
            'election_id'=>(int)$member['election_id'],'member_id'=>$memberId,'token_hash'=>$hash
        ]);
        Audit::log('voter.invitation_generated','census_member',$memberId,['election_id'=>(int)$member['election_id']]);
        return $raw;
    }
}
