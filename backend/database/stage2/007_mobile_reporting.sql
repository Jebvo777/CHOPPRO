CREATE TABLE IF NOT EXISTS cp_mobile_events (
 id CHAR(36) PRIMARY KEY,tenant_id CHAR(36) NOT NULL,user_id CHAR(36) NOT NULL,event_key VARCHAR(128) NOT NULL,event_type VARCHAR(48) NOT NULL,payload_hash CHAR(64) NOT NULL,response JSON NOT NULL,client_time DATETIME NOT NULL,created_at DATETIME NOT NULL,
 UNIQUE KEY mobile_event_once(tenant_id,user_id,event_key),KEY mobile_event_time(tenant_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_patrol_routes (
 id CHAR(36) PRIMARY KEY,tenant_id CHAR(36) NOT NULL,name VARCHAR(200) NOT NULL,facility_id CHAR(36) NOT NULL,status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',ordered TINYINT NOT NULL DEFAULT 1,window_minutes INT NOT NULL DEFAULT 120,tolerance_minutes INT NOT NULL DEFAULT 15,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,deleted_at DATETIME,version INT NOT NULL DEFAULT 1,KEY route_facility(tenant_id,facility_id,deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_patrol_route_points (
 id CHAR(36) PRIMARY KEY,tenant_id CHAR(36) NOT NULL,name VARCHAR(200) NOT NULL,route_id CHAR(36) NOT NULL,qr_point_id CHAR(36) NOT NULL,position INT NOT NULL,lat DECIMAL(10,7) NOT NULL,lng DECIMAL(10,7) NOT NULL,radius INT NOT NULL DEFAULT 100,offset_minutes INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,deleted_at DATETIME,version INT NOT NULL DEFAULT 1,KEY route_position(tenant_id,route_id,position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_mobile_patrol_runs (
 patrol_id CHAR(36) PRIMARY KEY,tenant_id CHAR(36) NOT NULL,route_id CHAR(36) NOT NULL,assignment_id CHAR(36) NOT NULL,employee_id CHAR(36) NOT NULL,client_key VARCHAR(128) NOT NULL,started_at DATETIME NOT NULL,deadline DATETIME NOT NULL,route_snapshot JSON NOT NULL,auto_closed TINYINT NOT NULL DEFAULT 0,UNIQUE KEY patrol_client(tenant_id,employee_id,client_key),KEY patrol_deadline(deadline)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_patrol_scans (
 id CHAR(36) PRIMARY KEY,tenant_id CHAR(36) NOT NULL,patrol_id CHAR(36) NOT NULL,point_id CHAR(36) NOT NULL,status VARCHAR(32) NOT NULL,client_time DATETIME NOT NULL,server_time DATETIME NOT NULL,lat DECIMAL(10,7),lng DECIMAL(10,7),accuracy DECIMAL(10,2),device_id VARCHAR(100) NOT NULL,qr_hash CHAR(64) NOT NULL,reasons JSON NOT NULL,explanation TEXT,KEY patrol_scan_lookup(tenant_id,patrol_id,point_id),KEY patrol_scan_time(tenant_id,client_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_mobile_incidents (
 incident_id CHAR(36) PRIMARY KEY,tenant_id CHAR(36) NOT NULL,employee_id CHAR(36) NOT NULL,assignment_id CHAR(36) NOT NULL,client_time DATETIME NOT NULL,measures TEXT NOT NULL,device_id VARCHAR(100) NOT NULL,escalation_status VARCHAR(32) NOT NULL DEFAULT 'NOT_CONNECTED',KEY mobile_incident_owner(tenant_id,employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_mobile_uploads (
 tenant_id CHAR(36) NOT NULL,user_id CHAR(36) NOT NULL,event_key VARCHAR(128) NOT NULL,file_id CHAR(36) NOT NULL,sha256 CHAR(64) NOT NULL,entity_id CHAR(36) NOT NULL,PRIMARY KEY(tenant_id,user_id,event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_attendance_explanations (
 id CHAR(36) PRIMARY KEY,tenant_id CHAR(36) NOT NULL,attendance_id CHAR(36) NOT NULL,user_id CHAR(36) NOT NULL,employee_id CHAR(36) NOT NULL,reason TEXT NOT NULL,client_time DATETIME NOT NULL,created_at DATETIME NOT NULL,KEY explanation_attendance(tenant_id,attendance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_mobile_policies (
 id CHAR(36) PRIMARY KEY,tenant_id CHAR(36) NOT NULL,name VARCHAR(200) NOT NULL,basis VARCHAR(200) NOT NULL,text TEXT NOT NULL,revision INT NOT NULL,status VARCHAR(32) NOT NULL DEFAULT 'DRAFT',created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,deleted_at DATETIME,version INT NOT NULL DEFAULT 1,UNIQUE KEY policy_revision(tenant_id,revision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_policy_receipts (
 id CHAR(36) PRIMARY KEY,tenant_id CHAR(36) NOT NULL,user_id CHAR(36) NOT NULL,policy_id CHAR(36) NOT NULL,revision INT NOT NULL,client_time DATETIME NOT NULL,created_at DATETIME NOT NULL,ip VARCHAR(64),UNIQUE KEY policy_receipt_once(user_id,policy_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_report_jobs (
 id CHAR(36) PRIMARY KEY,tenant_id CHAR(36) NOT NULL,user_id CHAR(36) NOT NULL,facility_id CHAR(36) NOT NULL,period VARCHAR(7) NOT NULL,status VARCHAR(32) NOT NULL DEFAULT 'GENERATING',report_id CHAR(36),attempts INT NOT NULL DEFAULT 0,error VARCHAR(200),created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE KEY monthly_report_once(tenant_id,facility_id,period),KEY report_job_status(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cp_backup_runs (
 id CHAR(36) PRIMARY KEY,status VARCHAR(32) NOT NULL,sha256 CHAR(64),bytes BIGINT,counts JSON,table_count INT,row_count BIGINT,file_count INT,error VARCHAR(200),created_at DATETIME NOT NULL,finished_at DATETIME,restored_at DATETIME,KEY backup_run_time(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
