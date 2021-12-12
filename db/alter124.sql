ALTER TABLE `events` ADD COLUMN `security_token` CHAR(32) NULL;
ALTER TABLE `tournaments` ADD COLUMN `security_token` CHAR(32) NULL;
