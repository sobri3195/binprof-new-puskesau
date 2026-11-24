SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE schema_migrations (
  version INT PRIMARY KEY,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_settings (
  name VARCHAR(80) PRIMARY KEY,
  value VARCHAR(1000) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (code VARCHAR(32) PRIMARY KEY, label VARCHAR(80) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  session_version INT UNSIGNED NOT NULL DEFAULT 1,
  case_manager BOOLEAN NOT NULL DEFAULT FALSE,
  case_creator BOOLEAN NOT NULL DEFAULT FALSE,
  case_cross_unit BOOLEAN NOT NULL DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE user_roles (
  user_id INT UNSIGNED NOT NULL,
  role_code VARCHAR(32) NOT NULL,
  PRIMARY KEY(user_id, role_code),
  FOREIGN KEY(user_id) REFERENCES users(id),
  FOREIGN KEY(role_code) REFERENCES roles(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE user_verification_modules (
  user_id INT UNSIGNED NOT NULL,
  module ENUM('profile','dikum','dikmil','award','retirement','service') NOT NULL,
  PRIMARY KEY(user_id,module),
  FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE units (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  parent_id INT UNSIGNED NULL,
  latitude DECIMAL(9,6) NULL,
  longitude DECIMAL(9,6) NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  CHECK ((latitude IS NULL AND longitude IS NULL) OR
    (latitude IS NOT NULL AND longitude IS NOT NULL AND latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180)),
  FOREIGN KEY(parent_id) REFERENCES units(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE user_unit_scopes (
  user_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED NOT NULL,
  PRIMARY KEY(user_id, unit_id),
  INDEX(unit_id, user_id),
  FOREIGN KEY(user_id) REFERENCES users(id),
  FOREIGN KEY(unit_id) REFERENCES units(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE identifier_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE personnel_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  identifier_type_id INT UNSIGNED NOT NULL,
  dikmil BOOLEAN NOT NULL DEFAULT FALSE,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  FOREIGN KEY(identifier_type_id) REFERENCES identifier_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE reference_values (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('rank','grade','corps','profession','dikum','dikmil','award') NOT NULL,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(160) NOT NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  UNIQUE(kind,code),
  INDEX(kind,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE facilities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unit_id INT UNSIGNED NOT NULL,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  FOREIGN KEY(unit_id) REFERENCES units(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE personnel (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unit_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  birth_cipher TEXT NULL,
  rank_id INT UNSIGNED NULL,
  grade_id INT UNSIGNED NULL,
  corps_id INT UNSIGNED NULL,
  profession_id INT UNSIGNED NULL,
  specialty VARCHAR(160) NULL,
  facility_id INT UNSIGNED NULL,
  position_title VARCHAR(160) NULL,
  service_status ENUM('active','retired','inactive','archived') NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(unit_id,service_status,category_id), INDEX(name,id),
  FOREIGN KEY(unit_id) REFERENCES units(id),
  FOREIGN KEY(category_id) REFERENCES personnel_categories(id),
  FOREIGN KEY(rank_id) REFERENCES reference_values(id),
  FOREIGN KEY(grade_id) REFERENCES reference_values(id),
  FOREIGN KEY(corps_id) REFERENCES reference_values(id),
  FOREIGN KEY(profession_id) REFERENCES reference_values(id),
  FOREIGN KEY(facility_id) REFERENCES facilities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE personnel_identifiers (
  personnel_id INT UNSIGNED PRIMARY KEY,
  type_id INT UNSIGNED NOT NULL,
  digest BINARY(32) NOT NULL,
  cipher TEXT NOT NULL,
  UNIQUE(type_id,digest),
  FOREIGN KEY(personnel_id) REFERENCES personnel(id),
  FOREIGN KEY(type_id) REFERENCES identifier_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE service_assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  personnel_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED NOT NULL,
  facility_id INT UNSIGNED NULL,
  position_title VARCHAR(160) NULL,
  service_status ENUM('active','retired','inactive','archived') NOT NULL,
  start_on DATE NOT NULL,
  end_before DATE NULL,
  open_personnel INT UNSIGNED GENERATED ALWAYS AS (IF(end_before IS NULL,personnel_id,NULL)) STORED,
  UNIQUE(open_personnel), INDEX(personnel_id,start_on),
  CHECK (end_before IS NULL OR end_before >= start_on),
  FOREIGN KEY(personnel_id) REFERENCES personnel(id),
  FOREIGN KEY(unit_id) REFERENCES units(id),
  FOREIGN KEY(facility_id) REFERENCES facilities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE education_histories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  personnel_id INT UNSIGNED NOT NULL,
  kind ENUM('dikum','dikmil') NOT NULL,
  type_id INT UNSIGNED NOT NULL,
  institution VARCHAR(160) NOT NULL,
  program VARCHAR(160) NULL,
  cohort VARCHAR(60) NULL,
  start_on DATE NULL, end_on DATE NULL,
  start_year SMALLINT UNSIGNED NULL, end_year SMALLINT UNSIGNED NULL,
  status ENUM('ongoing','completed','not_applicable') NOT NULL,
  outcome VARCHAR(160) NULL,
  certificate_cipher TEXT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  INDEX(personnel_id,kind,type_id), INDEX(kind,type_id,personnel_id),
  CHECK (start_on IS NULL OR start_year IS NULL),
  CHECK (end_on IS NULL OR end_year IS NULL),
  CHECK (start_on IS NULL OR end_on IS NULL OR end_on >= start_on),
  FOREIGN KEY(personnel_id) REFERENCES personnel(id),
  FOREIGN KEY(type_id) REFERENCES reference_values(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE personnel_awards (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  personnel_id INT UNSIGNED NOT NULL,
  type_id INT UNSIGNED NOT NULL,
  issuer VARCHAR(160) NOT NULL,
  level_name VARCHAR(100) NULL,
  awarded_on DATE NOT NULL,
  decision_number VARCHAR(120) NULL,
  description VARCHAR(500) NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  INDEX(personnel_id,type_id,awarded_on),
  FOREIGN KEY(personnel_id) REFERENCES personnel(id),
  FOREIGN KEY(type_id) REFERENCES reference_values(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE retirement_forecasts (
  personnel_id INT UNSIGNED PRIMARY KEY,
  estimate_date DATE NOT NULL,
  source VARCHAR(500) NOT NULL,
  method ENUM('manual_verified') NOT NULL DEFAULT 'manual_verified',
  validation_status ENUM('valid','review') NOT NULL DEFAULT 'valid',
  version INT UNSIGNED NOT NULL,
  basis_version INT UNSIGNED NOT NULL,
  verified_by INT UNSIGNED NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(validation_status,estimate_date),
  FOREIGN KEY(personnel_id) REFERENCES personnel(id),
  FOREIGN KEY(verified_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE disciplinary_cases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unit_id INT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 0,
  content_cipher MEDIUMTEXT NULL,
  INDEX(unit_id),
  FOREIGN KEY(unit_id) REFERENCES units(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE case_personnel (
  case_id INT UNSIGNED NOT NULL,
  personnel_id INT UNSIGNED NOT NULL,
  role_cipher TEXT NOT NULL,
  PRIMARY KEY(case_id,personnel_id), INDEX(personnel_id,case_id),
  FOREIGN KEY(case_id) REFERENCES disciplinary_cases(id),
  FOREIGN KEY(personnel_id) REFERENCES personnel(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE case_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  case_id INT UNSIGNED NOT NULL,
  actor_id INT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL,
  content_cipher MEDIUMTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(case_id,version),
  FOREIGN KEY(case_id) REFERENCES disciplinary_cases(id),
  FOREIGN KEY(actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE case_access_grants (
  case_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  capability ENUM('read','write','submit','verify','download','export') NOT NULL,
  granted_by INT UNSIGNED NOT NULL,
  expires_on DATE NULL,
  PRIMARY KEY(case_id,user_id,capability), INDEX(user_id,capability,case_id),
  FOREIGN KEY(case_id) REFERENCES disciplinary_cases(id),
  FOREIGN KEY(user_id) REFERENCES users(id),
  FOREIGN KEY(granted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE change_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  module ENUM('profile','dikum','dikmil','award','retirement','service','case') NOT NULL,
  unit_id INT UNSIGNED NOT NULL,
  personnel_id INT UNSIGNED NULL,
  case_id INT UNSIGNED NULL,
  education_id INT UNSIGNED NULL,
  award_id INT UNSIGNED NULL,
  owner_id INT UNSIGNED NOT NULL,
  status ENUM('draft','submitted','returned','approved','cancelled') NOT NULL DEFAULT 'draft',
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX(unit_id,status,module), INDEX(owner_id,status), INDEX(personnel_id), INDEX(case_id),
  CHECK ((module = 'case' AND case_id IS NOT NULL AND personnel_id IS NULL) OR (module <> 'case' AND case_id IS NULL)),
  FOREIGN KEY(unit_id) REFERENCES units(id),
  FOREIGN KEY(personnel_id) REFERENCES personnel(id),
  FOREIGN KEY(case_id) REFERENCES disciplinary_cases(id),
  FOREIGN KEY(education_id) REFERENCES education_histories(id),
  FOREIGN KEY(award_id) REFERENCES personnel_awards(id),
  FOREIGN KEY(owner_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE change_request_revisions (
  request_id INT UNSIGNED NOT NULL,
  revision INT UNSIGNED NOT NULL,
  base_version INT UNSIGNED NOT NULL,
  before_cipher MEDIUMTEXT NOT NULL,
  payload_cipher MEDIUMTEXT NOT NULL,
  submitted_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY(request_id,revision),
  FOREIGN KEY(request_id) REFERENCES change_requests(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE approval_actions (
  request_id INT UNSIGNED NOT NULL,
  revision INT UNSIGNED NOT NULL,
  actor_id INT UNSIGNED NOT NULL,
  decision ENUM('returned','approved','cancelled') NOT NULL,
  comment_cipher TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(request_id,revision),
  FOREIGN KEY(request_id,revision) REFERENCES change_request_revisions(request_id,revision),
  FOREIGN KEY(actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL,
  revision INT UNSIGNED NOT NULL,
  storage_key CHAR(48) NOT NULL UNIQUE,
  mime VARCHAR(40) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  checksum CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(request_id,revision) REFERENCES change_request_revisions(request_id,revision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE scheduled_service (
  request_id INT UNSIGNED PRIMARY KEY,
  personnel_id INT UNSIGNED NOT NULL,
  effective_on DATE NOT NULL,
  state ENUM('scheduled','applied','conflict') NOT NULL DEFAULT 'scheduled',
  INDEX(state,effective_on,request_id),
  FOREIGN KEY(request_id) REFERENCES change_requests(id),
  FOREIGN KEY(personnel_id) REFERENCES personnel(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dedupe_key CHAR(64) NOT NULL UNIQUE,
  recipient_id INT UNSIGNED NOT NULL,
  personnel_id INT UNSIGNED NULL,
  request_id INT UNSIGNED NULL,
  forecast_version INT UNSIGNED NULL,
  rule_version INT UNSIGNED NULL,
  kind VARCHAR(32) NOT NULL,
  read_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX(recipient_id,id), INDEX(personnel_id,forecast_version),
  FOREIGN KEY(recipient_id) REFERENCES users(id),
  FOREIGN KEY(personnel_id) REFERENCES personnel(id),
  FOREIGN KEY(request_id) REFERENCES change_requests(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id INT UNSIGNED NULL,
  event VARCHAR(48) NOT NULL,
  object_id INT UNSIGNED NULL,
  request_token CHAR(24) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX(actor_id,id),
  FOREIGN KEY(actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE login_limits (
  bucket BINARY(32) PRIMARY KEY,
  attempts SMALLINT UNSIGNED NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  INDEX(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE job_locks (
  name VARCHAR(40) PRIMARY KEY,
  token CHAR(32) NULL,
  expires_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE job_runs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at TIMESTAMP NULL DEFAULT NULL,
  processed INT UNSIGNED NOT NULL DEFAULT 0,
  failures INT UNSIGNED NOT NULL DEFAULT 0,
  reason VARCHAR(40) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles VALUES ('admin','Administrator sistem'),('operator','Operator satuan'),('verifier','Verifikator pusat'),('leader','Pimpinan'),('exporter','Ekspor personel'),('downloader','Unduh personel');
INSERT INTO identifier_types(id,code,name) VALUES(1,'NRP','NRP'),(2,'NIP','NIP');
INSERT INTO personnel_categories(code,name,identifier_type_id,dikmil) VALUES('MILITER','Militer',1,TRUE),('PNS','PNS',2,FALSE);
INSERT INTO app_settings VALUES ('bootstrap_done','0'),('reminders','12,6,3,1'),('reminder_version','1');
INSERT INTO job_locks(name) VALUES ('daily');
INSERT INTO schema_migrations(version) VALUES (1);

-- Migration 002. Index the operational history for date-based review.
CREATE INDEX audit_created_at ON audit_logs(created_at);
INSERT INTO schema_migrations(version) VALUES (2);

-- Migration 003. Keep detached draft files for audit and recovery.
ALTER TABLE documents ADD COLUMN detached_at TIMESTAMP NULL DEFAULT NULL;
INSERT INTO schema_migrations(version) VALUES (3);
