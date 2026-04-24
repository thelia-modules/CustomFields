SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- custom_field: add repeater type and position
-- ---------------------------------------------------------------------

ALTER TABLE `custom_field` ADD COLUMN `position` INTEGER DEFAULT 0;

-- ---------------------------------------------------------------------
-- custom_field_value: add repeater_row_id column
-- ---------------------------------------------------------------------

ALTER TABLE `custom_field_value` ADD COLUMN `repeater_row_id` INTEGER;

ALTER TABLE `custom_field_value` ADD INDEX `custom_field_value_fi_18014d` (`repeater_row_id`),;

ALTER TABLE `custom_field_value` CONSTRAINT `custom_field_value_fk_18014d`
    FOREIGN KEY (`repeater_row_id`)
    REFERENCES `custom_field_repeater_row` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE;

-- ---------------------------------------------------------------------
-- custom_field_repeater_row
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `custom_field_repeater_row`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `custom_field_id` INTEGER NOT NULL,
    `source` VARCHAR(100) NOT NULL,
    `source_id` INTEGER,
    `position` INTEGER DEFAULT 0 NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_cfrr_source` (`source`, `source_id`, `custom_field_id`),
    INDEX `custom_field_repeater_row_fi_361737` (`custom_field_id`),
    CONSTRAINT `custom_field_repeater_row_fk_361737`
        FOREIGN KEY (`custom_field_id`)
        REFERENCES `custom_field` (`id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE `custom_field` DROP FOREIGN KEY `custom_field_fk_636d31`;
ALTER TABLE `custom_field` DROP INDEX `custom_field_u_4db226`;

SET FOREIGN_KEY_CHECKS = 1;
