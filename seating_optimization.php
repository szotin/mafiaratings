<?php

require_once 'include/updater.php';
require_once 'include/seating.php';

define('MAX_ITEMS_IN_RUN', 10000);
define('SEATING_PLAYERS_SKIP_RUNS', 20);
define('SEATING_NUMBERS_SKIP_RUNS', 20);
define('SEATING_TABLES_SKIP_RUNS', 20);
define('SEATING_SCORE_MIN_IMPROVEMENT', 0.001); // minimum score improvement to consider a real gain (avoids float rounding artifacts)

class SeatingOptimization extends Updater
{
	function __construct()
	{
		parent::__construct(__FILE__);
	}

	//-------------------------------------------------------------------------------------------------------
	// SeatingOptimization.seatings
	//-------------------------------------------------------------------------------------------------------
	private function _next_players_itteration()
	{
		if ($this->vars->current_round >= count($this->vars->seating))
		{
			return false;
		}
		
		++$this->vars->current_number2;
		if ($this->vars->current_number2 < 10)
		{
			return true;
		}
		$this->vars->current_number2 = 0;
		
		++$this->vars->current_table2;
		if ($this->vars->current_table2 < count($this->vars->seating[$this->vars->current_round]))
		{
			return true;
		}
		
		++$this->vars->current_number1;
		if ($this->vars->current_number1 < 10)
		{
			$this->vars->current_table2 = $this->vars->current_table1 + 1;
			return true;
		}
		
		$this->vars->current_number1 = 0;
		++$this->vars->current_table1;
		if ($this->vars->current_table1 < count($this->vars->seating[$this->vars->current_round]) - 1)
		{
			$this->vars->current_table2 = $this->vars->current_table1 + 1;
			return true;
		}
		
		++$this->vars->current_round;
		if ($this->vars->current_round < count($this->vars->seating))
		{
			$this->vars->current_table1 = 0;
			$this->vars->current_table2 = 1;
			if ($this->vars->current_table2 < count($this->vars->seating[$this->vars->current_round]))
			{
				return true;
			}
			++$this->vars->current_round;
		}
		
		if (isset($this->vars->found) && $this->vars->found)
		{
			$this->vars->found = false;
			$this->vars->current_number1 = 0;
			$this->vars->current_table1 = 0;
			$this->vars->current_number2 = 0;
			$this->vars->current_table2 = 1;
			$this->vars->current_round = 0;
			return true;
		}
		return false;
	}
	
	private function _swap_current_players()
	{
		$tmp = $this->vars->seating[$this->vars->current_round][$this->vars->current_table1][$this->vars->current_number1];
		$this->vars->seating[$this->vars->current_round][$this->vars->current_table1][$this->vars->current_number1] = 
			$this->vars->seating[$this->vars->current_round][$this->vars->current_table2][$this->vars->current_number2];
		$this->vars->seating[$this->vars->current_round][$this->vars->current_table2][$this->vars->current_number2] = $tmp;
	}
	
