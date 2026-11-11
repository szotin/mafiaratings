<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/localization.php'; // get_label
require_once __DIR__ . '/rounds.php';

// Hiding the scoring table, or the bonus points in it, until the tournament ends.
//
// What is hidden is everything from a moment of the tournament on: from the start of one of its
// rounds, or from a tour of it. The games before the moment stay in the table, so a table hidden
// from the semi-final shows the standings the semi-final started with, and one hidden from tour
// 9 shows them after tour 8. The table of a round that is hidden as a whole is empty.
//
// The round is the hide mask in the tournament flags - TOURNAMENT_HIDE_TABLE_MASK for the table,
// TOURNAMENT_HIDE_BONUS_MASK for the bonus points - and the tour of it is
// tournaments.hide_table_tour and hide_bonus_tour: 0 for from the start of the round, N for from
// its N-th tour. TOURNAMENT_HIDE_ALL hides everything and has no moment. A finished tournament
// hides nothing, and a manager or a referee who asks to see everything has the masks taken off
// the flags - that alone is what shows the actual table.
//
// Rounds are numbered the way events.round numbers them - see include/rounds.php: 0 the main
// round, then 1 the final, 2 the semi-final, 3 the quarter-final. A bigger number is an earlier
// round, except 0, which is played first.

// The round a hide mode hides from, or NULL for a mode that does not hide from a round.
function hiding_mode_round($mode)
{
	switch ($mode)
	{
		case TOURNAMENT_HIDE_FROM_MAIN_ROUND:
			return 0;
		case TOURNAMENT_HIDE_FROM_QUARTER_FINALS:
			return 3;
		case TOURNAMENT_HIDE_FROM_SEMI_FINALS:
			return 2;
		case TOURNAMENT_HIDE_FROM_FINALS:
			return 1;
	}
	return NULL;
}

// And back: the mode that hides from a round, or NULL for a round there is no mode for.
function hiding_round_mode($round_num)
{
	switch ((int)$round_num)
	{
		case 0:
			return TOURNAMENT_HIDE_FROM_MAIN_ROUND;
		case 1:
			return TOURNAMENT_HIDE_FROM_FINALS;
		case 2:
			return TOURNAMENT_HIDE_FROM_SEMI_FINALS;
		case 3:
			return TOURNAMENT_HIDE_FROM_QUARTER_FINALS;
	}
	return NULL;
}

// Where a round stands in the order they are played: the main round first, and then from the
// biggest number down to the final.
function hiding_round_order($round_num)
{
	$round_num = (int)$round_num;
	return $round_num == 0 ? 0 : 1000 - $round_num;
}

// The hide modes as they are kept in the flags.
function table_hiding_mode($tournament_flags)
{
	return ($tournament_flags & TOURNAMENT_HIDE_TABLE_MASK) >> TOURNAMENT_HIDE_TABLE_MASK_OFFSET;
}

function bonus_hiding_mode($tournament_flags)
{
	return ($tournament_flags & TOURNAMENT_HIDE_BONUS_MASK) >> TOURNAMENT_HIDE_BONUS_MASK_OFFSET;
}

// The tours the hiding starts from, as they are kept in the tournament record. Read once a request.
function _hiding_tours($tournament_id)
{
	static $tours = array();
	$tournament_id = (int)$tournament_id;
	if (!array_key_exists($tournament_id, $tours))
	{
		$t = array(0, 0);
		$query = new DbQuery('SELECT hide_table_tour, hide_bonus_tour FROM tournaments WHERE id = ?', $tournament_id);
		if ($row = $query->next())
		{
			$t = array(max((int)$row[0], 0), max((int)$row[1], 0));
		}
		$tours[$tournament_id] = $t;
	}
	return $tours[$tournament_id];
}

function _hiding($tournament_id, $mode, $tour)
{
	$h = new stdClass();
	$h->tournament_id = (int)$tournament_id;
	$h->mode = $mode;
	$h->round = hiding_mode_round($mode);
	$h->tour = is_null($h->round) ? 0 : $tour;
	if ($h->tour == 1)
	{
		$h->tour = 0; // from the first tour is from the start
	}
	if ($h->round === 0 && $h->tour == 0)
	{
		// From the start of the main round is everything.
		$h->mode = TOURNAMENT_HIDE_ALL;
		$h->round = NULL;
	}
	return $h;
}

// What a tournament hides of its table, or NULL when it hides nothing.
function table_hiding($tournament_id, $tournament_flags)
{
	if (is_null($tournament_id) || ($tournament_flags & TOURNAMENT_FLAG_FINISHED) != 0)
	{
		return NULL;
	}
	$mode = table_hiding_mode($tournament_flags);
	if ($mode == TOURNAMENT_HIDE_NONE)
	{
		return NULL;
	}
	$tours = _hiding_tours($tournament_id);
	return _hiding($tournament_id, $mode, $tours[0]);
}

