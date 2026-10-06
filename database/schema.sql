CREATE DATABASE IF NOT EXISTS inaudis_vote
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE inaudis_vote;

CREATE TABLE organizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    tax_id VARCHAR(32) NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    logo_path VARCHAR(255) NULL,
    primary_color VARCHAR(7) NULL,
    secondary_color VARCHAR(7) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','organizer','witness','voter') NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_organization
        FOREIGN KEY (organization_id) REFERENCES organizations(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE elections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    voting_mode ENUM('secret','public') NOT NULL DEFAULT 'secret',
    status ENUM('draft','scheduled','open','closed','cancelled') NOT NULL DEFAULT 'draft',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    allow_weighted_vote TINYINT(1) NOT NULL DEFAULT 0,
    ballot_locked_at DATETIME NULL,
    census_locked_at DATETIME NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_elections_organization FOREIGN KEY (organization_id) REFERENCES organizations(id),
    CONSTRAINT fk_elections_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE election_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_election_documents_election
        FOREIGN KEY (election_id) REFERENCES elections(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE census_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id BIGINT UNSIGNED NOT NULL,
    rut VARCHAR(20) NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(30) NULL,
    vote_weight DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
    segment VARCHAR(120) NULL,
    status ENUM('eligible','blocked') NOT NULL DEFAULT 'eligible',
    has_voted TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_census_election_email (election_id, email),
    KEY idx_census_election_status (election_id, status),
    CONSTRAINT fk_census_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id BIGINT UNSIGNED NOT NULL,
    text TEXT NOT NULL,
    question_type ENUM('single','multiple') NOT NULL DEFAULT 'single',
    min_choices INT UNSIGNED NOT NULL DEFAULT 1,
    max_choices INT UNSIGNED NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_questions_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(255) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_options_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE voter_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id BIGINT UNSIGNED NOT NULL,
    census_member_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    otp_hash VARCHAR(255) NULL,
    otp_expires_at DATETIME NULL,
    verified_at DATETIME NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_voter_tokens_election (election_id),
    CONSTRAINT fk_tokens_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    CONSTRAINT fk_tokens_census FOREIGN KEY (census_member_id) REFERENCES census_members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ballots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id BIGINT UNSIGNED NOT NULL,
    ballot_token CHAR(64) NOT NULL UNIQUE,
    weight DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ballots_election (election_id),
    CONSTRAINT fk_ballots_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ballot_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ballot_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    option_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ballot_answer (ballot_id, question_id, option_id),
    CONSTRAINT fk_answers_ballot FOREIGN KEY (ballot_id) REFERENCES ballots(id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_option FOREIGN KEY (option_id) REFERENCES options(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    metadata JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_created_at (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE result_certificates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id BIGINT UNSIGNED NOT NULL UNIQUE,
    results_json JSON NOT NULL,
    sha256_hash CHAR(64) NOT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    generated_by BIGINT UNSIGNED NOT NULL,
    CONSTRAINT fk_cert_election FOREIGN KEY (election_id) REFERENCES elections(id),
    CONSTRAINT fk_cert_user FOREIGN KEY (generated_by) REFERENCES users(id)
) ENGINE=InnoDB;


CREATE TABLE election_live_state (
    election_id BIGINT UNSIGNED PRIMARY KEY,
    current_question_id BIGINT UNSIGNED NULL,
    display_results TINYINT(1) NOT NULL DEFAULT 0,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_live_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    CONSTRAINT fk_live_question FOREIGN KEY (current_question_id) REFERENCES questions(id) ON DELETE SET NULL,
    CONSTRAINT fk_live_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE election_witnesses (election_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,assigned_by BIGINT UNSIGNED NOT NULL,assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(election_id,user_id),CONSTRAINT fk_ew_election FOREIGN KEY(election_id) REFERENCES elections(id) ON DELETE CASCADE,CONSTRAINT fk_ew_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_ew_assigner FOREIGN KEY(assigned_by) REFERENCES users(id)) ENGINE=InnoDB;
CREATE TABLE witness_checkpoints (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,election_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,stage ENUM('setup_reviewed','census_verified','opening_attested','closing_attested','results_attested') NOT NULL,snapshot_json JSON NOT NULL,sha256_hash CHAR(64) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY idx_wc_election(election_id,created_at),CONSTRAINT fk_wc_election FOREIGN KEY(election_id) REFERENCES elections(id) ON DELETE CASCADE,CONSTRAINT fk_wc_user FOREIGN KEY(user_id) REFERENCES users(id)) ENGINE=InnoDB;
