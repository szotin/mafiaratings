<?php

require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/address.php';
require_once 'include/event.php';
require_once 'include/checkbox_filter.php';

define("CUT_NAME",45);
define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

define('FLAG_FILTER_VIDEOS', 0x0001);
define('FLAG_FILTER_NO_VIDEOS', 0x0002);
define('FLAG_FILTER_TOURNAMENT', 0x0004);
define('FLAG_FILTER_NOT_TOURNAMENT', 0x0008);
define('FLAG_FILTER_EMPTY', 0x0010);
define('FLAG_FILTER_NOT_EMPTY', 0x0020);
define('FLAG_FILTER_CANCELED', 0x0040);
define('FLAG_FILTER_NOT_CANCELED', 0x0080);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NOT_CANCELED | FLAG_FILTER_NOT_EMPTY);

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		$future = false;
		if (isset($_REQUEST['future']))
		{
			$future = ((int)$_REQUEST['future'] > 0);
		}
		
		$condition = new SQL(
			' FROM events e ' .
				' JOIN addresses a ON e.address_id = a.id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' .
				' WHERE e.club_id = ?',
			$this->id);
		if ($future)
		{
			$condition->add(' AND e.start_time + e.duration >= UNIX_TIMESTAMP()');
		}
		else
		{
			$condition->add(' AND e.start_time < UNIX_TIMESTAMP()');
			
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
			if ($filter & FLAG_FILTER_EMPTY)
			{
				$condition->add(' AND NOT EXISTS (SELECT g.id FROM games g WHERE g.event_id = e.id AND g.result > 0)');
			}
			if ($filter & FLAG_FILTER_NOT_EMPTY)
			{
				$condition->add(' AND EXISTS (SELECT g.id FROM games g WHERE g.event_id = e.id AND g.result > 0)');
			}
			if ($filter & FLAG_FILTER_CANCELED)
			{
				$condition->add(' AND (e.flags & ' . EVENT_FLAG_CANCELED . ') <> 0');
			}
			if ($filter & FLAG_FILTER_NOT_CANCELED)
			{
				$condition->add(' AND (e.flags & ' . EVENT_FLAG_CANCELED . ') = 0');
			}
		}
		
		echo '<div class="tab">';
		echo '<button ' . ($future ? '' : 'class="active" ') . 'onclick="goTo({future:0,page:0})">' . get_label('Past') . '</button>';
		echo '<button ' . (!$future ? '' : 'class="active" ') . 'onclick="goTo({future:1,page:0})">' . get_label('Future') . '</button>';
		echo '</div>';
		echo '<div class="tabcontent">';
		
		if (!$future)
		{
			echo '<p><table class="transp" width="100%"><tr><td>';
			show_checkbox_filter(array(get_label('with video'), get_label('tournament events'), get_label('unplayed events'), get_label('canceled events')), $filter, 'filterEvents');
			echo '</td></tr></table></p>';
		}
		
		list ($count) = Db::record(get_label('event'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT e.id, e.name, e.flags, e.start_time, ct.timezone, t.id, t.name, t.flags, a.id, a.name, a.flags, a.address,' .
				' (SELECT count(*) FROM games WHERE event_id = e.id AND is_canceled = FALSE AND result > 0) as games,' .
				' (SELECT count(distinct p.user_id) FROM players p JOIN games g ON g.id = p.game_id WHERE g.event_id = e.id) as users,' .
				' (SELECT count(*) FROM videos WHERE event_id = e.id) as videos',
			$condition);
		if ($future)
		{
			$query->add(' ORDER BY e.start_time, e.id');
		}
		else
		{
			$query->add(' ORDER BY e.start_time DESC, e.id DESC');
		}
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker">';
		echo '<td colspan="2">' . get_label('Event') . '</td>';
		echo '<td width="100" align="center">' . get_label('Games played') . '</td>';
		echo '<td width="100" align="center">' . get_label('Players attended') . '</td></tr>';
		
		$event_pic = new Picture(EVENT_PICTURE);
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		while ($row = $query->next())
		{
			list($event_id, $event_name, $event_flags, $event_time, $timezone, $tournament_id, $tournament_name, $tournament_flags, $address_id, $address_name, $address_flags, $address, $games_count, $users_count, $videos_count) = $row;

			if ($tournament_name != NULL)
			{
				$event_name = $tournament_name . ': ' . $event_name;
			}
			
			if ($event_flags & EVENT_FLAG_CANCELED)
			{
				echo '<tr class="dark">';
			}
			else
			{
				echo '<tr>';
			}
						echo '<td width="60" class="dark">';
			$event_pic->set($event_id, $event_name, $event_flags);
			$event_pic->show(ICONS_DIR, true, 60);
			echo '</td>';
			
			echo '<td><table width="100%" class="transp"><tr>';
			if ($tournament_id != NULL)
			{
				echo '<td width="40" align="center" valign="center" style="padding-left:12px;>';
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
			
			echo '<td align="center"><a href="event_games.php?bck=1&id=' . $event_id . '">' . $games_count . '</a></td>';
			echo '<td align="center"><a href="event_standings.php?bck=1&id=' . $event_id . '">' . $users_count . '</a></td>';
			
			echo '</tr>';
		}
		echo '</table>';
	}
	
	protected function js()
	{
?>
		function filterEvents()
		{
			goTo({ filter: checkboxFilterFlags(), page: 0 });
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Events history'));

?>