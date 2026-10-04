CREATE TABLE IF NOT EXISTS cp_tenants (id CHAR(36) PRIMARY KEY, name VARCHAR(200) NOT NULL, slug VARCHAR(64) NOT NULL UNIQUE, inn VARCHAR(12) UNIQUE, domain VARCHAR(200) UNIQUE, status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE', settings JSON NOT NULL, features JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME, version INT NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_users (id CHAR(36) PRIMARY KEY, tenant_id CHAR(36), customer_id CHAR(36), employee_id CHAR(36), name VARCHAR(200) NOT NULL, email VARCHAR(200) UNIQUE, phone VARCHAR(32) UNIQUE, password_hash VARCHAR(255), role VARCHAR(64) NOT NULL, scopes JSON NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE', mfa_secret TEXT, mfa_enabled TINYINT NOT NULL DEFAULT 0, mfa_last_step BIGINT, is_demo TINYINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME, version INT NOT NULL DEFAULT 1, FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_sessions (id CHAR(36) PRIMARY KEY, user_id CHAR(36) NOT NULL, access_hash CHAR(64) NOT NULL UNIQUE, refresh_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL, refresh_expires_at DATETIME NOT NULL, revoked_at DATETIME, created_at DATETIME NOT NULL, ip VARCHAR(64), FOREIGN KEY(user_id) REFERENCES cp_users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_challenges (id CHAR(36) PRIMARY KEY, user_id CHAR(36), channel VARCHAR(32), code_hash CHAR(64), attempts INT NOT NULL DEFAULT 0, expires_at DATETIME NOT NULL, used_at DATETIME, payload JSON) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_rate_limits (rate_key CHAR(64) PRIMARY KEY, attempts INT NOT NULL, resets_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_audit (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id CHAR(36), actor_id CHAR(36), action VARCHAR(100) NOT NULL, entity_type VARCHAR(64) NOT NULL, entity_id CHAR(36), metadata JSON NOT NULL, ip VARCHAR(64), device VARCHAR(100), correlation_id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, KEY audit_tenant_time(tenant_id,created_at), KEY audit_entity(entity_type,entity_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_roles (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `code` VARCHAR(64) NULL,
    `permissions` JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_roles_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_roles_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id),
    UNIQUE KEY uq_role_code(tenant_id,code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_employees (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `phone` VARCHAR(32) NULL,
    `email` VARCHAR(200) NULL,
    `qualification` INT NULL,
    `badge` VARCHAR(64) NULL,
    `passport` VARCHAR(128) NULL,
    `status` VARCHAR(32) NULL,
    `hired_at` DATE NULL,
    `personal_card` VARCHAR(128) NULL,
    `facility_id` CHAR(36) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_employees_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_employees_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_document_types (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `required_fields` JSON NULL,
    `duration_days` INT NULL,
    `expiring_days` INT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_document_types_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_document_types_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_documents (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `employee_id` CHAR(36) NULL,
    `type_id` CHAR(36) NULL,
    `number` VARCHAR(200) NULL,
    `issued_at` DATE NULL,
    `expires_at` DATE NULL,
    `status` VARCHAR(32) NULL,
    `scan_status` VARCHAR(32) NULL,
    `file_path` VARCHAR(300) NULL,
    `mime` VARCHAR(100) NULL,
    `sha256` CHAR(64) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_documents_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_documents_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_document_reviews (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `document_id` CHAR(36) NULL,
    `decision` VARCHAR(32) NULL,
    `reason` TEXT NULL,
    `actor_id` CHAR(36) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_document_reviews_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_document_reviews_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_licenses (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `number` VARCHAR(100) NULL,
    `issued_at` DATE NULL,
    `expires_at` DATE NULL,
    `status` VARCHAR(32) NULL,
    `services` JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_licenses_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_licenses_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_service_types (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `code` VARCHAR(64) NULL,
    `rule_version` INT NULL,
    `valid_from` DATE NULL,
    `source` TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_service_types_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_service_types_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_customers (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `inn` VARCHAR(12) NULL,
    `contact_name` VARCHAR(200) NULL,
    `phone` VARCHAR(32) NULL,
    `email` VARCHAR(200) NULL,
    `status` VARCHAR(32) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_customers_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_customers_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_contracts (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `number` VARCHAR(100) NULL,
    `customer_id` CHAR(36) NULL,
    `license_id` CHAR(36) NULL,
    `starts_at` DATE NULL,
    `ends_at` DATE NULL,
    `signed_at` DATE NULL,
    `amount` DECIMAL(12,2) NULL,
    `services` JSON NULL,
    `status` VARCHAR(32) NULL,
    `ownership_proof` TEXT NULL,
    `weapon_details` TEXT NULL,
    `compensation` TEXT NULL,
    `legacy` TINYINT NULL,
    `source_version` INT NULL,
    `legal_hold_until` DATE NULL,
    `override_reason` TEXT NULL,
    `signage` JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_contracts_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_contracts_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_contract_versions (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `contract_id` CHAR(36) NULL,
    `snapshot` JSON NULL,
    `actor_id` CHAR(36) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_contract_versions_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_contract_versions_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_compliance_rules (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `rule_code` VARCHAR(64) NULL,
    `rule_version` INT NULL,
    `service_code` VARCHAR(64) NULL,
    `days` INT NULL,
    `working_days` TINYINT NULL,
    `offset_hours` INT NULL,
    `valid_from` DATE NULL,
    `source` TEXT NULL,
    `template` TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_compliance_rules_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_compliance_rules_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_compliance_tasks (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `contract_id` CHAR(36) NULL,
    `rule_id` CHAR(36) NULL,
    `rule_version` INT NULL,
    `deadline` DATETIME NULL,
    `status` VARCHAR(32) NULL,
    `channel` VARCHAR(100) NULL,
    `sent_at` DATETIME NULL,
    `receipt` VARCHAR(300) NULL,
    `attachment` VARCHAR(300) NULL,
    `author_id` CHAR(36) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_compliance_tasks_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_compliance_tasks_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_facilities (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `customer_id` CHAR(36) NULL,
    `contract_id` CHAR(36) NULL,
    `address` VARCHAR(300) NULL,
    `city` VARCHAR(100) NULL,
    `lat` DECIMAL(10,7) NULL,
    `lng` DECIMAL(10,7) NULL,
    `radius` INT NULL,
    `timezone` VARCHAR(64) NULL,
    `status` VARCHAR(32) NULL,
    `contact_name` VARCHAR(200) NULL,
    `phone` VARCHAR(32) NULL,
    `polygon` JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_facilities_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_facilities_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_posts (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `facility_id` CHAR(36) NULL,
    `mode` VARCHAR(64) NULL,
    `qualification` INT NULL,
    `headcount` INT NULL,
    `status` VARCHAR(32) NULL,
    `instruction` TEXT NULL,
    `instruction_version` INT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_posts_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_posts_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_instructions (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `post_id` CHAR(36) NULL,
    `text` TEXT NULL,
    `revision` INT NULL,
    `author_id` CHAR(36) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_instructions_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_instructions_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_instruction_receipts (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `instruction_id` CHAR(36) NULL,
    `employee_id` CHAR(36) NULL,
    `acknowledged_at` DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_instruction_receipts_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_instruction_receipts_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_qr_points (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `facility_id` CHAR(36) NULL,
    `post_id` CHAR(36) NULL,
    `token` VARCHAR(100) NULL,
    `status` VARCHAR(32) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_qr_points_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_qr_points_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_shift_templates (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `post_id` CHAR(36) NULL,
    `start_time` VARCHAR(8) NULL,
    `duration_hours` DECIMAL(5,2) NULL,
    `weekdays` JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_shift_templates_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_shift_templates_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_shifts (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `post_id` CHAR(36) NULL,
    `starts_at` DATETIME NULL,
    `ends_at` DATETIME NULL,
    `status` VARCHAR(32) NULL,
    `published` TINYINT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_shifts_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_shifts_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id),
    KEY idx_shift_time(starts_at,ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_assignments (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `shift_id` CHAR(36) NULL,
    `employee_id` CHAR(36) NULL,
    `status` VARCHAR(32) NULL,
    `confirmed_at` DATETIME NULL,
    `override_reason` TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_assignments_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_assignments_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_assignment_history (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `assignment_id` CHAR(36) NULL,
    `old_employee_id` CHAR(36) NULL,
    `new_employee_id` CHAR(36) NULL,
    `reason` TEXT NULL,
    `actor_id` CHAR(36) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_assignment_history_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_assignment_history_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_attendance (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `assignment_id` CHAR(36) NULL,
    `employee_id` CHAR(36) NULL,
    `event_type` VARCHAR(32) NULL,
    `client_time` DATETIME NULL,
    `server_time` DATETIME NULL,
    `lat` DECIMAL(10,7) NULL,
    `lng` DECIMAL(10,7) NULL,
    `accuracy` DECIMAL(10,2) NULL,
    `device_id` VARCHAR(100) NULL,
    `qr_token` VARCHAR(100) NULL,
    `offline` TINYINT NULL,
    `status` VARCHAR(32) NULL,
    `reasons` JSON NULL,
    `idempotency_key` VARCHAR(128) NULL,
    `source_id` CHAR(36) NULL,
    `reason` TEXT NULL,
    `duration_minutes` INT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_attendance_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_attendance_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id),
    UNIQUE KEY uq_attendance_idempotency(tenant_id,idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_incidents (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `facility_id` CHAR(36) NULL,
    `category` VARCHAR(64) NULL,
    `severity` VARCHAR(32) NULL,
    `status` VARCHAR(32) NULL,
    `published` TINYINT NULL,
    `description` TEXT NULL,
    `internal_note` TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_incidents_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_incidents_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_patrols (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `facility_id` CHAR(36) NULL,
    `shift_id` CHAR(36) NULL,
    `status` VARCHAR(32) NULL,
    `completed_points` INT NULL,
    `total_points` INT NULL,
    `finished_at` DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_patrols_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_patrols_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_reports (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `customer_id` CHAR(36) NULL,
    `facility_id` CHAR(36) NULL,
    `period` VARCHAR(64) NULL,
    `status` VARCHAR(32) NULL,
    `published` TINYINT NULL,
    `content` TEXT NULL,
    `internal_note` TEXT NULL,
    `file_path` VARCHAR(300) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_reports_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_reports_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_report_receipts (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `report_id` CHAR(36) NULL,
    `user_id` CHAR(36) NULL,
    `report_version` INT NULL,
    `ip` VARCHAR(64) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_report_receipts_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_report_receipts_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_vacancies (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `facility_id` CHAR(36) NULL,
    `city` VARCHAR(100) NULL,
    `salary_from` DECIMAL(12,2) NULL,
    `salary_to` DECIMAL(12,2) NULL,
    `schedule` VARCHAR(100) NULL,
    `qualification` INT NULL,
    `description` TEXT NULL,
    `status` VARCHAR(32) NULL,
    `pinned_rank` INT NULL,
    `pinned_at` DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_vacancies_tenant(tenant_id,deleted_at),
    UNIQUE KEY uq_vacancy_pin(pinned_rank),
    CONSTRAINT fk_vacancies_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_applications (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `vacancy_id` CHAR(36) NULL,
    `phone` VARCHAR(32) NULL,
    `email` VARCHAR(200) NULL,
    `message` TEXT NULL,
    `status` VARCHAR(32) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_applications_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_applications_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_notifications (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `user_id` CHAR(36) NULL,
    `entity_type` VARCHAR(64) NULL,
    `entity_id` CHAR(36) NULL,
    `dedupe_key` VARCHAR(200) NULL,
    `status` VARCHAR(32) NULL,
    `payload` JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_notifications_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_notifications_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id),
    UNIQUE KEY uq_notifications_dedupe(tenant_id,dedupe_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_outbox (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `topic` VARCHAR(100) NULL,
    `dedupe_key` VARCHAR(200) NULL,
    `payload` JSON NULL,
    `status` VARCHAR(32) NULL,
    `attempts` INT NULL,
    `available_at` DATETIME NULL,
    `last_error` TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_outbox_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_outbox_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id),
    UNIQUE KEY uq_outbox_dedupe(tenant_id,dedupe_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_holidays (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `date` DATE NULL,
    `is_working` TINYINT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_holidays_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_holidays_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_contract_templates (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `text` TEXT NULL,
    `source` TEXT NULL,
    `revision` INT NULL,
    `published` TINYINT NULL,
    `published_by` CHAR(36) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_contract_templates_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_contract_templates_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_no_shows (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `assignment_id` CHAR(36) NULL,
    `status` VARCHAR(32) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_no_shows_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_no_shows_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id),
    UNIQUE KEY uq_no_show(tenant_id,assignment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_break_glass (
    id CHAR(36) PRIMARY KEY,
    tenant_id CHAR(36) NOT NULL,
    `name` VARCHAR(200) NULL,
    `user_id` CHAR(36) NULL,
    `reason` TEXT NULL,
    `expires_at` DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME,
    version INT NOT NULL DEFAULT 1,
    KEY idx_break_glass_tenant(tenant_id,deleted_at),
    CONSTRAINT fk_break_glass_tenant FOREIGN KEY(tenant_id) REFERENCES cp_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
