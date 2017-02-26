use mafia;

ALTER TABLE cities
  DROP INDEX name_en;

ALTER TABLE cities
  DROP INDEX name_ru;

ALTER TABLE cities
  ADD INDEX(name_en);

ALTER TABLE cities
  ADD INDEX(name_ru);