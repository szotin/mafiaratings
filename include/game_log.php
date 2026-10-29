<?php

require_once __DIR__ . '/game.php';

// What happened in a game, told as lines in the order it happened and grouped by the day or the
// night each line belongs to. This is what view_game.php has always shown under a played game,
// and what a text broadcast shows while the game is still being played - one telling of a game,
// so that the two cannot drift apart.
//
// $options may carry:
//   ->player      - how a player is named in a line, as a callable taking the number. The
//                   default is the bare number; view_game.php hands in its own, which links to
//                   the player and says their role.
//   ->hide_roles  - leave out what would give the roles away: who arranged with whom, who the
//                   don and the sheriff checked, and which of the mafia fired. The shot and the
//                   death stay, since the table sees those.
//
// Returns an array of sections, each {is_night, round, lines[]}, where a line is ready-made
// html. A section with no lines in it can happen when the roles are hidden and the whole of a
// night was the mafia's business; it is for the caller to leave such a section out.
function game_log_sections($game, $options = NULL)
{
	$player_html = (is_object($options) && isset($options->player))
		? $options->player
		: function ($num) { return (is_numeric($num) && $num >= 1 && $num <= 10) ? $num : get_label('no one'); };
	$hide_roles = (is_object($options) && isset($options->hide_roles) && $options->hide_roles);

	$told = game_log_told_filter($game);

	$sections = array();
	$section = NULL;

	$alive = array(true, true, true, true, true, true, true, true, true, true);
	$maf_alive = 3;
	$civ_alive = 7;
	$warnings = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
	$techFouls = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
	$round = -1;
	$is_night = true;
	$actions = $game->get_actions();
	$players = $game->data->players;
	foreach ($actions as $action)
	{
		$night = Game::is_night($action);
		if (isset($action->round))
		{
			$action_round = $action->round;
		}
		else if ($round >= 0)
		{
			$action_round = $round;
		}
		else
		{
			$action_round = 0;
		}
		if ($action_round != $round || $is_night != $night)
		{
			$section = new stdClass();
			$section->is_night = $night;
			$section->round = $action_round;
			$section->lines = array();
			$sections[] = $section;
			$round = $action_round;
			$is_night = $night;
		}

		// Where this line stands against the game: what the game has already left behind is
		// told whatever it says, and the step it is standing in is held back.
		$skip = !call_user_func($told, $action);

		// Nothing about the roles leaves the table when the table is played with them hidden.
		if ($hide_roles && ($action->action == GAME_ACTION_ARRANGEMENT ||
			$action->action == GAME_ACTION_DON || $action->action == GAME_ACTION_SHERIFF))
		{
			$skip = true;
		}

		// A line that is not to be told is still worked out: leaving the game and taking a
		// warning are counted here as well as said, and the lines after it are told in terms
		// of that counting. Only the saying of it is held back.
		$line = '';
		switch ($action->action)
		{
			case GAME_ACTION_ARRANGEMENT:
				$arrangement = '';
				for ($i = 0; $i < count($action->players); ++$i)
				{
					if ($i > 0)
					{
						$arrangement .= get_label(', then ');
					}
					$arrangement .= call_user_func($player_html, $action->players[$i]);
				}
				$line .= get_label('Mafia arranges to kill [0].', $arrangement);
				break;
			case GAME_ACTION_LEAVING:
				$alive[$action->player-1] = false;
				$player = $players[$action->player-1];
				$is_maf = false;
				if (isset($player->role) && ($player->role == 'maf' || $player->role == 'don'))
				{
					$is_maf = true;
					--$maf_alive;
				}
				else
				{
					--$civ_alive;
				}
				$info = '';
				if ($maf_alive <= 0)
				{
					$info = get_label('Town wins.');
				}
				else if ($maf_alive >= $civ_alive)
				{
					$info = get_label('Mafia wins.');
				}
				else
				{
					for ($i = 0; $i < 10; ++$i)
					{
						if (!$alive[$i])
						{
							continue;
						}
						if (!empty($info))
						{
							$info .= ', ';
						}
						$info .= call_user_func($player_html, $i + 1);
					}
					$info = get_label('[0] are still playing.', $info);
				}
				if (isset($player->death) && isset($player->death->type))
				{
					switch ($player->death->type)
					{
						case DEATH_TYPE_GIVE_UP:
							$line .= get_label('[0] gives up and leaves the game [2]. [1]', call_user_func($player_html, $action->player), $info, $game->get_gametime_text($action));
							break;
						case DEATH_TYPE_WARNINGS:
							$line .= get_label('[0] gets fourth warning and leaves the game. [1]', call_user_func($player_html, $action->player), $info);
							break;
						case DEATH_TYPE_TECH_FOULS:
							$line .= get_label('[0] gets the second technical foul and leaves the game. [1]', call_user_func($player_html, $action->player), $info);
							break;
						case DEATH_TYPE_KICK_OUT:
							$line .= get_label('[0] is kicked out from the game [2]. [1]', call_user_func($player_html, $action->player), $info, $game->get_gametime_text($action));
							break;
						case DEATH_TYPE_TEAM_KICK_OUT:
							$line .= get_label('[0] is kicked out from the game with team defeat [2]. [1]', call_user_func($player_html, $action->player), $is_maf ? get_label('Town wins.') : get_label('Mafia wins.'), $game->get_gametime_text($action));
							break;
						case DEATH_TYPE_NIGHT:
							$line .= get_label('[0] is shot and leaves the game. [1]', call_user_func($player_html, $action->player), $info);
							break;
						case DEATH_TYPE_DAY:
							$line .= get_label('[0] is voted out and leaves the game. [1]', call_user_func($player_html, $action->player), $info);
							break;
						default:
							$line .= get_label('[0] leaves the game. [1]', call_user_func($player_html, $action->player), $info);
							break;
					}
				}
				else
				{
					$line .= get_label('[0] leaves the game. [1]', call_user_func($player_html, $action->player), $info);
				}
				break;
			case GAME_ACTION_WARNING:
				switch (++$warnings[$action->player-1])
				{
					case 2:
						$line .= get_label('[0] gets second warning [1].', call_user_func($player_html, $action->player), $game->get_gametime_text($action));
						break;
					case 3:
						$line .= get_label('[0] gets third warning [1].', call_user_func($player_html, $action->player), $game->get_gametime_text($action));
						break;
					case 4:
						$line .= get_label('[0] gets fourth warning [1].', call_user_func($player_html, $action->player), $game->get_gametime_text($action));
						break;
					default:
						$line .= get_label('[0] gets a warning [1].', call_user_func($player_html, $action->player), $game->get_gametime_text($action));
						break;
				}
				break;
			case GAME_ACTION_TECH_FOUL:
				switch (++$techFouls[$action->player-1])
				{
					case 2:
						$line .= get_label('[0] gets the second technical foul [1].', call_user_func($player_html, $action->player), $game->get_gametime_text($action));
						break;
					default:
						$line .= get_label('[0] gets a technical foul [1].', call_user_func($player_html, $action->player), $game->get_gametime_text($action));
						break;
				}
				break;
			case GAME_ACTION_ON_RECORD:
				$r = '';
				foreach ($action->record as $rec)
				{
					if (!empty($r))
					{
						$r .= ', ';
					}
					if ($rec < 0)
					{
						$r .= get_label('[0] black', call_user_func($player_html, -$rec));
					}
					else
					{
						$r .= get_label('[0] red', call_user_func($player_html, $rec));
					}
				}
				$line .= get_label('[0] leaves on record: [1]', call_user_func($player_html, $action->speaker), $r);
				break;
			case GAME_ACTION_KILL_ALL:
				$v = '';
				foreach ($action->votes as $vote)
				{
					if (!empty($v))
					{
						$v .= ', ';
					}
					$v .= call_user_func($player_html, $vote);
				}
				if (empty($v))
				{
					$line .= get_label('Nobody voted to kill all');
				}
				else
				{
					$line .= get_label('Voted to kill all: [0].', $v);
				}
				break;
			case GAME_ACTION_DON:
				$line .= get_label('Don checks [0].', call_user_func($player_html, $action->player));
				break;
			case GAME_ACTION_SHERIFF:
				$line .= get_label('Sheriff checks [0].', call_user_func($player_html, $action->player));
				break;
			case GAME_ACTION_LEGACY:
				if (is_array($action->legacy))
				{
					$legacy = '';
					foreach ($action->legacy as $leg)
					{
						if (!empty($legacy))
						{
							$legacy .= ', ';
						}
						$legacy .= call_user_func($player_html, $leg);
					}
					$line .= get_label('[0]\'s legacy is [1].', call_user_func($player_html, $action->player), $legacy);
				}
				else if ((int)$action->legacy > 0)
				{
					$line .= get_label('[0] leaves [1] mafs in the legacy.', call_user_func($player_html, $action->player), (int)$action->legacy);
				}
				break;
			case GAME_ACTION_NOMINATING:
				$line .= get_label('[0] nominates [1].', call_user_func($player_html, $action->speaker), call_user_func($player_html, $action->nominee));
				break;
			case GAME_ACTION_VOTING:
				switch (count($action->votes))
				{
					case 0:
						$line .= get_label('No one votes for [0].', call_user_func($player_html, $action->nominee));
						break;
					case 1:
						$line .= get_label('[0] votes for [1].', call_user_func($player_html, $action->votes[0]), call_user_func($player_html, $action->nominee));
						break;
					default:
						$voters = '';
						foreach ($action->votes as $vote)
						{
							if (!empty($voters))
							{
								$voters .= ', ';
							}
							$voters .= call_user_func($player_html, $vote);
						}
						$line .= get_label('[0] vote for [1].', $voters, call_user_func($player_html, $action->nominee));
				}
				break;
			case GAME_ACTION_SHOOTING:
				// That the mafia missed is seen by the table; which of them fired is not. The
				// details are the only part of the shot that names them, so they go and the
				// rest of it stays.
				if ($hide_roles && is_array($action->shooting) && count($action->shooting) > 1)
				{
					$line .= get_label('Mafia misses.');
					break;
				}
				if (!is_array($action->shooting))
				{
					$line .= get_label('Mafia shoots [0].', $action->shooting);
				}
				else if (count($action->shooting) == 1)
				{
					$shooting = key($action->shooting);
					if (empty($shooting))
					{
						$line .= get_label('Mafia does not shoot.');
					}
					else
					{
						$line .= get_label('Mafia shoots [0].', call_user_func($player_html, $shooting));
					}
				}
				else
				{
					$miss_details = '';
					foreach ($action->shooting as $victim => $shot)
					{
						if (!empty($miss_details))
						{
							$miss_details .= '; ';
						}
						if (empty($victim))
						{
							if (count($shot) == 1)
							{
								$miss_details .= get_label('[0] does not shoot', call_user_func($player_html, $shot[0]));
							}
							else
							{
								$miss_details .= get_label('[0] and [1] do not shoot', call_user_func($player_html, $shot[0]), call_user_func($player_html, $shot[1]));
							}
						}
						else if (count($shot) == 1)
						{
							$miss_details .= get_label('[0] shoots [1]', call_user_func($player_html, $shot[0]), call_user_func($player_html, $victim));
						}
						else
						{
							$miss_details .= get_label('[0] and [1] shoot [2]', call_user_func($player_html, $shot[0]), call_user_func($player_html, $shot[1]), call_user_func($player_html, $victim));
						}
					}
					$line .= get_label('Mafia misses. [0].', $miss_details);
				}
				break;
		}
		if (!$skip)
		{
			$section->lines[] = $line;
		}
	}
	return $sections;
}

