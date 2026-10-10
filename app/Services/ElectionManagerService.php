<?php
declare(strict_types=1);
namespace InaudisVote\Services;

use InaudisVote\Core\Audit;
use InaudisVote\Core\Database;
use PDO;

final class ElectionManagerService
{
    public static function available(int $organizationId): array
    {
        $stmt=Database::connection()->prepare("SELECT id,name,email FROM users WHERE organization_id=:org AND role='organizer' AND status='active' ORDER BY name");
        $stmt->execute(['org'=>$organizationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function forElection(int $electionId): array
    {
        $stmt=Database::connection()->prepare('SELECT u.id,u.name,u.email,em.assigned_at FROM election_managers em JOIN users u ON u.id=em.user_id WHERE em.election_id=:id ORDER BY u.name');
        $stmt->execute(['id'=>$electionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function assign(int $electionId,int $userId,int $by): void
    {
        $pdo=Database::connection();
        $check=$pdo->prepare("SELECT COUNT(*) FROM users u JOIN elections e ON e.organization_id=u.organization_id WHERE u.id=:uid AND u.role='organizer' AND u.status='active' AND e.id=:eid");
        $check->execute(['uid'=>$userId,'eid'=>$electionId]);
        if(!(int)$check->fetchColumn())throw new \InvalidArgumentException('El encargado debe estar activo y pertenecer al cliente de la votación.');
        $stmt=$pdo->prepare('INSERT IGNORE INTO election_managers(election_id,user_id,assigned_by) VALUES(:eid,:uid,:by)');
        $stmt->execute(['eid'=>$electionId,'uid'=>$userId,'by'=>$by]);
        if($stmt->rowCount())Audit::log('manager.assigned','election',$electionId,['manager_user_id'=>$userId],$by);
    }

    public static function remove(int $electionId,int $userId,int $by): void
    {
        $pdo=Database::connection();
        $count=$pdo->prepare('SELECT COUNT(*) FROM election_managers WHERE election_id=:eid');$count->execute(['eid'=>$electionId]);
        if((int)$count->fetchColumn()<=1)throw new \RuntimeException('La votación debe conservar al menos un encargado.');
        $stmt=$pdo->prepare('DELETE FROM election_managers WHERE election_id=:eid AND user_id=:uid');$stmt->execute(['eid'=>$electionId,'uid'=>$userId]);
        if($stmt->rowCount())Audit::log('manager.removed','election',$electionId,['manager_user_id'=>$userId],$by);
    }
}
