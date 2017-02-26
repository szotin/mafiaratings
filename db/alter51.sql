use mafia;

ALTER TABLE log
   ADD COLUMN `message` VARCHAR(256) NOT NULL;

UPDATE log SET message = details WHERE locate(':', details) = 0;
UPDATE log SET details = NULL WHERE locate(':', details) = 0;

UPDATE log SET message = substr(details, 1, locate(':', details) - 1) WHERE locate(':', details) <> 0;
UPDATE log SET details = substr(details, locate(':', details) + 2) WHERE locate(':', details) <> 0;
