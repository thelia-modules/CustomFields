SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- custom_field: add repeater type and position
-- ---------------------------------------------------------------------

ALTER TABLE `custom_field` ADD COLUMN `position` INTEGER DEFAULT 0;

-- ---------------------------------------------------------------------
-- custom_field_value: add repeater_row_id column
-- ---------------------------------------------------------------------

ALTER TABLE `custom_field_value` ADD COLUMN `repeater_row_id` INTEGER;

SET FOREIGN_KEY_CHECKS = 1;
