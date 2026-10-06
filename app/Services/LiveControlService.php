<?php
declare(strict_types=1);
namespace InaudisVote\Services;
use InaudisVote\Core\Audit;
use InaudisVote\Core\Database;
use PDO;
final class LiveControlService
{
    public static function snapshot(int $electionId): array
    {
        $pdo=Database::connection();
        $stats=$pdo->prepare("SELECT COUNT(*) total,SUM(status='eligible') eligible,SUM(has_voted=1) voted FROM census_members WHERE election_id=:id");
        $stats->execute(['id'=>$electionId]);$s=$stats->fetch(PDO::FETCH_ASSOC)?:[];
        $tokens=$pdo->prepare("SELECT SUM(verified_at IS NOT NULL AND consumed_at IS NULL) authenticated,SUM(consumed_at IS NULL AND otp_expires_at>NOW()) active_sessions FROM voter_tokens WHERE election_id=:id");
        $tokens->execute(['id'=>$electionId]);$t=$tokens->fetch(PDO::FETCH_ASSOC)?:[];
        $state=$pdo->prepare("SELECT * FROM election_live_state WHERE election_id=:id");$state->execute(['id'=>$electionId]);$live=$state->fetch(PDO::FETCH_ASSOC)?:[];
        $eligible=(int)($s['eligible']??0);$voted=(int)($s['voted']??0);
        return ['eligible'=>$eligible,'voted'=>$voted,'pending'=>max(0,$eligible-$voted),'participation'=>$eligible>0?round($voted*100/$eligible,1):0,'authenticated'=>(int)($t['authenticated']??0),'active_sessions'=>(int)($t['active_sessions']??0),'current_question_id'=>(int)($live['current_question_id']??0),'display_results'=>(bool)($live['display_results']??false),'updated_at'=>$live['updated_at']??null];
    }
    public static function setCurrentQuestion(int $electionId,?int $questionId,int $userId): void
    {
        $pdo=Database::connection();
        if($questionId!==null){$q=$pdo->prepare('SELECT COUNT(*) FROM questions WHERE id=:qid AND election_id=:eid');$q->execute(['qid'=>$questionId,'eid'=>$electionId]);if(!(int)$q->fetchColumn())throw new \InvalidArgumentException('La pregunta no pertenece a esta votación.');}
        $stmt=$pdo->prepare('INSERT INTO election_live_state (election_id,current_question_id,display_results,updated_by) VALUES (:eid,:qid,0,:uid) ON DUPLICATE KEY UPDATE current_question_id=VALUES(current_question_id),display_results=0,updated_by=VALUES(updated_by),updated_at=NOW()');
        $stmt->execute(['eid'=>$electionId,'qid'=>$questionId,'uid'=>$userId]);Audit::log('live.question_changed','election',$electionId,['question_id'=>$questionId],$userId);
    }
    public static function setDisplayResults(int $electionId,bool $show,int $userId): void
    {
        $pdo=Database::connection();$stmt=$pdo->prepare('INSERT INTO election_live_state (election_id,display_results,updated_by) VALUES (:eid,:show,:uid) ON DUPLICATE KEY UPDATE display_results=VALUES(display_results),updated_by=VALUES(updated_by),updated_at=NOW()');$stmt->execute(['eid'=>$electionId,'show'=>$show?1:0,'uid'=>$userId]);Audit::log('live.results_visibility_changed','election',$electionId,['visible'=>$show],$userId);
    }
    public static function recentActivity(int $electionId,int $limit=8): array
    {
        $limit=max(1,min(20,$limit));$stmt=Database::connection()->prepare("SELECT event_type,created_at FROM audit_logs WHERE entity_type IN ('election','census_member') AND (entity_id=:id OR JSON_EXTRACT(metadata,'$.election_id')=:jsonid) ORDER BY id DESC LIMIT {$limit}");$stmt->execute(['id'=>$electionId,'jsonid'=>$electionId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
