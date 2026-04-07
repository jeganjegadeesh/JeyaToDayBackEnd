/* 06 April 2026 */

ALTER TABLE `stock_entries` ADD `bill_id` MEDIUMTEXT NULL DEFAULT NULL AFTER `retailer_id`, 
ADD `is_billed` BOOLEAN NOT NULL DEFAULT FALSE AFTER `bill_id`;

ALTER TABLE `returns` ADD `bill_id` MEDIUMTEXT NULL DEFAULT NULL AFTER `retailer_id`, 
ADD `is_billed` BOOLEAN NOT NULL DEFAULT FALSE AFTER `bill_id`;

ALTER TABLE `cash_payments` ADD `bill_id` MEDIUMTEXT NULL DEFAULT NULL AFTER `retailer_id`, 
ADD `is_billed` BOOLEAN NOT NULL DEFAULT FALSE AFTER `bill_id`;