// The same for the bonus points.
function bonus_hiding($tournament_id, $tournament_flags)
{
	if (is_null($tournament_id) || ($tournament_flags & TOURNAMENT_FLAG_FINISHED) != 0)
	{
		return NULL;
	}
	$mode = bonus_hiding_mode($tournament_flags);
	if ($mode == TOURNAMENT_HIDE_NONE)
	{
		return NULL;
	}
	$tours = _hiding_tours($tournament_id);
	return _hiding($tournament_id, $mode, $tours[1]);
}

// The tournament a round belongs to, for the round pages, which know the round rather than the
// tournament. Only asked when the flags hide something, so a round with nothing hidden costs nothing.
function event_hiding_tournament($event_id, $tournament_flags)
{
	if (($tournament_flags & TOURNAMENT_FLAG_FINISHED) != 0 || ($tournament_flags & (TOURNAMENT_HIDE_TABLE_MASK | TOURNAMENT_HIDE_BONUS_MASK)) == 0)
	{
		return NULL;
	}
	$query = new DbQuery('SELECT tournament_id FROM events WHERE id = ?', $event_id);
	if ($row = $query->next())
	{
		return $row[0];
	}
	return NULL;
}

// What a round's games are numbered from, less one. A round seated here numbers its tours from
// one, but a round brought over from another site may go on numbering where the round before it
// stopped - a semi-final whose first tour is game 12. A tour of a round is counted from its first
// game, then. Until the round has a game nothing is known, and nothing in it is hidden by number
// anyway. The main round starts the numbering, so it is taken as it is.
function hiding_tour_offset($tournament_id, $round_num)
{
	static $offsets = array();
	if ($round_num == 0)
	{
		return 0;
	}
	$key = (int)$tournament_id . ':' . (int)$round_num;
	if (!array_key_exists($key, $offsets))
	{
		list ($first) = Db::record(get_label('game'),
			'SELECT IFNULL(MIN(g.game_num), 1) FROM games g JOIN events e ON e.id = g.event_id' .
			' WHERE g.tournament_id = ? AND e.round = ? AND g.game_num IS NOT NULL', $tournament_id, $round_num);
		$offsets[$key] = max((int)$first - 1, 0);
	}
	return $offsets[$key];
}

// The game number the hiding starts from in its round, or 0 when the whole round is hidden.
function hiding_first_game_num($h)
{
	return $h->tour > 0 ? hiding_tour_offset($h->tournament_id, $h->round) + $h->tour : 0;
}

// Whether a round is played after the round the hiding starts in.
function is_round_after_hiding($h, $round_num)
{
	return hiding_round_order($round_num) > hiding_round_order($h->round);
}

// Whether a round is hidden as a whole - its own table has nothing to show.
function is_round_hidden($h, $round_num)
{
	if (is_null($h))
	{
		return false;
	}
	if ($h->mode == TOURNAMENT_HIDE_ALL)
	{
		return true;
	}
	if (is_round_after_hiding($h, $round_num))
	{
		return true;
	}
	return (int)$round_num == $h->round && $h->tour == 0;
}

// Whether a round is hidden from one of its tours on: some of it is shown and some is not.
function is_round_partly_hidden($h, $round_num)
{
	return !is_null($h) && $h->mode != TOURNAMENT_HIDE_ALL && (int)$round_num == $h->round && $h->tour > 0;
}

// Whether one game is hidden. A game with no number is not one of the tours of its round, and
// is hidden only with the whole round.
function is_game_hidden($h, $round_num, $game_num)
{
	if (is_round_hidden($h, $round_num))
	{
		return true;
	}
	if (is_round_partly_hidden($h, $round_num))
	{
		return !is_null($game_num) && (int)$game_num >= hiding_first_game_num($h);
	}
	return false;
}

// The games that are still shown, as an SQL expression over a game g and its round e.
function _hiding_shown_games_expr($h)
{
	if ($h->mode == TOURNAMENT_HIDE_ALL)
	{
		return 'FALSE';
	}
	$parts = array();
	if ($h->round != 0)
	{
		$parts[] = 'e.round = 0 OR e.round > ' . $h->round;
	}
	if ($h->tour > 0)
	{
		$parts[] = '(e.round = ' . $h->round . ' AND IFNULL(g.game_num, 0) < ' . hiding_first_game_num($h) . ')';
	}
	return empty($parts) ? 'FALSE' : '(' . implode(' OR ', $parts) . ')';
}

// The condition to add to a query of games joined with their rounds.
function hiding_games_condition($h)
{
	if (is_null($h))
	{
		return new SQL();
	}
	return new SQL(' AND ' . _hiding_shown_games_expr($h));
}

