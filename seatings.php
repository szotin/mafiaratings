<?php

require_once 'include/general_page_base.php';
require_once 'include/seating.php';
require_once 'include/pages.php';

define('PAGE_SIZE', TOURNAMENTS_PAGE_SIZE);

class Page extends GeneralPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->flt_players = isset($_REQUEST['players']) && $_REQUEST['players'] !== '' ? (int)$_REQUEST['players'] : 0;
		$this->flt_tables  = isset($_REQUEST['tables'])  && $_REQUEST['tables']  !== '' ? (int)$_REQUEST['tables']  : 0;
		$this->flt_games   = isset($_REQUEST['games'])   && $_REQUEST['games']   !== '' ? (int)$_REQUEST['games']   : 0;
	}

	protected function show_body()
	{
		global $_page;

		// Hash is "{players}_{tables}_{games}[_restrictions...]" — extract the first three
		// underscore-separated parts to filter by.
		$condition = new SQL(' FROM seatings WHERE 1');
		if ($this->flt_players > 0)
		{
			$condition->add(' AND CAST(SUBSTRING_INDEX(hash, \'_\', 1) AS UNSIGNED) = ?', $this->flt_players);
		}
		if ($this->flt_tables > 0)
		{
			$condition->add(' AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(hash, \'_\', 2), \'_\', -1) AS UNSIGNED) = ?', $this->flt_tables);
		}
		if ($this->flt_games > 0)
		{
			$condition->add(' AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(hash, \'_\', 3), \'_\', -1) AS UNSIGNED) = ?', $this->flt_games);
		}

		echo '<p><table class="transp"><tr>';
		echo '<td>' . get_label('Players') . ': <input type="number" min="0" step="1" style="width:55px;" id="flt-players" value="' . ($this->flt_players > 0 ? $this->flt_players : '') . '" onchange="applyFilter()"></td>';
		echo '<td>&emsp;' . get_label('Tables') . ': <input type="number" min="0" step="1" style="width:55px;" id="flt-tables" value="' . ($this->flt_tables > 0 ? $this->flt_tables : '') . '" onchange="applyFilter()"></td>';
		echo '<td>&emsp;' . get_label('Games per player') . ': <input type="number" min="0" step="1" style="width:55px;" id="flt-games" value="' . ($this->flt_games > 0 ? $this->flt_games : '') . '" onchange="applyFilter()"></td>';
		echo '</tr></table></p>';

		list($count) = Db::record(get_label('seating'), 'SELECT count(*)', $condition);

		$seatings = array();
		$query = new DbQuery('SELECT hash, players_score, numbers_score, tables_score', $condition);
		$query->add(' ORDER BY hash LIMIT ' . ((int)$_page * PAGE_SIZE) . ', ' . PAGE_SIZE);
		while ($row = $query->next())
		{
			list ($hash, $players_score, $numbers_score, $tables_score) = $row;
			$parts = explode('_', $hash);
			if (count($parts) < 3)
			{
				continue;
			}

			$seating = new stdClass();
			$seating->hash = $hash;
			$seating->players = (int)$parts[0];
			$seating->tables  = (int)$parts[1];
			$seating->games   = (int)$parts[2];
			$restriction_parts = array_slice($parts, 3);
			$seating->restrictions = format_seating_restrictions($restriction_parts);
			$players_max_score = SeatingDef::worst_acceptable_players_score($seating->players, $seating->tables, $seating->games);
			$numbers_max_score = SeatingDef::worst_acceptable_numbers_score($seating->players, $seating->tables, $seating->games);
			$tables_max_score = SeatingDef::worst_acceptable_tables_score($seating->players, $seating->tables, $seating->games);
			if ($seating->players == 10)
			{
				$seating->players_opt_level = '';
			}
			else if ($players_max_score == 0)
			{
				$seating->players_opt_level = '100%';
			}
			else
			{
				$seating->players_opt_level = number_format((1 - min(max($players_score/$players_max_score, 0), 1)) * 100, 0) . '%';
			}
			if ($numbers_max_score == 0)
			{
				$seating->numbers_opt_level = '100%';
			}
			else
			{
				$seating->numbers_opt_level = number_format((1 - min(max($numbers_score/$numbers_max_score, 0), 1)) * 100, 0) . '%';
			}
			if ($seating->tables < 3)
			{
				$seating->tables_opt_level = '';
			}
			else if ($tables_max_score == 0)
			{
				$seating->tables_opt_level = '100%';
			}
			else
			{
				$seating->tables_opt_level = number_format((1 - min(max($tables_score/$tables_max_score, 0), 1)) * 100, 0) . '%';
			}
			$seatings[] = $seating;
		}

		show_pages_navigation(PAGE_SIZE, $count);
		echo '<p>';
		echo '<table class="bordered light" width="100%">';

		// Header row: "+" create button + column headers.
		echo '<tr class="darker">';
		echo '<td width="80" align="center">';
		if (is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, ANY_ID, ANY_ID))
		{
			echo '<button class="icon" onclick="createSeating()" title="' . get_label('Create new seating') . '">';
			echo '<img src="images/create.png" border="0">';
			echo '</button>';
		}
		echo '</td>';
		echo '<td width="70" align="center"><b>' . get_label('Players') . '</b></td>';
		echo '<td width="70" align="center"><b>' . get_label('Tables') . '</b></td>';
		echo '<td width="70" align="center"><b>' . get_label('Games per player') . '</b></td>';
		echo '<td><b>' . get_label('Restrictions') . '</b></td>';
		echo '<td width="70" align="center"><b>' . get_label('Players opt level') . '</b></td>';
		echo '<td width="70" align="center"><b>' . get_label('Numbers opt level') . '</b></td>';
		echo '<td width="70" align="center"><b>' . get_label('Tables opt level') . '</b></td>';
		echo '</tr>';

		$is_admin = is_permitted(PERMISSION_ADMIN);

		foreach ($seatings as $seating)
		{
			echo '<tr>';
			echo '<td class="dark" align="center" style="white-space:nowrap">';
			echo '<a href="seating.php?bck=1&hash=' . urlencode($seating->hash) . '" title="' . get_label('View') . '">';
			echo '<img src="images/details.png" border="0">';
			echo '</a>';
			if ($is_admin)
			{
				echo '<button class="icon" onclick="deleteSeating(\'' . addslashes($seating->hash) . '\')" title="' . get_label('Delete') . '">';
				echo '<img src="images/delete.png" border="0">';
				echo '</button>';
			}
			echo '</td>';
			echo '<td align="center">' . $seating->players . '</td>';
			echo '<td align="center">' . $seating->tables . '</td>';
			echo '<td align="center">' . $seating->games . '</td>';
			echo '<td>' . $seating->restrictions . '</td>';
			echo '<td align="center">' . $seating->players_opt_level . '</td>';
			echo '<td align="center">' . $seating->numbers_opt_level . '</td>';
			echo '<td align="center">' . $seating->tables_opt_level . '</td>';
			echo '</tr>';
		}

		echo '</table>';
		echo '</p>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
	protected function js()
	{
?>
		function createSeating()
		{
			dlg.form("form/seating_create.php", refr);
		}

		function deleteSeating(hash)
		{
			dlg.yesNo("<?php echo get_label('Are you sure you want to delete this seating?'); ?>", null, null, function()
			{
				json.post("api/ops/seating.php", { op: 'delete', hash: hash }, refr);
			});
		}

		function applyFilter()
		{
			goTo({
				players: $('#flt-players').val(),
				tables:  $('#flt-tables').val(),
				games:   $('#flt-games').val(),
				page:    0
			});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Seatings'));

?>
