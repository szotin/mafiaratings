use mafia;

ALTER TABLE clubs
  ADD COLUMN `rating_limit` INT(11) NOT NULL;

UPDATE clubs SET rating_limit = UNIX_TIMESTAMP() - 24192000;
