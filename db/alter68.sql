use mafia;

ALTER TABLE scoring_systems MODIFY club_id int(11) null;
INSERT INTO scoring_systems (club_id, name, digits) VALUES (NULL, '', 1);
SET @system_id = LAST_INSERT_ID();
INSERT INTO scoring_points (system_id, flag, points) SELECT @system_id, s.flag, s.points FROM scoring_points s WHERE s.system_id = 10;
