use mafia;

ALTER TABLE users ADD games INT(11) NOT NULL;
ALTER TABLE users ADD games_won INT(11) NOT NULL;
ALTER TABLE users ADD rating DOUBLE NOT NULL;
ALTER TABLE users ADD max_rating DOUBLE NOT NULL;
ALTER TABLE users ADD max_rating_time INT(11) NOT NULL;
ALTER TABLE users ADD INDEX `rating` (`rating`, `games` DESC);
ALTER TABLE users ADD INDEX `max_rating` (`max_rating`, `games` DESC);

ALTER TABLE players DROP COLUMN rating;
ALTER TABLE players ADD rating_before DOUBLE NOT NULL;
ALTER TABLE players ADD rating_earned DOUBLE NOT NULL;
ALTER TABLE players ADD club_points INT(11) NOT NULL;
ALTER TABLE players ADD event_points INT(11) NOT NULL;

ALTER TABLE games ADD civ_odds DOUBLE NULL;

; SELECT SUM((civ_odds-(2 - result))*(civ_odds-(2 - result)))/count(*) FROM games WHERE civ_odds IS NOT NULL;

DROP table ratings;
DROP table rating_types;