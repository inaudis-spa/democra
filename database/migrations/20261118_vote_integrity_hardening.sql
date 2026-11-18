-- DEMOCRA: unicidad de participación e idempotencia del sufragio.
-- Aplicar únicamente con la aplicación en mantenimiento y sin votaciones abiertas.

CREATE TABLE election_participations (
    election_id BIGINT UNSIGNED NOT NULL,
    census_member_id BIGINT UNSIGNED NOT NULL,
    receipt_hash CHAR(64) NOT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (election_id, census_member_id),
    UNIQUE KEY uq_election_participation_receipt (receipt_hash),
    CONSTRAINT fk_ep_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    CONSTRAINT fk_ep_member FOREIGN KEY (census_member_id) REFERENCES census_members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vote_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    idempotency_key CHAR(64) NOT NULL,
    election_id BIGINT UNSIGNED NOT NULL,
    token_id BIGINT UNSIGNED NOT NULL,
    scope_key BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=tradicional; question_id=secuencial',
    receipt_hash CHAR(64) NULL,
    status ENUM('processing','committed') NOT NULL DEFAULT 'processing',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vote_submission_key (idempotency_key),
    UNIQUE KEY uq_vote_submission_scope (token_id, scope_key),
    KEY idx_vote_submission_election (election_id, created_at),
    CONSTRAINT fk_vs_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    CONSTRAINT fk_vs_token FOREIGN KEY (token_id) REFERENCES voter_tokens(id) ON DELETE CASCADE
) ENGINE=InnoDB;

