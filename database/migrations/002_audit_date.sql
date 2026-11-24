CREATE INDEX audit_created_at ON audit_logs(created_at);
INSERT INTO schema_migrations(version) VALUES (2);
