CREATE INDEX ix_attendance_assignment_time ON cp_attendance(assignment_id,client_time,status);
CREATE INDEX ix_attendance_source_status ON cp_attendance(source_id,status);
CREATE INDEX ix_attendance_server_time ON cp_attendance(server_time,assignment_id);
CREATE INDEX ix_assignments_shift_status ON cp_assignments(shift_id,status,deleted_at);
CREATE INDEX ix_shifts_post_time ON cp_shifts(post_id,starts_at,ends_at);
CREATE INDEX ix_shifts_tenant_time ON cp_shifts(tenant_id,starts_at,ends_at,published);
CREATE INDEX ix_no_shows_assignment ON cp_no_shows(assignment_id,status);
