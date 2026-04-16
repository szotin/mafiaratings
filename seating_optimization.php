<?php

require_once 'include/updater.php';
require_once 'include/seating.php';

define('MAX_ITEMS_IN_RUN', 10000);

class SeatingOptimization extends Updater
{
	function __construct()
	{
		parent::__construct(__FILE__);
		
		$this->infiniteTasks = true; // turn infinite tasks on to make sure we use all available time for optimization
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
		if (is_null($this->vars->hash))
		{
			return 0;
		}
		
		if (!isset($this->seatingDef))
		{
			$this->seatingDef = new SeatingDef($this->vars->hash);
		}
		
		if ($this->seatingDef->tables <= 1 || $this->vars->score <= 0 || $this->itemsProcessed() >= MAX_ITEMS_IN_RUN)
		{
			return 0;
		}
		
		for ($count = 0; $count < $items_count && $this->_next_players_itteration(); ++$count)
		{
			$this->_swap_current_players();
			$score = $this->seatingDef->calculatePlayersScore($this->vars->seating);
			if ($score < $this->vars->score)
			{
				// echo $this->vars->score . ' ↠ ' . $score . '<br>';
				$this->vars->score = $score;
				$this->vars->current_number1 = 0;
				$this->vars->current_table1 = 0;
				$this->vars->current_number2 = -1;
				$this->vars->current_table2 = 1;
				$this->vars->current_round = 0;
			}
			else
			{
				$this->_swap_current_players();
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
			}
			$this->vars->score = $this->seatingDef->calculatePlayersScore($this->vars->seating);
		}
		if ($this->vars->hash != null)
		{
			$this->log($this->vars->hash);
		}
	}

