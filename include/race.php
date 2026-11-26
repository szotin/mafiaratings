<?php

require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/utilities.php'; // versioned_asset
require_once __DIR__ . '/cards.php';

// The race of a competition: the places of the top players after every game, played back as a list
// whose rows change places as the points come in - the "bar chart race" of the sites that show how a
// season went. race.js draws it; this hands it the data and the frame.
//
// Who is in the top is worked out at every moment, not taken from the final standings: the race is
// of everybody, and somebody is in it while they are among the top RACE_TOP, out of it when they
// fall below, and back when they climb again.

define('RACE_TOP', 20);
define('RACE_RATING_FRAMES', 150); // the moments the race of a rating is played back in

// The logo of a player's club beside their name in a race, or nothing for a player of no club.
function race_club($pic, $id, $name, $flags)
{
	if ($id == NULL || $id <= 0)
	{
		return '';
	}
	$pic->set($id, $name, $flags);
	$title = htmlspecialchars(strip_tags($name), ENT_QUOTES, 'UTF-8');
	if ($pic->has_image())
	{
		return '<img class="rc" src="' . $pic->url(ICONS_DIR) . '" alt="' . $title . '" title="' . $title . '" loading="lazy">';
	}
	return '<span class="rc club-ini" title="' . $title . '">' . card_initials($name) . '</span>';
}

// One player of a race: who they are, their club and where their row leads, and $h - the running total after
// every moment it changed, as array(time, value) or array(time, value, key). The key, where there is
// one, is what the order goes by instead of the value: a series puts whoever went further in it
// above whoever has more points.
function race_player($id, $name, $avatar, $url, $h, $club = '')
{
	$p = new stdClass();
	$p->id = (int)$id;
	$p->name = $name;
	$p->av = $avatar;
	$p->club = $club;
	$p->url = $url;
	$p->h = $h;
	return $p;
}

// The players of a tournament or a game day, from tournament_scores() or event_scores() with
// SCORING_LOD_HISTORY: each with the running total of its points after every game it played.
// $avatar($player) returns the picture of a player and $url($player) the page their row leads to.
function race_scoring_players($players, $avatar, $url)
{
	$race = array();
	$club_pic = new Picture(CLUB_PICTURE);
	foreach ($players as $player)
	{
		if (!isset($player->history) || count($player->history) == 0)
		{
			continue;
		}
		// The history is a running total in the order the scoring went through the games, which is
		// round by round - the final of a tournament before its main round - rather than the order
		// they were played in. So it is taken apart into what each game brought, the games are put in
		// the order they ended, and the total is run again in that order.
		$games = array();
		$prev = 0;
		foreach ($player->history as $index => $point)
		{
			$games[] = array((int)$point->time, $index, (float)$point->points - $prev);
			$prev = (float)$point->points;
		}
		usort($games, function($a, $b) { return $a[0] - $b[0] ?: $a[1] - $b[1]; });
		$h = array();
		$total = 0;
		foreach ($games as $game)
		{
			$total += $game[2];
			$h[] = array($game[0], round($total, 2));
		}
		$race[] = race_player($player->id, $player->name, $avatar($player), $url($player), $h,
			isset($player->club_id) ? race_club($club_pic, $player->club_id, $player->club_name, $player->club_flags) : '');
	}
	return $race;
}

