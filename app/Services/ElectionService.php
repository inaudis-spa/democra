<?php

declare(strict_types=1);

namespace InaudisVote\Services;

use InaudisVote\Core\Audit;
use InaudisVote\Core\Database;
use PDO;

final class ElectionService
{
    public static function allForUser(array $user): array
    {
        $pdo = Database::connection();
        if (($user['role'] ?? null) === 'admin') {
            $stmt = $pdo->query('SELECT e.*, o.name organization_name FROM elections e JOIN organizations o ON o.id=e.organization_id ORDER BY e.created_at DESC');
        } else {
            $stmt = $pdo->prepare('SELECT e.*, o.name organization_name FROM elections e JOIN organizations o ON o.id=e.organization_id WHERE e.organization_id=:organization_id ORDER BY e.created_at DESC');
            $stmt->execute(['organization_id' => (int) ($user['organization_id'] ?? 0)]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT e.*, o.name organization_name FROM elections e JOIN organizations o ON o.id=e.organization_id WHERE e.id=:id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(array $data, int $createdBy): int
    {
        $pdo = Database::connection();
        $title = trim((string) ($data['title'] ?? ''));
        $organizationId = (int) ($data['organization_id'] ?? 0);
        if ($title === '' || $organizationId <= 0) {
            throw new \InvalidArgumentException('Organización y título son obligatorios.');
        }

        $mode = in_array(($data['voting_mode'] ?? 'secret'), ['secret','public'], true) ? $data['voting_mode'] : 'secret';
        $stmt = $pdo->prepare('INSERT INTO elections (organization_id,title,description,voting_mode,status,starts_at,ends_at,allow_weighted_vote,created_by) VALUES (:organization_id,:title,:description,:voting_mode,:status,:starts_at,:ends_at,:weighted,:created_by)');
        $stmt->execute([
            'organization_id' => $organizationId,
            'title' => $title,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'voting_mode' => $mode,
            'status' => 'draft',
            'starts_at' => ($data['starts_at'] ?? '') ?: null,
            'ends_at' => ($data['ends_at'] ?? '') ?: null,
            'weighted' => !empty($data['allow_weighted_vote']) ? 1 : 0,
            'created_by' => $createdBy,
        ]);
        $id = (int) $pdo->lastInsertId();
        Audit::log('election.created', 'election', $id, ['title' => $title, 'mode' => $mode]);
        return $id;
    }

    public static function addQuestion(int $electionId, array $data): int
    {
        $pdo = Database::connection();
        $text = trim((string) ($data['text'] ?? ''));
        $options = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($data['options'] ?? '')) ?: [])));
        if ($text === '' || count($options) < 2) {
            throw new \InvalidArgumentException('La pregunta requiere texto y al menos dos alternativas.');
        }
        $type = ($data['question_type'] ?? 'single') === 'multiple' ? 'multiple' : 'single';
        $max = $type === 'multiple' ? max(1, (int) ($data['max_choices'] ?? 1)) : 1;
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO questions (election_id,text,question_type,min_choices,max_choices,sort_order) VALUES (:election_id,:text,:type,1,:max_choices,(SELECT COALESCE(MAX(q.sort_order),0)+1 FROM questions q WHERE q.election_id=:sort_election))');
            $stmt->execute(['election_id'=>$electionId,'text'=>$text,'type'=>$type,'max_choices'=>$max,'sort_election'=>$electionId]);
            $questionId = (int) $pdo->lastInsertId();
            $optionStmt = $pdo->prepare('INSERT INTO options (question_id,label,sort_order) VALUES (:question_id,:label,:sort_order)');
            foreach ($options as $i => $label) {
                $optionStmt->execute(['question_id'=>$questionId,'label'=>$label,'sort_order'=>$i+1]);
            }
            $pdo->commit();
            Audit::log('question.created', 'question', $questionId, ['election_id'=>$electionId]);
            return $questionId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function questions(int $electionId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM questions WHERE election_id=:id ORDER BY sort_order,id');
        $stmt->execute(['id'=>$electionId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $opt = $pdo->prepare('SELECT id,label,sort_order FROM options WHERE question_id=:id ORDER BY sort_order,id');
        foreach ($questions as &$q) {
            $opt->execute(['id'=>$q['id']]);
            $q['options'] = $opt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $questions;
    }

    public static function setStatus(int $electionId, string $status): void
    {
        if (!in_array($status, ['draft','scheduled','open','closed','cancelled'], true)) {
            throw new \InvalidArgumentException('Estado inválido.');
        }
        $stmt = Database::connection()->prepare('UPDATE elections SET status=:status WHERE id=:id');
        $stmt->execute(['status'=>$status,'id'=>$electionId]);
        Audit::log('election.status_changed', 'election', $electionId, ['status'=>$status]);
    }
}
