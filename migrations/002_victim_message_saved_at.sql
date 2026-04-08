-- When each victim saved each line (UTC). Run on existing DBs.

ALTER TABLE victim_message
    ADD COLUMN saved_at DATETIME NULL;

UPDATE victim_message SET saved_at = UTC_TIMESTAMP() WHERE saved_at IS NULL;

ALTER TABLE victim_message
    MODIFY COLUMN saved_at DATETIME NOT NULL;
