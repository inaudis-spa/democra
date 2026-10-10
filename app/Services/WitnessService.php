<?php
declare(strict_types=1);
namespace InaudisVote\Services;

use InaudisVote\Core\Audit;
use InaudisVote\Core\Database;
use PDO;

final class WitnessService
{
    public static function assign(int $electionId,int $userId,int $by): void
    {
        $pdo=Database::connection();
        $check=$pdo->prepare("SELECT COUNT(*) FROM users u JOIN elections e ON e.organization_id=u.organization_id WHERE u.id=:uid AND u.role='witness' AND u.status='active' AND e.id=:eid");
        $check->execute(['uid'=>$userId,'eid'=>$electionId]);
        if(!(int)$check->fetchColumn())throw new \InvalidArgumentException('El Ministro de Fe debe estar activo y pertenecer al cliente de la votación.');
        $stmt=$pdo->prepare('INSERT IGNORE INTO election_witnesses(election_id,user_id,assigned_by) VALUES(:eid,:uid,:by)');
        $stmt->execute(['eid'=>$electionId,'uid'=>$userId,'by'=>$by]);
        if($stmt->rowCount())Audit::log('witness.assigned','election',$electionId,['witness_user_id'=>$userId],$by);
    }

    public static function remove(int $electionId,int $userId,int $by): void
    {
        $stmt=Database::connection()->prepare('DELETE FROM election_witnesses WHERE election_id=:eid AND user_id=:uid');
        $stmt->execute(['eid'=>$electionId,'uid'=>$userId]);
        if($stmt->rowCount())Audit::log('witness.removed','election',$electionId,['witness_user_id'=>$userId],$by);
    }

    public static function isAssigned(int $electionId,int $userId): bool
    {
        $stmt=Database::connection()->prepare('SELECT COUNT(*) FROM election_witnesses WHERE election_id=:eid AND user_id=:uid');
        $stmt->execute(['eid'=>$electionId,'uid'=>$userId]);return (bool)$stmt->fetchColumn();
    }

    public static function forElection(int $electionId): array
    {
        $stmt=Database::connection()->prepare('SELECT u.id,u.name,u.email,ew.assigned_at FROM election_witnesses ew JOIN users u ON u.id=ew.user_id WHERE ew.election_id=:id ORDER BY u.name');
        $stmt->execute(['id'=>$electionId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function available(int $organizationId): array
    {
        $stmt=Database::connection()->prepare("SELECT id,name,email FROM users WHERE organization_id=:org AND role='witness' AND status='active' ORDER BY name");
        $stmt->execute(['org'=>$organizationId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function nextStage(int $electionId,int $userId): ?string
    {
        $order=['setup_reviewed','census_verified','opening_attested','closing_attested','results_attested'];
        $stmt=Database::connection()->prepare('SELECT stage FROM witness_checkpoints WHERE election_id=:eid AND user_id=:uid');
        $stmt->execute(['eid'=>$electionId,'uid'=>$userId]);$done=$stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach($order as $stage)if(!in_array($stage,$done,true))return $stage;
        return null;
    }

    public static function checkpoint(int $electionId,string $stage,int $userId): string
    {
        if(!self::isAssigned($electionId,$userId))throw new \RuntimeException('No estás asignado como Ministro de Fe de este proceso.');
        $election=ElectionService::find($electionId);if(!$election)throw new \RuntimeException('Votación no encontrada.');
        $next=self::nextStage($electionId,$userId);
        if($next===null)throw new \RuntimeException('Todos los hitos del proceso ya fueron certificados.');
        if($stage!==$next)throw new \RuntimeException('Los hitos deben certificarse en el orden definido. El siguiente es: '.$next.'.');
        $allowedStatuses=[
            'setup_reviewed'=>['draft','scheduled','open'],
            'census_verified'=>['draft','scheduled','open'],
            'opening_attested'=>['open'],
            'closing_attested'=>['closed'],
            'results_attested'=>['closed'],
        ];
        if(!in_array($election['status'],$allowedStatuses[$stage],true))throw new \RuntimeException('El estado actual de la votación no permite certificar este hito.');
        $pdo=Database::connection();$counts=$pdo->prepare('SELECT COUNT(*) total,SUM(status="eligible") eligible,SUM(has_voted=1) voted FROM census_members WHERE election_id=:id');$counts->execute(['id'=>$electionId]);
        $snapshot=['election_id'=>$electionId,'stage'=>$stage,'status'=>$election['status'],'ballot_locked_at'=>$election['ballot_locked_at'],'census_locked_at'=>$election['census_locked_at'],'census'=>$counts->fetch(PDO::FETCH_ASSOC),'questions'=>ElectionService::questions($electionId),'at'=>gmdate('c')];
        if($stage==='results_attested')$snapshot['results']=ResultService::summary($electionId);
        $json=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);$hash=hash('sha256',(string)$json);
        $stmt=$pdo->prepare('INSERT INTO witness_checkpoints(election_id,user_id,stage,snapshot_json,sha256_hash) VALUES(:eid,:uid,:stage,:json,:hash)');
        $stmt->execute(['eid'=>$electionId,'uid'=>$userId,'stage'=>$stage,'json'=>$json,'hash'=>$hash]);
        Audit::log('witness.checkpoint','election',$electionId,['stage'=>$stage,'sha256'=>$hash],$userId);return $hash;
    }

    public static function checkpoints(int $electionId): array
    {
        $stmt=Database::connection()->prepare('SELECT wc.*,u.name user_name FROM witness_checkpoints wc JOIN users u ON u.id=wc.user_id WHERE wc.election_id=:id ORDER BY wc.created_at DESC');
        $stmt->execute(['id'=>$electionId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
