-- ЧОППРО. Целевая архитектурная схема данных MVP.
-- НЕ является production-миграцией. Реальные миграции создаются по этапам 2-4.
-- MySQL 8 / InnoDB / utf8mb4.

CREATE TABLE employees (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  user_id CHAR(36) NULL,
  full_name VARCHAR(255) NOT NULL,
  phone VARCHAR(32) NULL,
  status ENUM('ACTIVE','SUSPENDED','DISMISSED') NOT NULL DEFAULT 'ACTIVE',
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  INDEX idx_employee_tenant_status (tenant_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE document_types (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(255) NOT NULL,
  required_fields JSON NULL,
  default_validity_days INT NULL,
  UNIQUE KEY uq_document_type_tenant_code (tenant_id,code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE employee_documents (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  employee_id CHAR(36) NOT NULL,
  document_type_id CHAR(36) NOT NULL,
  valid_until DATE NULL,
  status ENUM('PENDING_REVIEW','VALID','EXPIRING','EXPIRED','REJECTED') NOT NULL,
  storage_key VARCHAR(512) NULL,
  scan_status ENUM('PENDING','CLEAN','INFECTED','ERROR') NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  INDEX idx_employee_documents_tenant_expiry (tenant_id,status,valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customers (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  name VARCHAR(255) NOT NULL,
  inn VARCHAR(12) NULL,
  status ENUM('ACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  INDEX idx_customer_tenant (tenant_id,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE facilities (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  customer_id CHAR(36) NOT NULL,
  name VARCHAR(255) NOT NULL,
  address TEXT NOT NULL,
  timezone VARCHAR(64) NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  geofence_radius_m INT NULL,
  status ENUM('ACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  INDEX idx_facility_tenant_customer (tenant_id,customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE posts (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  facility_id CHAR(36) NOT NULL,
  name VARCHAR(255) NOT NULL,
  required_headcount INT NOT NULL DEFAULT 1,
  status ENUM('ACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  INDEX idx_post_tenant_facility (tenant_id,facility_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE checkpoints (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  facility_id CHAR(36) NOT NULL,
  name VARCHAR(255) NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  status ENUM('ACTIVE','REVOKED') NOT NULL DEFAULT 'ACTIVE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE shifts (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  post_id CHAR(36) NOT NULL,
  starts_at DATETIME(6) NOT NULL,
  ends_at DATETIME(6) NOT NULL,
  status ENUM('DRAFT','PUBLISHED','IN_PROGRESS','COMPLETED','CANCELLED') NOT NULL,
  version INT NOT NULL DEFAULT 1,
  INDEX idx_shift_tenant_time (tenant_id,starts_at,ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE shift_assignments (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  shift_id CHAR(36) NOT NULL,
  employee_id CHAR(36) NOT NULL,
  status ENUM('ASSIGNED','ACCEPTED','DECLINED','REPLACED','CANCELLED') NOT NULL,
  accepted_at DATETIME(6) NULL,
  version INT NOT NULL DEFAULT 1,
  INDEX idx_assignment_tenant_employee (tenant_id,employee_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE attendance_events (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  assignment_id CHAR(36) NOT NULL,
  event_type ENUM('CHECK_IN','CHECK_OUT','MANUAL_OVERRIDE') NOT NULL,
  client_time DATETIME(6) NOT NULL,
  server_time DATETIME(6) NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  accuracy_m DECIMAL(8,2) NULL,
  device_id CHAR(36) NULL,
  network_state VARCHAR(32) NULL,
  qr_token_version INT NULL,
  idempotency_key VARCHAR(128) NOT NULL,
  result ENUM('VALID','REQUIRES_REVIEW','REJECTED') NOT NULL,
  correlation_id CHAR(36) NOT NULL,
  UNIQUE KEY uq_attendance_idempotency (tenant_id,idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE attendance_reviews (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  attendance_event_id CHAR(36) NOT NULL,
  reviewer_user_id CHAR(36) NOT NULL,
  decision ENUM('CONFIRMED','REJECTED') NOT NULL,
  reason TEXT NOT NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE patrol_routes (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  facility_id CHAR(36) NOT NULL,
  name VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE patrol_runs (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  route_id CHAR(36) NOT NULL,
  assignment_id CHAR(36) NOT NULL,
  started_at DATETIME(6) NULL,
  finished_at DATETIME(6) NULL,
  status ENUM('PLANNED','IN_PROGRESS','COMPLETE','PARTIAL','MISSED') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE checkpoint_scans (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  patrol_run_id CHAR(36) NOT NULL,
  checkpoint_id CHAR(36) NOT NULL,
  client_time DATETIME(6) NOT NULL,
  server_time DATETIME(6) NOT NULL,
  idempotency_key VARCHAR(128) NOT NULL,
  result ENUM('VALID','REQUIRES_REVIEW','REJECTED') NOT NULL,
  UNIQUE KEY uq_checkpoint_scan_idempotency(tenant_id,idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE incidents (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  facility_id CHAR(36) NOT NULL,
  assignment_id CHAR(36) NULL,
  category VARCHAR(64) NOT NULL,
  severity ENUM('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL,
  status ENUM('DRAFT','SUBMITTED','IN_REVIEW','PUBLISHED','CLOSED') NOT NULL,
  description TEXT NULL,
  public_summary TEXT NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  event_key VARCHAR(128) NOT NULL,
  recipient_key VARCHAR(255) NOT NULL,
  channel VARCHAR(32) NOT NULL,
  status ENUM('QUEUED','SENT','DELIVERED','FAILED') NOT NULL,
  payload_json JSON NOT NULL,
  dedupe_key VARCHAR(255) NOT NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_notification_dedupe(tenant_id,dedupe_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customer_reports (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  customer_id CHAR(36) NOT NULL,
  period_from DATE NOT NULL,
  period_to DATE NOT NULL,
  status ENUM('DRAFT','GENERATING','PUBLISHED','FAILED') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customer_report_versions (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NOT NULL,
  report_id CHAR(36) NOT NULL,
  version INT NOT NULL,
  storage_key VARCHAR(512) NOT NULL,
  published_at DATETIME(6) NOT NULL,
  UNIQUE KEY uq_customer_report_version(report_id,version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE outbox_events (
  id CHAR(36) PRIMARY KEY,
  tenant_id CHAR(36) NULL,
  event_type VARCHAR(128) NOT NULL,
  aggregate_type VARCHAR(128) NOT NULL,
  aggregate_id CHAR(36) NULL,
  payload_json JSON NOT NULL,
  status ENUM('PENDING','PROCESSING','DELIVERED','FAILED') NOT NULL DEFAULT 'PENDING',
  attempts INT NOT NULL DEFAULT 0,
  available_at DATETIME(6) NOT NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  INDEX idx_outbox_delivery(status,available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
