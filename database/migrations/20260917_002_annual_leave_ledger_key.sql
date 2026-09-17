ALTER TABLE annual_leave_ledger
    ADD COLUMN ledger_key VARCHAR(160) NULL AFTER amount,
    ADD UNIQUE KEY uq_annual_leave_ledger_key (ledger_key);
