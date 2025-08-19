ALTER TABLE tournaments ADD COLUMN imafia_id INT(11) NULL;
ALTER TABLE users ADD COLUMN imafia_id INT(11) NULL;
ALTER TABLE users ADD COLUMN imafia_name VARCHAR(128) NOT NULL;

ALTER TABLE `users` ADD UNIQUE KEY (`imafia_id`);
ALTER TABLE `tournaments` ADD UNIQUE KEY (`imafia_id`);
