-- Give the tournament and series counters a default of zero.
--
-- alter167 added them as NOT NULL with no default. They are running totals that the scoring
-- code fills in later, so no INSERT supplies them - and under strict mode, which is what the
-- server runs, an INSERT that leaves out a NOT NULL column with no default is an error rather
-- than an implicit zero. Creating a tournament failed with "Field 'rating_sum' doesn't have a
-- default value", and all three places that create one are written the same way
-- (api/ops/tournament.php, api/ops/event.php, include/mwt_game.php), as is the series_places
-- insert. Zero is what these columns mean before anything has been counted, and it is what a
-- permissive server was silently storing all along, so nothing about existing rows changes.
ALTER TABLE tournaments
  MODIFY rating_sum DOUBLE NOT NULL DEFAULT 0,
  MODIFY rating_sum_20 DOUBLE NOT NULL DEFAULT 0,
  MODIFY traveling_distance DOUBLE NOT NULL DEFAULT 0,
  MODIFY guest_coeff DOUBLE NOT NULL DEFAULT 0,
  MODIFY num_regs INT(11) NOT NULL DEFAULT 0;

ALTER TABLE series_places
  MODIFY total_cut_off FLOAT NOT NULL DEFAULT 0,
  MODIFY cut_off FLOAT NOT NULL DEFAULT 0;

-- The same mistake in other tables, found by listing every column a migration added as NOT
-- NULL with no default and checking which of them no INSERT supplies. Creating an address, a
-- user, a series, a game or adding a tournament to a series all failed the same way.
ALTER TABLE addresses
  MODIFY lat DOUBLE NOT NULL DEFAULT 0,
  MODIFY lon DOUBLE NOT NULL DEFAULT 0;

-- The counters and ratings of a user are not part of the insert that creates one either. Zero
-- and the empty string are what a permissive server has been storing there all along - the 712
-- local users who have played nothing all sit at a rating of zero - so existing rows and the
-- behaviour of registration are unchanged.
ALTER TABLE users
  MODIFY emo_name VARCHAR(128) NOT NULL DEFAULT '',
  MODIFY imafia_name VARCHAR(128) NOT NULL DEFAULT '',
  MODIFY mwt_name VARCHAR(128) NOT NULL DEFAULT '',
  MODIFY phone VARCHAR(64) NOT NULL DEFAULT '',
  MODIFY rating DOUBLE NOT NULL DEFAULT 0,
  MODIFY red_rating DOUBLE NOT NULL DEFAULT 0,
  MODIFY black_rating DOUBLE NOT NULL DEFAULT 0,
  MODIFY games INT(11) NOT NULL DEFAULT 0,
  MODIFY games_won INT(11) NOT NULL DEFAULT 0,
  MODIFY games_moderated INT(11) NOT NULL DEFAULT 0;

ALTER TABLE series
  MODIFY per_player_fee FLOAT NOT NULL DEFAULT 0;

ALTER TABLE series_tournaments
  MODIFY flags INT(11) NOT NULL DEFAULT 0;

-- games.json is TEXT, which cannot carry a default in MySQL 5.7, so the two statements that
-- create a game pass an empty string for it instead (api/ops/game.php, include/game.php).
ALTER TABLE games
  MODIFY flags INT(11) NOT NULL DEFAULT 0,
  MODIFY feature_flags INT(11) NOT NULL DEFAULT 0;
