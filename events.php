<?php

require_once 'include/general_page_base.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/event.php';
require_once 'include/ccc_filter.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';

define('PAGE_SIZE', EVENTS_PAGE_SIZE);

define('FLAG_FILTER_VIDEOS', 0x0001);
define('FLAG_FILTER_NO_VIDEOS', 0x0002);
define('FLAG_FILTER_TOURNAMENT', 0x0004);
define('FLAG_FILTER_NOT_TOURNAMENT', 0x0008);
define('FLAG_FILTER_EMPTY', 0x0010);
define('FLAG_FILTER_NOT_EMPTY', 0x0020);
define('FLAG_FILTER_CANCELED', 0x0040);
define('FLAG_FILTER_NOT_CANCELED', 0x0080);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NOT_CANCELED | FLAG_FILTER_NOT_EMPTY);

class Page extends GeneralPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$this->filter = (int)$_REQUEST['filter'];
		}
		
		$this->future = false;
		if (isset($_REQUEST['future']))
		{
			$this->future = ((int)$_REQUEST['future'] > 0);
		}
	}

	protected function show_body()
	{
		global $_profile, $_page;
		
		$condition = new SQL(' FROM events e JOIN addresses a ON e.address_id = a.id JOIN clubs c ON e.club_id = c.id LEFT OUTER JOIN tournaments t ON e.tournament_id = t.id JOIN cities ct ON ct.id = a.city_id');
		if ($this->future)
		{
			$condition->add(' WHERE e.start_time + e.duration >= UNIX_TIMESTAMP() AND (e.flags & ' . EVENT_FLAG_HIDDEN_BEFORE . ') = 0');
		}
		else
		{
			$condition->add(' WHERE e.start_time < UNIX_TIMESTAMP() AND (e.flags & ' . EVENT_FLAG_HIDDEN_AFTER . ') = 0');
			
			if ($this->filter & FLAG_FILTER_VIDEOS)
			{
				$condition->add(' AND EXISTS (SELECT v.id FROM videos v WHERE v.event_id = e.id)');
			}
			if ($this->filter & FLAG_FILTER_NO_VIDEOS)
			{
				$condition->add(' AND NOT EXISTS (SELECT v.id FROM videos v WHERE v.event_id = e.id)');
			}
			if ($this->filter & FLAG_FILTER_TOURNAMENT)
			{
				$condition->add(' AND e.tournament_id IS NOT NULL');
			}
			if ($this->filter & FLAG_FILTER_NOT_TOURNAMENT)
			{
				$condition->add(' AND e.tournament_id IS NULL');
			}
			if ($this->filter & FLAG_FILTER_EMPTY)
			{
				$condition->add(' AND NOT EXISTS (SELECT g.id FROM games g WHERE g.event_id = e.id AND g.result > 0)');
			}
			if ($this->filter & FLAG_FILTER_NOT_EMPTY)
			{
				$condition->add(' AND EXISTS (SELECT g.id FROM games g WHERE g.event_id = e.id AND g.result > 0)');
			}
			if ($this->filter & FLAG_FILTER_CANCELED)
			{
				$condition->add(' AND (e.flags & ' . EVENT_FLAG_CANCELED . ') <> 0');
			}
			if ($this->filter & FLAG_FILTER_NOT_CANCELED)
			{
				$condition->add(' AND (e.flags & ' . EVENT_FLAG_CANCELED . ') = 0');
			}
		}
		
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('events')));
		echo '&emsp;&emsp;';
		show_date_filter();
		if (!$this->future)
		{
			echo '&emsp;&emsp;';
			show_checkbox_filter(array(get_label('with video'), get_label('tournament events'), get_label('unplayed events'), get_label('canceled events')), $this->filter);
		}
		echo '</td></tr></table></p>';
		
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
		
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND e.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND e.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		echo '<div class="tab">';
		echo '<button ' . ($this->future ? '' : 'class="active" ') . 'onclick="goTo({future:0,page:0})">' . get_label('Past') . '</button>';
		echo '<button ' . (!$this->future ? '' : 'class="active" ') . 'onclick="goTo({future:1,page:0})">' . get_label('Future') . '</button>';
		echo '</div>';
		echo '<div class="tabcontent">';
		
		list ($count) = Db::record(get_label('event'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		$colunm_counter = 0;
		$query = new DbQuery(
			'SELECT e.id, e.name, e.flags, e.start_time, e.duration, ct.timezone, t.id, t.name, t.flags, c.id, c.name, c.flags, e.languages, a.id, a.address, a.flags,' .
			' (SELECT count(*) FROM games WHERE event_id = e.id AND result IN (1, 2) AND is_canceled = 0) as games,' .
			' (SELECT count(distinct p.user_id) FROM players p JOIN games g ON g.id = p.game_id WHERE g.event_id = e.id) as users,' .
			' (SELECT count(*) FROM videos WHERE event_id = e.id) as videos',
			$condition);
		if ($this->future)
		{
			$query->add(' ORDER BY e.start_time, e.id');
		}
		else
		{
			$query->add(' ORDER BY e.start_time DESC, e.id DESC');
		}
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker">';
		echo '<td colspan="2">' . get_label('Event') . '</td>';
		echo '<td width="100" align="center">' . get_label('Games played') . '</td>';
		echo '<td width="100" align="center">' . get_label('Players attended') . '</td></tr>';
		
		$now = time();
		$event_pic = new Picture(EVENT_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		while ($row = $query->next())
		{
			list ($event_id, $event_name, $event_flags, $event_time, $event_duration, $timezone, $tournament_id, $tournament_name, $tournament_flags, $club_id, $club_name, $club_flags, $languages, $addr_id, $addr, $addr_flags, $games_count, $users_count, $videos_count) = $row;
			$playing =($now >= $event_time && $now < $event_time + $event_duration);
			
			if ($tournament_name != NULL)
			{
				$event_name = $tournament_name . ': ' . $event_name;
			}
			if ($playing)
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
			
			echo '<td style="padding-left:12px;"><b><a href="event_standings.php?bck=1&id=' . $event_id . '">' . $event_name;
			if ($playing)
			{
				echo ' (' . get_label('playing now') . ')';
			}
			echo '</b><br>' . format_date($event_time, $timezone, true) . '</a></td>';
			
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
		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Events'));

?>