	function players_task($items_count)
	{
		if (is_null($this->vars->hash) || $this->vars->score <= 0 || $this->itemsProcessed() >= MAX_ITEMS_IN_RUN)
		{
			return 0;
		}
		
		if (!isset($this->seatingDef))
		{
			$this->seatingDef = new SeatingDef($this->vars->hash);
		}
		
		if ($this->seatingDef->tables <= 1)
		{
			$this->vars->current_round = count($this->vars->seating);
			return 0;
		}
		
		for ($count = 0; $count < $items_count && $this->_next_players_itteration(); ++$count)
		{
			//$this->log($this->vars->current_round . ': ' . $this->vars->current_table1 . '[' . $this->vars->current_number1 . '] ⇔ ' . $this->vars->current_table2 . '[' . $this->vars->current_number2 . ']');
			$this->_swap_current_players();
			$score = $this->seatingDef->calculatePlayersScore($this->vars->seating);
			if ($score < $this->vars->score)
			{
				// echo $this->vars->score . ' ↠ ' . $score . '<br>';
				$this->vars->score = $score;
				$this->vars->found = true;
			}
			else
			{
				$this->_swap_current_players();
			}
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		return $count;
	}
	
	function players_task_start()
	{
		$this->vars->hash = null;
		$hash = $this->getArg('hash');
		if ($hash == null)
		{
			$query = new DbQuery('SELECT hash, players_state FROM seatings WHERE players_full_runs < ' . SEATING_MAX_PLAYERS_OPTIMIZATIONS . ' AND players_score > 0 ORDER BY players_void_runs, players_runs LIMIT 1');
		}
		else
		{
			$query = new DbQuery('SELECT hash, players_state FROM seatings WHERE hash = ?', $hash);
		}
		if ($row = $query->next())
		{
			list ($this->vars->hash, $state) = $row;

			$this->seatingDef = new SeatingDef($this->vars->hash);
			if (empty($state))
			{
				$this->vars->seating = $this->seatingDef->generateInitialSeating();
				$this->vars->current_number1 = 0;
				$this->vars->current_table1 = 0;
				$this->vars->current_number2 = -1;
				$this->vars->current_table2 = 1;
				$this->vars->current_round = 0;
				$this->vars->found = false;
			}
			else
			{
				$state = json_decode($state);
				$this->vars->seating = $state->seating;
				$this->vars->current_number1 = $state->number1;
				$this->vars->current_table1 = $state->table1;
				$this->vars->current_number2 = $state->number2;
				$this->vars->current_table2 = $state->table2;
				$this->vars->current_round = $state->round;
				$this->vars->found = isset($state->found) && $state->found;
			}
			$this->vars->score = $this->seatingDef->calculatePlayersScore($this->vars->seating);
		}
		if ($this->vars->hash != null)
		{
			$this->important($this->vars->hash);
		}
	}

	function players_task_end()
	{
		if (is_null($this->vars->hash))
		{
			return;
		}
		
		Db::begin();
		list ($seating, $players_full_runs, $score, $players_skip_runs) = Db::record('seating', 'SELECT seating, players_full_runs, players_score, players_skip_runs FROM seatings WHERE hash = ?', $this->vars->hash);
		$seating = json_decode($seating);

		$this->log('Score: ' . round($this->vars->score) . '; Target: ' . round($score));
		$is_full_scan = ($this->vars->current_round >= count($this->vars->seating));
		if ($is_full_scan)
		{
			++$players_full_runs;
			$state = '';
			$this->log('Full scan end.');
		}
		else
		{
			$state = new stdClass();
			$state->seating = $this->vars->seating;
			$state->number1 = $this->vars->current_number1;
			$state->table1 = $this->vars->current_table1;
			$state->number2 = $this->vars->current_number2;
			$state->table2 = $this->vars->current_table2;
			$state->round = $this->vars->current_round;
			$state->found = isset($this->vars->found) && $this->vars->found;
			$state = json_encode($state);
			if ($this->vars->score == 0)
			{
				$players_full_runs = max($players_full_runs, 1); // We need to make sure it is greater than 0 for a perfect score, because number/table optimizers always wait until players optimizer make at least one full run. This is done to avoid numbers/tables optimizations to seatings that are often changing.
			}
		}

		$counter_reached = ($players_skip_runs + 1 >= SEATING_PLAYERS_SKIP_RUNS);
		$improved = ($score - $this->vars->score >= SEATING_SCORE_MIN_IMPROVEMENT);
		$save_seating = ($this->vars->score <= 0) || ($improved && ($is_full_scan || $counter_reached));
		$reset_counter = $is_full_scan || $counter_reached;

		if ($save_seating)
		{
			$this->log('Success!');

			if (isset($this->seatingDef))
			{
				$seatingDef = $this->seatingDef;
			}
			else
			{
				$seatingDef = new SeatingDef($this->vars->hash);
			}
			$this->vars->seating = $seatingDef->renumberByDistribution($this->vars->seating);
			$numbers_score = $seatingDef->calculateNumbersScore($this->vars->seating);
			$tables_score = $seatingDef->calculateTablesScore($this->vars->seating);
			Db::exec('seating',
				'UPDATE seatings SET players_state = ?, seating = ?, players_runs = players_runs + 1, players_full_runs = ?, players_score = ?, players_skip_runs = 0'.
				', numbers_runs = 0, numbers_full_runs = 0, numbers_void_runs = 0, numbers_state = "", numbers_score = ?'.
				', tables_runs = 0, tables_full_runs = 0, tables_void_runs = 0, tables_state = "", tables_score = ?'.
				' WHERE hash = ?', $state, json_encode($this->vars->seating), $players_full_runs, $this->vars->score, $numbers_score, $tables_score, $this->vars->hash);
			$r = ensure_seating_existance($this->vars->seating);
			if ($r->created)
			{
				$this->log('Also created seating for hash: ' . $r->hash);
			}
		}
		else
		{
			$skip_runs_expr = $reset_counter ? '0' : 'players_skip_runs + 1';
			$void_runs_expr = $improved ? '' : ' + 1';
			Db::exec('seating',
				'UPDATE seatings SET players_state = ?, players_runs = players_runs + 1, players_full_runs = ?, players_void_runs = players_void_runs' . $void_runs_expr . ', players_skip_runs = ' . $skip_runs_expr .
				' WHERE hash = ?', $state, $players_full_runs, $this->vars->hash);
		}
		Db::commit();
		
		if (isset($this->seatingDef))
		{
			unset($this->seatingDef);
		}
	}
	
	//-------------------------------------------------------------------------------------------------------
	// SeatingOptimization.tables
	//-------------------------------------------------------------------------------------------------------
	private function _next_tables_itteration()
	{
		if ($this->vars->current_round >= count($this->vars->seating))
		{
			return false;
		}
		
		if ($this->seatingDef->tables < 2)
		{
			return false;
		}
		
		$tables = count($this->vars->seating[$this->vars->current_round]);
		
		++$this->vars->current_table2;
		if ($this->vars->current_table2 < $tables)
		{
			return true;
		}
		
		++$this->vars->current_table1;
		if ($this->vars->current_table1 < $tables - 1)
		{
			$this->vars->current_table2 = $this->vars->current_table1 + 1;
			return true;
		}
		$this->vars->current_table1 = 0;
		$this->vars->current_table2 = 1;
		
		do
		{
			++$this->vars->current_round;
		}
		while ($this->vars->current_round < count($this->vars->seating) && count($this->vars->seating[$this->vars->current_round]) <= 1);
		
		return $this->vars->current_round < count($this->vars->seating);
	}
	
	private function _swap_current_tables()
	{
		$tmp = $this->vars->seating[$this->vars->current_round][$this->vars->current_table1];
		$this->vars->seating[$this->vars->current_round][$this->vars->current_table1] = 
			$this->vars->seating[$this->vars->current_round][$this->vars->current_table2];
		$this->vars->seating[$this->vars->current_round][$this->vars->current_table2] = $tmp;
	}
	
	private function _shuffle_tables()
	{
		foreach ($this->vars->seating as $ri => $round)
		{
			$round = (array)$round;
			shuffle($round);
			$this->vars->seating[$ri] = $round;
		}
	}
	
	function tables_task($items_count)
	{
		if (is_null($this->vars->hash) || $this->vars->score <= 0 || $this->itemsProcessed() >= MAX_ITEMS_IN_RUN)
		{
			return 0;
		}
		
		if (!isset($this->seatingDef))
		{
			$this->seatingDef = new SeatingDef($this->vars->hash);
		}
		
		for ($count = 0; $count < $items_count && $this->_next_tables_itteration(); ++$count)
		{
			$score = $this->seatingDef->calculateTablesScore($this->vars->seating);
			if ($score < $this->vars->score)
			{
				// echo $this->vars->score . ' ↠ ' . $score . '<br>';
				$this->vars->score = $score;
				$this->vars->current_table1 = 0;
				$this->vars->current_table2 = 0;
				$this->vars->current_round = 0;
			}
			else
			{
				$this->_swap_current_tables();
			}
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		return $count;
	}

	function tables_task_start()
	{
		$this->vars->hash = null;
		$hash = $this->getArg('hash');
		if ($hash == null)
		{
			$query = new DbQuery('SELECT hash, seating, tables_state FROM seatings WHERE tables_full_runs < ' . SEATING_MAX_TABLES_OPTIMIZATIONS . ' AND tables_score > 0 AND numbers_state = "" ORDER BY tables_void_runs, tables_runs LIMIT 1');
		}
		else
		{
			$query = new DbQuery('SELECT hash, seating, tables_state FROM seatings WHERE hash = ?', $hash);
		}
		if ($row = $query->next())
		{
			list ($this->vars->hash, $seating, $state) = $row;

			$this->seatingDef = new SeatingDef($this->vars->hash);
			if (empty($state))
			{
				$this->vars->seating = json_decode($seating);
				$this->vars->current_table1 = 0;
				$this->vars->current_table2 = 0;
				$this->vars->current_round = 0;
				$this->_shuffle_tables();
			}
			else
			{
				$state = json_decode($state);
				$this->vars->seating = $state->seating;
				$this->vars->current_table1 = $state->table1;
				$this->vars->current_table2 = $state->table2;
				$this->vars->current_round = $state->round;
			}
			$this->vars->score = $this->seatingDef->calculateTablesScore($this->vars->seating);
		}
		if ($this->vars->hash != null)
		{
			$this->important($this->vars->hash);
		}
	}

	function tables_task_end()
	{
		if (is_null($this->vars->hash))
		{
			return;
		}
		
		Db::begin();
		list ($seating, $tables_full_runs, $score, $tables_skip_runs) = Db::record('seating', 'SELECT seating, tables_full_runs, tables_score, tables_skip_runs FROM seatings WHERE hash = ?', $this->vars->hash);
		$seating = json_decode($seating);

		$this->log('Score: ' . round($this->vars->score) . '; Target: ' . round($score));
		$is_full_scan = ($this->vars->current_round >= count($this->vars->seating));
		if ($is_full_scan)
		{
			++$tables_full_runs;
			$state = '';
			$this->log('Full scan end.');
		}
		else
		{
			$state = new stdClass();
			$state->seating = $this->vars->seating;
			$state->table1 = $this->vars->current_table1;
			$state->table2 = $this->vars->current_table2;
			$state->round = $this->vars->current_round;
			$state = json_encode($state);
		}

		$counter_reached = ($tables_skip_runs + 1 >= SEATING_TABLES_SKIP_RUNS);
		$improved = ($score - $this->vars->score >= SEATING_SCORE_MIN_IMPROVEMENT);
		$save_seating = ($this->vars->score <= 0) || ($improved && ($is_full_scan || $counter_reached));
		$reset_counter = $is_full_scan || $counter_reached;

		if ($save_seating)
		{
			$this->log('Success!');
			Db::exec('seating',
				'UPDATE seatings SET tables_state = ?, seating = ?, tables_runs = tables_runs + 1, tables_full_runs = ?, tables_score = ?, tables_skip_runs = 0, numbers_state = ""'.
				' WHERE hash = ?', $state, json_encode($this->vars->seating), $tables_full_runs, $this->vars->score, $this->vars->hash);
		}
		else
		{
			$skip_runs_expr = $reset_counter ? '0' : 'tables_skip_runs + 1';
			$void_runs_expr = $improved ? '' : ' + 1';
			Db::exec('seating',
				'UPDATE seatings SET tables_state = ?, tables_runs = tables_runs + 1, tables_full_runs = ?, tables_void_runs = tables_void_runs' . $void_runs_expr . ', tables_skip_runs = ' . $skip_runs_expr .
				' WHERE hash = ?', $state, $tables_full_runs, $this->vars->hash);
		}
		Db::commit();
		
		if (isset($this->seatingDef))
		{
			unset($this->seatingDef);
		}
		
	}
	
	//-------------------------------------------------------------------------------------------------------
	// SeatingOptimization.numbers
	//-------------------------------------------------------------------------------------------------------
	private function _next_numbers_itteration()
	{
		if ($this->vars->current_round >= count($this->vars->seating))
		{
			return false;
		}
		
		++$this->vars->current_number2;
		if ($this->vars->current_number2 < 10)
		{
			return true;
		}
		
		++$this->vars->current_number1;
		if ($this->vars->current_number1 < 9)
		{
			$this->vars->current_number2 = $this->vars->current_number1 + 1;
			return true;
		}
		$this->vars->current_number1 = 0;
		$this->vars->current_number2 = 1;
		
		++$this->vars->current_table;
		if ($this->vars->current_table < count($this->vars->seating[$this->vars->current_round]))
		{
			return true;
		}
		$this->vars->current_table = 0;
		
		++$this->vars->current_round;
		return $this->vars->current_round < count($this->vars->seating);
	}
	
	private function _swap_current_numbers()
	{
		$tmp = $this->vars->seating[$this->vars->current_round][$this->vars->current_table][$this->vars->current_number1];
		$this->vars->seating[$this->vars->current_round][$this->vars->current_table][$this->vars->current_number1] = 
			$this->vars->seating[$this->vars->current_round][$this->vars->current_table][$this->vars->current_number2];
		$this->vars->seating[$this->vars->current_round][$this->vars->current_table][$this->vars->current_number2] = $tmp;
	}
	
	private function _shuffle_numbers()
	{
		foreach ($this->vars->seating as $ri => $round)
		{
			foreach ($round as $ti => $table)
			{
				$table = (array)$table;
				shuffle($table);
				$this->vars->seating[$ri][$ti] = $table;
			}
		}
	}
	
	function numbers_task($items_count)
	{
		if (is_null($this->vars->hash) || $this->vars->score <= 0 || $this->itemsProcessed() >= MAX_ITEMS_IN_RUN)
		{
			return 0;
		}
		
		if (!isset($this->seatingDef))
		{
			$this->seatingDef = new SeatingDef($this->vars->hash);
		}
		
		for ($count = 0; $count < $items_count && $this->_next_numbers_itteration(); ++$count)
		{
			$this->_swap_current_numbers();
			$score = $this->seatingDef->calculateNumbersScore($this->vars->seating);
			if ($score < $this->vars->score)
			{
				// echo $this->vars->score . ' ↠ ' . $score . '<br>';
				$this->vars->score = $score;
				$this->vars->current_number1 = 0;
				$this->vars->current_number2 = 0;
				$this->vars->current_table = 0;
				$this->vars->current_round = 0;
			}
			else
			{
				$this->_swap_current_numbers();
			}
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		return $count;
	}

	function numbers_task_start()
	{
		$this->vars->hash = null;
		$hash = $this->getArg('hash');
		if ($hash == null)
		{
			$query = new DbQuery('SELECT hash, seating, numbers_state FROM seatings WHERE numbers_full_runs < ' . SEATING_MAX_NUMBERS_OPTIMIZATIONS . ' AND numbers_score > 0 AND tables_state = "" ORDER BY numbers_void_runs, numbers_runs LIMIT 1');
		}
		else
		{
			$query = new DbQuery('SELECT hash, seating, numbers_state FROM seatings WHERE hash = ?', $hash);
		}
		if ($row = $query->next())
		{
			list ($this->vars->hash, $seating, $state) = $row;
			
			$this->seatingDef = new SeatingDef($this->vars->hash);
			if (empty($state))
			{
				$this->vars->seating = json_decode($seating);
				$this->vars->current_number1 = 0;
				$this->vars->current_number2 = 0;
				$this->vars->current_table = 0;
				$this->vars->current_round = 0;
				$this->_shuffle_numbers();
			}
			else
			{
				$state = json_decode($state);
				$this->vars->seating = $state->seating;
				$this->vars->current_number1 = $state->number1;
				$this->vars->current_number2 = $state->number2;
				$this->vars->current_table = $state->table;
				$this->vars->current_round = $state->round;
			}
			
			$this->vars->score = $this->seatingDef->calculateNumbersScore($this->vars->seating);
		}
		if ($this->vars->hash != null)
		{
			$this->important($this->vars->hash);
		}
	}

	function numbers_task_end()
	{
		if (is_null($this->vars->hash))
		{
			return;
		}
		
		Db::begin();
		list ($seating, $numbers_full_runs, $score, $numbers_skip_runs) = Db::record('seating', 'SELECT seating, numbers_full_runs, numbers_score, numbers_skip_runs FROM seatings WHERE hash = ?', $this->vars->hash);
		$seating = json_decode($seating);

		$this->log('Score: ' . round($this->vars->score) . '; Target: ' . round($score));
		$is_full_scan = ($this->vars->current_round >= count($this->vars->seating));
		if ($is_full_scan)
		{
			++$numbers_full_runs;
			$state = '';
			$this->log('Full scan end.');
		}
		else
		{
			$state = new stdClass();
			$state->seating = $this->vars->seating;
			$state->number1 = $this->vars->current_number1;
			$state->number2 = $this->vars->current_number2;
			$state->table = $this->vars->current_table;
			$state->round = $this->vars->current_round;
			$state = json_encode($state);
		}

		$counter_reached = ($numbers_skip_runs + 1 >= SEATING_NUMBERS_SKIP_RUNS);
		$improved = ($score - $this->vars->score >= SEATING_SCORE_MIN_IMPROVEMENT);
		$save_seating = ($this->vars->score <= 0) || ($improved && ($is_full_scan || $counter_reached));
		$reset_counter = $is_full_scan || $counter_reached;

		if ($save_seating)
		{
			$this->log('Success!');
			Db::exec('seating',
				'UPDATE seatings SET numbers_state = ?, seating = ?, numbers_runs = numbers_runs + 1, numbers_full_runs = ?, numbers_score = ?, numbers_skip_runs = 0, tables_state = ""'.
				' WHERE hash = ?', $state, json_encode($this->vars->seating), $numbers_full_runs, $this->vars->score, $this->vars->hash);
		}
		else
		{
			$skip_runs_expr = $reset_counter ? '0' : 'numbers_skip_runs + 1';
			$void_runs_expr = $improved ? '' : ' + 1';
			Db::exec('seating',
				'UPDATE seatings SET numbers_state = ?, numbers_runs = numbers_runs + 1, numbers_full_runs = ?, numbers_void_runs = numbers_void_runs' . $void_runs_expr . ', numbers_skip_runs = ' . $skip_runs_expr .
				' WHERE hash = ?', $state, $numbers_full_runs, $this->vars->hash);
		}
		Db::commit();

		if (isset($this->seatingDef))
		{
			unset($this->seatingDef);
		}
	}

	//-------------------------------------------------------------------------------------------------------
	// SeatingOptimization.extract_seatings
	// Scan every tournament that has not yet been processed and try to seed the seatings table from
	// its event data (misc.seating without a version, or raw game records).
	// Once a tournament is processed (regardless of outcome) it receives TOURNAMENT_FLAG_SEATING_EXTRACTED
	// so it is never visited again.
	//-------------------------------------------------------------------------------------------------------
	function extract_seatings_task_start()
	{
		$this->vars->last_id = 0;
	}

	function extract_seatings_task($items_count)
	{
		$count = 0;
		// Only process finished tournaments that have not yet been visited.
		$mask = TOURNAMENT_FLAG_FINISHED | TOURNAMENT_FLAG_SEATING_EXTRACTED;
		$query = new DbQuery(
			'SELECT id FROM tournaments WHERE id > ? AND (flags & ?) = ? ORDER BY id LIMIT ' . $items_count,
			$this->vars->last_id, $mask, TOURNAMENT_FLAG_FINISHED);
		while ($row = $query->next())
		{
			++$count;
			$tid = (int)$row[0];
			$this->vars->last_id = $tid;
			$this->_extract_tournament_seating($tid);
			Db::exec('tournament', 'UPDATE tournaments SET flags = flags | ? WHERE id = ?',
				TOURNAMENT_FLAG_SEATING_EXTRACTED, $tid);
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		return $count;
	}

	// Try to find and store a canonical seating for every event in a tournament
	// (main round, semifinals, finals — whatever exists).
	private function _extract_tournament_seating($tid)
	{
		$q = new DbQuery('SELECT id, misc FROM events WHERE tournament_id = ? ORDER BY round, id', $tid);
		while ($row = $q->next())
		{
			list($event_id, $misc_str) = $row;
			$event_id = (int)$event_id;
			$misc = $misc_str !== null ? json_decode($misc_str) : null;

			if ($misc !== null && isset($misc->seating) && isset($misc->seating->rounds))
			{
				if (isset($misc->seating->version))
				{
					continue; // Seating was assigned from the seatings table — nothing to do.
				}

				// Seating exists but was stored without a version (e.g. old DimTom import or
				// direct assignment before versioning was introduced).  Seed it now.
				// Values may be user IDs rather than 0-based indices, so normalise first.
				$rounds = json_decode(json_encode($misc->seating->rounds), true);
				if (is_array($rounds))
				{
					$rounds = normalize_seating_to_indices($rounds);
					$this->_log_extracted($tid, $event_id, 'misc', ensure_seating_existance($rounds));
					continue;
				}
			}

			// No misc.seating — try to reconstruct the seating from individual game records.
			$extracted = $this->_seating_from_games($tid, $event_id);
			if ($extracted !== null)
			{
				$r = ensure_seating_existance($extracted->rounds);
				$this->_log_extracted($tid, $event_id, 'games', $r);
				if ($r->hash !== null)
				{
					$this->_write_seating_to_event_misc($event_id, $misc, $r->hash, $extracted->rounds, $extracted->mapping);
				}
			}
		}
	}

	// Build misc.seating from the extracted seating and persist it on the event so future
	// runs (and other code that reads misc.seating) can use it directly.
	private function _write_seating_to_event_misc($event_id, $misc, $hash, $rounds, $mapping)
	{
		list($pr, $pvr, $tr, $tvr, $nr, $nvr) = Db::record(get_label('seating'),
			'SELECT players_runs, players_void_runs, tables_runs, tables_void_runs, numbers_runs, numbers_void_runs FROM seatings WHERE hash = ?',
			$hash);
		if ($misc === null)
		{
			$misc = new stdClass();
		}
		$misc->seating          = new stdClass();
		$misc->seating->hash    = $hash;
		$misc->seating->version = ($pr - $pvr) . '.' . ($tr - $tvr) . '.' . ($nr - $nvr);
		$misc->seating->rounds  = $rounds;
		$misc->seating->mapping = $mapping;
		Db::exec('event', 'UPDATE events SET misc = ? WHERE id = ?', json_encode($misc), $event_id);
	}

	private function _log_extracted($tid, $event_id, $source, $r)
	{
		$prefix = 'Tournament ' . $tid . ' event ' . $event_id;
		if ($r->hash === null)
		{
			$this->log($prefix . ' has invalid seating in ' . $source . ' (unequal play counts).');
		}
		else if ($r->created)
		{
			$this->log($prefix . ' extracted seating ' . $r->hash . ' from ' . $source . '.');
		}
		else
		{
			$this->log($prefix . ' extracted seating ' . $r->hash . ' from ' . $source . ' — already exists.');
		}
	}

	// Reconstruct a [round][table][seat]=slot seating from the games of one event.
	// Returns null when no valid uniform seating can be built.
	private function _seating_from_games($tid, $event_id)
	{
		// Load all rated, non-canceled games with their seated players in one query.
		// $games[i] = ['tnum' => ?, 'gnum' => ?, 'seats' => [0..9 => user_id]]
		$games = array();
		$q = new DbQuery(
			'SELECT g.id, g.table_num, g.game_num, p.number, p.user_id' .
			' FROM games g JOIN players p ON p.game_id = g.id' .
			' WHERE g.event_id = ? AND (g.flags & ' . (GAME_FLAG_CANCELED | GAME_FLAG_RATING) . ') = ' . GAME_FLAG_RATING .
			' ORDER BY g.id, p.number',
			$event_id);
		while ($row = $q->next())
		{
			$gid = (int)$row[0];
			if (!isset($games[$gid]))
			{
				$games[$gid] = array(
					'tnum'  => $row[1] !== null ? (int)$row[1] : null,
					'gnum'  => $row[2] !== null ? (int)$row[2] : null,
					'seats' => array(),
				);
			}
			$games[$gid]['seats'][(int)$row[3] - 1] = (int)$row[4];
		}
		// Keep only games with a full table of 10 players, re-index sequentially.
		foreach ($games as $gid => $g)
		{
			if (count($g['seats']) != 10)
			{
				unset($games[$gid]);
			}
		}
		$games = array_values($games);
		if (empty($games))
		{
			$this->log('Tournament ' . $tid . ' event ' . $event_id . ' has no rated games with a full table.');
			return null;
		}

		if ($games[0]['tnum'] === null || $games[0]['gnum'] === null)
		{
			$this->log('Tournament ' . $tid . ' event ' . $event_id . ' has no table/game numbers — seating cannot be extracted.');
			return null;
		}

		// Build raw seating: [round][table][seat] = user_id
		$raw = array();
		foreach ($games as $g)
		{
			if ($g['tnum'] !== null && $g['gnum'] !== null)
			{
				$raw[$g['gnum'] - 1][$g['tnum'] - 1] = $g['seats'];
			}
		}

		if (empty($raw))
		{
			$this->log('Tournament ' . $tid . ' event ' . $event_id . ' produced no rounds.');
			return null;
		}
		ksort($raw);
		foreach ($raw as &$round)
		{
			ksort($round);
		}
		unset($round);

		// Count how many rounds each user participated in.
		$user_rounds = array(); // user_id → [round => true]
		foreach ($raw as $r => $round)
		{
			foreach ($round as $table)
			{
				foreach ($table as $uid)
				{
					$user_rounds[$uid][$r] = true;
				}
			}
		}

		$round_counts = array_map('count', $user_rounds);

		if (count(array_unique($round_counts)) != 1)
		{
			// Substitute players: merge users with disjoint round sets summing to expected.
			$merge_map = $this->_merge_substitute_players($user_rounds, $round_counts, max($round_counts));
			if ($merge_map === null)
			{
				$this->log('Tournament ' . $tid . ' event ' . $event_id . ' unable to merge players with different number of games.');
				return null;
			}

			foreach ($raw as $r => $round)
			{
				foreach ($round as $t => $table)
				{
					foreach ($table as $seat => $uid)
					{
						if (isset($merge_map[$uid]))
						{
							$raw[$r][$t][$seat] = $merge_map[$uid];
						}
					}
				}
			}

			// Remap user_rounds via merge_map.
			$merged = array();
			foreach ($user_rounds as $uid => $rounds)
			{
				$canonical = isset($merge_map[$uid]) ? $merge_map[$uid] : $uid;
				$merged[$canonical] = isset($merged[$canonical]) ? $merged[$canonical] + $rounds : $rounds;
			}
			$user_rounds = $merged;
			if (count(array_unique(array_map('count', $user_rounds))) != 1)
			{
				$this->log('Tournament ' . $tid . ' event ' . $event_id . ' player round counts still uneven after merge.');
				return null;
			}
		}

		// Convert user_ids to 0-based slot indices.
		$users = array_keys($user_rounds);
		sort($users);
		$u2s = array_flip($users);

		$seating = array();
		foreach ($raw as $r => $round)
		{
			$seating[$r] = array();
			foreach ($round as $t => $table)
			{
				$seating[$r][$t] = array();
				foreach ($table as $uid)
				{
					$seating[$r][$t][] = $u2s[$uid];
				}
			}
		}
		$result = new stdClass();
		$result->rounds  = $seating;
		$result->mapping = $users; // slot → user_id
		return $result;
	}

	// Greedy merge for substitute players.
	// Partial users (< $expected rounds) are grouped so that each group's round sets are
	// pairwise disjoint and their combined round count equals $expected.
	// Returns merge_map [user_id → canonical_user_id] for non-canonical members, or null on failure.
	private function _merge_substitute_players($user_rounds, $round_counts, $expected)
	{
		$partial = array_keys(array_filter($round_counts, function($c) use ($expected) {
			return $c < $expected;
		}));

		$merge_map = array();
		$assigned  = array();

		foreach ($partial as $uid)
		{
			if (isset($assigned[$uid]))
			{
				continue;
			}
			$group      = array($uid);
			$grp_rounds = $user_rounds[$uid];
			$grp_count  = $round_counts[$uid];
			$assigned[$uid] = true;

			while ($grp_count < $expected)
			{
				$found = false;
				foreach ($partial as $uid2)
				{
					if (isset($assigned[$uid2]))
					{
						continue;
					}
					if ($grp_count + $round_counts[$uid2] > $expected)
					{
						continue;
					}
					// Round sets must be disjoint.
					if (!empty(array_intersect_key($grp_rounds, $user_rounds[$uid2])))
					{
						continue;
					}
					$group[]    = $uid2;
					$grp_rounds += $user_rounds[$uid2];
					$grp_count  += $round_counts[$uid2];
					$assigned[$uid2] = true;
					$found = true;
					break;
				}
				if (!$found)
				{
					return null;
				}
			}
			if ($grp_count !== $expected)
			{
				return null;
			}

			$canonical = $group[0];
			foreach ($group as $gm)
			{
				if ($gm !== $canonical)
				{
					$merge_map[$gm] = $canonical;
				}
			}
		}
		return $merge_map;
	}
}

$updater = new SeatingOptimization();
$updater->run();

?>
