-- DEMOCRA: invitation dispatch persistence. Additive migration; does not alter existing tables.
-- Apply only after backup and explicit production deployment approval.
CREATE TABLE IF NOT EXISTS invitation_campaigns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  election_id BIGINT UNSIGNED NOT NULL,
  status ENUM('queued','running','completed','paused') NOT NULL DEFAULT 'queued',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  UNIQUE KEY uq_invitation_campaign_election (election_id),
  CONSTRAINT fk_invitation_campaign_election FOREIGN KEY (election_id) REFERENCES elections(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invitation_deliveries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id BIGINT UNSIGNED NOT NULL,
  census_member_id BIGINT UNSIGNED NOT NULL,
  recipient_email VARCHAR(190) NOT NULL,
  status ENUM('queued','processing','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NULL,
  provider_message_id VARCHAR(255) NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_invitation_delivery_member (campaign_id,census_member_id),
  KEY idx_invitation_delivery_queue (status,next_attempt_at),
  CONSTRAINT fk_invitation_delivery_campaign FOREIGN KEY (campaign_id) REFERENCES invitation_campaigns(id),
  CONSTRAINT fk_invitation_delivery_member FOREIGN KEY (census_member_id) REFERENCES census_members(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invitation_delivery_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  delivery_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(60) NOT NULL,
  provider_event_id VARCHAR(190) NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_invitation_provider_event (provider_event_id),
  KEY idx_invitation_event_delivery (delivery_id,created_at),
  CONSTRAINT fk_invitation_event_delivery FOREIGN KEY (delivery_id) REFERENCES invitation_deliveries(id)
) ENGINE=InnoDB;
