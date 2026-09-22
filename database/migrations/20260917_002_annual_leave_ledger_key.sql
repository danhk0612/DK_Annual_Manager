ALTER TABLE annual_leave_ledger
    ADD COLUMN IF NOT EXISTS ledger_key VARCHAR(160) NULL AFTER amount,
    ADD UNIQUE INDEX IF NOT EXISTS uq_annual_leave_ledger_key (ledger_key);
