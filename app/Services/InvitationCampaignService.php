<?php
declare(strict_types=1);

namespace InaudisVote\Services;

use InaudisVote\Core\Database;
use PDO;

/**
 * Read-only monitoring of invitation campaigns.
 * Mail dispatch is intentionally not implemented until a provider is configured.
 */
final class InvitationCampaignService
{
    public static function summary(int $electionId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT c.id, c.status, c.created_at,
                    COUNT(d.id) AS total,
                    COALESCE(SUM(d.status='queued'),0) AS queued,
                    COALESCE(SUM(d.status='processing'),0) AS processing,
                    COALESCE(SUM(d.status='sent'),0) AS sent,
                    COALESCE(SUM(d.status='failed'),0) AS failed,
                    COALESCE(SUM(d.status='cancelled'),0) AS cancelled
             FROM invitation_campaigns c
             LEFT JOIN invitation_deliveries d ON d.campaign_id=c.id
             WHERE c.election_id=:election_id
             GROUP BY c.id,c.status,c.created_at"
        );
        $stmt->execute(['election_id'=>$electionId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function deliveries(int $electionId): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT d.census_member_id,d.recipient_email,d.status,d.attempts,d.sent_at
             FROM invitation_deliveries d
             JOIN invitation_campaigns c ON c.id=d.campaign_id
             WHERE c.election_id=:election_id ORDER BY d.id'
        );
        $stmt->execute(['election_id'=>$electionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
