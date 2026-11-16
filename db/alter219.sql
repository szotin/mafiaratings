-- Event names become localizable, the same way as club and league names (alter217.sql,
-- alter218.sql): the event refers to a set of names in "names", and every query joins it for the
-- language it shows the event in.
--
-- Many events share a name - every week's "Saturday Mafia", every round called "final" - so the
-- events with the same name share one set rather than each getting a copy of it. That is also what
-- Names::get_id() does for a new event: it takes the set that already has exactly these names.
-- Names are told apart by case (BINARY), as get_id() does it, and taken without the spaces around
-- them, so that "Commons" and "Commons " are one name.

ALTER TABLE `events` ADD COLUMN `name_id` INT(11) NOT NULL DEFAULT 0 AFTER `id`;

CREATE TEMPORARY TABLE `event_names` (
  `name_id` INT(11) NOT NULL DEFAULT 0,
  `name` VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO event_names (name) SELECT DISTINCT BINARY TRIM(name) FROM events;

SET @base = (SELECT IFNULL(MAX(id), 0) FROM names);
SET @n = 0;
UPDATE event_names SET name_id = @base + (@n := @n + 1) ORDER BY name;

INSERT INTO names (id, langs, name) SELECT name_id, 16777215, name FROM event_names;
UPDATE events e JOIN event_names t ON t.name = TRIM(e.name) COLLATE utf8_bin SET e.name_id = t.name_id;

DROP TEMPORARY TABLE `event_names`;

ALTER TABLE `events` DROP COLUMN `name`;

ALTER TABLE `events` ALTER COLUMN `name_id` DROP DEFAULT;
ALTER TABLE `events` ADD KEY `name_id` (`name_id`);
ALTER TABLE `events` ADD CONSTRAINT `event_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`);
