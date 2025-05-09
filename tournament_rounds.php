<?php

require_once 'include/player_stats.php';
require_once 'include/tournament.php';
require_once 'include/pages.php';
require_once 'include/address.php';
require_once 'include/event.php';
require_once 'include/checkbox_filter.php';

define("CUT_NAME",45);
define('PAGE_SIZE', EVENTS_PAGE_SIZE);

define('FLAG_FILTER_VIDEOS', 0x0001);
define('FLAG_FILTER_NO_VIDEOS', 0x0002);
define('FLAG_FILTER_EMPTY', 0x0004);
define('FLAG_FILTER_NOT_EMPTY', 0x0008);
define('FLAG_FILTER_CANCELED', 0x0010);
define('FLAG_FILTER_NOT_CANCELED', 0x0020);

define('FLAG_FILTER_DEFAULT', 0);

class Page extends TournamentPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		$can_create = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $this->club_id, $this->id);
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		echo '<table class="transp" width="100%"><tr><td>';
		show_checkbox_filter(array(get_label('with video'), get_label('unplayed events'), get_label('canceled events')), $filter, 'filterEvents');
		echo '</td></tr></table>';
		
		$condition = new SQL(' WHERE e.tournament_id = ?',
			$this->id);
		if ($filter & FLAG_FILTER_VIDEOS)
		{
			$condition->add(' AND EXISTS (SELECT v.id FROM videos v WHERE v.event_id = e.id)');
		}
		if ($filter & FLAG_FILTER_NO_VIDEOS)
		{
			$condition->add(' AND NOT EXISTS (SELECT v.id FROM videos v WHERE v.event_id = e.id)');
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
		
		list ($count) = Db::record(get_label('event'), 'SELECT count(*) FROM events e', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		$user_id = is_null($_profile) ? NULL : $_profile->user_id;
		$query = new DbQuery(
			'SELECT eu.flags, e.id, e.name, e.flags, e.start_time, e.duration, e.scoring_options, e.scoring_id, s.name, e.scoring_version, ct.timezone, a.id, a.name, a.flags, a.address,' .
				' (SELECT count(*) FROM games WHERE event_id = e.id AND is_canceled = FALSE AND result > 0) as games,' .
				' (SELECT count(distinct p.user_id) FROM players p JOIN games g ON g.id = p.game_id WHERE g.event_id = e.id) as users,' .
				' (SELECT count(*) FROM videos WHERE event_id = e.id) as videos' .
				' FROM events e ' .
				' LEFT OUTER JOIN event_users eu ON eu.event_id = e.id AND eu.user_id = ?' .
				' JOIN addresses a ON e.address_id = a.id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' JOIN scorings s ON s.id = e.scoring_id',
			$user_id, $condition);
		$query->add(' ORDER BY e.start_time DESC, e.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		$buttons_needed = $can_create;
		$rounds = array();
		while ($row = $query->next())
		{
			if (!is_null($row[0]) && ($row[0] & (USER_PERM_MANAGER | USER_PERM_REFEREE)) != NULL)
			{
				$buttons_needed = true;
			}
			$rounds[] = $row;
		}
			
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker">';
		if ($buttons_needed)
		{
			echo '<th width="112" align="left">';
			if ($can_create)
			{
				echo '<button class="icon" onclick="mr.createRound(' . $this->id . ')" title="'  . get_label('Create [0]', get_label('round')) . '"><img src="images/create.png" border="0"></button>';
			}
			echo '</th>';
		}
		echo '<th colspan="2">' . get_label('Round') . '</th>';
		echo '<th width="140" align="center">' . get_label('Scoring') . '</th>';
		echo '<th width="80" align="center">' . get_label('Scoring coefficient') . '</th>';
		echo '<th width="80" align="center">' . get_label('Games played') . '</th>';
		echo '<th width="80" align="center">' . get_label('Players attended') . '</th></tr>';
		
		$now = time();
		$event_pic = new Picture(EVENT_PICTURE);
		foreach ($rounds as $row)
		{
			list($user_event_flags, $event_id, $event_name, $event_flags, $event_time, $event_duration, $scoring_options, $scoring_id, $scoring_name, $scoring_version, $timezone, $address_id, $address_name, $address_flags, $address, $games_count, $users_count, $videos_count) = $row;
			$scoring_options = json_decode($scoring_options);
			$scoring_weight = isset($scoring_options->weight) ? $scoring_options->weight : 1;
			$scoring_flags = isset($scoring_options->flags) ? $scoring_options->flags : 0;
			$user_event_flags = is_null($user_event_flags) ? 0 : $user_event_flags;
			if ($can_create)
			{
				$user_event_flags |= USER_PERM_MANAGER;
			}

			if ($event_flags & EVENT_FLAG_CANCELED)
			{
				echo '<tr class="dark">';
			}
			else
			{
				echo '<tr>';
			}
			
			if ($buttons_needed)
			{
				echo '<td>';
				if ($user_event_flags & USER_PERM_MANAGER)
				{
					echo '<button class="icon" onclick="mr.editEvent(' . $event_id . ')" title="' . get_label('Edit the event') . '"><img src="images/edit.png" border="0"></button>';
					if (($event_flags & EVENT_FLAG_CANCELED) != 0)
					{
						echo '<button class="icon" onclick="mr.restoreEvent(' . $event_id . ')"><img src="images/undelete.png" border="0"></button>';
					}
					echo '<button class="icon" onclick="mr.deleteEvent(' . $event_id . ')" title="' . get_label('Delete round') . '"><img src="images/delete.png" border="0"></button>';
				}
				if ($user_event_flags & USER_PERM_REFEREE && $event_time + $event_duration + EVENT_ALIVE_TIME >= $now)
				{
					echo '<button class="icon" onclick="mr.extendEvent(' . $event_id . ')" title="' . get_label('Event flow. Finish event, or extend event.') . '"><img src="images/time.png" border="0"></button>';
					if ($event_time + $event_duration >= $now)
					{
						echo '<button class="icon" onclick="goTo(\'game.php\', {event_id: ' . $event_id . '})" title="' . get_label('Play the game') . '"><img src="images/game.png" border="0"></button>';
					}
					$no_buttons = false;
				}
				echo '</td>';
			}
			
			echo '<td width="60" class="dark">';
			$event_pic->set($event_id, $event_name, $event_flags);
			$event_pic->show(ICONS_DIR, true, 60);
			echo '</td>';
			
			echo '<td><table width="100%" class="transp"><tr>';
			echo '<td style="padding-left:12px;"><b><a href="event_standings.php?bck=1&id=' . $event_id . '">' . $event_name . '</b>';
			echo '<br>' . format_date_period($event_time, $event_duration, $timezone) . '</a></td>';
			if ($videos_count > 0)
			{
				echo '<td align="right"><a href="event_videos.php?id=' . $event_id . '&bck=1" title="' . get_label('[0] videos from [1]', $videos_count, $event_name) . '"><img src="images/video.png" width="40" height="40"></a></td>';
			}
			echo '</tr></table>';
			echo '</td>';
			
			echo '<td align="center"><a href="#" onclick="showScoring(' . $scoring_id .', ' .  $scoring_version . ', ' .  $scoring_flags . ')">' . $scoring_name;
			if (($scoring_flags & SCORING_OPTION_NO_NIGHT_KILLS) == 0)
			{
				echo '<br>' . get_label('with first night kill points');
			}
			if (($scoring_flags & SCORING_OPTION_NO_GAME_DIFFICULTY) == 0)
			{
				echo '<br>' . get_label('with game difficulty points');
			}
			echo '</a></td>';
			
			echo '<td align="center">' . $scoring_weight . '</td>';
			
			echo '<td align="center"><a href="event_games.php?bck=1&id=' . $event_id . '">' . $games_count . '</a></td>';
			echo '<td align="center"><a href="event_standings.php?bck=1&id=' . $event_id . '">' . $users_count . '</a></td>';
			
			echo '</tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
	
	protected function js()
	{
?>
		function filterEvents()
		{
			goTo({ filter: checkboxFilterFlags(), page: 0 });
		}
		
		function showScoring(id, version, flags)
		{
			dlg.infoForm("form/scoring_show.php?id=" + id + "&version=" + version + "&ops_flags=" + flags);
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Events history'));

?>