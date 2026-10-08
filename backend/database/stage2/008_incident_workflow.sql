ALTER TABLE cp_mobile_incidents ADD COLUMN client_key CHAR(36) NULL;
ALTER TABLE cp_files ADD COLUMN published TINYINT NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS cp_incident_updates (
 id CHAR(36) PRIMARY KEY, tenant_id CHAR(36) NOT NULL, incident_id CHAR(36) NOT NULL,
 user_id CHAR(36) NOT NULL, description TEXT NOT NULL, measures TEXT NOT NULL,
 client_time DATETIME NOT NULL, created_at DATETIME NOT NULL,
 INDEX ix_incident_updates (tenant_id,incident_id,created_at),
 CONSTRAINT fk_incident_update_parent FOREIGN KEY (incident_id) REFERENCES cp_incidents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_incident_actions (
 id CHAR(36) PRIMARY KEY, tenant_id CHAR(36) NOT NULL, incident_id CHAR(36) NOT NULL,
 name VARCHAR(200) NOT NULL, responsible_id CHAR(36) NULL, required TINYINT NOT NULL DEFAULT 1,
 status VARCHAR(32) NOT NULL DEFAULT 'OPEN', result TEXT NULL,
 deadline DATETIME NULL, completed_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
 version INT NOT NULL DEFAULT 1,
 INDEX ix_incident_actions (tenant_id,incident_id,status),
 CONSTRAINT fk_incident_action_parent FOREIGN KEY (incident_id) REFERENCES cp_incidents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_incident_resolutions (
 id CHAR(36) PRIMARY KEY, tenant_id CHAR(36) NOT NULL, incident_id CHAR(36) NOT NULL,
 actor_id CHAR(36) NOT NULL, responsible_id CHAR(36) NOT NULL, source_version INT NOT NULL,
 result TEXT NOT NULL, measures TEXT NOT NULL, created_at DATETIME NOT NULL,
 INDEX ix_incident_resolutions (tenant_id,incident_id,created_at),
 CONSTRAINT fk_incident_resolution_parent FOREIGN KEY (incident_id) REFERENCES cp_incidents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
