use mafia;

ALTER TABLE `tournaments` ADD COLUMN `scoring_weight` float NOT NULL DEFAULT 1;
