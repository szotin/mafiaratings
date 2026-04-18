<?php

require_once 'include/general_page_base.php';
require_once 'include/seating.php';
require_once 'include/pages.php';

define('PAGE_SIZE', TOURNAMENTS_PAGE_SIZE);

// Parses restriction segments from the hash and returns a human-readable HTML string.
// Each segment is a group: "0-2" means players 0,1,2; "0:2:5" means players 0,2,5.
// Groups are separated by semicolons in the output.
function format_seating_restrictions($parts)
{
	if (empty($parts))
	{
		return '';
	}
	$groups = array();
	foreach ($parts as $part)
	{
		$players = array();
		$tokens = explode(':', $part);
		foreach ($tokens as $token)
		{
			if (strpos($token, '-') !== false)
			{
				list($from, $to) = explode('-', $token, 2);
				for ($i = (int)$from; $i <= (int)$to; $i++)
				{
					$players[] = $i;
				}
			}
			else
			{
				$players[] = (int)$token;
			}
		}
		$groups[] = '(' . implode(', ', $players) . ')';
	}
	return implode(' &nbsp; ', $groups);
}

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_page;

		list($count) = Db::record(get_label('seating'), 'SELECT count(*) FROM seatings');

		$seatings = array();
		$query = new DbQuery('SELECT hash, players_score, numbers_score, tables_score FROM seatings ORDER BY hash LIMIT ' . ((int)$_page * PAGE_SIZE) . ', ' . PAGE_SIZE);
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
			$players_max_score = SeatingDef::worst_players_score($seating->players, $seating->tables, $seating->games);
			$numbers_max_score = SeatingDef::worst_numbers_score($seating->players, $seating->tables, $seating->games);
			$tables_max_score = SeatingDef::worst_tables_score($seating->players, $seating->tables, $seating->games);
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
		if (is_permitted(PERMISSION_USER))
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
<?php
	}
}

$page = new Page();
$page->run(get_label('Seatings'));

?>
