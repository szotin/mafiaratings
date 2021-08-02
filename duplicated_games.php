<?php 

require_once 'include/general_page_base.php';

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_page;
	
		check_permissions(PERMISSION_ADMIN);
		$query = new DbQuery(
			'SELECT g1.id, g2.id, g1.start_time, g1.end_time - g1.start_time, c.id, c.name, c.flags, i.timezone FROM games g1' . 
			' JOIN games g2 ON g1.start_time = g2.start_time AND g1.end_time = g2.end_time AND g1.club_id = g2.club_id' .
			' JOIN events e ON g1.event_id = e.id' .
			' JOIN addresses a ON e.address_id = a.id' . 
			' JOIN cities i ON i.id = a.city_id' . 
			' JOIN clubs c ON c.id = g1.club_id' . 
			' WHERE g1.id < g2.id AND g1.result = g2.result AND g1.event_id = g2.event_id' .
			' ORDER by g1.start_time DESC');
	
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td width="48">'.get_label('Club').'</td><td colspan="2"></td><td>' . get_label('Start') . '</td><td width="120">' . get_label('Duration') . '</td></tr>';
		while ($row = $query->next())
		{
			list ($game1_id, $game2_id, $start, $duration, $club_id, $club_name, $club_flags, $timezone) = $row;
		
			echo '<tr>';
			echo '<td>';
			$this->club_pic->set($club_id, $club_name, $club_flags);
			$this->club_pic->show(ICONS_DIR, true, 48);
			echo '</td>';
			echo '<td align="center" width="90"><a href="view_game.php?id=' . $game1_id . '&bck=1">' . get_label('Game #[0]', $game1_id) . '</a></td>';
			echo '<td align="center" width="90"><a href="view_game.php?id=' . $game2_id . '&bck=1">' . get_label('Game #[0]', $game2_id) . '</a></td>';
			echo '<td>' . format_date('M j Y, H:i', $start, $timezone) . '</td>';
			echo '<td>' . format_time($duration) . '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->set_ccc(CCCS_NO);
$page->run('Duplicated games');

?>
