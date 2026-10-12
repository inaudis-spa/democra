SET @has_primary_color = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='organizations' AND COLUMN_NAME='primary_color');
SET @sql = IF(@has_primary_color=0, 'ALTER TABLE organizations ADD COLUMN primary_color VARCHAR(7) NULL AFTER logo_path', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_secondary_color = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='organizations' AND COLUMN_NAME='secondary_color');
SET @sql = IF(@has_secondary_color=0, 'ALTER TABLE organizations ADD COLUMN secondary_color VARCHAR(7) NULL AFTER primary_color', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_ballot_locked = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='elections' AND COLUMN_NAME='ballot_locked_at');
SET @sql = IF(@has_ballot_locked=0, 'ALTER TABLE elections ADD COLUMN ballot_locked_at DATETIME NULL AFTER allow_weighted_vote', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_census_locked = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='elections' AND COLUMN_NAME='census_locked_at');
SET @sql = IF(@has_census_locked=0, 'ALTER TABLE elections ADD COLUMN census_locked_at DATETIME NULL AFTER ballot_locked_at', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS election_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  election_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_election_documents_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
) ENGINE=InnoDB;