// The race of the rating: everybody's rating moment by moment, the moments being RACE_RATING_FRAMES
// equal stretches of the time it covers. $condition narrows the players, as a condition on the users
// table under the alias u. The race is of whoever was ever among the top RACE_TOP.
function race_rating_players($condition)
{
	global $_lang;

	$where = ' WHERE (g.flags & ' . (GAME_FLAG_CANCELED | GAME_FLAG_RATING) . ') = ' . GAME_FLAG_RATING;
	// The stretches are of the whole history of rating games rather than of these players' own:
	// asking the games table how long it is costs nothing, asking it of the games of some players
	// costs a pass over all of them. A club that started late just has its first stretches empty.
	list($first_time, $last_time) = Db::record(get_label('game'),
		'SELECT MIN(end_time), MAX(end_time) FROM games WHERE (flags & ' . (GAME_FLAG_CANCELED | GAME_FLAG_RATING) . ') = ' . GAME_FLAG_RATING);
	if (is_null($first_time))
	{
		return array();
	}
	$first_day = (int)floor($first_time / 86400);
	$last_day = (int)floor($last_time / 86400);
	$period = max(1, (int)ceil(($last_day - $first_day + 1) / RACE_RATING_FRAMES)); // in days

	// What each player earned in each stretch, the stretches in order.
	$frames = array();
	$query = new DbQuery(
		'SELECT FLOOR((FLOOR(g.end_time / 86400) - ' . $first_day . ') / ' . $period . ') AS f, p.user_id, SUM(p.rating_earned) FROM players p' .
		' JOIN games g ON g.id = p.game_id JOIN users u ON u.id = p.user_id' . $where . ' AND ', $condition);
	$query->add(' GROUP BY f, p.user_id ORDER BY f');
	while ($row = $query->next())
	{
		list($f, $user_id, $earned) = $row;
		$frames[(int)$f][(int)$user_id] = (float)$earned;
	}

	// The ratings as they stood after each stretch, and who was among the top at any of them.
	$rating = array();
	$history = array();
	$in_race = array();
	foreach ($frames as $f => $earned)
	{
		// The last day of the stretch, or the last day anything was played if that is sooner.
		$time = (min($first_day + ($f + 1) * $period, $last_day + 1)) * 86400 - 1;
		foreach ($earned as $user_id => $points)
		{
			$rating[$user_id] = (isset($rating[$user_id]) ? $rating[$user_id] : 0) + $points;
			$history[$user_id][] = array($time, round(USER_INITIAL_RATING + $rating[$user_id]));
		}
		$top = $rating;
		arsort($top);
		$n = 0;
		foreach ($top as $user_id => $points)
		{
			if (++$n > RACE_TOP)
			{
				break;
			}
			$in_race[$user_id] = true;
		}
	}
	if (count($in_race) == 0)
	{
		return array();
	}

	$race = array();
	$pic = new Picture(USER_PICTURE);
	$club_pic = new Picture(CLUB_PICTURE);
	$query = new DbQuery(
		'SELECT u.id, nu.name, u.flags, c.id, nc.name, c.flags FROM users u JOIN names nu ON nu.id = u.name_id AND (nu.langs & ' . $_lang . ') <> 0' .
		' LEFT OUTER JOIN clubs c ON c.id = u.club_id LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & ' . $_lang . ') <> 0' .
		' WHERE u.id IN (' . implode(',', array_keys($in_race)) . ')');
	while ($row = $query->next())
	{
		list($id, $name, $flags, $club_id, $club_name, $club_flags) = $row;
		$id = (int)$id;
		if (!isset($history[$id]))
		{
			continue;
		}
		$race[] = race_player($id, $name, card_avatar($pic, $id, $name, $flags, ''), 'user_info.php?bck=1&id=' . $id, $history[$id],
			race_club($club_pic, $club_id, $club_name, $club_flags));
	}
	return $race;
}

// The race on the page. $options: 'rating' - the values are ratings, whole numbers that all start
// at a thousand, so a bar is measured from somewhat below the lowest of the top rather than from
// nothing; 'days' - the moments are named by their day alone; 'moments' - time => what that moment
// is, shown under its date; 'step' - what the count of the moments says, "[0] of [1]" by default.
function show_race($race, $timezone, $options = array())
{
	global $_lang;

	if (count($race) == 0)
	{
		echo '<p class="race-none">' . get_label('No games have been played yet.') . '</p>';
		return;
	}
	$times = array();
	foreach ($race as $p)
	{
		foreach ($p->h as $point)
		{
			$times[$point[0]] = true;
		}
	}
	$times = array_keys($times);
	sort($times);

	$params = new stdClass();
	$params->players = $race;
	$params->times = $times;
	$params->top = RACE_TOP;
	$params->timezone = $timezone;
	$params->lang = get_lang_code($_lang);
	$params->rating = isset($options['rating']) && $options['rating'];
	// Whether the moment on top is a day rather than the hour of a game: a rating moves in stretches
	// of days, and a series from one competition to the next.
	$params->days = $params->rating || (isset($options['days']) && $options['days']);
	// What each moment is called besides its date, where the race knows - the stage and the tour of
	// a game of a tournament - keyed by the moment.
	$params->moments = isset($options['moments']) ? (object)$options['moments'] : new stdClass();
	$params->labels = new stdClass();
	$params->labels->step = isset($options['step']) ? $options['step'] : get_label('[0] of [1]');
	$params->labels->play = get_label('Play~race');
	$params->labels->pause = get_label('Pause');

	echo '<section class="race" id="race">';
	echo '<div class="race-hd"><div class="race-when"><b id="race-date"></b><span id="race-step"></span></div>';
	echo '<div class="race-ctl"><button type="button" class="race-reset" id="race-reset" title="' . get_label('To the start') . '" aria-label="' . get_label('To the start') . '">' .
		'<svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 5h2v14H6zM20 5v14L9 12z" fill="currentColor"/></svg></button>';
	echo '<button type="button" class="race-play" id="race-play" aria-label="' . get_label('Play~race') . '">' . PageBase::icon('play') . '</button>';
	echo '<input type="range" id="race-pos" min="0" max="' . max(1, count($times) - 1) . '" step="any" value="0" aria-label="' . get_label('Where the race is') . '">';
	// How fast it plays: race.js marks the chosen one and remembers it.
	echo '<div class="race-speed" id="race-speed" role="group" aria-label="' . get_label('Speed') . '">';
	foreach (array(array(0.5, '½×'), array(1, '1×'), array(2, '2×'), array(4, '4×')) as $speed)
	{
		echo '<button type="button" data-speed="' . $speed[0] . '">' . $speed[1] . '</button>';
	}
	echo '</div></div></div>';
	echo '<div class="race-progress"><span id="race-bar"></span></div>';
	echo '<ol class="race-list" id="race-list"></ol>';
	echo '</section>';
	echo '<script src="' . versioned_asset('race.js') . '"></script>';
	echo '<script>raceStart(' . json_encode($params, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ');</script>';
}

?>