	function players_task_end()
	{
		if (isset($this->seatingDef))
		{
			$seatingDef = $this->seatingDef;
			unset($this->seatingDef);
		}
		else
		{
			$seatingDef = new SeatingDef($this->vars->hash);
		}
		
		if (is_null($this->vars->hash))
		{
			return;
		}
		
		Db::begin();
		list ($seating, $players_full_runs) = Db::record('seating', 'SELECT seating, players_full_runs FROM seatings WHERE hash = ?', $this->vars->hash);
		$seating = json_decode($seating);
		$score = $seatingDef->calculatePlayersScore($seating);
		
		$this->log('Score: ' . $this->vars->score . '; Target: ' . $score);
		if ($this->vars->current_round >= count($this->vars->seating))
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
			$state = json_encode($state);
			if ($this->vars->score == 0)
			{
				$players_full_runs = max($players_full_runs, 1); // We need to make sure it is greater than 0 for a perfect score, because number/table optimizers always wait until players optimizer make at least one full run. This is done to avoid numbers/tables optimizations to seatings that are often changing.
			}
		}
		
		
		if ($this->vars->score < $score)
		{
			$this->log('Success!');
			$numbers_score = $seatingDef->calculateNumbersScore($this->vars->seating);
			$tables_score = $seatingDef->calculateTablesScore($this->vars->seating);
			Db::exec('seating', 
				'UPDATE seatings SET players_state = ?, seating = ?, players_runs = players_runs + 1, players_full_runs = ?, players_score = ?'.
				', numbers_runs = 0, numbers_full_runs = 0, numbers_void_runs = 0, numbers_state = "", numbers_score = ?, numbers_max_score = ?'.
				', tables_runs = 0, tables_full_runs = 0, tables_void_runs = 0, tables_state = "", tables_score = ?, tables_max_score = ?'.
				' WHERE hash = ?', $state, json_encode($this->vars->seating), $players_full_runs, $this->vars->score, $numbers_score, $numbers_score, $tables_score, $tables_score, $this->vars->hash);
		}
		else
		{
			Db::exec('seating', 
				'UPDATE seatings SET players_state = ?, players_runs = players_runs + 1, players_full_runs = ?, players_void_runs = players_void_runs + 1'.
				' WHERE hash = ?', $state, $players_full_runs, $this->vars->hash);
		}
		Db::commit();
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
		}
		return $count;
	}

	function numbers_task_start()
	{
		$this->vars->hash = null;
		$hash = $this->getArg('hash');
		if ($hash == null)
		{
			$query = new DbQuery('SELECT hash, seating, numbers_state FROM seatings WHERE numbers_full_runs < ' . SEATING_MAX_NUMBERS_OPTIMIZATIONS . ' AND numbers_score > 0 AND players_full_runs > 0 AND tables_state = "" ORDER BY numbers_void_runs, numbers_runs LIMIT 1');
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
			$this->log($this->vars->hash);
		}
	}

	function numbers_task_end()
	{
		if (isset($this->seatingDef))
		{
			$seatingDef = $this->seatingDef;
			unset($this->seatingDef);
		}
		else
		{
			$seatingDef = new SeatingDef($this->vars->hash);
		}
		
		if (is_null($this->vars->hash))
		{
			return;
		}
		
		Db::begin();
		list ($seating, $numbers_full_runs) = Db::record('seating', 'SELECT seating, numbers_full_runs FROM seatings WHERE hash = ?', $this->vars->hash);
		$seating = json_decode($seating);
		$score = $seatingDef->calculateNumbersScore($seating);
		
		$this->log('Score: ' . $this->vars->score . '; Target: ' . $score);
		if ($this->vars->current_round >= count($this->vars->seating))
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
		
		if ($this->vars->score < $score)
		{
			$this->log('Success!');
			Db::exec('seating', 
				'UPDATE seatings SET numbers_state = ?, seating = ?, numbers_runs = numbers_runs + 1, numbers_full_runs = ?, numbers_score = ?, tables_state = ""'.
				' WHERE hash = ?', $state, json_encode($this->vars->seating), $numbers_full_runs, $this->vars->score, $this->vars->hash);
		}
		else
		{
			Db::exec('seating', 
				'UPDATE seatings SET numbers_state = ?, numbers_runs = numbers_runs + 1, numbers_full_runs = ?, numbers_void_runs = numbers_void_runs + 1'.
				' WHERE hash = ?', $state, $numbers_full_runs, $this->vars->hash);
		}
		Db::commit();
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
			$this->_swap_current_tables();
			$score = $this->seatingDef->calculateTablesScore($this->vars->seating);
			if ($score < $this->vars->score)
			{
				echo $this->vars->score . ' ↠ ' . $score . '<br>';
				$this->vars->score = $score;
				$this->vars->current_table1 = 0;
				$this->vars->current_table2 = 0;
				$this->vars->current_round = 0;
			}
			else
			{
				$this->_swap_current_tables();
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
			$query = new DbQuery('SELECT hash, seating, tables_state FROM seatings WHERE tables_full_runs < ' . SEATING_MAX_TABLES_OPTIMIZATIONS . ' AND tables_score > 0 AND players_full_runs > 0 AND numbers_state = "" ORDER BY tables_void_runs, tables_runs LIMIT 1');
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
			$this->log($this->vars->hash);
		}
	}

	function tables_task_end()
	{
		if (isset($this->seatingDef))
		{
			$seatingDef = $this->seatingDef;
			unset($this->seatingDef);
		}
		else
		{
			$seatingDef = new SeatingDef($this->vars->hash);
		}
		
		if (is_null($this->vars->hash))
		{
			return;
		}
		
		Db::begin();
		list ($seating, $tables_full_runs) = Db::record('seating', 'SELECT seating, tables_full_runs FROM seatings WHERE hash = ?', $this->vars->hash);
		$seating = json_decode($seating);
		$score = $seatingDef->calculateTablesScore($seating);
		
		$this->log('Score: ' . $this->vars->score . '; Target: ' . $score);
		if ($this->vars->current_round >= count($this->vars->seating))
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
		
		if ($this->vars->score < $score)
		{
			$this->log('Success!');
			Db::exec('seating', 
				'UPDATE seatings SET tables_state = ?, seating = ?, tables_runs = tables_runs + 1, tables_full_runs = ?, tables_score = ?, numbers_state = ""'.
				' WHERE hash = ?', $state, json_encode($this->vars->seating), $tables_full_runs, $this->vars->score, $this->vars->hash);
		}
		else
		{
			Db::exec('seating', 
				'UPDATE seatings SET tables_state = ?, tables_runs = tables_runs + 1, tables_full_runs = ?, tables_void_runs = tables_void_runs + 1'.
				' WHERE hash = ?', $state, $tables_full_runs, $this->vars->hash);
		}
		Db::commit();
	}
}

$updater = new SeatingOptimization();
$updater->run();

?>
