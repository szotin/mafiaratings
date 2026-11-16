-- Tournament schema names become localizable, the same way as the names of clubs, leagues, events,
-- tournaments, series, rules and systems (alter217.sql - alter224.sql): a schema refers to a set of
-- names in "names", and every query joins it for the language it shows the schema in.
--
-- The schemas with the same name - a schema copied to another league keeps its name - share one set
-- rather than each getting a copy of it; names are told apart by case (BINARY) and taken without the
-- spaces around them. A name stays unique in its league, now in every language it is for, which a
-- key can not say any more: api/ops/tournament_schema.php checks it. The unique (league_id, name) key
-- goes, and a plain key on league_id takes its place under the foreign key to the league.

ALTER TABLE `league_tournament_schemas` ADD COLUMN `name_id` INT(11) NOT NULL DEFAULT 0 AFTER `league_id`;

CREATE TEMPORARY TABLE `schema_names` (
  `name_id` INT(11) NOT NULL DEFAULT 0,
  `name` VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO schema_names (name) SELECT DISTINCT BINARY TRIM(name) FROM league_tournament_schemas;

SET @base = (SELECT IFNULL(MAX(id), 0) FROM names);
SET @n = 0;
UPDATE schema_names SET name_id = @base + (@n := @n + 1) ORDER BY name;

INSERT INTO names (id, langs, name) SELECT name_id, 16777215, name FROM schema_names;
UPDATE league_tournament_schemas s JOIN schema_names sn ON sn.name = TRIM(s.name) COLLATE utf8_bin SET s.name_id = sn.name_id;

DROP TEMPORARY TABLE `schema_names`;

ALTER TABLE `league_tournament_schemas` ADD KEY `league_id` (`league_id`);
ALTER TABLE `league_tournament_schemas` DROP INDEX `league_name`;
ALTER TABLE `league_tournament_schemas` DROP COLUMN `name`;

ALTER TABLE `league_tournament_schemas` ALTER COLUMN `name_id` DROP DEFAULT;
ALTER TABLE `league_tournament_schemas` ADD KEY `name_id` (`name_id`);
ALTER TABLE `league_tournament_schemas` ADD CONSTRAINT `tournament_schema_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`);