// The condition to add to a query of rounds e: the rounds anything is shown of. With
// $whole_rounds_only the round hidden from one of its tours is left out too - for what belongs
// to a round as a whole rather than to its games, such as the extra points given for it, which
// are given when the round is over.
function hiding_events_condition($h, $whole_rounds_only = false)
{
	if (is_null($h))
	{
		return new SQL();
	}
	if ($h->mode == TOURNAMENT_HIDE_ALL)
	{
		return new SQL(' AND FALSE');
	}
	$parts = array();
	if ($h->round != 0)
	{
		$parts[] = 'e.round = 0 OR e.round > ' . $h->round;
	}
	if ($h->tour > 0 && !$whole_rounds_only)
	{
		$parts[] = 'e.round = ' . $h->round;
	}
	return new SQL(empty($parts) ? ' AND FALSE' : ' AND (' . implode(' OR ', $parts) . ')');
}

// Whether anything has been played that the hiding is keeping out yet. Until then the table is
// the actual one and there is nothing to warn anybody about. $condition narrows the games down,
// as the tournament page narrows them down to the players it shows.
function is_hiding_in_effect($h, $condition = NULL)
{
	if (is_null($h))
	{
		return false;
	}
	list ($count) = Db::record(get_label('game'),
		'SELECT count(*) FROM games g JOIN events e ON e.id = g.event_id' .
		' WHERE g.tournament_id = ? AND (g.flags & ' . (GAME_FLAG_RATING | GAME_FLAG_CANCELED) . ') = ' . GAME_FLAG_RATING .
		' AND NOT ' . _hiding_shown_games_expr($h), $h->tournament_id, is_null($condition) ? new SQL() : $condition);
	return $count > 0;
}

// The same for the games of one round.
function is_event_hiding_in_effect($h, $event_id)
{
	if (is_null($h))
	{
		return false;
	}
	list ($count) = Db::record(get_label('game'),
		'SELECT count(*) FROM games g JOIN events e ON e.id = g.event_id' .
		' WHERE g.event_id = ? AND (g.flags & ' . (GAME_FLAG_RATING | GAME_FLAG_CANCELED) . ') = ' . GAME_FLAG_RATING .
		' AND NOT ' . _hiding_shown_games_expr($h), $event_id);
	return $count > 0;
}

// How many tours a round of the tournament has: as many as its seating lays out; where it has
// none, as many as its scheme makes - every player plays a game a tour, so it is the games of all
// the players spread over its tables; and where there is no scheme either, as many as were
// played in it once a later round has begun and it is over for sure. Zero when nothing says.
function hiding_round_tours($tournament_id, $round_num)
{
	$tours = 0;
	$scheme_tours = 0;
	$query = new DbQuery('SELECT misc, players, tables, games FROM events WHERE tournament_id = ? AND round = ? AND (flags & ' . EVENT_FLAG_CANCELED . ') = 0', $tournament_id, $round_num);
	while ($row = $query->next())
	{
		list ($misc, $players, $tables, $games) = $row;
		$misc = json_decode($misc);
		if (!is_null($misc) && isset($misc->seating->rounds) && is_array($misc->seating->rounds))
		{
			$tours = max($tours, count($misc->seating->rounds));
		}
		if ((int)$players > 0 && (int)$tables > 0 && (int)$games > 0)
		{
			$scheme_tours = max($scheme_tours, (int)ceil((int)$players * (int)$games / ((int)$tables * 10)));
		}
	}
	if ($tours > 0)
	{
		return $tours;
	}
	if ($scheme_tours > 0)
	{
		return $scheme_tours;
	}

	$query = new DbQuery('SELECT DISTINCT e.round FROM games g JOIN events e ON e.id = g.event_id WHERE g.tournament_id = ?', $tournament_id);
	while ($row = $query->next())
	{
		if (hiding_round_order($row[0]) > hiding_round_order($round_num))
		{
			list ($tours) = Db::record(get_label('game'), 'SELECT IFNULL(MAX(g.game_num), 0) FROM games g JOIN events e ON e.id = g.event_id WHERE g.tournament_id = ? AND e.round = ?', $tournament_id, $round_num);
			return max((int)$tours - hiding_tour_offset($tournament_id, $round_num), 0);
		}
	}
	return 0;
}

// "after the main round", "after the semi-final" - the end of a round.
function _hiding_after_round($round_num)
{
	switch ($round_num)
	{
		case 0:
			return get_label('after the main round');
		case 1:
			return get_label('after the final');
		case 2:
			return get_label('after the semi-final');
		case 3:
			return get_label('after the quarter-final');
	}
	return get_label('after the 1/[0] final', pow(2, $round_num - 1));
}

