use mafia;

DROP TABLE `email_templates`;

ALTER TABLE event_emails DROP COLUMN subject;
ALTER TABLE event_emails DROP COLUMN body;
ALTER TABLE event_emails DROP COLUMN lang;
ALTER TABLE event_emails ADD COLUMN langs INT(11) NOT NULL;
ALTER TABLE event_emails ADD COLUMN type INT(11) NOT NULL;
UPDATE event_emails SET langs = (SELECT e.languages FROM events e WHERE e.id = event_id), flags = flags & ~41, type = 0;
UPDATE event_emails SET send_time = (SELECT e.start_time FROM events e WHERE e.id = event_id) - send_time;

RENAME TABLE event_emails TO event_mailings;