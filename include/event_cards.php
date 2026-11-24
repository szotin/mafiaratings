<?php

require_once __DIR__ . '/tournament_cards.php';

// The lists of events (game days) as cards, the way include/tournament_cards.php shows tournaments:
// one for every list of events of the site.
//
// A page reads its events with its own query into objects with these fields:
//   id, name, flags, time, duration, timezone, city,
//   club_id, club_name, club_flags,
//   tournament_id, tournament_name, tournament_flags (NULL for an event of no tournament),
//   games_count, users_count, videos_count
// and optionally buttons - the html of the buttons of a manager, drawn on the cover - and extra,
// the html a list adds of its own under the place (the result of the player of user_events.php).
// Then it calls event_cards_show() to print them, grouped by month or by year as tournaments are.

// The filter by name of every list of events: the name of the event, of the tournament it is a
// round of, or of its club. The query must join the tournament as t (LEFT OUTER JOIN tournaments t).
// Returns the words looked for, for card_search_field().
function event_search_condition($condition)
{
	$search = isset($_REQUEST['search']) ? trim($_REQUEST['search']) : '';
	if ($search != '')
	{
		$like = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $search) . '%';
		$condition->add(
			' AND (e.name_id IN (SELECT n.id FROM names n WHERE n.name LIKE ?)' .
			' OR t.name_id IN (SELECT n.id FROM names n WHERE n.name LIKE ?)' .
			' OR e.club_id IN (SELECT _c.id FROM clubs _c JOIN names n ON n.id = _c.name_id WHERE n.name LIKE ?))',
			$like, $like, $like);
	}
	return $search;
}

// The cover: the picture of the event; without one, the picture of its tournament; without that,
// the logo of its club; and only without any of them, its initials.
function event_card_cover($event, $event_pic, $tournament_pic, $club_pic)
{
	$event_pic->set($event->id, $event->name, $event->flags);
	$club_pic->set($event->club_id, $event->club_name, $event->club_flags);
	if (!$event_pic->has_image(true) && !is_null($event->tournament_id))
	{
		$tournament_pic->set($event->tournament_id, $event->tournament_name, $event->tournament_flags);
		if ($tournament_pic->has_image(true))
		{
			return card_cover($tournament_pic, $club_pic, $event->name);
		}
	}
	if (!$event_pic->has_image(true) && $club_pic->has_image(true))
	{
		return card_cover($club_pic, $club_pic, $event->name);
	}
	return card_cover($event_pic, $club_pic, $event->name);
}

// The cards. $empty is the words when there is none.
// The menu "≡" of a game day for whoever manages or referees it, as on its own page; and what the
// list adds of its own (buttons). Coming or not is not a thing of a manager: it is left out.
function event_card_menu($event)
{
	$buttons = isset($event->buttons) ? $event->buttons : '';
	$prep = NULL;
	$items = array();
	$is_manager = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $event->club_id, $event->id, $event->tournament_id);
	$is_referee = is_permitted(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $event->club_id, $event->id, $event->tournament_id);
	if ($is_manager || $is_referee)
	{
		$own = PageBase::capture(function() use ($event, $is_manager, $is_referee)
		{
			show_event_buttons($event->id, $event->tournament_id, $event->time, $event->duration, $event->flags, $event->club_id, $event->club_flags, false, $is_manager, $is_referee);
		});
		$buttons = preg_replace('#<button\b[^>]*mr\.attendEvent\([^>]*>.*?</button>#s', '', $own) . $buttons;
		if (($event->club_flags & CLUB_FLAG_CLOSED) == 0)
		{
			$prep = new MenuItem('event_preparation.php?id=' . $event->id, get_label('Preparation'), get_label('Getting [0] ready: how many play, who is coming, where it is streamed from and who sits where.', $event->name));
		}
		$items = event_manage_items($event->id, $event->name, $event->club_id, $event->tournament_id, $is_manager);
	}
	return card_menu($buttons, $prep, $items);
}

function event_cards_show($events, $future, $empty)
{
	if (count($events) == 0)
	{
		echo '<p class="empty">' . $empty . '</p>';
		return;
	}

	$now = time();
	$event_pic = new Picture(EVENT_PICTURE);
	$tournament_pic = new Picture(TOURNAMENT_PICTURE);
	$club_pic = new Picture(CLUB_PICTURE);
	$headings = tournament_cards_headings($events, $future);
	echo '<div class="posters">';
	foreach ($events as $index => $event)
	{
		if (isset($headings[$index]))
		{
			echo '<h3 class="grp">' . $headings[$index] . '</h3>';
		}

		$playing = ($now >= $event->time && $now < $event->time + $event->duration);
		$canceled = ($event->flags & EVENT_FLAG_CANCELED) != 0;
		echo '<article class="pcard' . ($canceled ? ' is-off' : '') . '"><div class="pc-cover">' . event_card_cover($event, $event_pic, $tournament_pic, $club_pic);
		echo '<span class="pc-date">' . card_short_period($event->time, $event->duration, $event->timezone) . '</span>';
		echo event_card_menu($event);
		echo '</div><div class="pc-body"><h3><a href="event.php?bck=1&id=' . $event->id . '">' . $event->name . '</a></h3>';
		echo '<div class="pc-meta">';
		// The tournament a round belongs to is a link of its own, above the link of the card.
		if (!is_null($event->tournament_id))
		{
			echo '<a href="tournament.php?bck=1&id=' . $event->tournament_id . '">' . PageBase::icon('trophy') . $event->tournament_name . '</a>';
		}
		echo '<span>' . PageBase::icon('pin') . $event->city . '</span><span>' . PageBase::icon('users') . $event->club_name . '</span></div>';
		if (isset($event->extra))
		{
			echo $event->extra;
		}

		echo '<div class="pc-foot">';
		if ($canceled)
		{
			echo '<span class="chip off">' . get_label('Canceled') . '</span>';
		}
		else if ($playing)
		{
			echo '<span class="chip live">' . get_label('playing now') . '</span>';
		}
		echo '<span class="pc-nums">';
		if ($event->users_count > 0)
		{
			echo '<span title="' . get_label('Players attended') . '">' . PageBase::icon('users') . $event->users_count . '</span>';
		}
		if ($event->games_count > 0)
		{
			echo '<a href="event_games.php?bck=1&id=' . $event->id . '" title="' . get_label('Games played') . '">' . PageBase::icon('play') . $event->games_count . '</a>';
		}
		if ($event->videos_count > 0)
		{
			echo '<a href="event_videos.php?bck=1&id=' . $event->id . '" title="' . get_label('[0] videos from [1]', $event->videos_count, strip_tags($event->name)) . '">' . PageBase::icon('video') . $event->videos_count . '</a>';
		}
		echo '</span></div></div></article>';
	}
	echo '</div>';
}

?>
