CREATE TABLE IF NOT EXISTS cp_personal_cards (
 id CHAR(36) PRIMARY KEY, tenant_id CHAR(36) NOT NULL, name VARCHAR(200) NOT NULL, employee_id CHAR(36) NOT NULL, number VARCHAR(100) NOT NULL, issued_at DATE, surrendered_at DATE, authority VARCHAR(200), receipt VARCHAR(300), status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME, version INT NOT NULL DEFAULT 1, INDEX ix_cards_tenant(tenant_id,deleted_at), FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_report_versions (
 id CHAR(36) PRIMARY KEY, tenant_id CHAR(36) NOT NULL, report_id CHAR(36) NOT NULL, revision INT NOT NULL, snapshot JSON NOT NULL, created_at DATETIME NOT NULL, UNIQUE KEY uq_report_revision(report_id,revision), FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_files (
 id CHAR(36) PRIMARY KEY, tenant_id CHAR(36) NOT NULL, entity_type VARCHAR(64) NOT NULL, entity_id CHAR(36) NOT NULL, name VARCHAR(200) NOT NULL, file_path VARCHAR(100) NOT NULL, mime VARCHAR(100) NOT NULL, sha256 CHAR(64) NOT NULL, scan_status VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, deleted_at DATETIME, INDEX ix_files_entity(tenant_id,entity_type,entity_id), FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
