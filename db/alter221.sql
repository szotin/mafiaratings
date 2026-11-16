-- Tournament names become localizable, the same way as club, league and event names (alter217.sql,
-- alter218.sql, alter219.sql): the tournament refers to a set of names in "names", and every query
-- joins it for the language it shows the tournament in.
--
-- Some tournaments share a name - a "Birthday Cup" every year - so, as with the events, the
-- tournaments with the same name share one set rather than each getting a copy of it. Names are
-- told apart by case (BINARY) and taken without the spaces around them.

ALTER TABLE `tournaments` ADD COLUMN `name_id` INT(11) NOT NULL DEFAULT 0 AFTER `id`;

CREATE TEMPORARY TABLE `tournament_names` (
  `name_id` INT(11) NOT NULL DEFAULT 0,
  `name` VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO tournament_names (name) SELECT DISTINCT BINARY TRIM(name) FROM tournaments;

SET @base = (SELECT IFNULL(MAX(id), 0) FROM names);
SET @n = 0;
UPDATE tournament_names SET name_id = @base + (@n := @n + 1) ORDER BY name;

INSERT INTO names (id, langs, name) SELECT name_id, 16777215, name FROM tournament_names;
UPDATE tournaments t JOIN tournament_names tn ON tn.name = TRIM(t.name) COLLATE utf8_bin SET t.name_id = tn.name_id;

DROP TEMPORARY TABLE `tournament_names`;

ALTER TABLE `tournaments` DROP COLUMN `name`;

ALTER TABLE `tournaments` ALTER COLUMN `name_id` DROP DEFAULT;
ALTER TABLE `tournaments` ADD KEY `name_id` (`name_id`);
ALTER TABLE `tournaments` ADD CONSTRAINT `tournament_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`);
