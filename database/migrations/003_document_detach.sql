ALTER TABLE documents ADD COLUMN detached_at TIMESTAMP NULL DEFAULT NULL;
INSERT INTO schema_migrations(version) VALUES (3);
