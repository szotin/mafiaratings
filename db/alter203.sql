-- The log of a game under way is its whole history - one copy of the game state per step - and
-- it outgrows TEXT, which holds 65535 bytes, somewhere around the fourth round of an ordinary
-- game. What happens then depends on the server: a strict one refuses the write, so the game
-- stops being saved at all and the moderator's screen keeps trying for ever; a lenient one cuts
-- the log in the middle and it stops being readable JSON, which throws the history away.
--
-- bug_reports.log is a straight copy of the same column, so it is widened with it.

ALTER TABLE current_games MODIFY log MEDIUMTEXT;
ALTER TABLE bug_reports MODIFY log MEDIUMTEXT;
