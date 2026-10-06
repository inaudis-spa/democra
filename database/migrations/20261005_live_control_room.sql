CREATE TABLE IF NOT EXISTS election_live_state (
    election_id BIGINT UNSIGNED PRIMARY KEY,
    current_question_id BIGINT UNSIGNED NULL,
    display_results TINYINT(1) NOT NULL DEFAULT 0,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_live_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    CONSTRAINT fk_live_question FOREIGN KEY (current_question_id) REFERENCES questions(id) ON DELETE SET NULL,
    CONSTRAINT fk_live_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
