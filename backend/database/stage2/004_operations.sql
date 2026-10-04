CREATE TABLE IF NOT EXISTS cp_tenant_limits (
 tenant_id CHAR(36) PRIMARY KEY, max_users INT NOT NULL DEFAULT 1000, max_employees INT NOT NULL DEFAULT 10000, max_facilities INT NOT NULL DEFAULT 1000, storage_mb INT NOT NULL DEFAULT 2048, updated_at DATETIME NOT NULL, version INT NOT NULL DEFAULT 1, FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_file_sizes (
 id CHAR(36) PRIMARY KEY, tenant_id CHAR(36) NOT NULL, kind VARCHAR(32) NOT NULL, bytes BIGINT UNSIGNED NOT NULL DEFAULT 0, INDEX ix_file_sizes_tenant(tenant_id), FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_presence (
 assignment_id CHAR(36) PRIMARY KEY, tenant_id CHAR(36) NOT NULL, started_at DATETIME, finished_at DATETIME, worked_minutes INT, status VARCHAR(32) NOT NULL, updated_at DATETIME NOT NULL, INDEX ix_presence_tenant(tenant_id,status), FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_audit_facilities (
 audit_id BIGINT UNSIGNED NOT NULL, facility_id CHAR(36) NOT NULL, PRIMARY KEY(audit_id,facility_id), INDEX ix_audit_facility(facility_id,audit_id), FOREIGN KEY(audit_id) REFERENCES cp_audit(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_retention_runs (
 id CHAR(36) PRIMARY KEY, tenant_id CHAR(36) NOT NULL, cutoff DATETIME NOT NULL, counts JSON NOT NULL, created_at DATETIME NOT NULL, INDEX ix_retention_run(tenant_id,created_at), FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_retention_pending_files (
 id CHAR(36) PRIMARY KEY, tenant_id CHAR(36) NOT NULL, file_name VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO cp_tenant_limits(tenant_id,updated_at) SELECT id,UTC_TIMESTAMP() FROM cp_tenants;
UPDATE cp_contracts SET legal_hold_until=DATE_ADD(ends_at,INTERVAL 3 YEAR),version=version+1 WHERE ends_at IS NOT NULL AND (legal_hold_until IS NULL OR legal_hold_until>DATE_ADD(ends_at,INTERVAL 3 YEAR));
