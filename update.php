<?php

require_once 'include/updater.php';
require_once 'include/rules.php';

define('UPDATE_CLUBS', 0);
define('UPDATE_EVENTS', 1);
define('UPDATE_TOURNAMENTS', 2);
define('UPDATE_GAMES', 3);
define('UPDATE_CURRENT_GAMES', 4);
define('UPDATE_LEAGUES', 5);
define('UPDATE_SERIES', 6);
define('UPDATE_CLUB_RULES', 7);
define('UPDATE_LEAGUE_CLUBS', 8);

class UpdateRules extends Updater
{
	function __construct()
	{
		parent::__construct(__FILE__);
	}
	
	private function convertRules($rules)
	{
		if (strlen($rules) == 12)
		{
			return $rules;
		}
		
		if (strlen($rules) == 13)
		{
			return substr($rules, 1);
		}
		
		while (strlen($rules) < 29)
		{
			$rules .= '0';
		}
		
		$rules = substr($rules, 0, 20);
		$rules = substr($rules, 0, 17) . substr($rules, 19);
		$rules = substr($rules, 0, 12) . substr($rules, 15);
		$rules = substr($rules, 0, 9) . substr($rules, 10);
		$rules = substr($rules, 0, 6) . substr($rules, 8);
		$rules = substr($rules, 0, 4) . substr($rules, 5);
		$rules = substr($rules, 3);
		
		$rules = substr($rules, 0, 1) . '00' . substr($rules, 1);
		$rules = substr($rules, 0, 5) . '0' . substr($rules, 5);
		$rules = substr($rules, 0, 8) . '0' . substr($rules, 8);
		return $rules;
	}
	
	private function convertRulesFilter($rules)
	{
		global $_rules_options;
		$new_rules = new stdClass();
		foreach ($_rules_options as $option)
		{
			$name = $option[RULE_OPTION_NAME];
			if (isset($rules->$name))
			{
				$new_rules->$name = $rules->$name; 
			}
		}
		return $new_rules;
	}
	
	private function updateClubs($state)
	{
		$updated = false;
		if (!isset($state->clubCount))
		{
			$state->clubCount = 0;
		}
		
		Db::begin();
		$query = new DbQuery('SELECT id, rules FROM clubs WHERE LENGTH(rules) <> 12 LIMIT 50');
		while ($row = $query->next())
		{
			list ($club_id, $rules) = $row;
			Db::exec('club', 'UPDATE clubs SET rules = ? WHERE id = ?', $this->convertRules($rules), $club_id);
			++$state->clubCount;
			$updated = true;
		}
		Db::commit();
		
		if (!$updated)
		{
			$this->log('Updated ' . $state->clubCount . ' clubs');
		}
		return $updated;
	}
	
	private function updateEvents($state)
	{
		$updated = false;
		if (!isset($state->eventCount))
		{
			$state->eventCount = 0;
		}
		
		Db::begin();
		$query = new DbQuery('SELECT id, rules FROM events WHERE LENGTH(rules) <> 12 LIMIT 50');
		while ($row = $query->next())
		{
			list ($event_id, $rules) = $row;
			Db::exec('event', 'UPDATE events SET rules = ? WHERE id = ?', $this->convertRules($rules), $event_id);
			++$state->eventCount;
			$updated = true;
		}
		Db::commit();
		
		if (!$updated)
		{
			$this->log('Updated ' . $state->eventCount . ' events');
		}
		return $updated;
	}
	
	private function updateTournaments($state)
	{
		$updated = false;
		if (!isset($state->tournamentCount))
		{
			$state->tournamentCount = 0;
		}
		
		Db::begin();
		$query = new DbQuery('SELECT id, rules FROM tournaments WHERE LENGTH(rules) <> 12 LIMIT 50');
		while ($row = $query->next())
		{
			list ($tournament_id, $rules) = $row;
			Db::exec('tournament', 'UPDATE tournaments SET rules = ? WHERE id = ?', $this->convertRules($rules), $tournament_id);
			++$state->tournamentCount;
			$updated = true;
		}
		Db::commit();
		
		if (!$updated)
		{
			$this->log('Updated ' . $state->tournamentCount . ' tournaments');
		}
		return $updated;
	}
	
