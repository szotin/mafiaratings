-- The tournament preparation page has a new tab, Rules, right after the scheme - the rules, the
-- scoring and the special awards move there from the tournament form - so every tab after it
-- moves one place on, the same as db/alter210.sql did. The page remembers the tab a tournament
-- was left on by its number.
UPDATE tournaments SET preparation_stage = preparation_stage + 1 WHERE preparation_stage >= 1;
