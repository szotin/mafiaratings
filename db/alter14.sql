use mafia;

ALTER TABLE users
  ADD COLUMN `languages` INT(11) NOT NULL; -- bit flags 1 - English; 2 - Russian; 30 others can be added

UPDATE users SET languages = 3;

ALTER TABLE events
  ADD COLUMN `languages` INT(11) NOT NULL; -- bit flags 1 - English; 2 - Russian; 30 others can be added

UPDATE events SET languages = 2;

ALTER TABLE games
  ADD COLUMN `language` INT(11) NOT NULL; -- bit flags 1 - English; 2 - Russian; 30 others can be added

UPDATE games SET language = 2;

ALTER TABLE signup
  ADD COLUMN `languages` INT(11) NOT NULL; -- bit flags 1 - English; 2 - Russian; 30 others can be added

UPDATE signup SET languages = 3;
