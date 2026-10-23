-- Select your existing database. Run once AFTER the address/image migration.
ALTER TABLE co_so ADD COLUMN logo_clb VARCHAR(255) NULL DEFAULT NULL;
