ALTER TABLE leave_requests
    ADD COLUMN IF NOT EXISTS cancelled_by BIGINT UNSIGNED NULL AFTER review_note,
    ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL AFTER cancelled_by,
    ADD COLUMN IF NOT EXISTS cancellation_source ENUM('user', 'admin') NULL AFTER cancelled_at,
    ADD COLUMN IF NOT EXISTS cancellation_note TEXT NULL AFTER cancellation_source,
    ADD INDEX IF NOT EXISTS idx_leave_requests_cancelled_by (cancelled_by);
