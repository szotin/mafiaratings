<?php 

require_once 'include/general_page_base.php';

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_page;
	
		check_permissions(PERMISSION_ADMIN);
		$query = new DbQuery(
			'SELECT g1.id, g2.id, g1.table_num, g1.game_num, g2.table_num, g2.game_num, g1.start_time, g1.end_time - g1.start_time, c.id, c.name, c.flags, i.timezone, e.name, t.name FROM games g1' .
			' JOIN games g2 ON g1.start_time = g2.start_time AND g1.end_time = g2.end_time AND g1.club_id = g2.club_id' .
			' JOIN events e ON g1.event_id = e.id' .
			' JOIN addresses a ON e.address_id = a.id' .
			' JOIN cities i ON i.id = a.city_id' .
			' JOIN clubs c ON c.id = g1.club_id' .
			' LEFT OUTER JOIN tournaments t ON t.id = g1.tournament_id' .
			' WHERE g1.id < g2.id AND g1.result = g2.result AND g1.event_id = g2.event_id' .
			' ORDER by g1.start_time DESC');

		$format_game_link = function($id, $tnum, $gnum)
		{
			if (is_null($gnum))
			{
				$label = get_label('Game #[0]', $id);
			}
			else if (is_null($tnum))
			{
				$label = get_label('Game [0]', $gnum);
			}
			else
			{
				$label = get_label('Table [0], Game [1]', $tnum, $gnum);
			}
			return '<a href="view_game.php?id=' . $id . '&bck=1">' . $label . '</a>';
		};

		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td width="48">'.get_label('Club').'</td><td>' . get_label('Tournament') . ' / ' . get_label('Event') . '</td><td colspan="2"></td><td>' . get_label('Start') . '</td><td width="120">' . get_label('Duration') . '</td></tr>';
		while ($row = $query->next())
		{
			list ($game1_id, $game2_id, $t1, $g1, $t2, $g2, $start, $duration, $club_id, $club_name, $club_flags, $timezone, $event_name, $tournament_name) = $row;

			echo '<tr>';
			echo '<td>';
			$this->club_pic->set($club_id, $club_name, $club_flags);
			$this->club_pic->show(ICONS_DIR, true, 48);
			echo '</td>';
			echo '<td>';
			if (!is_null($tournament_name))
			{
				echo '<b>' . $tournament_name . '</b><br>';
			}
			echo $event_name . '</td>';
			echo '<td align="center" width="120">' . $format_game_link($game1_id, $t1, $g1) . '</td>';
			echo '<td align="center" width="120">' . $format_game_link($game2_id, $t2, $g2) . '</td>';
			echo '<td>' . format_date($start, $timezone, true) . '</td>';
			echo '<td>' . format_time($duration) . '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run('Duplicated games');

?>
