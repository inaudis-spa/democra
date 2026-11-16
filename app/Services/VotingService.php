<?php

declare(strict_types=1);

namespace InaudisVote\Services;

use InaudisVote\Core\Audit;
use InaudisVote\Core\Database;
use PDO;

final class VotingService
{
    public static function resolveToken(string $rawToken): ?array
    {
        $hash = hash('sha256', $rawToken);
        $stmt = Database::connection()->prepare(
            'SELECT vt.id token_id, vt.election_id, vt.census_member_id, vt.otp_hash, vt.otp_expires_at, vt.verified_at, vt.consumed_at,
                    cm.name voter_name, cm.vote_weight, cm.has_voted, cm.status member_status,
                    vt.otp_attempts, vt.otp_locked_at,
                    e.title election_title, o.logo_path organization_logo_path, e.description, e.voting_mode, e.question_flow, e.status election_status, e.starts_at, e.ends_at
             FROM voter_tokens vt
             JOIN census_members cm ON cm.id=vt.census_member_id
             JOIN elections e ON e.id=vt.election_id
             JOIN organizations o ON o.id=e.organization_id
             WHERE vt.token_hash=:hash LIMIT 1'
        );
        $stmt->execute(['hash'=>$hash]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function verifyOtp(string $rawToken, string $otp): bool
    {
        $context=self::resolveToken($rawToken);
        if(!$context||empty($context['otp_hash'])||!empty($context['consumed_at'])||!empty($context['otp_locked_at'])) return false;
        if(!empty($context['otp_expires_at'])&&strtotime((string)$context['otp_expires_at'])<time()) return false;
        if(!password_verify(trim($otp),(string)$context['otp_hash'])){
            $failed=Database::connection()->prepare('UPDATE voter_tokens SET otp_attempts=otp_attempts+1,otp_locked_at=IF(otp_attempts+1>=8,NOW(),otp_locked_at) WHERE id=:id AND consumed_at IS NULL');
            $failed->execute(['id'=>(int)$context['token_id']]);
            return false;
        }
        $stmt=Database::connection()->prepare('UPDATE voter_tokens SET verified_at=NOW(),otp_attempts=0 WHERE id=:id AND consumed_at IS NULL AND otp_locked_at IS NULL');
        $stmt->execute(['id'=>(int)$context['token_id']]);
        if ($stmt->rowCount() !== 1) return false;
        Audit::log('voter.otp_verified','census_member',(int)$context['census_member_id'],['election_id'=>(int)$context['election_id']]);
        return true;
    }

    public static function canVote(array $context): bool
    {
        if (($context['member_status'] ?? '') !== 'eligible') return false;
        if (($context['question_flow'] ?? 'traditional') !== 'sequential' && (int)($context['has_voted'] ?? 0) === 1) return false;
        if (!empty($context['consumed_at']) || !empty($context['otp_locked_at'])) return false;
        if (empty($context['verified_at'])) return false;
        if (($context['election_status'] ?? '') !== 'open') return false;
        $now=time();
        if (!empty($context['starts_at']) && strtotime((string)$context['starts_at']) > $now) return false;
        if (!empty($context['ends_at']) && strtotime((string)$context['ends_at']) < $now) return false;
        return true;
    }


    public static function answeredQuestions(int $memberId): array
    {
        $stmt=Database::connection()->prepare('SELECT question_id FROM question_participations WHERE census_member_id=:id');
        $stmt->execute(['id'=>$memberId]);
        return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function submitSequential(string $rawToken, array $answers, array $context): string
    {
        $pdo=Database::connection();
        $electionId=(int)$context['election_id'];
        $pdo->beginTransaction();
        try {
            $lock=$pdo->prepare('SELECT status,question_flow,starts_at,ends_at FROM elections WHERE id=:id FOR UPDATE');
            $lock->execute(['id'=>$electionId]);
            $e=$lock->fetch(PDO::FETCH_ASSOC);
            if (!$e || $e['question_flow']!=='sequential' || $e['status']!=='open'
                || (!empty($e['starts_at']) && strtotime($e['starts_at'])>time())
                || (!empty($e['ends_at']) && strtotime($e['ends_at'])<time())) {
                throw new \RuntimeException('El proceso no está abierto.');
            }
            $token=$pdo->prepare('SELECT vt.verified_at,vt.consumed_at,vt.otp_locked_at,cm.status,cm.vote_weight FROM voter_tokens vt JOIN census_members cm ON cm.id=vt.census_member_id WHERE vt.id=:id FOR UPDATE');
            $token->execute(['id'=>(int)$context['token_id']]);
            $fresh=$token->fetch(PDO::FETCH_ASSOC);
            if (!$fresh || !$fresh['verified_at'] || $fresh['consumed_at'] || $fresh['otp_locked_at'] || $fresh['status']!=='eligible') {
                throw new \RuntimeException('Invitación no habilitada.');
            }
            $stmt=$pdo->prepare("SELECT * FROM questions WHERE election_id=:id AND voting_status='open'");
            $stmt->execute(['id'=>$electionId]);
            $questions=$stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($questions)!==1) throw new \RuntimeException('No hay una pregunta habilitada.');
            $q=$questions[0];
            $qid=(int)$q['id'];
            if (count($answers)!==1 || !array_key_exists((string)$qid,$answers)) {
                throw new \RuntimeException('Solo puedes responder la pregunta habilitada.');
            }
            $selected=$answers[(string)$qid];
            if (!is_array($selected)) $selected=[$selected];
            $selected=array_values(array_unique(array_filter(array_map('intval',$selected))));
            $opts=$pdo->prepare('SELECT id FROM options WHERE question_id=:id');
            $opts->execute(['id'=>$qid]);
            $allowed=array_map('intval',$opts->fetchAll(PDO::FETCH_COLUMN));
            if (count($selected)<(int)$q['min_choices'] || count($selected)>(int)$q['max_choices']
                || array_diff($selected,$allowed)) throw new \RuntimeException('Selección inválida.');
            $participation=$pdo->prepare('INSERT INTO question_participations(question_id,census_member_id) VALUES(:qid,:mid)');
            $participation->execute(['qid'=>$qid,'mid'=>(int)$context['census_member_id']]);
            $ballotToken=bin2hex(random_bytes(32));
            $stmt=$pdo->prepare('INSERT INTO ballots(election_id,ballot_token,weight,submitted_at) VALUES(:eid,:token,:weight,NOW())');
            $stmt->execute(['eid'=>$electionId,'token'=>$ballotToken,'weight'=>$fresh['vote_weight']]);
            $ballotId=(int)$pdo->lastInsertId();
            $stmt=$pdo->prepare('INSERT INTO ballot_answers(ballot_id,question_id,option_id) VALUES(:bid,:qid,:oid)');
            foreach($selected as $optionId)$stmt->execute(['bid'=>$ballotId,'qid'=>$qid,'oid'=>$optionId]);
            Audit::log('vote.question_submitted','election',$electionId,['question_id'=>$qid]);
            $pdo->commit();
            return hash('sha256',$ballotToken);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function submit(string $rawToken, array $answers): string
    {
        $pdo=Database::connection();
        $context=self::resolveToken($rawToken);
        if (!$context || !self::canVote($context)) {
            throw new \RuntimeException('La invitación no está habilitada para votar.');
        }
        if (($context['question_flow'] ?? 'traditional') === 'sequential') return self::submitSequential($rawToken,$answers,$context);
        $questions=ElectionService::questions((int)$context['election_id']);
        foreach ($questions as $question) {
            $selected=$answers[(string)$question['id']] ?? [];
            if (!is_array($selected)) $selected=[$selected];
            $selected=array_values(array_unique(array_filter(array_map('intval',$selected))));
            $allowed=array_map(static fn(array $o): int => (int)$o['id'],$question['options']);
            foreach ($selected as $optionId) {
                if (!in_array($optionId,$allowed,true)) throw new \InvalidArgumentException('Alternativa inválida.');
            }
            $count=count($selected);
            if ($count < (int)$question['min_choices'] || $count > (int)$question['max_choices']) {
                throw new \InvalidArgumentException('La selección no cumple las reglas de la papeleta.');
            }
        }
        $ballotToken=bin2hex(random_bytes(32));
        $pdo->beginTransaction();
        try {
            // Serialize submissions with election closure on the same InnoDB row.
            $lock=$pdo->prepare('SELECT status,starts_at,ends_at FROM elections WHERE id=:id FOR UPDATE');
            $lock->execute(['id'=>(int)$context['election_id']]);
            $state=$lock->fetch(PDO::FETCH_ASSOC);
            $now=time();
            if (!$state || $state['status'] !== 'open'
                || (!empty($state['starts_at']) && strtotime((string)$state['starts_at']) > $now)
                || (!empty($state['ends_at']) && strtotime((string)$state['ends_at']) < $now)) {
                throw new \RuntimeException('La votación ya no está abierta.');
            }
            // Recheck the invitation inside the transaction, after acquiring the election lock.
            $tokenLock=$pdo->prepare('SELECT vt.verified_at,vt.consumed_at,vt.otp_locked_at,cm.has_voted,cm.status
                FROM voter_tokens vt JOIN census_members cm ON cm.id=vt.census_member_id
                WHERE vt.id=:id FOR UPDATE');
            $tokenLock->execute(['id'=>(int)$context['token_id']]);
            $fresh=$tokenLock->fetch(PDO::FETCH_ASSOC);
            if (!$fresh || empty($fresh['verified_at']) || !empty($fresh['consumed_at'])
                || !empty($fresh['otp_locked_at']) || (int)$fresh['has_voted']===1
                || $fresh['status']!=='eligible') {
                throw new \RuntimeException('La invitación ya no está habilitada para votar.');
            }
            $stmt=$pdo->prepare('INSERT INTO ballots (election_id,ballot_token,weight,submitted_at) VALUES (:election_id,:ballot_token,:weight,NOW())');
            $stmt->execute(['election_id'=>(int)$context['election_id'],'ballot_token'=>$ballotToken,'weight'=>(float)$context['vote_weight']]);
            $ballotId=(int)$pdo->lastInsertId();
            $answerStmt=$pdo->prepare('INSERT INTO ballot_answers (ballot_id,question_id,option_id) VALUES (:ballot_id,:question_id,:option_id)');
            foreach ($questions as $question) {
                $selected=$answers[(string)$question['id']] ?? [];
                if (!is_array($selected)) $selected=[$selected];
                foreach (array_unique(array_filter(array_map('intval',$selected))) as $optionId) {
                    $answerStmt->execute(['ballot_id'=>$ballotId,'question_id'=>(int)$question['id'],'option_id'=>$optionId]);
                }
            }
            $updateMember=$pdo->prepare('UPDATE census_members SET has_voted=1 WHERE id=:id AND has_voted=0');
            $updateMember->execute(['id'=>(int)$context['census_member_id']]);
            if ($updateMember->rowCount()!==1) throw new \RuntimeException('El voto ya fue registrado.');
            $updateToken=$pdo->prepare('UPDATE voter_tokens SET consumed_at=NOW() WHERE id=:id AND consumed_at IS NULL');
            $updateToken->execute(['id'=>(int)$context['token_id']]);
            if($updateToken->rowCount()!==1) throw new \RuntimeException('La invitación ya fue utilizada.');
            Audit::log('vote.submitted','election',(int)$context['election_id'],['ballot_receipt'=>hash('sha256',$ballotToken)]);
            $pdo->commit();
            return hash('sha256',$ballotToken);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
