
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- custom_field_parent
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `custom_field_parent`;

CREATE TABLE `custom_field_parent`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- custom_field
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `custom_field`;

CREATE TABLE `custom_field`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(100) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `is_international` TINYINT(1) DEFAULT 1 NOT NULL,
    `type` TINYINT DEFAULT 0 NOT NULL,
    `position` INTEGER DEFAULT 0,
    `custom_field_parent_id` INTEGER,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `custom_field_u_4db226` (`code`),
    INDEX `custom_field_fi_636d31` (`custom_field_parent_id`),
    CONSTRAINT `custom_field_fk_636d31`
        FOREIGN KEY (`custom_field_parent_id`)
        REFERENCES `custom_field_parent` (`id`)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- custom_field_source
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `custom_field_source`;

CREATE TABLE `custom_field_source`
(
    `custom_field_id` INTEGER NOT NULL,
    `source` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`custom_field_id`,`source`),
    CONSTRAINT `custom_field_source_fk_361737`
        FOREIGN KEY (`custom_field_id`)
        REFERENCES `custom_field` (`id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- custom_field_value
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `custom_field_value`;

CREATE TABLE `custom_field_value`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `custom_field_id` INTEGER NOT NULL,
    `source` VARCHAR(100) NOT NULL,
    `source_id` INTEGER,
    `repeater_row_id` INTEGER,
    `simple_value` TEXT,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `uq_cfv_field_source` (`custom_field_id`, `source`, `source_id`, `repeater_row_id`),
    INDEX `idx_cfv_source` (`source`, `source_id`),
    INDEX `custom_field_value_fi_18014d` (`repeater_row_id`),
    CONSTRAINT `custom_field_value_fk_361737`
        FOREIGN KEY (`custom_field_id`)
        REFERENCES `custom_field` (`id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `custom_field_value_fk_18014d`
        FOREIGN KEY (`repeater_row_id`)
        REFERENCES `custom_field_repeater_row` (`id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- custom_field_repeater_row
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `custom_field_repeater_row`;

CREATE TABLE `custom_field_repeater_row`
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

-- ---------------------------------------------------------------------
-- custom_field_option_page
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `custom_field_option_page`;

CREATE TABLE `custom_field_option_page`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `code` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `custom_field_option_page_u_4db226` (`code`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- custom_field_value_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `custom_field_value_i18n`;

CREATE TABLE `custom_field_value_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `value` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `custom_field_value_i18n_fk_b4af85`
        FOREIGN KEY (`id`)
        REFERENCES `custom_field_value` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
