ALTER TABLE service_assignments ADD INDEX staffing_history(unit_id,position_title,service_status,start_on);

CREATE TABLE staffing_targets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unit_id INT UNSIGNED NOT NULL,
    period DATE NOT NULL,
    position_title VARCHAR(160) NOT NULL,
    required_count INT UNSIGNED NOT NULL,
    UNIQUE(unit_id,period,position_title),
    INDEX(period,unit_id),
    CHECK (DAYOFMONTH(period)=1),
    FOREIGN KEY(unit_id) REFERENCES units(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_batches (
    token CHAR(64) PRIMARY KEY,
    author_id INT UNSIGNED NOT NULL,
    draft_count INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(author_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations(version) VALUES (4);