	private function updateGames($state)
	{
		$updated = false;
		if (!isset($state->gameCount))
		{
			$state->gameCount = 0;
		}
		
		Db::begin();
		$query = new DbQuery('SELECT id, rules, json FROM games WHERE LENGTH(rules) <> 12 LIMIT 50');
		while ($row = $query->next())
		{
			list ($game_id, $rules, $game) = $row;
			$game = json_decode($game);
			if (isset($game->rules))
			{
				$game->rules = $this->convertRules($game->rules);
			}
			$game = json_encode($game);
			Db::exec('game', 'UPDATE games SET rules = ?, json = ? WHERE id = ?', $this->convertRules($rules), $game, $game_id);
			++$state->gameCount;
			$updated = true;
		}
		Db::commit();
		
		if (!$updated)
		{
			$this->log('Updated ' . $state->gameCount . ' games');
		}
		return $updated;
	}
	
	private function updateCurrentGames($state)
	{
		$updated = false;
		if (!isset($state->currentGameCount))
		{
			$state->currentGameCount = 0;
		}
		
		Db::begin();
		$query = new DbQuery('SELECT event_id, table_num, round_num, game, log FROM current_games WHERE tmp = 0 LIMIT 50');
		while ($row = $query->next())
		{
			list ($event_id, $table, $round, $game, $log) = $row;
			
			$game = json_decode($game);
			if (isset($game->rules))
			{
				$game->rules = $this->convertRules($game->rules);
			}
			$game = json_encode($game);
			
			$log = json_decode($log);
			foreach ($log as $g)
			{
				if ($g && isset($g->rules))
				{
					$g->rules = $this->convertRules($g->rules);
				}
			}
			$log = json_encode($log);
			
			Db::exec('game', 'UPDATE current_games SET game = ?, log = ?, tmp = 1 WHERE event_id = ? AND table_num = ? AND round_num = ?', $game, $log, $event_id, $table, $round);
			++$state->currentGameCount;
			$updated = true;
		}
		Db::commit();
		
		if (!$updated)
		{
			$this->log('Updated ' . $state->currentGameCount . ' current games');
		}
		return $updated;
	}
	
	private function updateLeagues($state)
	{
		$updated = false;
		if (!isset($state->leagueCount))
		{
			$state->leagueCount = 0;
		}
		
		Db::begin();
		$query = new DbQuery('SELECT id, rules FROM leagues WHERE (langs & 64) = 0 LIMIT 50');
		while ($row = $query->next())
		{
			list ($league_id, $rules) = $row;
			$rules = json_decode($rules);
			$rules = $this->convertRulesFilter($rules);
			$rules = json_encode($rules);
			
			Db::exec('league', 'UPDATE leagues SET rules = ?, langs = langs | 64 WHERE id = ?', $rules, $league_id);
			++$state->leagueCount;
			$updated = true;
		}
		if (!$updated)
		{
			Db::exec('league', 'UPDATE leagues SET langs = langs & ~64');
			$this->log('Updated ' . $state->leagueCount . ' leagues');
		}
		Db::commit();
		return $updated;
	}
	
	private function updateSeries($state)
	{
		$updated = false;
		if (!isset($state->seriesCount))
		{
			$state->seriesCount = 0;
		}
		
		Db::begin();
		$query = new DbQuery('SELECT id, rules FROM series WHERE (langs & 64) = 0 LIMIT 50');
		while ($row = $query->next())
		{
			list ($series_id, $rules) = $row;
			$rules = json_decode($rules);
			$rules = $this->convertRulesFilter($rules);
			$rules = json_encode($rules);
			
			Db::exec('series', 'UPDATE series SET rules = ?, langs = langs | 64 WHERE id = ?', $rules, $series_id);
			++$state->seriesCount;
			$updated = true;
		}
		if (!$updated)
		{
			Db::exec('series', 'UPDATE series SET langs = langs & ~64');
			$this->log('Updated ' . $state->seriesCount . ' series');
		}
		Db::commit();
		return $updated;
	}

