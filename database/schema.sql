-- Apply this schema after creating the database configured in config/config.php.
-- The application database user does not need CREATE DATABASE permission.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS schema_migrations (
    migration_name VARCHAR(190) PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    telegram_user_id BIGINT UNSIGNED NULL,
    telegram_username VARCHAR(100) NULL,
    department VARCHAR(100) NULL,
    position VARCHAR(100) NULL,
    hire_date DATE NULL,
    employment_end_date DATE NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    status ENUM('pending', 'active', 'inactive') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_telegram_user_id (telegram_user_id),
    KEY idx_users_status (status),
    KEY idx_users_hire_date (hire_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_types (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL,
    name VARCHAR(50) NOT NULL,
    default_amount DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    deducts_annual_leave TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_leave_types_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO leave_types (code, name, default_amount, deducts_annual_leave, sort_order) VALUES
    ('V', '연차', 1.00, 1, 10),
    ('H', '반차', 0.50, 1, 20),
    ('G', '공가', 1.00, 0, 30),
    ('S', '병가', 1.00, 0, 40),
    ('A', '대체휴가', 1.00, 0, 50);

CREATE TABLE IF NOT EXISTS leave_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    leave_type_id SMALLINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    requested_amount DECIMAL(6,2) NOT NULL DEFAULT 0,
    half_day_period ENUM('am', 'pm') NULL,
    reason TEXT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    review_note TEXT NULL,
    cancelled_by BIGINT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    cancellation_source ENUM('user', 'admin') NULL,
    cancellation_note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_requests_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_leave_requests_type FOREIGN KEY (leave_type_id) REFERENCES leave_types(id),
    CONSTRAINT fk_leave_requests_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id),
    KEY idx_leave_requests_user_status (user_id, status),
    KEY idx_leave_requests_dates (start_date, end_date),
    KEY idx_leave_requests_status_created (status, created_at),
    KEY idx_leave_requests_cancelled_by (cancelled_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_request_days (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    leave_request_id BIGINT UNSIGNED NOT NULL,
    leave_date DATE NOT NULL,
    amount DECIMAL(4,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_request_days_request
        FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id) ON DELETE CASCADE,
    UNIQUE KEY uq_leave_request_day (leave_request_id, leave_date),
    KEY idx_leave_request_days_date (leave_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS annual_leave_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    leave_year SMALLINT UNSIGNED NOT NULL,
    transaction_type ENUM('grant', 'carryover', 'adjustment', 'usage', 'reversal') NOT NULL,
    amount DECIMAL(6,2) NOT NULL,
    ledger_key VARCHAR(160) NULL,
    reference_request_id BIGINT UNSIGNED NULL,
    note VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_annual_leave_ledger_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_annual_leave_ledger_request FOREIGN KEY (reference_request_id) REFERENCES leave_requests(id),
    CONSTRAINT fk_annual_leave_ledger_creator FOREIGN KEY (created_by) REFERENCES users(id),
    UNIQUE KEY uq_annual_leave_ledger_key (ledger_key),
    KEY idx_annual_leave_ledger_user_year (user_id, leave_year),
    KEY idx_annual_leave_ledger_request (reference_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS holidays (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL,
    name VARCHAR(120) NOT NULL,
    source ENUM('public_api', 'manual', 'company') NOT NULL DEFAULT 'public_api',
    is_public_holiday TINYINT(1) NOT NULL DEFAULT 1,
    external_key VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_holidays_date_name_source (holiday_date, name, source),
    KEY idx_holidays_date (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_app_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) NULL,
    target_id BIGINT UNSIGNED NULL,
    details_json LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
    KEY idx_audit_logs_created_at (created_at),
    KEY idx_audit_logs_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (migration_name) VALUES
    ('20260917_001_users_hire_date_nullable.sql'),
    ('20260917_002_annual_leave_ledger_key.sql'),
    ('20260922_003_leave_usability.sql'),
    ('20260922_004_leave_cancellation_metadata.sql');
