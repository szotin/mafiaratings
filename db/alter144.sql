ALTER TABLE `users` ADD COLUMN `mwt_id` INT(11) NULL;
ALTER TABLE `users` ADD UNIQUE KEY (`mwt_id`);
ALTER TABLE `tournaments` ADD COLUMN `mwt_id` INT(11) NULL;
ALTER TABLE `tournaments` ADD UNIQUE KEY (`mwt_id`);

