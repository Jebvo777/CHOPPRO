CREATE TABLE IF NOT EXISTS cp_data_migrations (
    name VARCHAR(120) PRIMARY KEY,
    applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX ix_documents_filter_type_expiry ON cp_documents(tenant_id,type_id,expires_at,status);
CREATE INDEX ix_applications_vacancy_status ON cp_applications(tenant_id,vacancy_id,status);
CREATE INDEX ix_vacancies_public_filters ON cp_vacancies(status,city,qualification,salary_to);
