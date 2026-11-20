<?php

require_once __DIR__ . '/tournament.php';
require_once __DIR__ . '/datetime.php';
require_once __DIR__ . '/cards.php';

// The lists of tournaments as cards, the way tournaments.php shows them: one for every list of
// tournaments of the site - of a club, a league, a series, an address, a player.
//
// A page reads its tournaments with its own query into objects with these fields:
//   id, name, flags, time, duration, timezone,
//   club_id, club_name, club_flags, city,
//   num_players, games_count, videos_count
// and optionally buttons - the html of the buttons of a manager, drawn on the cover - and extra,
// the html of a line of the card under its place and club. Then it calls
// tournament_cards_load() for their series and winners and tournament_cards_show() to print them.

// The filter by name of a list of tournaments: the name of the tournament, of its club, of a series
// it is in, or of the league of that series. The query must have the tournament as t. Returns the
// words looked for, for card_search_field().
function tournament_search_condition($condition)
{
	$search = isset($_REQUEST['search']) ? trim($_REQUEST['search']) : '';
	if ($search != '')
	{
		$like = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $search) . '%';
		$condition->add(
			' AND (t.name_id IN (SELECT n.id FROM names n WHERE n.name LIKE ?)' .
			' OR t.club_id IN (SELECT _c.id FROM clubs _c JOIN names n ON n.id = _c.name_id WHERE n.name LIKE ?)' .
			' OR t.id IN (SELECT _st.tournament_id FROM series_tournaments _st JOIN series _s ON _s.id = _st.series_id JOIN names n ON n.id = _s.name_id WHERE n.name LIKE ?)' .
			' OR t.id IN (SELECT _st.tournament_id FROM series_tournaments _st JOIN series _s ON _s.id = _st.series_id JOIN leagues _l ON _l.id = _s.league_id JOIN names n ON n.id = _l.name_id WHERE n.name LIKE ?))',
			$like, $like, $like, $like);
	}
	return $search;
}

// The series every tournament is in (the stars on its cover), and, unless $future, its winner.
function tournament_cards_load($tournaments, $future = false)
{
	global $_lang;

	$ids = array();
	$by_id = array();
	foreach ($tournaments as $tournament)
	{
		$tournament->series = array();
		$ids[] = (int)$tournament->id;
		$by_id[$tournament->id] = $tournament;
	}
	if (count($ids) == 0)
	{
		return;
	}
	$cs = implode(',', $ids);

	$query = new DbQuery(
		'SELECT st.tournament_id, st.stars, s.id, ns.name, s.flags, ' . series_owner_fields() .
		' FROM series_tournaments st' .
		' JOIN series s ON s.id = st.series_id' .
		' JOIN names ns ON ns.id = s.name_id AND (ns.langs & '.$_lang.') <> 0' .
		series_owner_join() .
		' WHERE st.tournament_id IN (' . $cs . ') ORDER BY s.id DESC');
	while ($row = $query->next())
	{
		list ($tournament_id, $stars, $series_id, $series_name, $series_flags, $league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags) = $row;
		if (isset($by_id[$tournament_id]))
		{
			$series = new stdClass();
			$series->stars = $stars;
			$series->id = $series_id;
			$series->name = $series_name;
			$series->flags = $series_flags;
			$series->owner = SeriesOwner::from_row($league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags);
			$by_id[$tournament_id]->series[] = $series;
		}
	}

	if (!$future)
	{
		$query = new DbQuery(
			'SELECT p.tournament_id, nu.name FROM tournament_places p' .
			' JOIN users u ON u.id = p.user_id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0' .
			' WHERE p.place = 1 AND p.tournament_id IN (' . $cs . ')');
		while ($row = $query->next())
		{
			list ($tournament_id, $winner) = $row;
			if (isset($by_id[$tournament_id]) && !isset($by_id[$tournament_id]->winner))
			{
				$by_id[$tournament_id]->winner = $winner;
			}
		}
	}
}

// The month a tournament is grouped under: the one it starts in, or, for the future, the one it ends
// in. As a number - twelve a year.
function tournament_cards_month($tournament, $future)
{
	date_default_timezone_set($tournament->timezone);
	$time = $future ? $tournament->time + $tournament->duration : $tournament->time;
	return date('Y', $time) * 12 + date('n', $time) - 1;
}

