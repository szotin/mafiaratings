ALTER TABLE `series_places` ADD COLUMN `tournaments` INT(11) NOT NULL;
ALTER TABLE `series_places` ADD COLUMN `games` INT(11) NOT NULL;
ALTER TABLE `series_places` ADD COLUMN `wins` INT(11) NOT NULL;
ALTER TABLE `tournament_places` ADD COLUMN `wins` INT(11) NOT NULL;

