<?php

require_once 'include/general_page_base.php';
require_once 'include/seating.php';

define('VIEW_BY_GAME',       0);
define('VIEW_BY_TABLE',      1);
define('VIEW_TABLE_STATS',   2);
define('VIEW_PVP_STATS',     3);
define('VIEW_NUMBERS_STATS', 4);
define('VIEW_COUNT',         5);

class Page extends GeneralPageBase
{
	protected function prepare()
	{
		parent::prepare();

		$this->hash = '';
		if (isset($_REQUEST['hash']))
		{
			$this->hash = $_REQUEST['hash'];
		}

		$this->highlight = -1;
		if (isset($_REQUEST['hlt']))
		{
			$this->highlight = (int)$_REQUEST['hlt'];
		}
	}

	private function getPlayerName($player_index)
	{
		return 'player_' . $player_index;
	}

	private function showPlayer($player_index, $cell_attributes = '')
	{
		$highlighted = ($player_index == $this->highlight);
		if (empty($cell_attributes))
		{
			$cell_attributes = $highlighted ? ' class="darker"' : '';
		}
		else if ($highlighted)
		{
			// Merge highlight into existing attributes.
			$cell_attributes = str_replace('class="', 'class="darker ', $cell_attributes);
			if (strpos($cell_attributes, 'class=') === false)
			{
				$cell_attributes .= ' class="darker"';
			}
		}
		$name = $this->getPlayerName($player_index);
		if ($highlighted)
		{
			$link = $name;
		}
		else
		{
			$link = '<a href="javascript:highlight(' . $player_index . ')">' . $name . '</a>';
		}
		echo '<td align="center"' . $cell_attributes . '>' . $link . '</td>';
	}

	private function showByGame()
	{
		// $this->tables[table][game][seat] = player_index
		for ($j = 0; $j < $this->num_games; $j++)
		{
			// Collect which tables participate in this game.
			$tables_in_game = array();
			for ($i = 0; $i < $this->num_tables; $i++)
			{
				if (isset($this->tables[$i][$j]))
				{
					$tables_in_game[] = $i;
				}
			}

			echo '<p><center><h2>' . get_label('Game [0]', $j + 1) . '</h2></center></p>';
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="dark"><td width="8%"></td>';
			for ($k = 0; $k < 10; $k++)
			{
				echo '<td width="9.2%" align="center"><b>' . ($k + 1) . '</b></td>';
			}
			echo '</tr>';

			// Collect all playing players in this game.
			$playing = array();
			foreach ($tables_in_game as $i)
			{
				echo '<tr><td align="center" class="dark"><b>' . get_label('Table [0]', $i + 1) . '</b></td>';
				$game = $this->tables[$i][$j];
				for ($k = 0; $k < 10; $k++)
				{
					$this->showPlayer($game[$k]);
					$playing[$game[$k]] = true;
				}
				echo '</tr>';
			}

			// Skipping row: only when this is a full round (all tables present).
			if (count($tables_in_game) == $this->num_tables)
			{
				$skipping = array();
				foreach ($this->all_players as $pidx => $v)
				{
					if (!isset($playing[$pidx]))
					{
						$skipping[] = $pidx;
					}
				}

				if (count($skipping) > 0)
				{
					$rows = 1 + (int)((count($skipping) - 1) / 10);
					echo '<tr class="dark"><td align="center" class="dark"';
					if ($rows > 1)
					{
						echo ' rowspan="' . $rows . '"';
					}
					echo '><b>' . get_label('Skipping') . '</b></td>';
					$col_count = 0;
					foreach ($skipping as $pidx)
					{
						if ($col_count == 10)
						{
							$col_count = 0;
							echo '</tr><tr class="dark">';
						}
						$this->showPlayer($pidx);
						$col_count++;
					}
					echo '</tr>';
				}
			}

			echo '</table>';
		}
	}

