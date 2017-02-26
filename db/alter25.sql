use mafia;

UPDATE users SET flags = (flags & ~0x10);

ALTER TABLE users
  ADD COLUMN reg_time INT(11) NOT NULL;

UPDATE users u SET u.reg_time = (SELECT MIN(g.start_time) FROM games g, players p WHERE p.user_id = u.id AND g.id = p.game_id);

UPDATE users SET reg_time = UNIX_TIMESTAMP() WHERE reg_time = 0;

DROP TABLE signup;
