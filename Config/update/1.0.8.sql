SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- custom_field: remove FK constraint on custom_field_parent_id
-- This allows the column to reference either a custom_field_parent.id
-- (for field grouping) OR a custom_field.id (for repeater sub-fields)
-- ---------------------------------------------------------------------

ALTER TABLE `custom_field` DROP FOREIGN KEY `custom_field_fk_636d31`;

SET FOREIGN_KEY_CHECKS = 1;
