# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- custom_field_image
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `custom_field_image`;

CREATE TABLE `custom_field_image`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `custom_field_value_id` INTEGER,
    `file` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `custom_field_image_fi_7441d9` (`custom_field_value_id`),
    CONSTRAINT `custom_field_image_fk_7441d9`
        FOREIGN KEY (`custom_field_value_id`)
            REFERENCES `custom_field_value` (`id`)
            ON UPDATE RESTRICT
            ON DELETE CASCADE
) ENGINE=InnoDB;
SET FOREIGN_KEY_CHECKS = 1;
