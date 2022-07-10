ALTER TABLE `event_places` ADD COLUMN `importance` FLOAT NOT NULL;
ALTER TABLE `tournament_places` ADD COLUMN `importance` FLOAT NOT NULL;
ALTER TABLE `series_places` ADD COLUMN `importance` FLOAT NOT NULL;

ALTER TABLE `event_places` ADD KEY (`user_id`, `importance`);
ALTER TABLE `tournament_places` ADD KEY (`user_id`, `importance`);
ALTER TABLE `series_places` ADD KEY (`user_id`, `importance`);
