
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- custom_field
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `custom_field`;

CREATE TABLE `custom_field`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(100) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `type` TINYINT DEFAULT 0 NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `custom_field_u_4db226` (`code`)
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
    `source_id` INTEGER NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `uq_cfv_field_source` (`custom_field_id`, `source`, `source_id`),
    INDEX `idx_cfv_source` (`source`, `source_id`),
    CONSTRAINT `custom_field_value_fk_361737`
        FOREIGN KEY (`custom_field_id`)
        REFERENCES `custom_field` (`id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE
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
