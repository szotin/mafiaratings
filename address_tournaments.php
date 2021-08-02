<?php

require_once 'include/page_base.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/address.php';
require_once 'include/pages.php';
require_once 'include/tournament.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

class Page extends AddressPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		$season = SEASON_ALL_TIME;
		if (isset($_REQUEST['season']))
		{
			$season = (int)$_REQUEST['season'];
		}
		
		echo '<form method="get" name="clubForm">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<table class="transp" width="100%"><tr><td>';
		$season = show_club_seasons_select($this->club_id, $season, 'document.clubForm.submit()', get_label('Show tournaments of a specific season.'));
		echo '</td></tr></table></form>';
		
		$condition = new SQL(' FROM tournaments t WHERE t.start_time < UNIX_TIMESTAMP() AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0 AND t.address_id = ?', $this->id);
		$condition->add(get_club_season_condition($season, 't.start_time', '(t.start_time + t.duration)'));
		
		list ($count) = Db::record(get_label('tournament'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		$query = new DbQuery(
			'SELECT t.id, t.name, t.start_time, t.flags, ' .
				' (SELECT count(*) FROM games WHERE tournament_id = t.id AND canceled = FALSE AND result > 0 AND (flags & ' . GAME_FLAG_FUN . ') = 0) as games,' .
				' (SELECT count(*) FROM events WHERE tournament_id = t.id) as rounds',
			$condition);
		$query->add(' ORDER BY t.start_time DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker">';
		echo '<td colspan="2">' . get_label('Tournament') . '</td>';
		echo '<td>' . get_label('Address') . '</td>';
		echo '<td width="60" align="center">' . get_label('Rounds') . '</td>';
		echo '<td width="60" align="center">' . get_label('Games played') . '</td></tr>';
		
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		while ($row = $query->next())
		{
			list ($tournament_id, $tournament_name, $tournament_time, $tournament_flags, $games_count, $rounds_count) = $row;
			
			if ($tournament_flags & TOURNAMENT_FLAG_CANCELED)
			{
				echo '<tr class="dark">';
			}
			else
			{
				echo '<tr>';
			}
			
			echo '<td width="50" class="dark">';
			$tournament_pic->set($tournament_id, $tournament_name, $tournament_flags);
			$tournament_pic->show(ICONS_DIR, true, 50);
			echo '</td>';
			echo '<td width="180">' . $tournament_name . '<br><b>' . format_date('l, F d, Y', $tournament_time, $this->timezone) . '</b></td>';
			
			echo '<td>' . $this->address . '</td>';
			
			echo '<td align="center"><a href="tournament_rounds.php?bck=1&id=' . $tournament_id . '">' . $rounds_count . '</a></td>';
			echo '<td align="center"><a href="tournament_games.php?bck=1&id=' . $tournament_id . '">' . $games_count . '</a></td>';
			
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Tournaments history'));

?>