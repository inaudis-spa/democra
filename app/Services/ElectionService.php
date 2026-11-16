<?php

declare(strict_types=1);

namespace InaudisVote\Services;

use InaudisVote\Core\Audit;
use InaudisVote\Core\Database;
use PDO;
use DateTimeImmutable;

final class ElectionService
{
    public static function allForUser(array $user): array
    {
        $pdo = Database::connection();
        $role = $user['role'] ?? null;
        if ($role === 'admin') {
            $stmt = $pdo->query('SELECT e.*, o.name organization_name FROM elections e JOIN organizations o ON o.id=e.organization_id ORDER BY e.created_at DESC');
        } elseif ($role === 'organizer') {
            $stmt = $pdo->prepare('SELECT e.*, o.name organization_name FROM elections e JOIN organizations o ON o.id=e.organization_id JOIN election_managers em ON em.election_id=e.id WHERE em.user_id=:user_id ORDER BY e.created_at DESC');
            $stmt->execute(['user_id' => (int) ($user['id'] ?? 0)]);
        } elseif ($role === 'witness') {
            $stmt = $pdo->prepare('SELECT e.*, o.name organization_name FROM elections e JOIN organizations o ON o.id=e.organization_id JOIN election_witnesses ew ON ew.election_id=e.id WHERE ew.user_id=:user_id ORDER BY e.created_at DESC');
            $stmt->execute(['user_id' => (int) ($user['id'] ?? 0)]);
        } else {
            return [];
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
        if ($title === '' || $organizationId <= 0) throw new \InvalidArgumentException('Organización y título son obligatorios.');
        if (mb_strlen($title) > 180) throw new \InvalidArgumentException('El título no puede superar 180 caracteres.');
        $org=$pdo->prepare("SELECT COUNT(*) FROM organizations WHERE id=:id AND status='active'");$org->execute(['id'=>$organizationId]);
        if(!(int)$org->fetchColumn()) throw new \InvalidArgumentException('La organización seleccionada no existe o está inactiva.');
        $startsAt=trim((string)($data['starts_at']??''));$endsAt=trim((string)($data['ends_at']??''));
        try{$start=$startsAt!==''?new DateTimeImmutable($startsAt):null;$end=$endsAt!==''?new DateTimeImmutable($endsAt):null;}catch(\Throwable){throw new \InvalidArgumentException('Las fechas ingresadas no son válidas.');}
        if($start&&$end&&$end<=$start) throw new \InvalidArgumentException('La fecha de término debe ser posterior a la fecha de inicio.');
        $flow = ($data['question_flow'] ?? 'traditional') === 'sequential' ? 'sequential' : 'traditional';
        $mode = in_array(($data['voting_mode'] ?? 'secret'), ['secret','public'], true) ? $data['voting_mode'] : 'secret';
        $stmt = $pdo->prepare('INSERT INTO elections (organization_id,title,description,voting_mode,question_flow,status,starts_at,ends_at,allow_weighted_vote,created_by) VALUES (:organization_id,:title,:description,:voting_mode,:question_flow,:status,:starts_at,:ends_at,:weighted,:created_by)');
        $stmt->execute(['organization_id'=>$organizationId,'title'=>$title,'description'=>trim((string)($data['description']??''))?:null,'voting_mode'=>$mode,'question_flow'=>$flow,'status'=>'draft','starts_at'=>$start?->format('Y-m-d H:i:s'),'ends_at'=>$end?->format('Y-m-d H:i:s'),'weighted'=>!empty($data['allow_weighted_vote'])?1:0,'created_by'=>$createdBy]);
        $id=(int)$pdo->lastInsertId();
        $creatorRole=$pdo->prepare('SELECT role FROM users WHERE id=:id');$creatorRole->execute(['id'=>$createdBy]);
        if($creatorRole->fetchColumn()==='organizer'){
            $assign=$pdo->prepare('INSERT INTO election_managers(election_id,user_id,assigned_by) VALUES(:eid,:uid,:by)');
            $assign->execute(['eid'=>$id,'uid'=>$createdBy,'by'=>$createdBy]);
        }
        Audit::log('election.created','election',$id,['title'=>$title,'mode'=>$mode]);
        return $id;
    }

    private static function requireEditable(int $electionId): void
    {
        $e=self::find($electionId);
        if(!$e || !in_array($e['status'],['draft','scheduled'],true) || !empty($e['ballot_locked_at'])) {
            throw new \RuntimeException('La papeleta está bloqueada y ya no admite cambios.');
        }
    }

    public static function addQuestion(int $electionId, array $data): int
    {
        self::requireEditable($electionId);
        $pdo=Database::connection();
        $text=trim((string)($data['text']??''));
        $options=array_values(array_filter(array_map('trim',preg_split('/\r\n|\r|\n/',(string)($data['options']??''))?:[])));
        if($text===''||count($options)<2) throw new \InvalidArgumentException('La pregunta requiere texto y al menos dos alternativas.');
        $type=($data['question_type']??'single')==='multiple'?'multiple':'single';
        $max=$type==='multiple'?max(1,min(count($options),(int)($data['max_choices']??1))):1;
        $pdo->beginTransaction();
        try {
            $stmt=$pdo->prepare('INSERT INTO questions (election_id,text,question_type,min_choices,max_choices,sort_order) VALUES (:election_id,:text,:type,1,:max_choices,(SELECT COALESCE(MAX(q.sort_order),0)+1 FROM questions q WHERE q.election_id=:sort_election))');
            $stmt->execute(['election_id'=>$electionId,'text'=>$text,'type'=>$type,'max_choices'=>$max,'sort_election'=>$electionId]);
            $questionId=(int)$pdo->lastInsertId();
            $optionStmt=$pdo->prepare('INSERT INTO options (question_id,label,sort_order) VALUES (:question_id,:label,:sort_order)');
            foreach($options as $i=>$label) $optionStmt->execute(['question_id'=>$questionId,'label'=>$label,'sort_order'=>$i+1]);
            $pdo->commit();
            Audit::log('question.created','question',$questionId,['election_id'=>$electionId]);
            return $questionId;
        } catch(\Throwable $e){$pdo->rollBack();throw $e;}
    }

    public static function deleteQuestion(int $electionId,int $questionId): void
    {
        self::requireEditable($electionId);
        $stmt=Database::connection()->prepare('DELETE FROM questions WHERE id=:question_id AND election_id=:election_id');
        $stmt->execute(['question_id'=>$questionId,'election_id'=>$electionId]);
        if($stmt->rowCount()!==1) throw new \RuntimeException('Pregunta no encontrada.');
        Audit::log('question.deleted','question',$questionId,['election_id'=>$electionId]);
    }

    public static function questions(int $electionId): array
    {
        $pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT * FROM questions WHERE election_id=:id ORDER BY sort_order,id');$stmt->execute(['id'=>$electionId]);
        $questions=$stmt->fetchAll(PDO::FETCH_ASSOC);$opt=$pdo->prepare('SELECT id,label,sort_order FROM options WHERE question_id=:id ORDER BY sort_order,id');
        foreach($questions as &$q){$opt->execute(['id'=>$q['id']]);$q['options']=$opt->fetchAll(PDO::FETCH_ASSOC);}return $questions;
    }

    public static function setStatus(int $electionId,string $status): void
    {
        if(!in_array($status,['draft','scheduled','open','closed','cancelled'],true)) {
            throw new \InvalidArgumentException('Estado inválido.');
        }
        $pdo=Database::connection();
        $pdo->beginTransaction();
        try {
            // The voting service locks the same row before recording a ballot.
            $lock=$pdo->prepare('SELECT status FROM elections WHERE id=:id FOR UPDATE');
            $lock->execute(['id'=>$electionId]);
            $current=$lock->fetch(PDO::FETCH_ASSOC);
            if(!$current) throw new \RuntimeException('Votación no encontrada.');
            $allowed=['draft'=>['scheduled','open','cancelled'],'scheduled'=>['draft','open','cancelled'],'open'=>['closed','cancelled'],'closed'=>[],'cancelled'=>[]];
            if($status!==$current['status']&&!in_array($status,$allowed[$current['status']]??[],true)) {
                throw new \RuntimeException('Transición de estado no permitida.');
            }
            if($status==='closed') {
                $flow=$pdo->prepare('SELECT question_flow FROM elections WHERE id=:id');$flow->execute(['id'=>$electionId]);
                if($flow->fetchColumn()==='sequential') {
                    $pending=$pdo->prepare("SELECT COUNT(*) FROM questions WHERE election_id=:id AND voting_status<>'closed'");$pending->execute(['id'=>$electionId]);
                    if((int)$pending->fetchColumn()>0) throw new \RuntimeException('Cierra todas las preguntas antes de finalizar.');
                }
            }
            if($status==='open'){
                $q=$pdo->prepare('SELECT COUNT(*) FROM questions WHERE election_id=:id');
                $q->execute(['id'=>$electionId]);
                $c=$pdo->prepare("SELECT COUNT(*) FROM census_members WHERE election_id=:id AND status='eligible'");
                $c->execute(['id'=>$electionId]);
                if((int)$q->fetchColumn()===0||(int)$c->fetchColumn()===0) {
                    throw new \RuntimeException('No se puede abrir sin preguntas y votantes habilitados.');
                }
                $stmt=$pdo->prepare('UPDATE elections SET status=:status,ballot_locked_at=COALESCE(ballot_locked_at,NOW()),census_locked_at=COALESCE(census_locked_at,NOW()) WHERE id=:id');
            } else {
                $stmt=$pdo->prepare('UPDATE elections SET status=:status WHERE id=:id');
            }
            $stmt->execute(['status'=>$status,'id'=>$electionId]);
            // Queue invitations atomically on the first opening. Delivery stays disabled
            // until a mail transport is configured; no voter token is exposed here.
            if($status==='open' && $current['status']!=='open'){
                $campaign=$pdo->prepare("INSERT INTO invitation_campaigns (election_id,status) VALUES (:id,'queued')");
                $campaign->execute(['id'=>$electionId]);
                $campaignId=(int)$pdo->lastInsertId();
                $queue=$pdo->prepare("INSERT INTO invitation_deliveries (campaign_id,census_member_id,recipient_email,status) SELECT :campaign_id,id,email,'queued' FROM census_members WHERE election_id=:election_id AND status='eligible' AND has_voted=0");
                $queue->execute(['campaign_id'=>$campaignId,'election_id'=>$electionId]);
            }
            Audit::log('election.status_changed','election',$electionId,['from'=>$current['status'],'status'=>$status]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
