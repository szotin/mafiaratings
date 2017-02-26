use mafia;

ALTER TABLE users
  ADD COLUMN def_lang INT(11) NOT NULL;

UPDATE users SET def_lang = 1;
