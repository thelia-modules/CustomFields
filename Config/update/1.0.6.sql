SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- custom_field_option_page
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `custom_field_option_page`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `code` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `custom_field_option_page_u_code` (`code`)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
