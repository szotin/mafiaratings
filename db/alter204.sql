-- How a game ended, in numbers and in one flag: who was still at the table when it was over,
-- and whether it was given away rather than played out.
--
-- The town wins by taking the last mafioso out, so maf_alive is 0 there; the mafia wins by
-- drawing level, so the two counts are equal and the pair reads as the 1 on 1, 2 on 2 or 3 on 3
-- everybody says out loud. A game can also end without being played to either: one player is
-- sent away for a team kick out (ППК) and the other team wins there and then. It counts as a
-- win like any other and nothing until now said it had been won that way, so it stood among the
-- wins earned at the table while the numbers left on it said nothing useful about it.
--
-- Nothing in the database said any of this before, and all three can be worked out for a game
-- whose seats are all accounted for: players.kill_type says how each player left the table, and
-- 6 is the team kick out, which exactly one seat in a game ever carries. So the flag goes on
-- first and the counts are filled in after it, since which games are worth counting depends on
-- it.
--
-- Two things have to hold for a game to be counted. It must have all ten of its seats in the
-- players table, because a game missing one has a survivor the count would never see. And,
-- unless it was given away, its own result must agree with the count - a fair number of the
-- older games were written down as a result and a seating with hardly a death in them. A game
-- that was given away is exempt from the second test and not from the first: it stopped
-- mid-play, so the mafia can be ahead, behind or level, and the count agrees with nothing.
--
-- Whatever fails either test keeps NULL, which is what NULL is for here: not nobody left, but
-- nobody wrote it down.

ALTER TABLE games
	ADD COLUMN maf_alive tinyint(2) DEFAULT NULL,
	ADD COLUMN civ_alive tinyint(2) DEFAULT NULL;

-- kill_type 6 is the team kick out; the eighth bit of games.flags is GAME_FLAG_TEAM_KICK_OUT.
UPDATE games g
	JOIN (SELECT DISTINCT p.game_id FROM players p WHERE p.kill_type = 6) k ON k.game_id = g.id
	SET g.flags = g.flags | 8;

-- roles 2 and 3 are the mafia and the don, 0 and 1 the civilian and the sheriff; kill_type 0 is
-- the player who was still there at the end.
UPDATE games g
	JOIN (
		SELECT p.game_id,
			SUM(p.role IN (2, 3) AND p.kill_type = 0) AS maf_alive,
			SUM(p.role IN (0, 1) AND p.kill_type = 0) AS civ_alive
		FROM players p
		GROUP BY p.game_id
		HAVING COUNT(*) = 10
	) a ON a.game_id = g.id
	SET g.maf_alive = a.maf_alive, g.civ_alive = a.civ_alive
	WHERE (g.flags & 8) <> 0
		OR (g.result = 1 AND a.maf_alive = 0)
		OR (g.result = 2 AND a.maf_alive > 0 AND a.maf_alive = a.civ_alive);
