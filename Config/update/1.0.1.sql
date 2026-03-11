# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE custom_field_value ADD COLUMN `simple_value` TEXT;

SET FOREIGN_KEY_CHECKS = 1;
