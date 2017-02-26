use mafia;

RENAME TABLE event_emails TO emails;

ALTER TABLE emails
  ADD COLUMN obj TINYINT(2) NOT NULL;

ALTER TABLE emails
  ADD COLUMN obj_id INT(11) NOT NULL;

UPDATE emails SET obj = 0, obj_id = event_id;

ALTER TABLE emails
  DROP FOREIGN KEY email_event;

ALTER TABLE emails
  DROP COLUMN event_id;

ALTER TABLE emails
  ADD KEY (`obj`, `obj_id`);

ALTER TABLE emails
  ADD KEY (`send_time`);

ALTER TABLE emails
  DROP PRIMARY KEY;