// Whether what an action says may be told yet, as a callable taking an action.
//
// The step the game is standing in, where it is still being played. What happens on a step is
// written down as the moderator works through it and can be changed until they leave it: the
// shot can be moved while the night screen is open, a check re-entered, a nomination corrected
// while the player is still speaking. The worst of them is the counting of hands, where the
// last nominee is given everybody who has not been counted for somebody else - that is how a
// table is counted - so halfway through it reads as the whole table voting against one person.
//
// So a step is told once the game has left it, which on the moderator's screen is the moment
// they press Next. A game that is over has no step standing open and is told whole - which is
// what view_game.php shows.
function game_log_told_filter($game)
{
	$now = NULL;
	$now_round = 0;
	$now_step = 0;
	$now_voting = 0;
	$now_nominee = 0;
	if (isset($game->data->time) && isset($game->data->time->time) && $game->data->time->time != GAMETIME_END)
	{
		$now = $game->data->time;
		$now_round = isset($now->round) ? (int)$now->round : 0;
		// Before the first hands are counted the screen already holds a vote for everybody,
		// so that step is the counting of the day's first vote by another name.
		$now_time = ($now->time == GAMETIME_VOTING_START) ? GAMETIME_VOTING : $now->time;
		$now_step = Game::gametime_to_int($now_time);
		$now_voting = ($now->time == GAMETIME_VOTING && isset($now->votingRound)) ? (int)$now->votingRound : 0;
		$now_nominee = ($now->time == GAMETIME_VOTING && isset($now->nominee)) ? (int)$now->nominee : 0;
	}

	// The hands are not counted at one go: the moderator counts them for one name, presses on,
	// counts them for the next. So each name is a step of its own - the names counted already
	// stand, while the one being counted is held back, and with it the names after it, which
	// are holding everybody nobody has counted yet.
	//
	// The last name is the exception, and it is told while the moderator is still standing on
	// it. What it holds is everybody who was not counted for anybody else, so by the time the
	// counting has reached it there is nothing left that could be taken away from it: pressing
	// on from the name before it settled the last name too.
	//
	// Which name is which is the order the moderator calls them in, round the table from the
	// first speaker, and get_nominees() is where that order is kept - including the rule by
	// which it is reversed on every other re-vote. A name that is not in it at all, and the
	// moment before the first name is called, come to the same thing: nothing counted yet.
	$voting_order = array();
	$counted_before = -1; // how far down that order the counting has got
	$counting_last = false; // and whether what it has got to is the last name of all
	if (!is_null($now) && ($now->time == GAMETIME_VOTING || $now->time == GAMETIME_VOTING_START))
	{
		foreach ($game->get_nominees($now_round, $now_voting) as $place => $nominee)
		{
			$voting_order[(int)$nominee] = $place;
		}
		$counted_before = ($now_nominee > 0 && isset($voting_order[$now_nominee])) ? $voting_order[$now_nominee] : 0;
		$counting_last = ($now_nominee > 0 && isset($voting_order[$now_nominee]) &&
			$voting_order[$now_nominee] == count($voting_order) - 1);
	}

	// Except for what is told as it happens and is not gone back over. A nomination is called
	// during a speech, a warning is given during one, and a player leaving names their best
	// move and what they leave on record in theirs: held until the speech is over, the news of
	// a speech would always arrive a speech late. The leaving itself goes with them, so that
	// the best move does not turn up before anybody has heard that its owner is out. The checks
	// go in as the don and the sheriff point, which is the whole of what happens in a night
	// besides the shot - and a table played with the roles hidden is not telling any of it
	// anyway, early or late.
	$told_at_once = array(GAME_ACTION_NOMINATING, GAME_ACTION_LEGACY,
		GAME_ACTION_ON_RECORD, GAME_ACTION_LEAVING,
		GAME_ACTION_WARNING, GAME_ACTION_TECH_FOUL,
		GAME_ACTION_DON, GAME_ACTION_SHERIFF);

	return function ($action) use ($now, $now_round, $now_step, $now_voting, $voting_order, $counted_before, $counting_last, $told_at_once)
	{
		if (is_null($now) || !isset($action->time) || !isset($action->round))
		{
			return true;
		}

		$when = ((int)$action->round == $now_round)
			? Game::gametime_to_int($action->time) - $now_step
			: ((int)$action->round - $now_round);

		if ($when > 0)
		{
			// It has not happened yet. A player shot in the night is written down the moment
			// the shot is set, and the line that says they are leaving belongs to the morning -
			// so without this the table would be told who died while the night was still being
			// played.
			return false;
		}
		if ($when < 0 || in_array($action->action, $told_at_once))
		{
			return true;
		}
		if ($action->action != GAME_ACTION_VOTING)
		{
			return false;
		}

		// A day voted more than once keeps the counts it has already taken whole; inside the
		// count being taken, it is name by name - see $counted_before above.
		if (isset($action->votingRound) && $action->votingRound < $now_voting)
		{
			return true;
		}
		$place = (isset($action->nominee) && isset($voting_order[$action->nominee]))
			? $voting_order[$action->nominee] : $counted_before;
		return ($place < $counted_before || ($counting_last && $place == $counted_before));
	};
}

