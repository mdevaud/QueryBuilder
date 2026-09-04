# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- query_builder_rule
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `query_builder_rule`;

CREATE TABLE `query_builder_rule`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `context` VARCHAR(50) NOT NULL,
    `hooks` TEXT,
    `condition_tree` TEXT,
    `activate` TINYINT DEFAULT 0,
    `position` INTEGER DEFAULT 0,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- query_builder_action
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `query_builder_action`;

CREATE TABLE `query_builder_action`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `rule_id` INTEGER NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `code` VARCHAR(100) NOT NULL,
    `type` VARCHAR(20) NOT NULL,
    `condition_tree` TEXT,
    `parameters` TEXT,
    `activate` TINYINT DEFAULT 0,
    `position` INTEGER DEFAULT 0,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `fi_query_builder_action_rule_id` (`rule_id`),
    CONSTRAINT `fk_query_builder_action_rule_id`
        FOREIGN KEY (`rule_id`)
        REFERENCES `query_builder_rule` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- query_builder_suggestion
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `query_builder_suggestion`;

CREATE TABLE `query_builder_suggestion`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `customer_id` INTEGER NOT NULL,
    `product_id` INTEGER NOT NULL,
    `rule_id` INTEGER NOT NULL,
    `action_id` INTEGER NOT NULL,
    `hook` VARCHAR(100),
    `displayed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL,
    `purchased_at` TIMESTAMP NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `query_builder_suggestion_unique` (`customer_id`, `product_id`, `action_id`),
    INDEX `idx_query_builder_suggestion_active` (`customer_id`, `purchased_at`, `expires_at`),
    INDEX `fi_query_builder_suggestion_product_id` (`product_id`),
    INDEX `fi_query_builder_suggestion_rule_id` (`rule_id`),
    INDEX `fi_query_builder_suggestion_action_id` (`action_id`),
    CONSTRAINT `fk_query_builder_suggestion_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_query_builder_suggestion_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_query_builder_suggestion_rule_id`
        FOREIGN KEY (`rule_id`)
        REFERENCES `query_builder_rule` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_query_builder_suggestion_action_id`
        FOREIGN KEY (`action_id`)
        REFERENCES `query_builder_action` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
