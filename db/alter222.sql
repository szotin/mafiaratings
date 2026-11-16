-- Series names become localizable, the same way as club, league, event and tournament names
-- (alter217.sql - alter219.sql, alter221.sql): the series refers to a set of names in "names",
-- and every query joins it for the language it shows the series in.
--
-- Two series may share a name, so, as with the events and the tournaments, the series with the
-- same name share one set rather than each getting a copy of it. Names are told apart by case
-- (BINARY) and taken without the spaces around them.

ALTER TABLE `series` ADD COLUMN `name_id` INT(11) NOT NULL DEFAULT 0 AFTER `id`;

CREATE TEMPORARY TABLE `series_names` (
  `name_id` INT(11) NOT NULL DEFAULT 0,
  `name` VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO series_names (name) SELECT DISTINCT BINARY TRIM(name) FROM series;

SET @base = (SELECT IFNULL(MAX(id), 0) FROM names);
SET @n = 0;
UPDATE series_names SET name_id = @base + (@n := @n + 1) ORDER BY name;

INSERT INTO names (id, langs, name) SELECT name_id, 16777215, name FROM series_names;
UPDATE series s JOIN series_names sn ON sn.name = TRIM(s.name) COLLATE utf8_bin SET s.name_id = sn.name_id;

DROP TEMPORARY TABLE `series_names`;

ALTER TABLE `series` DROP COLUMN `name`;

ALTER TABLE `series` ALTER COLUMN `name_id` DROP DEFAULT;
ALTER TABLE `series` ADD KEY `name_id` (`name_id`);
ALTER TABLE `series` ADD CONSTRAINT `series_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`);
