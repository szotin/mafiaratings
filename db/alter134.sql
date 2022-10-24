ALTER TABLE tournament_places ADD COLUMN main_points FLOAT NOT NULL;
ALTER TABLE tournament_places ADD COLUMN bonus_points FLOAT NULL;
ALTER TABLE tournament_places ADD COLUMN shot_points FLOAT NULL;
ALTER TABLE tournament_places ADD COLUMN games_count INT(11) NULL;

ALTER TABLE event_places ADD COLUMN main_points FLOAT NOT NULL;
ALTER TABLE event_places ADD COLUMN bonus_points FLOAT NULL;
ALTER TABLE event_places ADD COLUMN shot_points FLOAT NULL;
ALTER TABLE event_places ADD COLUMN games_count INT(11) NULL;

ALTER TABLE series_places ADD COLUMN score FLOAT NOT NULL;