// "after tour 8", "after tour 2 of the semi-final" - a tour of a round. The main round is the
// tournament itself rather than a part of it, so its tours are not called by its name.
function _hiding_after_tour($round_num, $tour)
{
	switch ($round_num)
	{
		case 0:
			return get_label('after tour [0]', $tour);
		case 1:
			return get_label('after tour [0] of the final', $tour);
		case 2:
			return get_label('after tour [0] of the semi-final', $tour);
		case 3:
			return get_label('after tour [0] of the quarter-final', $tour);
	}
	return get_label('after tour [0] of the 1/[1] final', $tour, pow(2, $round_num - 1));
}

// The moment the table shows the standings at, as words: the last tour before the hiding, or the
// round before it when the hiding starts with a round. A table hidden from a tour past the end
// of its round shows the standings after the round, and says so.
function hidden_table_moment($h)
{
	if ($h->tour > 0)
	{
		$tour = $h->tour - 1;
		$tours = hiding_round_tours($h->tournament_id, $h->round);
		if ($tours <= 0 || $tour < $tours)
		{
			return _hiding_after_tour($h->round, $tour);
		}
		return _hiding_after_round($h->round);
	}

	// The round played before this one: the nearest to it of the rounds the tournament has,
	// or the main round, which every tournament starts with.
	list ($before) = Db::record(get_label('round'),
		'SELECT IFNULL(MIN(round), 0) FROM events WHERE tournament_id = ? AND round > ? AND (flags & ' . EVENT_FLAG_CANCELED . ') = 0',
		$h->tournament_id, $h->round);
	return _hiding_after_round((int)$before);
}

// The warning above a table that is hidden from a moment on: first that it is not the actual
// table, then which moment it shows.
function hidden_table_message($h)
{
	return get_label('The table is closed: these are the standings [0].', hidden_table_moment($h));
}

// "starting from tour 9", "starting from the semi-final" - where the hiding starts, as words.
function hiding_start($h)
{
	if ($h->tour > 0)
	{
		switch ($h->round)
		{
			case 0:
				return get_label('starting from tour [0]', $h->tour);
			case 1:
				return get_label('starting from tour [0] of the final', $h->tour);
			case 2:
				return get_label('starting from tour [0] of the semi-final', $h->tour);
			case 3:
				return get_label('starting from tour [0] of the quarter-final', $h->tour);
		}
		return get_label('starting from tour [0] of the 1/[1] final', $h->tour, pow(2, $h->round - 1));
	}
	switch ($h->round)
	{
		case 1:
			return get_label('starting from the final');
		case 2:
			return get_label('starting from the semi-final');
		case 3:
			return get_label('starting from the quarter-final');
	}
	return get_label('starting from the 1/[0] final', pow(2, $h->round - 1));
}

// The note about the bonus points held back.
function hidden_bonus_message($h)
{
	if ($h->mode == TOURNAMENT_HIDE_ALL)
	{
		return get_label('Bonus points are hidden for this tournament until it ends.');
	}
	return get_label('Bonus points are hidden [0] until the tournament ends.', hiding_start($h));
}

// The last moment anything has been played at: the latest round with a game written down, and
// the latest tour of it - 0 when its games have no numbers. NULL when nothing has been played.
function hiding_last_played($tournament_id)
{
	$last = NULL;
	$query = new DbQuery(
		'SELECT e.round, MAX(g.game_num) FROM games g JOIN events e ON e.id = g.event_id' .
		' WHERE g.tournament_id = ? AND (g.flags & ' . GAME_FLAG_CANCELED . ') = 0 GROUP BY e.round', $tournament_id);
	while ($row = $query->next())
	{
		$round_num = (int)$row[0];
		if (is_null($last) || hiding_round_order($round_num) > hiding_round_order($last->round))
		{
			$last = new stdClass();
			$last->round = $round_num;
			$last->tour = is_null($row[1]) ? 0 : max((int)$row[1] - hiding_tour_offset($tournament_id, $round_num), 0);
		}
	}
	return $last;
}

// Whether the table is hidden by now, the way the streams page puts it: hidden altogether, or
// hidden from a moment the tournament has reached - the moment is no later than the tour that is
// next to be played. A table set up to hide from the final while the main round is still on is
// not hidden yet.
function is_table_hidden_now($tournament_id, $tournament_flags)
{
	$h = table_hiding($tournament_id, $tournament_flags);
	if (is_null($h))
	{
		return false;
	}
	if ($h->mode == TOURNAMENT_HIDE_ALL)
	{
		return true;
	}
	$last = hiding_last_played($tournament_id);
	$next_round = is_null($last) ? 0 : $last->round;
	$next_tour = is_null($last) ? 1 : $last->tour + 1;

	$order = hiding_round_order($h->round);
	$next_order = hiding_round_order($next_round);
	if ($order != $next_order)
	{
		return $order < $next_order;
	}
	return max($h->tour, 1) <= $next_tour;
}

?>
