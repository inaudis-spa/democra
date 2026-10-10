USE inaudis_vote;

CREATE TABLE IF NOT EXISTS election_managers (
    election_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (election_id,user_id),
    CONSTRAINT fk_em_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    CONSTRAINT fk_em_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_em_assigner FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT IGNORE INTO election_managers (election_id,user_id,assigned_by)
SELECT e.id,e.created_by,e.created_by
FROM elections e
JOIN users u ON u.id=e.created_by
WHERE u.role='organizer';

SET @has_otp_attempts = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='voter_tokens' AND COLUMN_NAME='otp_attempts'
);
SET @sql = IF(@has_otp_attempts=0,
    'ALTER TABLE voter_tokens ADD COLUMN otp_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER consumed_at',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_otp_locked = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='voter_tokens' AND COLUMN_NAME='otp_locked_at'
);
SET @sql = IF(@has_otp_locked=0,
    'ALTER TABLE voter_tokens ADD COLUMN otp_locked_at DATETIME NULL AFTER otp_attempts',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
