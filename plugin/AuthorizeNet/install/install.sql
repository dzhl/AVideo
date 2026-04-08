CREATE TABLE IF NOT EXISTS `anet_webhook_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uniq_key` VARCHAR(120) NOT NULL,
  `event_type` VARCHAR(120) NOT NULL,
  `trans_id` VARCHAR(45) NULL,
  `payload_json` JSON NOT NULL,
  `processed` TINYINT(1) NOT NULL DEFAULT 0,
  `error_text` TEXT NULL,
  `status` VARCHAR(45) NULL,
  `created_php_time` BIGINT NULL,
  `modified_php_time` BIGINT NULL,
  `users_id` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `uniq_key_UNIQUE` (`uniq_key` ASC),
  INDEX `fk_anet_webhook_log_users1_idx` (`users_id` ASC),
  INDEX `event_type_index` (`event_type` ASC),
  CONSTRAINT `fk_anet_webhook_log_users1`
    FOREIGN KEY (`users_id`)
    REFERENCES `users` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `anet_pending_payment` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ref_id` VARCHAR(40) NOT NULL,
  `users_id` INT(11) NOT NULL,
  `plans_id` INT(11) NOT NULL DEFAULT 0,
  `amount` DECIMAL(12,2) NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `transaction_id` VARCHAR(45) NULL,
  `metadata_json` LONGTEXT NULL,
  `attempts` INT(11) NOT NULL DEFAULT 0,
  `last_checked_php_time` BIGINT NULL,
  `created_php_time` BIGINT NOT NULL,
  `modified_php_time` BIGINT NOT NULL,
  `error_text` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `anet_pending_payment_ref_id_uq` (`ref_id` ASC),
  INDEX `anet_pending_payment_users_id_idx` (`users_id` ASC),
  INDEX `anet_pending_payment_status_idx` (`status` ASC),
  INDEX `anet_pending_payment_transaction_id_idx` (`transaction_id` ASC))
ENGINE = InnoDB;