	private function showByTable()
	{
		for ($i = 0; $i < $this->num_tables; $i++)
		{
			echo '<p><center><h2>' . get_label('Table [0]', $i + 1) . '</h2></center></p>';
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="dark"><td width="8%"></td>';
			for ($k = 0; $k < 10; $k++)
			{
				echo '<td width="9.2%" align="center"><b>' . ($k + 1) . '</b></td>';
			}
			echo '</tr>';
			for ($j = 0; $j < $this->num_games; $j++)
			{
				if (!isset($this->tables[$i][$j]))
				{
					continue;
				}
				echo '<tr><td align="center" class="dark"><b>' . get_label('Game [0]', $j + 1) . '</b></td>';
				$game = $this->tables[$i][$j];
				for ($k = 0; $k < 10; $k++)
				{
					$this->showPlayer($game[$k]);
				}
				echo '</tr>';
			}
			echo '</table>';
		}
	}

	private function showTableStats()
	{
		// Count how many games each player plays at each table.
		$pl = array();
		for ($i = 0; $i < $this->num_tables; $i++)
		{
			for ($j = 0; $j < $this->num_games; $j++)
			{
				if (!isset($this->tables[$i][$j]))
				{
					continue;
				}
				foreach ($this->tables[$i][$j] as $player_index)
				{
					if (!array_key_exists($player_index, $pl))
					{
						$pl[$player_index] = array_fill(0, $this->num_tables, 0);
					}
					$pl[$player_index][$i]++;
				}
			}
		}

		ksort($pl);

		echo '<table class="bordered light">';
		echo '<tr class="darker"><td width="120"></td>';
		for ($i = 0; $i < $this->num_tables; $i++)
		{
			echo '<td width="80" align="center"><b>' . get_label('Table [0]', $i + 1) . '</b></td>';
		}
		echo '</tr>';

		foreach ($pl as $player_index => $tables)
		{
			echo '<tr>';
			$this->showPlayer($player_index, ' class="dark"');
			foreach ($tables as $count)
			{
				echo '<td align="center">' . $count . '</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
	}

	private function showPvpStats()
	{
		// Build player list and count shared games.
		$pl = array();
		for ($i = 0; $i < $this->num_tables; $i++)
		{
			for ($j = 0; $j < $this->num_games; $j++)
			{
				if (!isset($this->tables[$i][$j]))
				{
					continue;
				}
				foreach ($this->tables[$i][$j] as $player_index)
				{
					if (!array_key_exists($player_index, $pl))
					{
						$pl[$player_index] = array();
					}
				}
			}
		}

		foreach ($pl as $p1 => $v)
		{
			foreach ($pl as $p2 => $v2)
			{
				if ($p1 != $p2)
				{
					$pl[$p1][$p2] = 0;
				}
			}
		}

		for ($i = 0; $i < $this->num_tables; $i++)
		{
			for ($j = 0; $j < $this->num_games; $j++)
			{
				if (!isset($this->tables[$i][$j]))
				{
					continue;
				}
				$game = $this->tables[$i][$j];
				for ($k = 0; $k < 10; $k++)
				{
					for ($l = 0; $l < 10; $l++)
					{
						if ($k != $l)
						{
							$pl[$game[$k]][$game[$l]]++;
						}
					}
				}
			}
		}

		ksort($pl);

		$highlight = $this->highlight;

		if ($highlight >= 0 && array_key_exists($highlight, $pl))
		{
			// Show who this player plays with grouped by count.
			$playing_with = array();
			foreach ($pl[$highlight] as $pid => $count)
			{
				$index = $count;
				for ($i = count($playing_with); $i <= $index; $i++)
				{
					$playing_with[] = array();
				}
				$playing_with[$index][] = $pid;
			}

			foreach ($playing_with as &$group)
			{
				sort($group);
			}
			unset($group);

			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><th width="80">' . get_label('Games together') . '</th><th></th></tr>';
			for ($i = count($playing_with) - 1; $i >= 0; $i--)
			{
				if (count($playing_with[$i]) == 0)
				{
					continue;
				}
				echo '<tr><td align="center" class="dark"><b>' . $i . '</b></td><td><div style="display:flex;flex-wrap:wrap;">';
				foreach ($playing_with[$i] as $pid)
				{
					$highlighted = ($pid == $this->highlight);
					$name = $this->getPlayerName($pid);
					$link = $highlighted ? $name : '<a href="javascript:highlight(' . $pid . ')">' . $name . '</a>';
					$bg = $highlighted ? 'background:#ddd;' : '';
					echo '<div style="width:90px;text-align:center;padding:2px;' . $bg . '">' . $link . '</div>';
				}
				echo '</div></td></tr>';
			}
			echo '</table>';
		}
		else
		{
			// Aggregate stats: pairs[count] = number_of_pairs.
			$pairs = array();
			$sum = 0;
			$max = 1;
			$min_index = 10000;
			$player_keys = array_keys($pl);
			for ($a = 0; $a < count($player_keys); $a++)
			{
				for ($b = $a + 1; $b < count($player_keys); $b++)
				{
					$p1 = $player_keys[$a];
					$p2 = $player_keys[$b];
					$index = $pl[$p1][$p2];
					for ($i = count($pairs); $i <= $index; $i++)
					{
						$pairs[] = 0;
					}
					$max = max(++$pairs[$index], $max);
					$min_index = min($index, $min_index);
					$sum++;
				}
			}

			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><th width="80">' . get_label('Games together') . '</th><th width="80">' . get_label('Pairs') . '</th><th></th></tr>';
			for ($i = count($pairs) - 1; $i >= $min_index; $i--)
			{
				echo '<tr align="center"><td class="dark"><b>' . $i . '</b></td><td>' . $pairs[$i] . '</td>';
				$bar_width = $sum > 0 ? round((760 * $pairs[$i]) / $max) : 0;
				echo '<td align="left"><img src="images/black_dot.png" width="' . $bar_width . '" height="12" title="' . $pairs[$i] . ' (' . format_float(100 * $pairs[$i] / $sum, 1) . '%)" style="opacity: 0.3;"></td>';
				echo '</tr>';
			}
			echo '</table>';
		}
	}

	private function showNumbersStats()
	{
		// Count how many times each player sat at each seat number.
		$pl = array();
		for ($i = 0; $i < $this->num_tables; $i++)
		{
			for ($j = 0; $j < $this->num_games; $j++)
			{
				if (!isset($this->tables[$i][$j]))
				{
					continue;
				}
				$game = $this->tables[$i][$j];
				for ($k = 0; $k < 10; $k++)
				{
					$player_index = $game[$k];
					if (!array_key_exists($player_index, $pl))
					{
						$pl[$player_index] = array_fill(0, 10, 0);
					}
					$pl[$player_index][$k]++;
				}
			}
		}

		ksort($pl);

		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="120"></td>';
		for ($i = 0; $i < 10; $i++)
		{
			echo '<td width="80" align="center"><b>' . ($i + 1) . '</b></td>';
		}
		echo '</tr>';

		foreach ($pl as $player_index => $numbers)
		{
			echo '<tr>';
			$this->showPlayer($player_index, ' class="dark"');
			for ($i = 0; $i < 10; $i++)
			{
				echo '<td align="center">' . $numbers[$i] . '</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
	}

	protected function show_body()
	{
		if (empty($this->hash))
		{
			echo '<p>' . get_label('No seating hash specified.') . '</p>';
			return;
		}

		$query = new DbQuery('SELECT seating FROM seatings WHERE hash = ?', $this->hash);
		$row = $query->next();
		if (!$row)
		{
			echo '<p>' . get_label('Seating not found.') . '</p>';
			return;
		}

		$seating_data = json_decode($row[0], true);
		if (!is_array($seating_data) || count($seating_data) === 0)
		{
			echo '<p>' . get_label('Seating data is empty.') . '</p>';
			return;
		}

		// seating_data[round][table][seat] = player_index
		// Reorganize into $this->tables[table][game] = [seat0..seat9]
		$parts = explode('_', $this->hash);
		$this->num_tables = count($seating_data[0]);
		$this->num_games  = count($seating_data);

		$this->tables = array();
		for ($i = 0; $i < $this->num_tables; $i++)
		{
			$this->tables[$i] = array();
		}
		foreach ($seating_data as $round_idx => $round)
		{
			foreach ($round as $table_idx => $game)
			{
				$this->tables[$table_idx][$round_idx] = $game;
			}
		}

		$view = VIEW_BY_GAME;
		if (isset($_REQUEST['view']))
		{
			$view = (int)$_REQUEST['view'];
			if ($view < 0 || $view >= VIEW_COUNT)
			{
				$view = VIEW_BY_GAME;
			}
		}

		// Collect all player indices.
		$all_players = array();
		for ($i = 0; $i < $this->num_tables; $i++)
		{
			for ($j = 0; $j < $this->num_games; $j++)
			{
				if (!isset($this->tables[$i][$j]))
				{
					continue;
				}
				foreach ($this->tables[$i][$j] as $pidx)
				{
					$all_players[$pidx] = true;
				}
			}
		}
		ksort($all_players);
		$this->all_players = $all_players;

		// Parse hash for display info.
		$players = isset($parts[0]) ? (int)$parts[0] : '?';
		$tables  = isset($parts[1]) ? (int)$parts[1] : '?';
		$games   = isset($parts[2]) ? (int)$parts[2] : '?';
		echo '<p>' . get_label('Players') . ': <b>' . $players . '</b> &nbsp; ';
		echo get_label('Tables') . ': <b>' . $tables . '</b> &nbsp; ';
		echo get_label('Games per player') . ': <b>' . $games . '</b></p>';

		// Highlight selector.
		$hlt = $this->highlight;
		echo '<p><select id="player-select" onchange="highlight($(\'#player-select\').val())">';
		show_option(-1, $hlt, '');
		foreach ($all_players as $pidx => $v)
		{
			show_option($pidx, $hlt, $this->getPlayerName($pidx));
		}
		echo '</select></p>';

		$hlt_param = $hlt >= 0 ? ', hlt:' . $hlt : '';

		echo '<div class="tab">';
		echo '<button' . ($view == VIEW_BY_GAME       ? ' class="active"' : '') . ' onclick="goTo({view:' . VIEW_BY_GAME       . $hlt_param . '})">' . get_label('By game')          . '</button>';
		echo '<button' . ($view == VIEW_BY_TABLE      ? ' class="active"' : '') . ' onclick="goTo({view:' . VIEW_BY_TABLE      . $hlt_param . '})">' . get_label('By table')         . '</button>';
		echo '<button' . ($view == VIEW_TABLE_STATS   ? ' class="active"' : '') . ' onclick="goTo({view:' . VIEW_TABLE_STATS   . $hlt_param . '})">' . get_label('By table stats')   . '</button>';
		echo '<button' . ($view == VIEW_PVP_STATS     ? ' class="active"' : '') . ' onclick="goTo({view:' . VIEW_PVP_STATS     . $hlt_param . '})">' . get_label('PvP stats')        . '</button>';
		echo '<button' . ($view == VIEW_NUMBERS_STATS ? ' class="active"' : '') . ' onclick="goTo({view:' . VIEW_NUMBERS_STATS . $hlt_param . '})">' . get_label('By numbers stats') . '</button>';
		echo '</div>';

		switch ($view)
		{
		case VIEW_BY_GAME:
			$this->showByGame();
			break;
		case VIEW_BY_TABLE:
			$this->showByTable();
			break;
		case VIEW_TABLE_STATS:
			$this->showTableStats();
			break;
		case VIEW_PVP_STATS:
			$this->showPvpStats();
			break;
		case VIEW_NUMBERS_STATS:
			$this->showNumbersStats();
			break;
		}
	}

	protected function js()
	{
?>
		function highlight(playerIndex)
		{
			goTo({hlt: playerIndex});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Seating'));

?>
