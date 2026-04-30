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

		$this->can_optimize = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, ANY_ID, ANY_ID);
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

	private function showOptLevelBar($percent, $task)
	{
		$pct = round($percent);
		echo '<p><div style="display:flex;align-items:center;gap:8px;">';
		echo '<span style="white-space:nowrap;">' . get_label('Quality') . ':</span>';
		echo '<div style="position:relative;flex:1;height:24px;line-height:24px;overflow:hidden;">';
		if ($pct > 0)
		{
			echo '<img src="images/red_dot.png" style="position:absolute;left:0;top:0;width:' . $pct . '%;height:24px;opacity:0.6;">';
		}
		if ($pct < 100)
		{
			echo '<img src="images/black_dot.png" style="position:absolute;left:' . $pct . '%;top:0;width:' . (100 - $pct) . '%;height:24px;opacity:0.6;">';
		}
		echo '<b style="position:absolute;left:0;top:0;width:100%;text-align:center;color:white;">' . $pct . '%</b>';
		echo '</div>';
		if ($this->can_optimize && $pct < 100)
		{
			echo '<button onclick="startOptimization(\'' . $task . '\')">' . get_label('Optimize') . '</button>';
		}
		echo '</div></p>';
	}

	private function showTableStats()
	{
		if (!is_null($this->tables_pct)) $this->showOptLevelBar($this->tables_pct, 'tables');
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
		if (!is_null($this->players_pct)) $this->showOptLevelBar($this->players_pct, 'players');
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
		if (!is_null($this->numbers_pct)) $this->showOptLevelBar($this->numbers_pct, 'numbers');
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

		$query = new DbQuery(
			'SELECT seating, players_score, numbers_score, tables_score,' .
			' players_runs, players_void_runs, tables_runs, tables_void_runs, numbers_runs, numbers_void_runs' .
			' FROM seatings WHERE hash = ?', $this->hash);
		$row = $query->next();
		if (!$row)
		{
			echo '<p>' . get_label('Seating not found.') . '</p>';
			return;
		}

		list ($seating_json, $players_score, $numbers_score, $tables_score,
			$pr, $pvr, $tr, $tvr, $nr, $nvr) = $row;
		$seating_data = json_decode($seating_json, true);
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
		$players = isset($parts[0]) ? (int)$parts[0] : 0;
		$tables  = isset($parts[1]) ? (int)$parts[1] : 0;
		$games   = isset($parts[2]) ? (int)$parts[2] : 0;
		$restriction_parts = array_slice($parts, 3);
		$restrictions_html = format_seating_restrictions($restriction_parts);
		$version = ($pr - $pvr) . '.' . ($tr - $tvr) . '.' . ($nr - $nvr);
		echo '<p style="display:flex;justify-content:space-between;align-items:center;">';
		echo '<span>' . get_label('Players') . ': <b>' . $players . '</b> &nbsp; ';
		echo get_label('Tables') . ': <b>' . $tables . '</b> &nbsp; ';
		echo get_label('Games per player') . ': <b>' . $games . '</b></span>';
		echo '<small style="color:gray">' . htmlspecialchars($this->hash) . ' &nbsp; v' . htmlspecialchars($version) . '</small>';
		echo '</p>';
		if (!empty($restrictions_html))
		{
			echo '<p>' . get_label('Restrictions') . ': ' . $restrictions_html . '</p>';
		}

		// Pre-compute optimization level percentages (same formula as seatings.php).
		$calc_pct = function($score, $max_score) {
			if ($max_score <= 0) return 100.0;
			return (1 - min(max($score / $max_score, 0), 1)) * 100;
		};
		$this->players_pct = ($players > 10)
			? $calc_pct($players_score, SeatingDef::worst_acceptable_players_score($players, $tables, $games))
			: null;
		$this->numbers_pct = $calc_pct($numbers_score, SeatingDef::worst_acceptable_numbers_score($players, $tables, $games));
		$this->tables_pct  = ($tables >= 3)
			? $calc_pct($tables_score, SeatingDef::worst_acceptable_tables_score($players, $tables, $games))
			: null;

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

		echo '<div id="opt-dialog" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">';
		echo '<div class="bordered light" style="background:white;padding:20px;min-width:280px;">';
		echo '<p>' . get_label('Optimization time (minutes)') . ':</p>';
		echo '<p><input type="number" id="opt-minutes" value="60" min="3" max="120" step="3" style="width:100%;font-size:1.2em;"></p>';
		echo '<p style="text-align:right;">';
		echo '<button onclick="cancelOptimization()" style="margin-right:8px;">' . get_label('Cancel') . '</button>';
		echo '<button onclick="confirmOptimization()">OK</button>';
		echo '</p></div></div>';
	}

	protected function js()
	{
		$hash = addslashes($this->hash);
?>
		function highlight(playerIndex)
		{
			goTo({hlt: playerIndex});
		}

		var _opt_task = '';

		function startOptimization(task)
		{
			_opt_task = task;
			document.getElementById('opt-minutes').value = 60;
			document.getElementById('opt-dialog').style.display = 'flex';
		}

		function cancelOptimization()
		{
			document.getElementById('opt-dialog').style.display = 'none';
		}

		function confirmOptimization()
		{
			var minutes = parseInt(document.getElementById('opt-minutes').value, 10);
			if (isNaN(minutes) || minutes < 3) minutes = 3;
			if (minutes > 120) minutes = 120;
			document.getElementById('opt-dialog').style.display = 'none';
			var runs = Math.round(minutes / 3);
			window.open('seating_optimization.php?log_level=info&time=180&runs=' + runs + '&loop=1&task=' + _opt_task + '&hash=<?php echo $hash; ?>', '_blank');
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Seating'));

?>
