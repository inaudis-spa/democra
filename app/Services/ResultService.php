<?php

declare(strict_types=1);

namespace InaudisVote\Services;

use InaudisVote\Core\Audit;
use InaudisVote\Core\Database;
use PDO;

final class ResultService
{
    public static function summary(int $electionId): array
    {
        $pdo=Database::connection();
        $qStmt=$pdo->prepare('SELECT id,text,question_type FROM questions WHERE election_id=:id ORDER BY sort_order,id');
        $qStmt->execute(['id'=>$electionId]);
        $questions=$qStmt->fetchAll(PDO::FETCH_ASSOC);
        $result=[];
        $optStmt=$pdo->prepare(
            'SELECT o.id,o.label,COALESCE(SUM(b.weight),0) weighted_votes,COUNT(ba.id) votes
             FROM options o
             LEFT JOIN ballot_answers ba ON ba.option_id=o.id
             LEFT JOIN ballots b ON b.id=ba.ballot_id
             WHERE o.question_id=:qid
             GROUP BY o.id,o.label,o.sort_order
             ORDER BY o.sort_order,o.id'
        );
        foreach ($questions as $q) {
            $optStmt->execute(['qid'=>$q['id']]);
            $result[]=['id'=>(int)$q['id'],'text'=>$q['text'],'question_type'=>$q['question_type'],'options'=>$optStmt->fetchAll(PDO::FETCH_ASSOC)];
        }
        $stats=$pdo->prepare('SELECT COUNT(*) total, SUM(has_voted=1) voted FROM census_members WHERE election_id=:id');
        $stats->execute(['id'=>$electionId]);
        $participation=$stats->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'voted'=>0];
        return ['election_id'=>$electionId,'participation'=>$participation,'questions'=>$result];
    }

    public static function certify(int $electionId, int $userId): array
    {
        $pdo=Database::connection();
        $summary=self::summary($electionId);
        $canonical=json_encode($summary, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);
        if ($canonical===false) throw new \RuntimeException('No fue posible serializar los resultados.');
        $hash=hash('sha256',$canonical);
        $stmt=$pdo->prepare(
            'INSERT INTO result_certificates (election_id,results_json,sha256_hash,generated_at,generated_by)
             VALUES (:election_id,:results_json,:hash,NOW(),:generated_by)
             ON DUPLICATE KEY UPDATE results_json=VALUES(results_json),sha256_hash=VALUES(sha256_hash),generated_at=NOW(),generated_by=VALUES(generated_by)'
        );
        $stmt->execute(['election_id'=>$electionId,'results_json'=>$canonical,'hash'=>$hash,'generated_by'=>$userId]);
        Audit::log('results.certified','election',$electionId,['sha256'=>$hash],$userId);
        return ['sha256'=>$hash,'results'=>$summary];
    }
}
