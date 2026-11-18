<?php

require_once __DIR__ . '/series.php';
require_once __DIR__ . '/cards.php';

// The lists of series as tiles, the way series.php shows them: one for every list of series of the
// site - of a club, a league, a player, the series a series is made of.
//
// A page reads its series with its own query into objects with these fields:
//   id, name, flags, start_time, duration, max_stars, owner (a SeriesOwner), tournaments
// and optionally buttons - the html of the buttons of a manager - and extra, the html of a line of
// the tile under its dates (the result of a player, the stars of a subseries). Then it calls
// series_tiles_show() to print them.

// The dates of a series, short as on the cards of tournaments. A series - a season - runs from one
// year into the next more often than not, and card_short_period() names one year at most: that is
// "15 Jan – 15 Jan" for a whole year. Then each date takes its own year.
function series_short_period($start, $duration, $timezone)
{
	date_default_timezone_set($timezone);
	$end = $start + max($duration, 1) - 1;
	if (date('Y', $start) == date('Y', $end))
	{
		return card_short_period($start, $duration, $timezone);
	}
	return series_short_date($start) . ' – ' . series_short_date($end);
}

// One date with its year, in the words of card_short_period(): "15 янв 2025", "Jan 15, 2025". The
// time zone is set already.
function series_short_date($time)
{
	global $_lang;
	$month = card_month_abbr(date('n', $time));
	if ($_lang == LANG_RUSSIAN || $_lang == LANG_UKRAINIAN)
	{
		return date('j', $time) . ' ' . mb_strtolower($month, 'UTF-8') . ' ' . date('Y', $time);
	}
	return $month . ' ' . date('j', $time) . ', ' . date('Y', $time);
}

// The class of the colour of a type: a stripe down the left edge of the tile and its chip.
function series_type_class($type)
{
	switch ($type)
	{
		case SERIES_TYPE_SEASON:
			return 'season';
		case SERIES_TYPE_RECURRING:
			return 'recurring';
	}
	return 'series';
}

// One tile.
function series_tile_show($series, $timezone = NULL)
{
	static $series_pic = NULL;
	static $types = NULL;
	if (is_null($series_pic))
	{
		$series_pic = new SeriesPicture();
		$types = get_series_types();
	}
	if (is_null($timezone))
	{
		$timezone = get_timezone();
	}

	$now = time();
	$infinite = series_is_infinite($series->start_time);
	// A recurring tournament is always under way. Saying so adds nothing to its type.
	$playing = (!$infinite && $now >= $series->start_time && $now < $series->start_time + $series->duration);
	$canceled = ($series->flags & SERIES_FLAG_CANCELED) != 0;
	$type = series_type($series->start_time, $series->max_stars);

	echo '<div class="tile st-' . series_type_class($type) . ($canceled ? ' is-off' : '') . '">' . card_logo($series_pic->set($series->id, $series->name, $series->flags, $series->owner), $series->name);
	echo '<div class="who-txt"><a class="who-name" href="series_standings.php?bck=1&id=' . $series->id . '">' . $series->name . '</a>';
	echo '<span class="who-sub">' . PageBase::icon($series->owner->is_league() ? 'trophy' : 'users') . $series->owner->name . '</span>';
	echo '<span class="tile-meta">';
	if (!$infinite)
	{
		// Short: a long date ran to two lines and made the tile taller than the others.
		echo '<span>' . PageBase::icon('cal') . series_short_period($series->start_time, $series->duration, $timezone) . '</span>';
	}
	if ($canceled)
	{
		echo '<span class="chip off">' . get_label('canceled') . '</span>';
	}
	else if ($playing)
	{
		echo '<span class="chip live">' . get_label('playing now') . '</span>';
	}
	echo '</span>';
	if (isset($series->extra))
	{
		echo $series->extra;
	}
	echo '</div>';
	// The type stands in one place on every tile: over the number of its tournaments.
	echo '<div class="tile-side"><span class="chip st-chip">' . $types[$type] . '</span>';
	echo '<a class="tile-num" href="series_tournaments.php?bck=1&id=' . $series->id . '" title="' . get_label('Tournaments') . '"><b>' . $series->tournaments . '</b><span>' . get_label('Tournaments') . '</span></a></div>';
	if (isset($series->buttons) && trim($series->buttons) != '')
	{
		echo '<div class="tile-actions">' . $series->buttons . '</div>';
	}
	echo '</div>';
}

// The tiles in their grid, or $empty when there is none.
function series_tiles_show($list, $empty, $timezone = NULL)
{
	if (count($list) == 0)
	{
		echo '<p class="empty">' . $empty . '</p>';
		return;
	}
	echo '<div class="tiles wide">';
	foreach ($list as $series)
	{
		series_tile_show($series, $timezone);
	}
	echo '</div>';
}

?>