	private function updateClubRules($state)
	{
		$updated = false;
		if (!isset($state->clubRuleCount))
		{
			$state->clubRuleCount = 0;
		}
		
		Db::begin();
		$query = new DbQuery('SELECT id, rules FROM club_rules WHERE LENGTH(rules) <> 12 LIMIT 50');
		while ($row = $query->next())
		{
			list ($club_rule_id, $rules) = $row;
			Db::exec('club rules', 'UPDATE club_rules SET rules = ? WHERE id = ?', $this->convertRules($rules), $club_rule_id);
			++$state->clubRuleCount;
			$updated = true;
		}
		Db::commit();
		
		if (!$updated)
		{
			$this->log('Updated ' . $state->clubRuleCount . ' club rules');
		}
		return $updated;
	}

	private function updateLeagueClubs($state)
	{
		$updated = false;
		if (!isset($state->leagueClubCount))
		{
			$state->leagueClubCount = 0;
		}
		
		Db::begin();
		$query = new DbQuery('SELECT league_id, club_id, rules FROM league_clubs WHERE LENGTH(rules) <> 12 LIMIT 50');
		while ($row = $query->next())
		{
			list ($league_id, $club_id, $rules) = $row;
			Db::exec('league club', 'UPDATE league_clubs SET rules = ? WHERE league_id = ? AND club_id = ?', $this->convertRules($rules), $league_id, $club_id);
			++$state->leagueClubCount;
			$updated = true;
		}
		Db::commit();
		
		if (!$updated)
		{
			$this->log('Updated ' . $state->leagueClubCount . ' league clubs');
		}
		return $updated;
	}
	
	protected function onTimeout($state)
	{
		// In the future keep batch sizes in the $state and reduce it on timeout
	}
	
	protected function update($state)
	{
		if (!isset($state->stage))
		{
			$state->stage = UPDATE_CLUBS;
		}
		
		switch ($state->stage)
		{
		case UPDATE_CLUBS:
			if (!$this->updateClubs($state))
			{
				$state->stage = UPDATE_EVENTS;
			}
			break;
		case UPDATE_EVENTS:
			if (!$this->updateEvents($state))
			{
				$state->stage = UPDATE_TOURNAMENTS;
			}
			break;
		case UPDATE_TOURNAMENTS:
			if (!$this->updateTournaments($state))
			{
				$state->stage = UPDATE_GAMES;
			}
			break;
		case UPDATE_GAMES:
			if (!$this->updateGames($state))
			{
				$state->stage = UPDATE_CURRENT_GAMES;
			}
			break;
		case UPDATE_CURRENT_GAMES:
			if (!$this->updateCurrentGames($state))
			{
				$state->stage = UPDATE_LEAGUES;
			}
			break;
		case UPDATE_LEAGUES:
			if (!$this->updateLeagues($state))
			{
				$state->stage = UPDATE_SERIES;
			}
			break;
		case UPDATE_SERIES:
			if (!$this->updateSeries($state))
			{
				$state->stage = UPDATE_CLUB_RULES;
			}
			break;
		case UPDATE_CLUB_RULES:
			if (!$this->updateClubRules($state))
			{
				$state->stage = UPDATE_LEAGUE_CLUBS;
			}
			break;
		case UPDATE_LEAGUE_CLUBS:
			if (!$this->updateLeagueClubs($state))
			{
				$state->done = true;
			}
			break;
		}
	}
}

$updater = new UpdateRules();
$updater->run();

?>