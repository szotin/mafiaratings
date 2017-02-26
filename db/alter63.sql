use mafia;

ALTER TABLE news
  ADD COLUMN `expires` INT(11) NOT NULL;

UPDATE news SET expires = `timestamp` + 604800;