// Which count of a day is in play. A day can be voted more than once: when the hands come out
// even the table splits, everybody's hand comes down, the tied players speak again and the
// whole table votes again. While the game is standing in a count that is the count; once the
// day has gone past its voting it is the last count the day took.
//
// $actions is the game's actions where the caller already has them.
function game_log_voting_round($game, $round, $actions = NULL)
{
	$now = isset($game->data->time) ? $game->data->time : NULL;
	if (!is_null($now) && isset($now->time) && isset($now->round) && (int)$now->round == $round &&
		($now->time == GAMETIME_VOTING || $now->time == GAMETIME_VOTING_START))
	{
		return isset($now->votingRound) ? (int)$now->votingRound : 0;
	}

	$last = 0;
	if (is_null($actions))
	{
		$actions = $game->get_actions();
	}
	foreach ($actions as $action)
	{
		if ($action->action == GAME_ACTION_VOTING && isset($action->round) && (int)$action->round == $round &&
			isset($action->votingRound))
		{
			$last = max($last, (int)$action->votingRound);
		}
	}
	return $last;
}

// The hands counted in the round being played now - who has put theirs up, and for whom. It is
// for a broadcast to show the voting as it is taken, so it says only what the moderator has
// already moved on from, by the same rule the telling goes by: see game_log_told_filter().
//
// Only the count in play - see game_log_voting_round(). A table that has split has put every
// hand down, so the hands counted in the count before it are no longer up: at the moment the
// split is called this comes back empty, and fills again as the new count is taken. By the end
// of the day it is where the whole table stood when the day was decided.
//
// Returns an array of {nominee, voters[]}, the voters in the order they sit. A day nobody has
// been counted for yet comes back empty.
function game_log_votes($game)
{
	$told = game_log_told_filter($game);
	$round = (isset($game->data->time) && isset($game->data->time->round)) ? (int)$game->data->time->round : 0;
	$actions = $game->get_actions();
	$voting_round = game_log_voting_round($game, $round, $actions);

	$voted_for = array(); // voter number => the nominee they are counted for
	foreach ($actions as $action)
	{
		if ($action->action != GAME_ACTION_VOTING || !isset($action->round) || (int)$action->round != $round ||
			(isset($action->votingRound) ? (int)$action->votingRound : 0) != $voting_round ||
			!isset($action->nominee) || !is_array($action->votes) || !call_user_func($told, $action))
		{
			continue;
		}
		foreach ($action->votes as $vote)
		{
			$voted_for[(int)$vote] = (int)$action->nominee;
		}
	}

	ksort($voted_for); // a column of hands stands in the order of the seats
	$by_nominee = array();
	foreach ($voted_for as $voter => $nominee)
	{
		if (!isset($by_nominee[$nominee]))
		{
			$by_nominee[$nominee] = array();
		}
		$by_nominee[$nominee][] = $voter;
	}

	$votes = array();
	foreach ($by_nominee as $nominee => $voters)
	{
		$vote = new stdClass();
		$vote->nominee = $nominee;
		$vote->voters = $voters;
		$votes[] = $vote;
	}
	return $votes;
}
