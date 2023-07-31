ALTER TABLE `series_tournaments` ADD COLUMN `flags` INT(11) NOT NULL;
ALTER TABLE `series_tournaments` ADD COLUMN `fee` INT(11) NOT NULL;

ALTER TABLE `series` ADD COLUMN `per_player_fee` FLOAT NOT NULL;

CREATE TABLE `currencies` (
`id` INT(11) NOT NULL AUTO_INCREMENT,
`name_id` INT(11) NOT NULL,
`pattern` VARCHAR(128) NOT NULL,

PRIMARY KEY (`id`),
KEY (`name_id`),
CONSTRAINT `currency_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`),
INDEX currency_pattern (`pattern`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `countries` ADD COLUMN `currency_id` INT(11) NULL;
ALTER TABLE `countries` ADD KEY (`currency_id`);
ALTER TABLE `countries` ADD CONSTRAINT country_currency FOREIGN KEY(currency_id) REFERENCES currencies(id);

ALTER TABLE `clubs` ADD COLUMN `fee` INT(11) NULL;
ALTER TABLE `clubs` ADD COLUMN `currency_id` INT(11) NULL;
ALTER TABLE `clubs` ADD KEY (`currency_id`);
ALTER TABLE `clubs` ADD CONSTRAINT club_currency FOREIGN KEY(currency_id) REFERENCES currencies(id);

ALTER TABLE `events` ADD COLUMN `fee` INT(11) NULL;
ALTER TABLE `events` ADD COLUMN `currency_id` INT(11) NULL;
ALTER TABLE `events` ADD KEY (`currency_id`);
ALTER TABLE `events` ADD CONSTRAINT event_currency FOREIGN KEY(currency_id) REFERENCES currencies(id);

ALTER TABLE `tournaments` ADD COLUMN `expected_players_count` INT(11) NOT NULL;
ALTER TABLE `tournaments` ADD COLUMN `fee` INT(11) NULL;
ALTER TABLE `tournaments` ADD COLUMN `currency_id` INT(11) NULL;
ALTER TABLE `tournaments` ADD KEY (`currency_id`);
ALTER TABLE `tournaments` ADD CONSTRAINT tournament_currency FOREIGN KEY(currency_id) REFERENCES currencies(id);

ALTER TABLE `series` ADD COLUMN `fee` INT(11) NULL;
ALTER TABLE `series` ADD COLUMN `currency_id` INT(11) NULL;

UPDATE tournaments t SET t.expected_players_count = (SELECT count(*) FROM tournament_places p WHERE p.tournament_id = t.id);
UPDATE tournaments t SET t.expected_players_count = -(SELECT max(s.stars) FROM series_tournaments s WHERE s.tournament_id = t.id) WHERE t.expected_players_count = 0;
UPDATE tournaments SET expected_players_count = 30 WHERE expected_players_count <= -4;
UPDATE tournaments SET expected_players_count = 20 WHERE expected_players_count <= -3;
UPDATE tournaments SET expected_players_count = 15 WHERE expected_players_count <= -2;
UPDATE tournaments SET expected_players_count = 10 WHERE expected_players_count <= 0;

//-----
UPDATE clubs SET c.currency_id = (SELECT co.currency_id FROM cities ci JOIN countries co ON co.id = ci.country_id WHERE ci.id = c.city_id);
UPDATE tournaments t SET t.currency_id = (SELECT c.currency_id FROM clubs c WHERE c.id = t.club_id);
