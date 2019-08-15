<?php

require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/address.php';
require_once 'include/tournament.php';

define("CUT_NAME",45);
define("PAGE_SIZE",15);

class Page extends ClubPageBase
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
		$season = show_club_seasons_select($this->id, $season, 'document.clubForm.submit()', get_label('Show tournaments of a specific season.'));
		echo '</td></tr></table></form>';
		
		$condition = new SQL(
			' FROM tournaments t ' .
				' JOIN addresses a ON t.address_id = a.id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' WHERE t.start_time < UNIX_TIMESTAMP() AND t.club_id = ? AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0',
			$this->id);
		$condition->add(get_club_season_condition($season, 't.start_time', '(t.start_time + t.duration)'));
		
		list ($count) = Db::record(get_label('tournament'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		$tournament_pic = new Picture(TOURNAMENT_PICTURE, new Picture(ADDRESS_PICTURE));
		$query = new DbQuery(
			'SELECT t.id, t.name, t.flags, t.start_time, ct.timezone, a.id, a.name, a.flags, a.address,' .
			' (SELECT count(*) FROM games _g JOIN events _e ON _e.id = _g.event_id WHERE _e.tournament_id = t.id AND result IN (1, 2)) as games,' .
			' (SELECT count(*) FROM events WHERE tournament_id = t.id AND (flags & ' . EVENT_FLAG_CANCELED . ') = 0) as events',
			$condition);
		$query->add(' ORDER BY t.start_time DESC, t.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker">';
		echo '<td colspan="2">' . get_label('Tournament') . '</td>';
		echo '<td>' . get_label('Address') . '</td>';
		echo '<td width="60" align="center">' . get_label('Games played') . '</td>';
		echo '<td width="60" align="center">' . get_label('Number of rounds') . '</td></tr>';
		while ($row = $query->next())
		{
			list ($tournament_id, $tournament_name, $tournament_flags, $tournament_time, $timezone, $address_id, $address_name, $address_flags, $address, $games_count, $rounds_count) = $row;

			if ($tournament_flags & TOURNAMENT_FLAG_CANCELED)
			{
				echo '<tr class="dark">';
			}
			else
			{
				echo '<tr>';
			}
			
			echo '<td width="50"><a href="tournament_standings.php?bck=1&id=' . $tournament_id . '">';
			$tournament_pic->
				set($tournament_id, $tournament_name, $tournament_flags)->
				set($address_id, $address_name, $address_flags);
			$tournament_pic->show(ICONS_DIR, 50);
			echo '</a></td>';
			echo '<td width="180">' . $tournament_name . '<br><b>' . format_date('l, F d, Y', $tournament_time, $timezone) . '</b></td>';
			
			echo '<td>' . $address . '</td>';
			
			echo '<td align="center"><a href="tournament_games.php?bck=1&id=' . $tournament_id . '">' . $games_count . '</a></td>';
			echo '<td align="center"><a href="tournament_rounds.php?bck=1&id=' . $tournament_id . '">' . $rounds_count . '</a></td>';
			
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Tournaments'));

?>