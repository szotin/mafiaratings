<?php

require_once 'include/user.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/event.php';
require_once 'include/ccc_filter.php';
require_once 'include/scoring.php';
require_once 'include/checkbox_filter.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);
define('ETYPE_ALL', 0);

define('FLAG_FILTER_VIDEOS', 0x0001);
define('FLAG_FILTER_NO_VIDEOS', 0x0002);
define('FLAG_FILTER_TOURNAMENT', 0x0004);
define('FLAG_FILTER_NOT_TOURNAMENT', 0x0008);

define('FLAG_FILTER_DEFAULT', 0);

class Page extends UserPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		echo '<table class="transp" width="100%"><tr><td>';
		$ccc_filter->show('onCCC', get_label('Filter events by club, city, or country.'));
		show_checkbox_filter(array(get_label('with video'), get_label('tournament events')), $filter, 'filterEvents');
		echo '</td></tr></table>';
		
		$condition = new SQL(
			' FROM events e' .
			' JOIN games g ON g.event_id = e.id' .
			' JOIN players p ON p.game_id = g.id' .
			' JOIN addresses a ON e.address_id = a.id' .
			' JOIN clubs c ON e.club_id = c.id' . 
			' LEFT OUTER JOIN tournaments t ON e.tournament_id = t.id' . 
			' JOIN cities ct ON ct.id = c.city_id' .
			' WHERE p.user_id = ? AND g.canceled = FALSE AND g.result > 0 AND (e.flags & ' . EVENT_FLAG_HIDDEN_AFTER . ') = 0', $this->id);
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND e.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND e.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND a.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND ct.country_id = ?', $ccc_id);
			break;
		}
		
		if ($filter & FLAG_FILTER_VIDEOS)
		{
			$condition->add(' AND EXISTS (SELECT v.id FROM videos v WHERE v.event_id = e.id)');
		}
		if ($filter & FLAG_FILTER_NO_VIDEOS)
		{
			$condition->add(' AND NOT EXISTS (SELECT v.id FROM videos v WHERE v.event_id = e.id)');
		}
		if ($filter & FLAG_FILTER_TOURNAMENT)
		{
			$condition->add(' AND e.tournament_id IS NOT NULL');
		}
		if ($filter & FLAG_FILTER_NOT_TOURNAMENT)
		{
			$condition->add(' AND e.tournament_id IS NULL');
		}
		
		list ($count) = Db::record(get_label('event'), 'SELECT count(DISTINCT e.id)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT e.id, e.name, e.flags, e.start_time, ct.timezone, t.id, t.name, t.flags, c.id, c.name, c.flags, e.languages, a.id, a.address, a.flags, SUM(p.rating_earned), COUNT(g.id), SUM(p.won),' .
			' (SELECT count(*) FROM videos WHERE event_id = e.id) as videos',
			$condition);
		$query->add(' GROUP BY e.id ORDER BY e.start_time DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker">';
		echo '<td colspan="2">' . get_label('Event') . '</td>';
		echo '<td width="60" align="center">'.get_label('Rating earned').'</td>';
		echo '<td width="60" align="center">'.get_label('Games played').'</td>';
		echo '<td width="60" align="center">'.get_label('Wins').'</td>';
		echo '<td width="60" align="center">'.get_label('Winning %').'</td>';
		echo '<td width="60" align="center">'.get_label('Rating per game').'</td></tr>';

		$event_pic = new Picture(EVENT_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		while ($row = $query->next())
		{
			list ($event_id, $event_name, $event_flags, $event_time, $timezone, $tournament_id, $tournament_name, $tournament_flags, $club_id, $club_name, $club_flags, $languages, $address_id, $address, $address_flags, $rating, $games_played, $games_won, $videos_count) = $row;
			
			if ($tournament_name != NULL)
			{
				$event_name = $tournament_name . ': ' . $event_name;
			}
			
			echo '<tr>';
			
			echo '<td width="60" class="dark">';
			$event_pic->set($event_id, $event_name, $event_flags);
			$event_pic->show(ICONS_DIR, true, 60);
			echo '</td>';
			
			echo '<td><table width="100%" class="transp"><tr>';
			echo '<td width="40" align="center" valign="center" style="padding-left:12px;>';
			$club_pic->set($club_id, $club_name, $club_flags);
			$club_pic->show(ICONS_DIR, false, 40);
			echo '</td>';
			if ($tournament_id != NULL)
			{
				echo '<td width="40" align="center" valign="center" style="padding-left:4px;>';
				$tournament_pic->set($tournament_id, $tournament_name, $tournament_flags);
				$tournament_pic->show(ICONS_DIR, false, 40);
				echo '</td>';
			}
			echo '<td style="padding-left:12px;"><b><a href="event_standings.php?bck=1&id=' . $event_id . '">' . $event_name . '</b>';
			echo '<br>' . format_date('F d, Y', $event_time, $timezone) . '</a></td>';
			if ($videos_count > 0)
			{
				echo '<td align="right"><a href="event_videos.php?id=' . $event_id . '&bck=1" title="' . get_label('[0] videos from [1]', $videos_count, $event_name) . '"><img src="images/video.png" width="40" height="40"></a></td>';
			}
			echo '</tr></table>';
			echo '</td>';
			
			echo '<td align="center" class="dark">' . number_format($rating, 2) . '</td>';
			echo '<td align="center">' . $games_played . '</td>';
			echo '<td align="center">' . $games_won . '</td>';
			if ($games_played != 0)
			{
				echo '<td align="center">' . number_format(($games_won*100.0)/$games_played, 1) . '%</td>';
				echo '<td align="center">' . number_format($rating/$games_played, 2) . '</td>';
			}
			else
			{
				echo '<td align="center">&nbsp;</td><td width="60">&nbsp;</td>';
			}
			
			echo '</tr>';
		}
		echo '</table>';
	}
	
	function js()
	{
?>
		function onCCC(code)
		{
			goTo({ ccc: code });
		}

		function filterEvents()
		{
			goTo({ filter: checkboxFilterFlags() });
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Events'));

?>