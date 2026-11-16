-- Configurable voter census templates. Apply after database backup.
CREATE TABLE IF NOT EXISTS organization_census_templates (
 organization_id BIGINT UNSIGNED PRIMARY KEY,
 fields_json JSON NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS election_census_templates (
 election_id BIGINT UNSIGNED PRIMARY KEY,
 fields_json JSON NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS census_custom_values (
 census_member_id BIGINT UNSIGNED PRIMARY KEY,
 values_json JSON NOT NULL,
 FOREIGN KEY (census_member_id) REFERENCES census_members(id) ON DELETE CASCADE
) ENGINE=InnoDB;
