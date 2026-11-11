-- Hiding the scoring table and the bonus points from a tour of a round, not only from a round.
--
-- Which round they are hidden from is still the hide masks in tournaments.flags (see
-- TOURNAMENT_HIDE_TABLE_MASK and TOURNAMENT_HIDE_BONUS_MASK, which now also say "from the main
-- round" and "from the quarter-final"). These two say from which tour of that round: 0 - from
-- its start, as before; N - from its N-th tour, the tours before it staying in the table. See
-- include/table_hiding.php.
ALTER TABLE tournaments
  ADD COLUMN hide_table_tour INT(11) NOT NULL DEFAULT 0,
  ADD COLUMN hide_bonus_tour INT(11) NOT NULL DEFAULT 0;

-- The tournament preparation page has a new tab right after the scheme - the hiding of the table
-- moves there from the tournament form - so every tab after it moves one place on. The page
-- remembers the tab a tournament was left on by its number.
UPDATE tournaments SET preparation_stage = preparation_stage + 1 WHERE preparation_stage >= 1;
