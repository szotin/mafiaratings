ALTER TABLE event_extra_points ADD COLUMN scoring_group VARCHAR(64) NOT NULL;
ALTER TABLE event_extra_points ADD COLUMN scoring_matter INT(11) NOT NULL;
UPDATE event_extra_points SET scoring_group = 'extra', scoring_matter = 4194304;
