ALTER TABLE `tournaments` ADD COLUMN `type` INT(11) NOT NULL;
DROP TABLE club_requests;
ALTER TABLE `clubs` ADD COLUMN `activated` INT(11) NOT NULL;
UPDATE clubs c SET c.activated = (SELECT MAX(g.start_time) FROM games g WHERE g.club_id = c.id);
UPDATE clubs SET activated = UNIX_TIMESTAMP() WHERE activated = 0;

UPDATE users SET flags = flags & ~128;
UPDATE club_users SET flags = flags & ~32;