// The headings of the groups of the cards, by the index of the first tournament of each. A list is
// grouped by month when its months are full enough - three tournaments a month on average or more;
// a list of a few tournaments a month (of a club, a player) is grouped by year instead, not a heading
// over every card or two.
define('TOURNAMENT_CARDS_PER_MONTH', 3);
function tournament_cards_headings($tournaments, $future)
{
	global $_lang;
	
	$months = array();
	foreach ($tournaments as $index => $tournament)
	{
		$months[$index] = tournament_cards_month($tournament, $future);
	}
	$by_year = count($tournaments) < TOURNAMENT_CARDS_PER_MONTH * count(array_unique($months));
	
	$headings = array();
	$current = NULL;
	foreach ($months as $index => $month)
	{
		$group = $by_year ? floor($month / 12) : $month;
		if ($group !== $current)
		{
			$headings[$index] = $by_year ? $group : get_month_name($month % 12 + 1, $_lang, true) . ' ' . floor($month / 12);
			$current = $group;
		}
	}
	// A single group needs no heading: there is nothing to tell it from.
	if (count($headings) < 2)
	{
		return array();
	}
	return $headings;
}

// The cards, grouped by month or by year (see tournament_cards_headings()). $empty is the words when
// there is none.
function tournament_cards_show($tournaments, $future, $empty)
{
	if (count($tournaments) == 0)
	{
		echo '<p class="empty">' . $empty . '</p>';
		return;
	}

	$now = time();
	$tournament_pic = new Picture(TOURNAMENT_PICTURE);
	$club_pic = new Picture(CLUB_PICTURE);
	$headings = tournament_cards_headings($tournaments, $future);
	echo '<div class="posters">';
	foreach ($tournaments as $index => $tournament)
	{
		if (isset($headings[$index]))
		{
			echo '<h3 class="grp">' . $headings[$index] . '</h3>';
		}

		$playing = ($now >= $tournament->time && $now < $tournament->time + $tournament->duration);
		$canceled = ($tournament->flags & TOURNAMENT_FLAG_CANCELED) != 0;
		$url = 'tournament.php?bck=1&id=' . $tournament->id;

		$tournament_pic->set($tournament->id, $tournament->name, $tournament->flags);
		$club_pic->set($tournament->club_id, $tournament->club_name, $tournament->club_flags);
		echo '<article class="pcard' . ($canceled ? ' is-off' : '') . '"><div class="pc-cover">' . card_cover($tournament_pic, $club_pic, $tournament->name);
		echo '<span class="pc-date">' . card_short_period($tournament->time, $tournament->duration, $tournament->timezone) . '</span>';

		// The stars it has in each of its series, the most of them on the cover.
		$stars = 0;
		$series_names = array();
		if (isset($tournament->series))
		{
			foreach ($tournament->series as $series)
			{
				$stars = max($stars, (float)$series->stars);
				$series_names[] = strip_tags($series->name) . ': ' . tournament_stars_str($series->stars, false);
			}
		}
		if ($stars > 0)
		{
			echo '<span class="pc-stars" title="' . htmlspecialchars(implode("\n", $series_names), ENT_QUOTES, 'UTF-8') . '"><span class="stars">' . tournament_stars_str($stars, false) . '</span></span>';
		}
		if (isset($tournament->buttons) && trim($tournament->buttons) != '')
		{
			echo '<span class="pc-actions">' . $tournament->buttons . '</span>';
		}
		echo '</div><div class="pc-body"><h3><a href="' . $url . '">' . $tournament->name . '</a></h3>';
		echo '<div class="pc-meta"><span>' . PageBase::icon('pin') . $tournament->city . '</span><span>' . PageBase::icon('users') . $tournament->club_name . '</span></div>';
		// What the list adds of its own: the result of the player of user_tournaments.php, the stage
		// of a series in series_tournaments.php.
		if (isset($tournament->extra))
		{
			echo $tournament->extra;
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
		else if (isset($tournament->winner))
		{
			// The cup says what the name is; the word "Winner" left the name no room beside the numbers.
			echo '<span class="pc-win" title="' . get_label('Winner') . ': ' . $tournament->winner . '">' . PageBase::icon('trophy') . '<b>' . $tournament->winner . '</b></span>';
		}
		echo '<span class="pc-nums">';
		if ($future)
		{
			if ($tournament->num_players > 0)
			{
				echo '<span title="' . get_label('Expected number of players') . '">' . PageBase::icon('users') . $tournament->num_players . '</span>';
			}
		}
		else
		{
			if ($tournament->num_players > 0)
			{
				echo '<span title="' . get_label('Players') . '">' . PageBase::icon('users') . $tournament->num_players . '</span>';
			}
			if ($tournament->games_count > 0)
			{
				echo '<a href="tournament_games.php?bck=1&id=' . $tournament->id . '" title="' . get_label('Games') . '">' . PageBase::icon('play') . $tournament->games_count . '</a>';
			}
			if ($tournament->videos_count > 0)
			{
				echo '<a href="tournament_videos.php?bck=1&id=' . $tournament->id . '" title="' . get_label('Videos from [0]', strip_tags($tournament->name)) . '">' . PageBase::icon('video') . $tournament->videos_count . '</a>';
			}
		}
		echo '</span></div></div></article>';
	}
	echo '</div>';
}

?>
