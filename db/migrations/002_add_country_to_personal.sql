-- Add country column to personal table
ALTER TABLE `personal` ADD COLUMN `country` varchar(100) DEFAULT NULL AFTER `street_address`;