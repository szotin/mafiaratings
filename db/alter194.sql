-- Let series belong to a club as well as to a league.
--
-- A series used to be a league-only object: series.league_id was NOT NULL. Clubs now run
-- series of their own, and everything else about them - subseries, tournaments, standings,
-- extra points, registrations, the whole gaining engine in complete_competitions.php - is
-- identical, so the same table carries both kinds. Exactly one of league_id / club_id is
-- set on any row; which one it is decides who manages the series and whose logo it shows.
ALTER TABLE series
  MODIFY league_id INT(11) DEFAULT NULL,
  ADD COLUMN club_id INT(11) DEFAULT NULL;

ALTER TABLE series
  ADD KEY club_id (club_id, start_time);

ALTER TABLE series
  ADD CONSTRAINT series_club FOREIGN KEY (club_id) REFERENCES clubs (id);

-- Gaining (series scoring) systems could only be global or league-owned. A club series has
-- no league to inherit one from, so clubs get their own, exactly the way they already own
-- tournament scoring systems and normalizers: scorings.club_id / normalizers.club_id.
ALTER TABLE gainings
  ADD COLUMN club_id INT(11) DEFAULT NULL;

ALTER TABLE gainings
  ADD KEY club_id (club_id, name);

ALTER TABLE gainings
  ADD CONSTRAINT gaining_club FOREIGN KEY (club_id) REFERENCES clubs (id);

-- Give series_series.flags a default of zero.
--
-- The same mistake alter193 fixed in series_tournaments and half a dozen other tables, in the
-- one table it missed: flags is NOT NULL with no default, and neither of the two statements
-- that add a parent series supplies it (both in api/ops/series.php - create and change). Under
-- strict mode, which is what the server runs, that INSERT fails with "Field 'flags' doesn't
-- have a default value" instead of storing an implicit zero, so a series could not be created
-- as part of a parent series at all. Zero means no flags (the only bit is
-- SERIES_SERIES_FLAG_NOT_PAYED) and is what a permissive server was silently storing all
-- along, so nothing about existing rows changes.
ALTER TABLE series_series
  MODIFY flags INT(11) NOT NULL DEFAULT 0;
