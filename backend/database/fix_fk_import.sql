-- ============================================================================
-- Foreign-key-safe import helper
-- ============================================================================
-- Prepend this file to your SQL dump (or run it before importing) so that
-- orphaned rows in a partially-cleaned database do not block the
-- `ALTER TABLE ... ADD FOREIGN KEY` statements that phpMyAdmin / mysqldump
-- emit at the end of an import.
--
-- Usage (command line):
--   cat fix_fk_import.sql your_dump.sql > import_ready.sql
--   mysql -u USER -p DB_NAME < import_ready.sql
--
-- Or paste the two SET lines into the top/bottom of your dump in phpMyAdmin.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- (your dump's CREATE / INSERT / ALTER statements go here)

SET FOREIGN_KEY_CHECKS = 1;