<?php

declare(strict_types=1);

namespace InaudisVote\Services;

use InaudisVote\Core\Audit;
use InaudisVote\Core\Database;
use PDO;

final class ResultService
{
    private static function assertIntegrity(int $electionId): void
    {
        $pdo=Database::connection();
        $election=$pdo->prepare('SELECT question_flow FROM elections WHERE id=:id');$election->execute(['id'=>$electionId]);
        $flow=$election->fetchColumn();if($flow===false)throw new \RuntimeException('Votación no encontrada.');
        $crossed=$pdo->prepare('SELECT COUNT(*) FROM ballot_answers ba JOIN ballots b ON b.id=ba.ballot_id JOIN questions q ON q.id=ba.question_id JOIN options o ON o.id=ba.option_id WHERE b.election_id=:id AND (q.election_id<>b.election_id OR o.question_id<>q.id)');
        $crossed->execute(['id'=>$electionId]);if((int)$crossed->fetchColumn()>0)throw new \RuntimeException('La certificación fue bloqueada: existen respuestas cruzadas entre preguntas o procesos.');
        $negative=$pdo->prepare('SELECT COUNT(*) FROM ballots WHERE election_id=:id AND (weight<=0 OR weight>99999999.9999)');$negative->execute(['id'=>$electionId]);
        if((int)$negative->fetchColumn()>0)throw new \RuntimeException('La certificación fue bloqueada: existen pesos fuera de rango.');
        if($flow==='traditional'){
            $counts=$pdo->prepare("SELECT (SELECT COUNT(*) FROM ballots WHERE election_id=:id1) ballots,(SELECT COUNT(*) FROM census_members WHERE election_id=:id2 AND has_voted=1) voted,(SELECT COUNT(*) FROM census_members WHERE election_id=:id3 AND status='eligible') eligible,(SELECT COUNT(*) FROM election_participations WHERE election_id=:id4) participations");
            $counts->execute(['id1'=>$electionId,'id2'=>$electionId,'id3'=>$electionId,'id4'=>$electionId]);$c=$counts->fetch(PDO::FETCH_ASSOC);
            if((int)$c['ballots']!==(int)$c['voted']||(int)$c['ballots']>(int)$c['eligible'])throw new \RuntimeException('La certificación fue bloqueada: participación y papeletas no coinciden.');
            if((int)$c['participations']!==(int)$c['ballots'])throw new \RuntimeException('La certificación fue bloqueada: el registro único de participación no coincide.');
            $weights=$pdo->prepare("SELECT COALESCE(SUM(weight),0) ballots_weight, (SELECT COALESCE(SUM(vote_weight),0) FROM census_members WHERE election_id=:id1 AND status='eligible') eligible_weight FROM ballots WHERE election_id=:id2");
            $weights->execute(['id1'=>$electionId,'id2'=>$electionId]);$w=$weights->fetch(PDO::FETCH_ASSOC);
            if((float)$w['ballots_weight']>(float)$w['eligible_weight'])throw new \RuntimeException('La certificación fue bloqueada: el peso total de las papeletas supera el padrón habilitado.');
            $invalidChoices=$pdo->prepare('SELECT COUNT(*) FROM (SELECT b.id,q.id question_id,q.min_choices,q.max_choices,COUNT(DISTINCT ba.option_id) choices FROM ballots b JOIN questions q ON q.election_id=b.election_id LEFT JOIN ballot_answers ba ON ba.ballot_id=b.id AND ba.question_id=q.id WHERE b.election_id=:id GROUP BY b.id,q.id,q.min_choices,q.max_choices HAVING choices<q.min_choices OR choices>q.max_choices) anomalies');
            $invalidChoices->execute(['id'=>$electionId]);if((int)$invalidChoices->fetchColumn()>0)throw new \RuntimeException('La certificación fue bloqueada: una papeleta incumple mínimos o máximos.');
        }else{
            $mismatch=$pdo->prepare('SELECT COUNT(*) FROM (SELECT q.id,(SELECT COUNT(*) FROM question_participations qp WHERE qp.question_id=q.id) participants,(SELECT COUNT(DISTINCT ba.ballot_id) FROM ballot_answers ba JOIN ballots b ON b.id=ba.ballot_id WHERE ba.question_id=q.id AND b.election_id=:eid) ballots FROM questions q WHERE q.election_id=:qid) totals WHERE participants<>ballots');
            $mismatch->execute(['eid'=>$electionId,'qid'=>$electionId]);if((int)$mismatch->fetchColumn()>0)throw new \RuntimeException('La certificación fue bloqueada: las participaciones secuenciales no coinciden.');
        }
    }

    public static function identifiedVotes(int $electionId, int $viewerId): array
    {
        $pdo=Database::connection();
        $election=$pdo->prepare('SELECT voting_mode,status FROM elections WHERE id=:id');
        $election->execute(['id'=>$electionId]);
        $mode=$election->fetch(PDO::FETCH_ASSOC);
        if(!$mode||$mode['voting_mode']!=='public') throw new \RuntimeException('Esta votación no es identificada.');
        if($mode['status']!=='closed') throw new \RuntimeException('Los votos identificados solo se muestran después del cierre.');
        $stmt=$pdo->prepare(
            'SELECT cm.id member_id,cm.name voter_name,cm.rut,cm.email,b.submitted_at,b.weight,
                    q.id question_id,q.text question_text,o.label option_label
             FROM ballots b
             JOIN census_members cm ON cm.id=b.census_member_id
             JOIN ballot_answers ba ON ba.ballot_id=b.id
             JOIN questions q ON q.id=ba.question_id
             JOIN options o ON o.id=ba.option_id
             WHERE b.election_id=:id
             ORDER BY cm.name,cm.id,q.sort_order,q.id,o.sort_order,o.id'
        );
        $stmt->execute(['id'=>$electionId]);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        Audit::log('results.identified_votes_viewed','election',$electionId,['rows'=>count($rows)],$viewerId);
        return $rows;
    }

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
        self::assertIntegrity($electionId);
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
