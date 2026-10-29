<?php

require_once 'include/updater.php';
require_once 'include/game.php';

// A one-off repair: fills in how a game ended for the games that were left without it.
//
// games.maf_alive and games.civ_alive say who was still at the table when the game was over -
// the numbers the 1 on 1, 2 on 2 and 3 on 3 are read from. They came with db/alter204.sql,
// which filled them in for the games already played by counting the survivors in the players
// table. A seat that nobody was picked for has no row there, so a game with one came out as
// nine seats, failed the "all ten accounted for" test and kept NULL - and on the statistics
// screen of a broadcast it stands as a mafia win of no particular kind.
//
// The game's own json has all ten seats, the empty one included, so the counts can be worked
// out after all. That is what this does, by the same reckoning the site uses when a game is
// saved today - see Game::update(). A game given away by a team kick out gets the flag that
// says so, for the same reason: alter204 took that from the players table as well.
//
// Only games that have none of this yet are touched, and only the two counts and that one
// flag are written. A game whose json does not agree with the result it was saved under is
// left alone: those are old records of a result and a seating with hardly a death in them,
// where the table at the end says nothing. They keep NULL, which is what NULL is for here -
// not nobody left, but nobody wrote it down.
//
//   php fix_game_counts.php -dry     - says what it would do and changes nothing
//   php fix_game_counts.php          - does it
//
// Run it until it says the task is complete, then delete it: it is of no use afterwards.
class FixGameCounts extends Updater
{
	function __construct()
	{
		parent::__construct(__FILE__);
	}

	// Whether this is a rehearsal. Checked on every run rather than kept in vars, so that a
	// dry run and a real one can follow each other without the state file confusing them.
	private function isDry()
	{
		if (isset($_REQUEST['dry']))
		{
			return true;
		}
		if (isset($_SERVER['argv']))
		{
			foreach ($_SERVER['argv'] as $arg)
			{
				if ($arg == '-dry')
				{
					return true;
				}
			}
		}
		return false;
	}

	//-------------------------------------------------------------------------------------------------------
	// FixGameCounts.counts
	//-------------------------------------------------------------------------------------------------------
	// One game is an item. The games are gone through in the order they were written down, and
	// where the task stops it carries on from the next time - see $this->vars->last_game_id.
	function counts_task($items_count)
	{
		if (!isset($this->vars->last_game_id))
		{
			$this->vars->last_game_id = 0;
			$this->vars->filled = 0;
			$this->vars->kick_outs = 0;
			$this->vars->left_alone = 0;
		}

		$dry = $this->isDry();
		$query = new DbQuery(
			'SELECT id, result, flags, json FROM games' .
			' WHERE id > ? AND maf_alive IS NULL AND (flags & ' . GAME_FLAG_CANCELED . ') = 0' .
			' ORDER BY id LIMIT ' . (int)$items_count,
			$this->vars->last_game_id);

		$count = 0;
		Db::begin();
		while ($row = $query->next())
		{
			list ($game_id, $result, $flags, $json) = $row;
			$game_id = (int)$game_id;
			$this->vars->last_game_id = $game_id;
			++$count;

			$data = json_decode($json);
			if (!is_object($data) || !isset($data->players) || !is_array($data->players) || count($data->players) != 10)
			{
				// Not a game this can speak about: without ten seats there is no table to count.
				++$this->vars->left_alone;
				$this->debug('Game ' . $game_id . ': no ten seats in the json, left alone.');
				continue;
			}

			// Who was still sitting there. A player with a death of any kind has left the
			// table, whichever way they went; a seat nobody was picked for is a civilian who
			// sat through the game, which is exactly how the game screen counted it.
			$maf_alive = 0;
			$civ_alive = 0;
			$team_kick_out = false;
			foreach ($data->players as $player)
			{
				if (!is_object($player))
				{
					continue;
				}
				if (isset($player->death))
				{
					$death = $player->death;
					$death_type = is_string($death) ? $death : (isset($death->type) ? $death->type : '');
					if ($death_type == DEATH_TYPE_TEAM_KICK_OUT)
					{
						$team_kick_out = true;
					}
					continue;
				}
				if (isset($player->role) && ($player->role == 'maf' || $player->role == 'don'))
				{
					++$maf_alive;
				}
				else
				{
					++$civ_alive;
				}
			}

			// Whether the table at the end agrees with the result the game was saved under.
			// The town wins by taking the last mafioso out; the mafia wins by drawing level.
			// A game given away by a team kick out was not played out at all, so it is taken
			// on the strength of the kick out itself.
			$result = (int)$result;
			$agrees = ($result == GAME_RESULT_TOWN && $maf_alive == 0) ||
				($result == GAME_RESULT_MAFIA && $maf_alive > 0 && $maf_alive == $civ_alive);
			if (!$team_kick_out && !$agrees)
			{
				++$this->vars->left_alone;
				$this->debug('Game ' . $game_id . ': result ' . $result . ' but ' . $maf_alive . ' on ' . $civ_alive .
					' at the table, left alone.');
				continue;
			}

			$new_flags = (int)$flags | ($team_kick_out ? GAME_FLAG_TEAM_KICK_OUT : 0);
			++$this->vars->filled;
			if ($team_kick_out)
			{
				++$this->vars->kick_outs;
			}
			$this->info('Game ' . $game_id . ': ' . $maf_alive . ' on ' . $civ_alive .
				($team_kick_out ? ', given away by a team kick out' : '') .
				($dry ? ' (dry run, nothing written)' : ''));
			if (!$dry)
			{
				Db::exec(get_label('game'),
					'UPDATE games SET maf_alive = ?, civ_alive = ?, flags = ? WHERE id = ?',
					$maf_alive, $civ_alive, $new_flags, $game_id);
			}

			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		Db::commit();
		return $count;
	}

	function counts_task_end()
	{
		$this->important('Games filled in: ' . (int)$this->vars->filled .
			' (of them given away by a team kick out: ' . (int)$this->vars->kick_outs . ').' .
			' Left alone: ' . (int)$this->vars->left_alone . '.' .
			($this->isDry() ? ' This was a dry run - nothing was written.' : ''));
	}
}

$updater = new FixGameCounts();
$updater->run();

?>
