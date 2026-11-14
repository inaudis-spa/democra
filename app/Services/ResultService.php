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
        $status=$pdo->prepare('SELECT status FROM elections WHERE id=:id');$status->execute(['id'=>$electionId]);
        if($status->fetchColumn()!=='closed')throw new \RuntimeException('Los resultados solo pueden certificarse después de cerrar la votación.');
        $summary=self::summary($electionId);
        $legacyCanonical=json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);
        $canonical=self::canonicalJson($summary);
        if ($legacyCanonical===false) throw new \RuntimeException('No fue posible serializar los resultados.');
        $hash=hash('sha256',$canonical);
        $existing=$pdo->prepare('SELECT results_json,sha256_hash FROM result_certificates WHERE election_id=:id');$existing->execute(['id'=>$electionId]);$certificate=$existing->fetch(PDO::FETCH_ASSOC);
        if($certificate){
            $storedJson=(string)$certificate['results_json'];
            $storedHash=(string)$certificate['sha256_hash'];
            // MySQL normalizes JSON storage (spacing and representation), so verify
            // the same canonical encoding that was hashed when the certificate was created.
            $storedResults=json_decode($storedJson,true,512,JSON_THROW_ON_ERROR);
            $storedCanonical=self::canonicalJson($storedResults);
            $canonicalMatch=hash_equals($canonical,$storedCanonical);
            $currentHashMatch=hash_equals($storedHash,$hash);
            $legacyHashMatch=$canonicalMatch&&hash_equals($storedHash,hash('sha256',$legacyCanonical));
            if(!$canonicalMatch||(!$currentHashMatch&&!$legacyHashMatch)){
                throw new \RuntimeException('La certificación almacenada no supera la verificación de integridad.');
            }
            return ['sha256'=>$storedHash,'results'=>$storedResults];
        }
        $stmt=$pdo->prepare('INSERT INTO result_certificates (election_id,results_json,sha256_hash,generated_at,generated_by) VALUES (:election_id,:results_json,:hash,NOW(),:generated_by)');
        $stmt->execute(['election_id'=>$electionId,'results_json'=>$canonical,'hash'=>$hash,'generated_by'=>$userId]);
        Audit::log('results.certified','election',$electionId,['sha256'=>$hash],$userId);
        return ['sha256'=>$hash,'results'=>$summary];
    }

    private static function canonicalJson(array $value): string
    {
        $sort=function(array $items) use (&$sort): array {
            foreach($items as $key=>$item)if(is_array($item))$items[$key]=$sort($item);
            if(!array_is_list($items))ksort($items,SORT_STRING);
            return $items;
        };
        $json=json_encode($sort($value),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);
        if($json===false)throw new \RuntimeException('No fue posible serializar los resultados.');
        return $json;
    }
}
