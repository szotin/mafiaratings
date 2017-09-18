use mafia;

ALTER TABLE seasons
  CHANGE name name VARCHAR(256) NOT NULL;
  
INSERT INTO events 
(name, price, address_id, club_id, start_time, notes, duration, flags, languages, rules_id, scoring_id)
VALUES ('Regular game', '', 1, 1, 1284782440, '', 21600, 17, 2, 1, 10);
SET @event_id = LAST_INSERT_ID();
UPDATE games SET event_id = @event_id WHERE id = 1;
UPDATE games SET event_id = @event_id WHERE id = 4;
UPDATE games SET event_id = @event_id WHERE id = 6;

INSERT INTO events 
(name, price, address_id, club_id, start_time, notes, duration, flags, languages, rules_id, scoring_id)
VALUES ('Regular game', '', 3, 1, 1290924025, '', 21600, 17, 2, 1, 10);
SET @event_id = LAST_INSERT_ID();
UPDATE games SET event_id = @event_id WHERE id = 57;
UPDATE games SET event_id = @event_id WHERE id = 58;

INSERT INTO events 
(name, price, address_id, club_id, start_time, notes, duration, flags, languages, rules_id, scoring_id)
VALUES ('Regular game', '', 3, 1, 1292212842, '', 21600, 17, 2, 1, 10);
SET @event_id = LAST_INSERT_ID();
UPDATE games SET event_id = @event_id WHERE id = 80;
UPDATE games SET event_id = @event_id WHERE id = 81;

INSERT INTO events 
(name, price, address_id, club_id, start_time, notes, duration, flags, languages, rules_id, scoring_id)
VALUES ('Regular game', '', 2, 1, 1292716825, '', 21600, 17, 2, 1, 10);
SET @event_id = LAST_INSERT_ID();
UPDATE games SET event_id = @event_id WHERE id = 88;
UPDATE games SET event_id = @event_id WHERE id = 89;

DELETE FROM dons WHERE game_id = 614;
DELETE FROM sheriffs WHERE game_id = 614;
DELETE FROM mafiosos WHERE game_id = 614;
DELETE FROM players WHERE game_id = 614;
DELETE FROM games WHERE id = 614;

ALTER TABLE games
  DROP FOREIGN KEY game_event;
ALTER TABLE games
  CHANGE event_id event_id int(11) NOT NULL;
ALTER TABLE games
  ADD CONSTRAINT game_event FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);
