use mafia;

ALTER TABLE games
  ADD COLUMN log_version INT(11) NOT NULL;

UPDATE games SET log_version = 0;

ALTER TABLE players
  ADD COLUMN announced_sheriff INT(11) NOT NULL;

ALTER TABLE players
  ADD COLUMN sheriff_status BOOL NOT NULL;

UPDATE players SET announced_sheriff = 0, sheriff_status = false;
