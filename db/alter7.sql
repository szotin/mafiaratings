use mafia;

ALTER TABLE `users`
  ADD COLUMN `games_moderated` INT(11);

ALTER TABLE `users`
  ADD INDEX user_moderated (`games_moderated`);

UPDATE users u SET u.games_moderated =
(SELECT count(*) FROM games g WHERE u.id = g.moderator_id AND g.result > 0 AND g.result < 3);
