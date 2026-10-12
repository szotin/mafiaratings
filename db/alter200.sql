-- League referees.
--
-- A league could only have managers, and league_managers was a membership table with nothing but
-- the two ids. It carries flags now, the way club_regs and series_regs do, so one row says which
-- permissions a user has in the league: 4 is USER_PERM_MANAGER, 2 is USER_PERM_REFEREE - see
-- include/constants.php.
--
-- A league referee is a referee in every tournament played in any series of the league. That is
-- the whole point of the flag: a league running its competitions through series had no way to
-- appoint the people who referee them, short of making each of them a manager of the league or a
-- referee of every club taking part.
--
-- Four is the default, so every row that exists stays exactly what it was - a manager.
ALTER TABLE league_managers
  ADD COLUMN flags INT(11) NOT NULL DEFAULT 4;
