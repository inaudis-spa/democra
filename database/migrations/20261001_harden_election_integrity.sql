USE inaudis_vote;

ALTER TABLE organizations
  ADD COLUMN primary_color VARCHAR(7) NULL AFTER logo_path,
  ADD COLUMN secondary_color VARCHAR(7) NULL AFTER primary_color;

ALTER TABLE elections
  ADD COLUMN ballot_locked_at DATETIME NULL AFTER allow_weighted_vote,
  ADD COLUMN census_locked_at DATETIME NULL AFTER ballot_locked_at;

CREATE TABLE IF NOT EXISTS election_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  election_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_election_documents_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
) ENGINE=InnoDB;
