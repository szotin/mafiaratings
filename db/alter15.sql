use mafia;

ALTER TABLE event_emails MODIFY send_time INT(11) NOT NULL;
ALTER TABLE events MODIFY start_time INT(11) NOT NULL;
ALTER TABLE events MODIFY duration INT(11) NOT NULL;
ALTER TABLE registrations MODIFY duration INT(11) NOT NULL;
ALTER TABLE registrations MODIFY start_time INT(11) NOT NULL;

UPDATE events SET duration = duration * 3600;
