use mafia;

-- Reconcile a from-scratch build with the live/production schema. These columns
-- were removed from production out-of-band (no earlier migration recorded the
-- drops), and two columns had their nullability changed there. Applying this
-- brings create_database.sql + alter1..alter187 exactly in line with production.

ALTER TABLE clubs DROP COLUMN price;
ALTER TABLE clubs DROP COLUMN rating_limit;
ALTER TABLE events DROP COLUMN price;
ALTER TABLE tournaments DROP COLUMN price;

ALTER TABLE series_tournaments MODIFY COLUMN fee INT(11) NULL;
ALTER TABLE users MODIFY COLUMN games_moderated INT(11) NOT NULL;
