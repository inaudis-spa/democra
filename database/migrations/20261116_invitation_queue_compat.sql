-- Invitation queue used when opening an election. Delivery is not enabled.
-- Safe for existing installations: does not replace existing tables.
CREATE TABLE IF NOT EXISTS invitation_campaigns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id BIGINT UNSIGNED NOT NULL,
    status ENUM('queued','processing','completed','failed','cancelled') NOT NULL DEFAULT 'queued',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inv_campaign_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invitation_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT UNSIGNED NOT NULL,
    census_member_id BIGINT UNSIGNED NOT NULL,
    recipient_email VARCHAR(190) NOT NULL,
    status ENUM('queued','processing','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inv_delivery_campaign FOREIGN KEY (campaign_id) REFERENCES invitation_campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_delivery_member FOREIGN KEY (census_member_id) REFERENCES census_members(id) ON DELETE CASCADE
) ENGINE=InnoDB;
