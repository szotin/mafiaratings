<?php

require_once 'include/player_stats.php';
require_once 'include/tournament.php';
require_once 'include/pages.php';
require_once 'include/address.php';
require_once 'include/event.php';
require_once 'include/checkbox_filter.php';
require_once 'include/cards.php';

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
		global $_profile, $_page, $_lang;

		$can_create = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $this->club_id, $this->id);
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		echo '<div class="toolbar"><div class="tb-line tb-start">';
		show_checkbox_filter(array(get_label('with video'), get_label('unplayed events'), get_label('canceled events')), $filter, 'filterEvents');
		if ($can_create)
		{
			echo '<a class="btn tb-create" href="#" onclick="mr.createRound(' . $this->id . '); return false;">' . PageBase::icon('plus') . '<span>' . get_label('Create [0]', get_label('round')) . '</span></a>';
		}
		echo '</div></div>';
		
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
			$condition->add(' AND NOT EXISTS (SELECT g.id FROM games g WHERE g.event_id = e.id)');
		}
		if ($filter & FLAG_FILTER_NOT_EMPTY)
		{
			$condition->add(' AND EXISTS (SELECT g.id FROM games g WHERE g.event_id = e.id)');
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
			'SELECT eu.flags, e.id, ne.name, e.flags, e.start_time, e.duration, e.scoring_options, e.scoring_id, ns.name, e.scoring_version, ct.timezone, a.id, na.name, a.flags, a.address,' .
				' (SELECT count(*) FROM games WHERE event_id = e.id AND (flags & '.GAME_FLAG_CANCELED.') = 0) as games,' .
				' (SELECT count(distinct p.user_id) FROM players p JOIN games g ON g.id = p.game_id WHERE g.event_id = e.id) as users,' .
				' (SELECT count(*) FROM videos WHERE event_id = e.id) as videos' .
				' FROM events e ' .
				' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN event_regs eu ON eu.event_id = e.id AND eu.user_id = ?' .
				' JOIN addresses a ON e.address_id = a.id' .
				' JOIN names na ON na.id = a.name_id AND (na.langs & '.$_lang.') <> 0' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' JOIN scorings s ON s.id = e.scoring_id' .
				' JOIN names ns ON ns.id = s.name_id AND (ns.langs & '.$_lang.') <> 0',
			$user_id, $condition);
		// In the order they are played: the main round (0), then the finals from the farthest to the
		// last one - ..., quarter-final (3), semi-final (2), final (1).
		$query->add(' ORDER BY e.round = 0 DESC, e.round DESC, e.start_time, e.id LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
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
			
		if (count($rounds) == 0)
		{
			echo '<p class="empty">' . get_label('No rounds found.') . '</p>';
		}
		else
		{
			echo '<div class="posters">';
		}

		// The cover of a round is its own picture; without one, its initials with the tournament's
		// logo in the corner, the way a tournament without a picture shows its club.
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$tournament_pic->set($this->id, $this->name, $this->flags);
		$event_pic = new Picture(EVENT_PICTURE);
		$now = time();
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

			$canceled = ($event_flags & EVENT_FLAG_CANCELED) != 0;
			$playing = !$canceled && $now >= $event_time && $now < $event_time + $event_duration;
			$event_pic->set($event_id, $event_name, $event_flags);
			echo '<article class="pcard' . ($canceled ? ' is-off' : '') . '"><div class="pc-cover">' . card_cover($event_pic, $tournament_pic, $event_name);
			echo '<span class="pc-date">' . card_short_period($event_time, $event_duration, $timezone) . '</span>';

			$buttons = '';
			if ($buttons_needed)
			{
				$buttons = PageBase::capture(function() use ($user_event_flags, $event_id, $event_flags, $event_time, $event_duration, $now)
				{
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
				}
				});
			}
			if (trim($buttons) != '')
			{
				echo '<span class="pc-actions">' . $buttons . '</span>';
			}
			echo '</div><div class="pc-body"><h3><a href="event.php?bck=1&id=' . $event_id . '">' . $event_name . '</a></h3>';

			// The scoring, with what it adds to the points and the coefficient when it is not the usual 1.
			$scoring_title = array();
			if (($scoring_flags & SCORING_OPTION_NO_NIGHT_KILLS) == 0)
			{
				$scoring_title[] = get_label('with first night kill points');
			}
			if (($scoring_flags & SCORING_OPTION_NO_GAME_DIFFICULTY) == 0)
			{
				$scoring_title[] = get_label('with game difficulty points');
			}
			echo '<div class="pc-meta"><span>' . PageBase::icon('pin') . $address_name . '</span>';
			echo '<a href="#" onclick="showScoring(' . $scoring_id .', ' .  $scoring_version . ', ' .  $scoring_flags . '); return false;" title="' . htmlspecialchars(implode("\n", $scoring_title), ENT_QUOTES, 'UTF-8') . '">' . PageBase::icon('layers') . $scoring_name;
			if ($scoring_weight != 1)
			{
				echo ' <b>&times;' . $scoring_weight . '</b>';
			}
			echo '</a></div>';

			echo '<div class="pc-foot">';
			if ($canceled)
			{
				echo '<span class="chip off">' . get_label('canceled') . '</span>';
			}
			else if ($playing)
			{
				echo '<span class="chip live">' . get_label('playing now') . '</span>';
			}
			echo '<span class="pc-nums">';
			if ($users_count > 0)
			{
				echo '<span title="' . get_label('Players attended') . '">' . PageBase::icon('users') . $users_count . '</span>';
			}
			if ($games_count > 0)
			{
				echo '<a href="event_games.php?bck=1&id=' . $event_id . '" title="' . get_label('Games played') . '">' . PageBase::icon('play') . $games_count . '</a>';
			}
			if ($videos_count > 0)
			{
				echo '<a href="event_videos.php?bck=1&id=' . $event_id . '" title="' . get_label('[0] videos from [1]', $videos_count, strip_tags($event_name)) . '">' . PageBase::icon('video') . $videos_count . '</a>';
			}
			echo '</span></div></div></article>';
		}
		if (count($rounds) > 0)
		{
			echo '</div>';
		}
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