SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `custom_field_value` ADD INDEX `custom_field_value_fi_18014d` (`repeater_row_id`);

SET FOREIGN_KEY_CHECKS = 1;
