<?php

require_once 'include/updater.php';
require_once 'include/rules.php';

function convert_game($game)
{
	if (is_null($game))
	{
		return false;
	}
	
	if (isset($game->rules))
	{
		$rules = upgrade_rules_code($game->rules);
	}
	if ($rules !== $game->rules)
	{
		$game->rules = $rules;
		return true;
	}
	return false;
}

class RulesUpdater extends Updater
{
	function __construct()
	{
		parent::__construct(__FILE__);
	}
	
	//-------------------------------------------------------------------------------------------------------
	// RulesUpdater.clubs
	//-------------------------------------------------------------------------------------------------------
	function clubs_task($items_count)
	{
		if (!isset($this->vars->club))
		{
			$this->vars->club = 0;
		}
		
		if (!isset($this->vars->real_count))
		{
			$this->vars->real_count = 0;
			$old_real_count = -1;
		}
		else
		{
			$old_real_count = $this->vars->real_count;
		}

		$count = 0;
		$query = new DbQuery('SELECT id, rules FROM clubs WHERE id > ? ORDER BY id LIMIT ' . $items_count, $this->vars->club);
		while ($row = $query->next())
		{
			++$count;
			list($club_id, $rules_code) = $row;
			$new_rules_code = upgrade_rules_code($rules_code);
			if ($new_rules_code !== $rules_code)
			{
				Db::exec('club', 'UPDATE clubs SET rules = ? WHERE id = ?', $new_rules_code, $club_id);
				++$this->vars->real_count;
			}
			$this->vars->club = (int)$club_id;
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		if ($old_real_count != $this->vars->real_count)
		{
			$this->log('Upgraded '.$this->vars->real_count.' rules codes');
		}
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// RulesUpdater.club_rules
	//-------------------------------------------------------------------------------------------------------
	function club_rules_task($items_count)
	{
		if (!isset($this->vars->club_rule))
		{
			$this->vars->club_rule = 0;
		}
		
		if (!isset($this->vars->real_count))
		{
			$this->vars->real_count = 0;
			$old_real_count = -1;
		}
		else
		{
			$old_real_count = $this->vars->real_count;
		}

		$count = 0;
		$query = new DbQuery('SELECT id, rules FROM club_rules WHERE id > ? ORDER BY id LIMIT ' . $items_count, $this->vars->club_rule);
		while ($row = $query->next())
		{
			++$count;
			list($club_rule_id, $rules_code) = $row;
			$new_rules_code = upgrade_rules_code($rules_code);
			if ($new_rules_code !== $rules_code)
			{
				Db::exec('rules', 'UPDATE club_rules SET rules = ? WHERE id = ?', $new_rules_code, $club_rule_id);
				++$this->vars->real_count;
			}
			$this->vars->club_rule = (int)$club_rule_id;
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		if ($old_real_count != $this->vars->real_count)
		{
			$this->log('Upgraded '.$this->vars->real_count.' rules codes');
		}
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// RulesUpdater.events
	//-------------------------------------------------------------------------------------------------------
	function events_task($items_count)
	{
		if (!isset($this->vars->event))
		{
			$this->vars->event = 0;
		}
		
		if (!isset($this->vars->real_count))
		{
			$this->vars->real_count = 0;
			$old_real_count = -1;
		}
		else
		{
			$old_real_count = $this->vars->real_count;
		}

		$count = 0;
		$query = new DbQuery('SELECT id, rules FROM events WHERE id > ? ORDER BY id LIMIT ' . $items_count, $this->vars->event);
		while ($row = $query->next())
		{
			++$count;
			list($event_id, $rules_code) = $row;
			$new_rules_code = upgrade_rules_code($rules_code);
			if ($new_rules_code !== $rules_code)
			{
				Db::exec('event', 'UPDATE events SET rules = ? WHERE id = ?', $new_rules_code, $event_id);
				++$this->vars->real_count;
			}
			$this->vars->event = (int)$event_id;
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		if ($old_real_count != $this->vars->real_count)
		{
			$this->log('Upgraded '.$this->vars->real_count.' rules codes');
		}
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// RulesUpdater.league_clubs
	//-------------------------------------------------------------------------------------------------------
	function league_clubs_task($items_count)
	{
		if (!isset($this->vars->league))
		{
			$this->vars->league = 0;
			$this->vars->club = 0;
		}
		
		if (!isset($this->vars->real_count))
		{
			$this->vars->real_count = 0;
			$old_real_count = -1;
		}
		else
		{
			$old_real_count = $this->vars->real_count;
		}

		$count = 0;
		$query = new DbQuery('SELECT league_id, club_id, rules FROM league_clubs WHERE league_id > ? OR (league_id = ? AND club_id > ?) ORDER BY league_id, club_id LIMIT ' . $items_count, $this->vars->league, $this->vars->league, $this->vars->club);
		while ($row = $query->next())
		{
			++$count;
			list($league_id, $club_id, $rules_code) = $row;
			$new_rules_code = upgrade_rules_code($rules_code);
			if ($new_rules_code !== $rules_code)
			{
				Db::exec('league_club', 'UPDATE league_clubs SET rules = ? WHERE league_id = ? AND club_id = ?', $new_rules_code, $league_id, $club_id);
				++$this->vars->real_count;
			}
			
			if ($this->vars->league != $league_id)
			{
				$this->vars->league = (int)$league_id;
				$this->vars->club = (int)0;
			}
			else
			{
				$this->vars->club = (int)$club_id;
			}
			
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		if ($old_real_count != $this->vars->real_count)
		{
			$this->log('Upgraded '.$this->vars->real_count.' rules codes');
		}
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// RulesUpdater.tournaments
	//-------------------------------------------------------------------------------------------------------
	function tournaments_task($items_count)
	{
		if (!isset($this->vars->tournament))
		{
			$this->vars->tournament = 0;
		}
		
		if (!isset($this->vars->real_count))
		{
			$this->vars->real_count = 0;
			$old_real_count = -1;
		}
		else
		{
			$old_real_count = $this->vars->real_count;
		}

		$count = 0;
		$query = new DbQuery('SELECT id, rules FROM tournaments WHERE id > ? ORDER BY id LIMIT ' . $items_count, $this->vars->tournament);
		while ($row = $query->next())
		{
			++$count;
			list($tournament_id, $rules_code) = $row;
			$new_rules_code = upgrade_rules_code($rules_code);
			if ($new_rules_code !== $rules_code)
			{
				Db::exec('tournament', 'UPDATE tournaments SET rules = ? WHERE id = ?', $new_rules_code, $tournament_id);
				++$this->vars->real_count;
			}
			$this->vars->tournament = (int)$tournament_id;
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		if ($old_real_count != $this->vars->real_count)
		{
			$this->log('Upgraded '.$this->vars->real_count.' rules codes');
		}
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// RulesUpdater.games
	//-------------------------------------------------------------------------------------------------------
	function games_task($items_count)
	{
		if (!isset($this->vars->game))
		{
			$this->vars->game = 0;
		}
		
		if (!isset($this->vars->real_count))
		{
			$this->vars->real_count = 0;
			$old_real_count = -1;
		}
		else
		{
			$old_real_count = $this->vars->real_count;
		}

		$count = 0;
		$query = new DbQuery('SELECT id, json FROM games WHERE id > ? ORDER BY id LIMIT ' . $items_count, $this->vars->game);
		while ($row = $query->next())
		{
			++$count;
			list($game_id, $game) = $row;
			$game = json_decode($game);
			if (convert_game($game))
			{
				$game = json_encode($game);
				Db::exec('game', 'UPDATE games SET json = ? WHERE id = ?', $game, $game_id);
				++$this->vars->real_count;
			}
			$this->vars->game = (int)$game_id;
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		if ($old_real_count != $this->vars->real_count)
		{
			$this->log('Upgraded '.$this->vars->real_count.' games');
		}
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// RulesUpdater.current_games
	//-------------------------------------------------------------------------------------------------------
	function current_games_task($items_count)
	{
		if (!isset($this->vars->event))
		{
			$this->vars->event = 0;
			$this->vars->table = 0;
			$this->vars->game = 0;
		}
		
		if (!isset($this->vars->real_count))
		{
			$this->vars->real_count = 0;
			$old_real_count = -1;
		}
		else
		{
			$old_real_count = $this->vars->real_count;
		}

		$count = 0;
		$query = new DbQuery('SELECT event_id, table_num, game_num, game, log FROM current_games WHERE event_id > ? OR (event_id = ? AND (table_num > ? OR (table_num = ? AND game_num > ?))) ORDER BY event_id, table_num, game_num LIMIT ' . $items_count,
			$this->vars->event, $this->vars->event, $this->vars->table, $this->vars->table, $this->vars->game);
		while ($row = $query->next())
		{
			++$count;
			list($event_id, $table_num, $game_num, $game, $log) = $row;
			$changed = false;

			$game = json_decode($game);
			$changed = convert_game($game) || $changed;
			
			$log = json_decode($log);
			if ($log != null)
			{
				foreach ($log as $g)
				{
					$changed = convert_game($g) || $changed;
				}
			}
			
			if ($changed)
			{
				++$this->vars->real_count;
				Db::exec('game', 'UPDATE current_games SET game = ?, log = ? WHERE event_id = ? AND table_num = ? AND game_num = ?', json_encode($game), json_encode($log), $event_id, $table_num, $game_num);
			}
			
			if ($this->vars->event != $event_id)
			{
				$this->vars->event = (int)$event_id;
				$this->vars->table = 0;
				$this->vars->game = 0;
			}
			else if ($this->vars->table != $table_num)
			{
				$this->vars->table = $table_num;
				$this->vars->game = 0;
			}
			else
			{
				$this->vars->game = (int)$game_num;
			}

			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		if ($old_real_count != $this->vars->real_count)
		{
			$this->log('Upgraded '.$this->vars->real_count.' games');
		}
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// RulesUpdater.bug_reports
	//-------------------------------------------------------------------------------------------------------
	function bug_reports_task($items_count)
	{
		if (!isset($this->vars->bug_report))
		{
			$this->vars->bug_report = 0;
		}
		
		if (!isset($this->vars->real_count))
		{
			$this->vars->real_count = 0;
			$old_real_count = -1;
		}
		else
		{
			$old_real_count = $this->vars->real_count;
		}

		$count = 0;
		$query = new DbQuery('SELECT id, game, log FROM bug_reports WHERE id > ? ORDER BY id LIMIT ' . $items_count, $this->vars->bug_report);
		while ($row = $query->next())
		{
			++$count;
			list($bug_id, $game, $log) = $row;
			$changed = false;

			$game = json_decode($game);
			$changed = convert_game($game) || $changed;

			$log = json_decode($log);
			if ($log != null)
			{
				foreach ($log as $g)
				{
					$changed = convert_game($g) || $changed;
				}
			}
			
			if ($changed)
			{
				++$this->vars->real_count;
				Db::exec('game', 'UPDATE bug_reports SET game = ?, log = ? WHERE id = ?', json_encode($game), json_encode($log), $bug_id);
			}
			$this->vars->bug_report = $bug_id;

			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		if ($old_real_count != $this->vars->real_count)
		{
			$this->log('Upgraded '.$this->vars->real_count.' games');
		}
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// RulesUpdater.game_issues
	//-------------------------------------------------------------------------------------------------------
	function game_issues_task($items_count)
	{
		if (!isset($this->vars->game_issue))
		{
			$this->vars->game_issue = 0;
		}
		
		if (!isset($this->vars->real_count))
		{
			$this->vars->real_count = 0;
			$old_real_count = -1;
		}
		else
		{
			$old_real_count = $this->vars->real_count;
		}

		$count = 0;
		$query = new DbQuery('SELECT game_id, json FROM game_issues WHERE game_id > ? ORDER BY game_id LIMIT ' . $items_count, $this->vars->game_issue);
		while ($row = $query->next())
		{
			++$count;
			list($game_id, $game) = $row;
			$game = json_decode($game);
			if (convert_game($game))
			{
				$game = json_encode($game);
				Db::exec('game', 'UPDATE game_issues SET json = ? WHERE game_id = ?', $game, $game_id);
				++$this->vars->real_count;
			}
			$this->vars->game_issue = (int)$game_id;
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		if ($old_real_count != $this->vars->real_count)
		{
			$this->log('Upgraded '.$this->vars->real_count.' games');
		}
		return $count;
	}
}

$updater = new RulesUpdater();
$updater->run();

?>