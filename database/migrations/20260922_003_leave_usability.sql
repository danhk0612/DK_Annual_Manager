SET NAMES utf8mb4;

ALTER TABLE users
    ADD COLUMN department VARCHAR(100) NULL AFTER telegram_username,
    ADD COLUMN position VARCHAR(100) NULL AFTER department;

ALTER TABLE leave_requests
    ADD COLUMN half_day_period ENUM('am', 'pm') NULL AFTER requested_amount;

UPDATE leave_types SET is_active = 0 WHERE code = 'P';

INSERT INTO leave_types (code, name, default_amount, deducts_annual_leave, is_active, sort_order)
VALUES ('G', '공가', 1.00, 0, 1, 30)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    default_amount = VALUES(default_amount),
    deducts_annual_leave = VALUES(deducts_annual_leave),
    is_active = VALUES(is_active),
    sort_order = VALUES(sort_order);

UPDATE leave_types SET name = '대체휴가', deducts_annual_leave = 0, is_active = 1, sort_order = 50 WHERE code = 'A';
UPDATE leave_types SET deducts_annual_leave = 0, is_active = 1, sort_order = 40 WHERE code = 'S';
