<?php
declare(strict_types=1);
namespace InaudisVote\Services;

use InaudisVote\Core\Audit;
use InaudisVote\Core\Database;
use PDO;

final class SequentialQuestionService
{
    public static function change(int $electionId, int $questionId, string $action, int $userId): void
    {
        if (!in_array($action, ['open', 'close'], true)) throw new \InvalidArgumentException('Acción inválida.');
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT status,question_flow FROM elections WHERE id=:id FOR UPDATE');
            $stmt->execute(['id'=>$electionId]);
            $election = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$election || $election['question_flow'] !== 'sequential' || $election['status'] !== 'open') {
                throw new \RuntimeException('La votación secuencial no está abierta.');
            }
            $stmt = $pdo->prepare('SELECT id,voting_status,sort_order FROM questions WHERE id=:qid AND election_id=:eid');
            $stmt->execute(['qid'=>$questionId,'eid'=>$electionId]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$question) throw new \RuntimeException('Pregunta no encontrada.');
            if ($action === 'open') {
                if ($question['voting_status'] !== 'pending') throw new \RuntimeException('La pregunta ya fue habilitada.');
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE election_id=:eid AND voting_status='open'");
                $stmt->execute(['eid'=>$electionId]);
                if ((int)$stmt->fetchColumn() > 0) throw new \RuntimeException('Cierra primero la pregunta activa.');
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE election_id=:eid AND (sort_order<:ord OR (sort_order=:ord2 AND id<:qid)) AND voting_status<>'closed'");
                $stmt->execute(['eid'=>$electionId,'ord'=>$question['sort_order'],'ord2'=>$question['sort_order'],'qid'=>$questionId]);
                if ((int)$stmt->fetchColumn() > 0) throw new \RuntimeException('Debes seguir el orden de las preguntas.');
                $stmt = $pdo->prepare("UPDATE questions SET voting_status='open',opened_at=NOW() WHERE id=:qid");
                $stmt->execute(['qid'=>$questionId]);
                $stmt = $pdo->prepare('INSERT INTO election_live_state(election_id,current_question_id,display_results,updated_by) VALUES(:eid,:qid,0,:uid) ON DUPLICATE KEY UPDATE current_question_id=VALUES(current_question_id),display_results=0,updated_by=VALUES(updated_by),updated_at=NOW()');
                $stmt->execute(['eid'=>$electionId,'qid'=>$questionId,'uid'=>$userId]);
            } else {
                if ($question['voting_status'] !== 'open') throw new \RuntimeException('La pregunta no está abierta.');
                $stmt = $pdo->prepare("UPDATE questions SET voting_status='closed',closed_at=NOW() WHERE id=:qid");
                $stmt->execute(['qid'=>$questionId]);
                $stmt = $pdo->prepare('UPDATE election_live_state SET display_results=1,updated_by=:uid WHERE election_id=:eid');
                $stmt->execute(['eid'=>$electionId,'uid'=>$userId]);
            }
            Audit::log($action==='open'?'question.opened':'question.closed','election',$electionId,['question_id'=>$questionId],$userId);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function participation(int $questionId): int
    {
        $stmt=Database::connection()->prepare('SELECT COUNT(*) FROM question_participations WHERE question_id=:id');
        $stmt->execute(['id'=>$questionId]);
        return (int)$stmt->fetchColumn();
    }
}
