<?php

require_once 'include/general_page_base.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/tournament.php';
require_once 'include/ccc_filter.php';

define("PAGE_SIZE",15);

class Page extends GeneralPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->ccc_title = get_label('Filter tournaments by club, city, or country.');
	}

	protected function show_body()
	{
		global $_profile, $_page;
		
		$condition = new SQL(' FROM tournaments t JOIN addresses a ON t.address_id = a.id JOIN clubs c ON t.club_id = c.id JOIN cities ct ON ct.id = a.city_id WHERE t.start_time < UNIX_TIMESTAMP() AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0');
		
		$ccc_id = $this->ccc_filter->get_id();
		switch($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND t.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND t.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND a.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND ct.country_id = ?', $ccc_id);
			break;
		}
		
		
		list ($count) = Db::record(get_label('tournament'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);


		$colunm_counter = 0;
		$query = new DbQuery(
			'SELECT t.id, t.name, t.flags, t.start_time, ct.timezone, c.id, c.name, c.flags, t.langs, a.id, a.address, a.flags,' .
			' (SELECT count(*) FROM games _g JOIN events _e ON _e.id = _g.event_id WHERE _e.tournament_id = t.id AND result IN (1, 2)) as games,' .
			' (SELECT count(*) FROM events WHERE tournament_id = t.id AND (flags & ' . EVENT_FLAG_CANCELED . ') = 0) as events',
			$condition);
		$query->add(' ORDER BY t.start_time DESC, t.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker">';
		echo '<td colspan="2">' . get_label('Tournament') . '</td>';
		echo '<td>' . get_label('Address') . '</td>';
		echo '<td width="60" align="center">' . get_label('Games played') . '</td>';
		echo '<td width="60" align="center">' . get_label('Number of rounds') . '</td></tr>';
		
		$tournament_pic = new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE));
		while ($row = $query->next())
		{
			list ($tournament_id, $tournament_name, $tournament_flags, $tournament_time, $timezone, $club_id, $club_name, $club_flags, $languages, $addr_id, $addr, $addr_flags, $games_count, $rounds_count) = $row;
			
			echo '<tr>';
			
			echo '<td width="50" class="dark"><a href="tournament_standings.php?bck=1&id=' . $tournament_id . '">';
			$tournament_pic->
				set($tournament_id, $tournament_name, $tournament_flags)->
				set($club_id, $club_name, $club_flags);
			$tournament_pic->show(ICONS_DIR, 50);
			echo '</a></td>';
			echo '<td width="180">' . $tournament_name . '<br><b>' . format_date('l, F d, Y', $tournament_time, $timezone) . '</b></td>';
			
			echo '<td>' . $addr . '</td>';
			
			echo '<td align="center"><a href="tournament_games.php?bck=1&id=' . $tournament_id . '">' . $games_count . '</a></td>';
			echo '<td align="center"><a href="tournament_rounds.php?bck=1&id=' . $tournament_id . '">' . $rounds_count . '</a></td>';
			
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Tournaments history'));

?>