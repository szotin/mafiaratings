<?php

require_once __DIR__ . '/page_base.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/languages.php';
require_once __DIR__ . '/image.php';
require_once __DIR__ . '/names.php';
require_once __DIR__ . '/league.php';
require_once __DIR__ . '/city.php';
require_once __DIR__ . '/country.php';
require_once __DIR__ . '/user.php';

define('SERIES_OWNER_LEAGUE', 1);
define('SERIES_OWNER_CLUB', 2);

// The kind of competition a series is. Not a column and not a choice: it follows from the start
// and the maximum stars - see series_type() - so there is no stored value that can go stale or
// disagree with what the series actually is.
//
// A season and a series of tournaments are treated identically everywhere for now; the name only
// says what the organizers call it, and what they call it follows from whether the series grades
// its tournaments. A recurring tournament is different: it has no start and no end, and is stored
// with an eternal duration so that every query asking whether a series is over answers no, which
// is the truth about it.
define('SERIES_TYPE_SERIES', 0);
define('SERIES_TYPE_SEASON', 1);
define('SERIES_TYPE_RECURRING', 2);

// A series with no dates at all - infinite in both directions. It starts at the epoch and runs
// for as long as a signed 32 bit duration allows, which is what makes every query asking whether
// it has started answer yes and every query asking whether it is over answer no, with no special
// case anywhere.
//
// Zero cannot collide with a real series: the earliest one on record starts in 2017. And zero
// plus the eternal duration is exactly the largest 32 bit integer, so the sum does not overflow
// the arithmetic of a 32 bit PHP build the way a real start time would.
define('SERIES_INFINITE_START', 0);
define('SERIES_ETERNAL_DURATION', 0x7FFFFFFF);

// Whether this series has no dates. The stored start is the marker, so nothing has to consult the
// type to find out.
function series_is_infinite($start_time)
{
	return (int)$start_time == SERIES_INFINITE_START;
}

// The stage of a series a tournament belongs to, stored on series_tournaments.
//
// Numbered the way tournaments number their own rounds in events.round: zero is the base stage,
// one is the final, two the semifinal, three the quarterfinal. So a bigger number is an EARLIER
// stage, except zero, and the stages of a series with N of them are 0 and 1..N-1 - which is why
// series.stages, a plain count, is all that has to be stored about the ladder.
//
// Formulas see a different number: the position in the order the stages are played, 0 for the
// base stage and +1 for each one after it. That one is computed, never stored.
define('SERIES_STAGE_BASE', 0);
define('SERIES_STAGE_FINAL', 1);
define('SERIES_STAGE_SEMIFINAL', 2);
define('SERIES_STAGE_QUARTERFINAL', 3);

// Base stage plus a ladder down to 1/8. Nobody plays deeper than that - see
// get_series_stages_options() for the ladder as the editor of a series offers it.
define('SERIES_MAX_STAGES', 5);

// A series always has at least the base stage.
function series_stage_count($stages)
{
	$stages = (int)$stages;
	if ($stages < 1)
	{
		return 1;
	}
	return min($stages, SERIES_MAX_STAGES);
}

// What the base stage of a series of this type is called. The rungs above it are the rounds of a
// knockout and borrow the names tournaments give them.
function get_series_base_stage_name($type)
{
	switch ((int)$type)
	{
		case SERIES_TYPE_SERIES:
			return get_label('Qualifier');
		case SERIES_TYPE_SEASON:
			return get_label('Tournament of the season');
	}
	return get_label('Tournament');
}

// The stages of a series with this many of them, each with its label, in the order they are
// played: the base stage, then the deepest rung down to the final.
function get_series_stages($type, $stages)
{
	$stages = series_stage_count($stages);
	$result = array(SERIES_STAGE_BASE => get_series_base_stage_name($type));
	for ($stage = $stages - 1; $stage >= SERIES_STAGE_FINAL; --$stage)
	{
		switch ($stage)
		{
			case SERIES_STAGE_FINAL:
				$result[$stage] = get_label('final');
				break;
			case SERIES_STAGE_SEMIFINAL:
				$result[$stage] = get_label('semi-final');
				break;
			case SERIES_STAGE_QUARTERFINAL:
				$result[$stage] = get_label('quarter-final');
				break;
			default:
				$result[$stage] = get_label('1/[0] final', pow(2, $stage - 1));
				break;
		}
	}
	return $result;
}

// The same list as get_series_stages(), shaped for json: an array of objects in the order the
// stages are played. An object keyed by the stage number would not survive the trip - a json
// object has no order, and these numbers are not in ascending order to begin with.
function series_stage_names($type, $stages)
{
	$result = array();
	foreach (get_series_stages($type, $stages) as $stage => $name)
	{
		$entry = new stdClass();
		$entry->stage = $stage;
		$entry->name = $name;
		$result[] = $entry;
	}
	return $result;
}

function get_series_stage_name($type, $stages, $stage)
{
	$all = get_series_stages($type, $stages);
	$stage = (int)$stage;
	return isset($all[$stage]) ? $all[$stage] : $all[SERIES_STAGE_BASE];
}

// A stage the series does not have falls back to the base one rather than being refused: the
// number of stages can be lowered under a membership that was already stored.
function series_stage($stages, $stage)
{
	$stage = (int)$stage;
	if ($stage < SERIES_STAGE_BASE || $stage >= series_stage_count($stages))
	{
		return SERIES_STAGE_BASE;
	}
	return $stage;
}

// Where this stage sits in the order the stages are played: 0 for the base stage, then +1 for
// each one after it. This is what formulas get, because counting up to the final reads better in
// a formula than counting down from it.
function series_stage_order($stages, $stage)
{
	$stages = series_stage_count($stages);
	$stage = series_stage($stages, $stage);
	if ($stage == SERIES_STAGE_BASE)
	{
		return 0;
	}
	return $stages - $stage;
}

// How deep a ladder is, offered the way an organizer thinks of it rather than as a count of
// rungs: the number of stages is the same thing said in numbers, so the editor of a series picks
// from this and stores the key.
function get_series_stages_options()
{
	$options = array(1 => get_label('no playoff'));
	for ($stages = 2; $stages <= SERIES_MAX_STAGES; ++$stages)
	{
		// The deepest stage this many of them reaches - the one played first after the base stage.
		switch ($stages - 1)
		{
			case SERIES_STAGE_FINAL:
				$options[$stages] = get_label('with a final');
				break;
			case SERIES_STAGE_SEMIFINAL:
				$options[$stages] = get_label('with semi-finals');
				break;
			case SERIES_STAGE_QUARTERFINAL:
				$options[$stages] = get_label('with quarter-finals');
				break;
			default:
				$options[$stages] = get_label('with 1/[0] finals', pow(2, $stages - 2));
				break;
		}
	}
	return $options;
}

// The stage standing at this position in the order the stages are played - the inverse of
// series_stage_order().
function series_stage_by_order($stages, $order)
{
	$stages = series_stage_count($stages);
	$order = (int)$order;
	if ($order <= 0)
	{
		return SERIES_STAGE_BASE;
	}
	if ($order >= $stages)
	{
		$order = $stages - 1;
	}
	return $stages - $order;
}

// What a ranking that leaves out the last $cut_stages stages is called: the series as it stood
// before the stage that was cut away. Zero cuts nothing and is the series itself.
//
// Each depth has a label of its own instead of the stage name being substituted into one common
// phrase, because the languages this is written in decline that name and the phrase would come
// out ungrammatical.
function get_series_cut_name($stages, $cut_stages)
{
	$stages = series_stage_count($stages);
	$cut_stages = (int)$cut_stages;
	if ($cut_stages <= 0)
	{
		return get_label('The whole series');
	}
	$stage = series_stage_by_order($stages, $stages - $cut_stages);
	switch ($stage)
	{
		case SERIES_STAGE_FINAL:
			return get_label('Up to the final');
		case SERIES_STAGE_SEMIFINAL:
			return get_label('Up to the semi-final');
		case SERIES_STAGE_QUARTERFINAL:
			return get_label('Up to the quarter-final');
	}
	return get_label('Up to the 1/[0] final', pow(2, $stage - 1));
}

// The stage one entry of the parent_series json asks for - if whoever sent it is allowed to ask.
//
// Only a manager of the series says which stage a tournament of it belongs to: the ladder is the
// shape of the competition, and a club manager entering their tournament into somebody else's
// series is not shown it at all. What such a caller sends is ignored and $default stands instead:
// the base stage for a new membership, and the stored stage for one that already exists, so that
// saving a tournament does not quietly undo the stage its series manager set.
function series_requested_stage($owner, $series_id, $stages, $entry, $default)
{
	if (isset($entry->stage) && is_series_manager($owner, $series_id))
	{
		return series_stage($stages, $entry->stage);
	}
	return series_stage($stages, $default);
}

// What is played in a series: tournaments, subseries, or both, as two bits of series.flags.
// Never neither - a series that takes no competition at all has nothing to rank - so a row that
// says so reads as tournaments, which is what every series was before the bits existed.
//
// It is a property of the series like its stars and its stages, but unlike those two it says
// nothing about the type: a season usually holds both, a series of tournaments and a recurring
// tournament hold tournaments, and any of them may be set to anything.
function series_plays($flags)
{
	$plays = (int)$flags & SERIES_FLAGS_PLAYS_MASK;
	return $plays == 0 ? SERIES_FLAG_PLAYS_TOURNAMENTS : $plays;
}

function get_series_plays_options()
{
	return array(
		SERIES_FLAG_PLAYS_TOURNAMENTS => get_label('tournaments'),
		SERIES_FLAG_PLAYS_SERIES => get_label('subseries'),
		SERIES_FLAGS_PLAYS_MASK => get_label('tournaments and subseries'));
}

// What one of those bits is called in a refusal, for the one message both operations need.
function get_series_plays_name($kind)
{
	return $kind == SERIES_FLAG_PLAYS_SERIES ? get_label('subseries') : get_label('tournaments');
}

// How many stars a series can hand out to a tournament or a subseries entered into it. Five is
// what every series used before the maximum became a property of the series itself. Zero means
// the series does not grade its tournaments at all.
define('SERIES_MAX_STARS_LIMIT', 10);
define('SERIES_DEFAULT_MAX_STARS', 5);

function series_max_stars($max_stars)
{
	$max_stars = (int)$max_stars;
	if ($max_stars < 0)
	{
		return 0;
	}
	return min($max_stars, SERIES_MAX_STARS_LIMIT);
}

function series_has_stars($max_stars)
{
	return series_max_stars($max_stars) > 0;
}

// The star rating a tournament actually gets for belonging to this series. A series that does not
// grade its tournaments gives none: it says what each tournament is - its stage, or simply that
// the recurring tournament happened again - instead of rating it, and is never asked about stars.
//
// Zero, not one: a single star shows up under the icon in the series tournament list and means
// nothing there, which is what made it confusing. Zero is also not more than one, so such a series
// never picks up the elite flag, which is driven by stars > 1.
//
// One consequence shows up on the next recount: complete_competitions.php takes max(st.stars)
// over all series of a tournament and hands it to the scoring of the tournament itself as the
// value stars. For a tournament whose series all went starless that is now zero, so a scoring
// formula reading stars gives a different answer than before.
function series_membership_stars($max_stars, $stars)
{
	$max_stars = series_max_stars($max_stars);
	if ($max_stars < 1)
	{
		return 0;
	}
	$stars = (int)$stars;
	if ($stars < 1)
	{
		return 1;
	}
	return min($stars, $max_stars);
}

// What a series with these properties is called. The type is not a thing an organizer picks: it
// follows from the three properties that actually matter, and is stored only so that lists can
// filter and sort on it without recomputing it.
//
// A series with no dates is a recurring tournament - the same tournament happening again and
// again, which is exactly what having no dates means. Of the dated ones, the ones that grade
// their tournaments with stars are seasons and the rest are series of tournaments, because a
// series of tournaments describes a tournament by the stage it belongs to instead. The number of
// stages is the third property of a series and does not enter into this: a season can have a
// final, and a series of tournaments can have nothing but qualifiers.
function series_type($start_time, $max_stars)
{
	if (series_is_infinite($start_time))
	{
		return SERIES_TYPE_RECURRING;
	}
	if (!series_has_stars($max_stars))
	{
		return SERIES_TYPE_SERIES;
	}
	return SERIES_TYPE_SEASON;
}

// The same derivation written as SQL, for the queries that select or filter by type. Without a
// stored column there is nothing that can disagree with series_type().
function series_type_condition($type, $series_alias = 's')
{
	$a = $series_alias;
	$dated = $a . '.start_time <> ' . SERIES_INFINITE_START;
	switch ((int)$type)
	{
		case SERIES_TYPE_RECURRING:
			return $a . '.start_time = ' . SERIES_INFINITE_START;

		case SERIES_TYPE_SERIES:
			return $dated . ' AND ' . $a . '.max_stars < 1';
	}
	return $dated . ' AND ' . $a . '.max_stars >= 1';
}

// The types in the order they are offered, each with the label to offer it under. The order is
// the organizers' - most of them think of a season first - and has nothing to do with the values.
function get_series_types()
{
	return array(
		SERIES_TYPE_SEASON => get_label('Season'),
		SERIES_TYPE_SERIES => get_label('Series of tournaments'),
		SERIES_TYPE_RECURRING => get_label('Recurring tournament'));
}

// The shape a series of this type usually has. The type is derived from the three properties, not
// chosen, so this is a convenience for the editor only: picking a type fills the three controls
// in, and the organizer is free to change any of them afterwards - which may well derive a
// different type, and that is the point.
function get_series_type_defaults($type)
{
	switch ((int)$type)
	{
		case SERIES_TYPE_SEASON:
			return array('infinite' => 0, 'max_stars' => SERIES_DEFAULT_MAX_STARS, 'stages' => 1,
				'plays' => SERIES_FLAGS_PLAYS_MASK);
		case SERIES_TYPE_RECURRING:
			return array('infinite' => 1, 'max_stars' => 0, 'stages' => 1,
				'plays' => SERIES_FLAG_PLAYS_TOURNAMENTS);
	}
	return array('infinite' => 0, 'max_stars' => 0, 'stages' => 1,
		'plays' => SERIES_FLAG_PLAYS_TOURNAMENTS);
}

function get_series_type_name($type)
{
	$types = get_series_types();
	$type = (int)$type;
	return isset($types[$type]) ? $types[$type] : $types[SERIES_TYPE_SERIES];
}

function is_series_type_valid($type)
{
	$types = get_series_types();
	return isset($types[(int)$type]);
}

// A <select> of the types, the given one preselected.
function show_series_type_options($type, $on_change = NULL)
{
	echo '<select id="form-type"' . ($on_change == NULL ? '' : ' onchange="' . $on_change . '"') . '>';
	foreach (get_series_types() as $t => $name)
	{
		show_option($t, (int)$type, $name);
	}
	echo '</select>';
}

// What goes under the name of a series in a list. A series with no dates has none to print, so it
// names its kind instead of an invented period - and that kind is what having no dates means, so
// the start alone answers it and the list does not have to select anything else.
function series_period($start_time, $duration, $timezone)
{
	if (series_is_infinite($start_time))
	{
		return get_series_type_name(SERIES_TYPE_RECURRING);
	}
	return format_date_period($start_time, $duration, $timezone);
}

// The condition behind the "Past" tab of a series list, without a leading AND or WHERE - the
// caller supplies whichever it needs.
//
// A series lands there as soon as it has started, which is why one playing right now shows up
// under both tabs: the other tab asks whether the series has ended yet, and a running one answers
// both questions. A series with no dates started before any moment you can name and never ends, so
// it answers both as well, and it belongs here for the same reason a running series does.
//
// It used to be kept out of this tab until it was actually finished. That only made its checkbox in
// the type filter look broken: ticking it changed nothing, because the tab had already thrown the
// series away. Whether such a series is on screen is the filter's business, not this condition's.
function series_past_condition($series_alias = 's')
{
	return $series_alias . '.start_time < UNIX_TIMESTAMP()';
}

// Filtering series lists by type.
//
// The types are mutually exclusive, so the filter is simply the set of types to show - unlike
// the three-state include/exclude filter the other list conditions use. Recurring tournaments
// never end, so they would sit on top of every list forever; they are shown only when asked for.
define('SERIES_TYPE_FILTER_SEASON', 0x1);
define('SERIES_TYPE_FILTER_SERIES', 0x2);
define('SERIES_TYPE_FILTER_RECURRING', 0x4);
define('SERIES_TYPE_FILTER_ALL', 0x7);
define('SERIES_TYPE_FILTER_DEFAULT', SERIES_TYPE_FILTER_SEASON | SERIES_TYPE_FILTER_SERIES);

function series_type_filter_bit($type)
{
	switch ((int)$type)
	{
		case SERIES_TYPE_SEASON:
			return SERIES_TYPE_FILTER_SEASON;
		case SERIES_TYPE_RECURRING:
			return SERIES_TYPE_FILTER_RECURRING;
	}
	return SERIES_TYPE_FILTER_SERIES;
}

// What the checkboxes of a series list are labelled: plural and lowercase, because they filter a
// list of things rather than name one. The editor of a series needs the other spelling - there the
// name stands for the series in front of you.
function get_series_type_filter_names()
{
	return array(
		SERIES_TYPE_SEASON => get_label('seasons'),
		SERIES_TYPE_SERIES => get_label('series of tournaments'),
		SERIES_TYPE_RECURRING => get_label('recurring tournaments'));
}

// Which types a list shows. $default is what it shows before anybody touches the checkboxes, and
// it differs from one list to another: a club wants its recurring tournament in plain sight, while
// a site-wide list would have every one of them standing above the series that do end.
function get_series_type_filter($default = SERIES_TYPE_FILTER_DEFAULT)
{
	if (isset($_REQUEST['types']))
	{
		return (int)$_REQUEST['types'] & SERIES_TYPE_FILTER_ALL;
	}
	return $default & SERIES_TYPE_FILTER_ALL;
}

// The SQL for a type filter on the series aliased as $series_alias. Only the type constants
// reach the query, so there is nothing to parameterize.
function series_type_filter_condition($types, $series_alias = 's')
{
	$types = (int)$types & SERIES_TYPE_FILTER_ALL;
	if ($types == SERIES_TYPE_FILTER_ALL)
	{
		return '';
	}
	$terms = array();
	foreach (get_series_types() as $t => $name)
	{
		if ($types & series_type_filter_bit($t))
		{
			$terms[] = '(' . series_type_condition($t, $series_alias) . ')';
		}
	}
	if (count($terms) == 0)
	{
		// Nothing is checked. Show nothing rather than everything.
		return ' AND FALSE';
	}
	return ' AND (' . implode(' OR ', $terms) . ')';
}

// One checkbox per type, checked when that type is shown. Reloads the page on a change unless
// $on_change names a function to call instead.
function show_series_type_filter($types, $on_change = NULL)
{
	echo get_label('Types') . ':';
	foreach (get_series_type_filter_names() as $t => $name)
	{
		$bit = series_type_filter_bit($t);
		echo ' <input type="checkbox" id="stf' . $bit . '" onclick="stf()"' . (($types & $bit) ? ' checked' : '') . '> ' . $name;
	}
?>
	<script>
		function seriesTypeFilterFlags()
		{
			var types = 0;
<?php
			foreach (get_series_type_filter_names() as $t => $name)
			{
				$bit = series_type_filter_bit($t);
				echo "\t\t\tif ($('#stf" . $bit . "').attr('checked')) types += " . $bit . ";\n";
			}
?>
			return types;
		}

		function stf()
		{
<?php
		if ($on_change == NULL)
		{
			echo "\t\t\tgoTo({types: seriesTypeFilterFlags(), page: 0});\n";
		}
		else
		{
			echo "\t\t\t" . $on_change . "();\n";
		}
?>
		}
	</script>
<?php
}

// A club may run a tournament in a series when it owns the series, when it belongs to the league
// owning it, or when the series has invited it. Returns the SQL condition for the series aliased
// as $series_alias, without a leading AND.
//
// Three sources, one rule, and no permission over the series comes with any of them: an invited
// club can enter its tournaments and nothing more.
function series_club_condition($club_id, $series_alias = 's')
{
	$a = $series_alias;
	$club_id = (int)$club_id;
	return '(' . $a . '.club_id = ' . $club_id .
		' OR ' . $a . '.league_id IN (SELECT league_id FROM league_clubs WHERE club_id = ' . $club_id . ')' .
		' OR ' . $a . '.id IN (SELECT series_id FROM series_clubs WHERE club_id = ' . $club_id . '))';
}

// Who may enter a tournament or a subseries into a series. An ordinary series takes them from
// any club that can reach it - see series_club_condition() above. A private one takes them only
// from the people who run it: a manager of the league or of the club it belongs to, a manager of
// the series itself, or an admin. Being private hides nothing - the series is still listed, still
// has standings, and anybody may look at it; what it does not take is entries.
//
// Returns the SQL condition for the series aliased as $series_alias, without a leading AND, for
// whoever is logged in.
function series_entry_condition($series_alias = 's')
{
	global $_profile;

	$a = $series_alias;
	$open = '(' . $a . '.flags & ' . SERIES_FLAG_PRIVATE . ') = 0';
	if ($_profile == NULL)
	{
		return '(' . $open . ')';
	}
	if ($_profile->is_admin())
	{
		return '(TRUE)';
	}

	// The clubs are in the profile already. The other two sources are a table lookup each, and a
	// list query cannot do one per row, so they go in as subqueries.
	$mine = array();
	$clubs = array();
	foreach ($_profile->clubs as $club_id => $club)
	{
		if (($club->flags & USER_PERM_MANAGER) != 0)
		{
			$clubs[] = (int)$club_id;
		}
	}
	if (count($clubs) > 0)
	{
		$mine[] = $a . '.club_id IN (' . implode(',', $clubs) . ')';
	}
	$user_id = (int)$_profile->user_id;
	$mine[] = $a . '.league_id IN (SELECT league_id FROM league_managers WHERE user_id = ' . $user_id . ')';
	$mine[] = $a . '.id IN (SELECT series_id FROM series_regs WHERE user_id = ' . $user_id .
		' AND (flags & ' . USER_PERM_MANAGER . ') <> 0)';

	return '(' . $open . ' OR ' . implode(' OR ', $mine) . ')';
}

// The same question about one series, for the operations that write the membership. Whoever this
// says no to can neither add an entry nor remove one: a save by somebody else leaves the links
// they cannot touch exactly as they were.
function can_enter_series($flags, $owner, $series_id)
{
	return ($flags & SERIES_FLAG_PRIVATE) == 0 || is_series_manager($owner, $series_id);
}

// The clubs of a series that cannot be taken off it: the owning club, or every club of the owning
// league. Keyed by club id so a page can tell them from the invited ones.
function get_series_permanent_clubs($owner)
{
	$clubs = array();
	if ($owner->is_league())
	{
		$query = new DbQuery('SELECT club_id FROM league_clubs WHERE league_id = ?', $owner->league_id);
		while ($row = $query->next())
		{
			$clubs[(int)$row[0]] = true;
		}
	}
	else
	{
		$clubs[$owner->club_id] = true;
	}
	return $clubs;
}

// A series belongs either to a league or to a club - exactly one of series.league_id and
// series.club_id is set. SeriesOwner hides which one it is from the code that only wants to
// show the owner or to find out who is allowed to manage the series.
class SeriesOwner
{
	public $kind;
	public $id;
	public $name;
	public $flags;
	public $league_id; // 0 when the series belongs to a club
	public $club_id;   // 0 when the series belongs to a league

	function __construct($league_id, $club_id)
	{
		if (!is_null($league_id) && (int)$league_id > 0)
		{
			$this->kind = SERIES_OWNER_LEAGUE;
			$this->league_id = (int)$league_id;
			$this->club_id = 0;
		}
		else
		{
			$this->kind = SERIES_OWNER_CLUB;
			$this->league_id = 0;
			$this->club_id = (int)$club_id;
		}
		$this->id = $this->league_id + $this->club_id;
		$this->name = NULL;
		$this->flags = 0;
	}

	// Builds an owner from the columns series_owner_fields() selects, in that order.
	public static function from_row($league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags)
	{
		$owner = new SeriesOwner($league_id, $club_id);
		if ($owner->kind == SERIES_OWNER_LEAGUE)
		{
			$owner->name = $league_name;
			$owner->flags = (int)$league_flags;
		}
		else
		{
			$owner->name = $club_name;
			$owner->flags = (int)$club_flags;
		}
		return $owner;
	}

	public function is_league()
	{
		return $this->kind == SERIES_OWNER_LEAGUE;
	}

	// Leagues and clubs mark themselves closed with their own flag. Check each against its own
	// constant rather than against whichever one happens to be in scope - the two are separate
	// bit sets and nothing guarantees they keep the same value.
	public function is_closed()
	{
		if ($this->kind == SERIES_OWNER_LEAGUE)
		{
			return ($this->flags & LEAGUE_FLAG_CLOSED) != 0;
		}
		return ($this->flags & CLUB_FLAG_CLOSED) != 0;
	}

	public function picture()
	{
		$pic = new Picture($this->kind == SERIES_OWNER_LEAGUE ? LEAGUE_PICTURE : CLUB_PICTURE);
		$pic->set($this->id, $this->name, $this->flags);
		return $pic;
	}

	public function url()
	{
		if ($this->kind == SERIES_OWNER_LEAGUE)
		{
			return 'league_main.php?bck=1&id=' . $this->id;
		}
		return 'club_main.php?bck=1&id=' . $this->id;
	}
}

// Joins both possible owners of the series aliased as $series_alias. The owner that is not set
// on the row comes back as NULLs, which is why both joins have to be outer ones.
function series_owner_join($series_alias = 's')
{
	return
		' LEFT OUTER JOIN leagues sol ON sol.id = ' . $series_alias . '.league_id' .
		' LEFT OUTER JOIN clubs soc ON soc.id = ' . $series_alias . '.club_id';
}

// The six columns SeriesOwner::from_row() expects, in order.
function series_owner_fields()
{
	return 'sol.id, sol.name, sol.flags, soc.id, soc.name, soc.flags';
}

// Series icons fall back to the icon of their owner, and the fallback picture type differs
// between a league and a club. One chain per owner kind is kept so that a list mixing the two
// can still reuse the same object.
class SeriesPicture
{
	private $pics;

	function __construct()
	{
		$this->pics = array(
			SERIES_OWNER_LEAGUE => new Picture(SERIES_PICTURE, new Picture(LEAGUE_PICTURE)),
			SERIES_OWNER_CLUB => new Picture(SERIES_PICTURE, new Picture(CLUB_PICTURE)));
	}

	// Returns a Picture ready to show() - the series icon, falling back to its owner's icon.
	public function set($id, $name, $flags, $owner)
	{
		$pic = $this->pics[$owner->kind];
		$pic->set($id, $name, $flags)->set($owner->id, $owner->name, $owner->flags);
		return $pic;
	}
}

// The ids line up with the order is_permitted() reads them in: club, then league, then series.
// The owner that is not set is 0, which never matches anything, so one call covers both kinds.
function is_series_manager($owner, $series_id)
{
	return is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, $owner->club_id, $owner->league_id, $series_id);
}

function check_series_permissions($owner, $series_id)
{
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, $owner->club_id, $owner->league_id, $series_id);
}

function show_series_buttons($id, $start_time, $duration, $flags, $owner)
{
	global $_profile;

	$now = time();
	if (!$owner->is_closed() && is_series_manager($owner, $id))
	{
		echo '<button class="icon" onclick="mr.editSeries(' . $id . ')" title="' . get_label('Edit the series') . '"><img src="images/edit.png" border="0"></button>';
		if ($start_time >= $now)
		{
			if (($flags & SERIES_FLAG_CANCELED) != 0)
			{
				echo '<button class="icon" onclick="mr.restoreSeries(' . $id . ')"><img src="images/undelete.png" border="0"></button>';
			}
			else
			{
				echo '<button class="icon" onclick="mr.cancelSeries(' . $id . ', \'' . get_label('Are you sure you want to cancel the series?') . '\')" title="' . get_label('Cancel the series') . '"><img src="images/delete.png" border="0"></button>';
			}
		}
		// Finishing a series means cutting it short of the end it was given. A series with no dates
		// was never given one, so there is nothing to cut and nothing to finish - it simply runs.
		if (($flags & SERIES_FLAG_FINISHED) == 0 && !series_is_infinite($start_time) && $now < $start_time + $duration)
		{
			echo '<button class="icon" onclick="mr.finishSeries(' . $id . ', \'' . get_label('Are you sure you want to finish the series?') . '\', \'' . get_label('The series is finished. Results will be applyed to series within one hour') . '\')" title="' . get_label('Finish the series') . '"><img src="images/time.png" border="0"></button>';
		}
	}
}

function get_subseries_csv($series_id)
{
	$series = array();
	while (true)
	{
		$continue = false;
		$csv = $series_id;
		foreach ($series as $sid => $v)
		{
			$csv .= ',' . $sid;
		}
		
		$old_count = count($series);
		$query = new DbQuery('SELECT child_id FROM series_series WHERE parent_id IN (' . $csv . ')');
		while ($row = $query->next())
		{
			list($sid) = $row;
			$series[$sid] = 0;
		}
		
		if ($old_count == count($series))
		{
			return $csv;
		}
	}
}

class SeriesPageBase extends PageBase
{
	protected $id;
	protected $name;
	protected $owner;
	protected $owner_pic;
	protected $parent_series;
	protected $start_time;
	protected $duration;
	protected $max_stars;
	protected $stages;
	// Derived from the start and the maximum stars, not read from the database. See series_type().
	protected $type;
	protected $langs;
	protected $notes;
	protected $flags;
	protected $timezone;
	protected $gaining_id;
	protected $gaining_version;
	
	protected function prepare()
	{
		global $_profile;
		
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('sеriеs')));
		}
		$this->id = (int)$_REQUEST['id'];
		
		$this->timezone = get_timezone();
		list(
			$this->name, $league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags,
			$this->start_time, $this->duration, $this->max_stars, $this->stages, $this->langs, $this->notes, $this->flags, $this->rules, $this->gaining_id, $this->gaining_version) =
		Db::record(
			get_label('sеriеs'),
			'SELECT s.name, ' . series_owner_fields() . ', s.start_time, s.duration, s.max_stars, s.stages, s.langs, s.notes, s.flags, s.rules, s.gaining_id, s.gaining_version FROM series s' .
				series_owner_join() .
				' WHERE s.id = ?',
			$this->id);
		$this->max_stars = series_max_stars($this->max_stars);
		$this->stages = series_stage_count($this->stages);
		$this->type = series_type($this->start_time, $this->max_stars);
		$this->owner = SeriesOwner::from_row($league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags);
		$this->owner_pic = $this->owner->picture();

		// The series this one feeds into. Their logos go next to the owner's in the page header.
		$this->parent_series = array();
		$query = new DbQuery(
			'SELECT s.id, s.name, s.flags, ' . series_owner_fields() .
			' FROM series_series ss' .
			' JOIN series s ON s.id = ss.parent_id' .
			series_owner_join() .
			' WHERE ss.child_id = ? ORDER BY s.start_time, s.id', $this->id);
		while ($row = $query->next())
		{
			$s = new stdClass();
			list($s->id, $s->name, $s->flags, $p_league_id, $p_league_name, $p_league_flags, $p_club_id, $p_club_name, $p_club_flags) = $row;
			$s->owner = SeriesOwner::from_row($p_league_id, $p_league_name, $p_league_flags, $p_club_id, $p_club_name, $p_club_flags);
			$this->parent_series[] = $s;
		}
	}
	
	protected function show_title()
	{
		if ($this->hasError())
		{
			trigger_error('Exception: ' . $this->getErrorMessage(), E_USER_ERROR);
			parent::show_title();
			return;
		}
		echo '<table class="head" width="100%">';

		$is_manager = is_series_manager($this->owner, $this->id);
		$menu = array
		(
			new MenuItem('series_info.php?id=' . $this->id, get_label('Sеriеs '), get_label('General series information')),
			new MenuItem('series_standings.php?id=' . $this->id, get_label('Standings'), get_label('Series standings')),
			new MenuItem('series_series.php?id=' . $this->id, get_label('Subseries'), get_label('Subseries of this series')),
			new MenuItem('series_tournaments.php?id=' . $this->id, get_label('Tournaments'), get_label('Tournaments of this series')),
			new MenuItem('series_games.php?id=' . $this->id, get_label('Games'), get_label('Games list of the series')),
			new MenuItem('#stats', get_label('Reports'), NULL, array
			(
				new MenuItem('series_stats.php?id=' . $this->id, get_label('General stats'), get_label('General statistics. How many games played, mafia winning percentage, how many players, etc.', PRODUCT_NAME)),
				new MenuItem('series_by_numbers.php?id=' . $this->id, get_label('By numbers'), get_label('Statistics by table numbers. What is the most winning number, or what number is shot more often.')),
				new MenuItem('series_nominations.php?id=' . $this->id, get_label('Nomination winners'), get_label('Custom nomination winners. For example who had most warnings, or who was checked by sheriff most often.')),
				new MenuItem('series_referees.php?id=' . $this->id, get_label('Referees'), get_label('Statistics of the series referees')),
				new MenuItem('series_competition.php?id=' . $this->id, get_label('Competition chart'), get_label('How players were competing on this series.')),
			)),
			new MenuItem('#resources', get_label('Resources'), NULL, array
			(
				new MenuItem('series_rules.php?id=' . $this->id, get_label('Rulebook'), get_label('Rules of the game in [0]', $this->name)),
				new MenuItem('series_albums.php?id=' . $this->id, get_label('Photos'), get_label('Series photo albums')),
				new MenuItem('series_videos.php?id=' . $this->id, get_label('Videos'), get_label('Videos from the series.')),
				// new MenuItem('series_tasks.php?id=' . $this->id, get_label('Tasks'), get_label('Learning tasks and puzzles.')),
				// new MenuItem('series_articles.php?id=' . $this->id, get_label('Articles'), get_label('Books and articles.')),
				// new MenuItem('series_links.php?id=' . $this->id, get_label('Links'), get_label('Links to custom mafia web sites.')),
			)),
		);
		if ($is_manager)
		{
			$manager_menu = array
			(
				// Also a button on the clubs page, where the owner is in front of you. Here is
				// where a manager looks for it.
				new MenuItem('javascript:mr.transferSeries(' . $this->id . ')', get_label('Transfer the series'), get_label('Give the series to another club or league.')),
				new MenuItem('series_clubs.php?id=' . $this->id, get_label('Clubs'), get_label('Clubs allowed to enter their tournaments into [0]', $this->name)),
				new MenuItem(null, null, null),
				new MenuItem('series_finance.php?id=' . $this->id, get_label('Financial report'), get_label('Financial report of the [0]', $this->name)),
				new MenuItem(null, null, null),
				new MenuItem('series_extra_points.php?id=' . $this->id, get_label('Extra points'), get_label('Add/remove extra points for players of [0]', $this->name)),
			);
			$menu[] = new MenuItem('#management', get_label('Management'), NULL, $manager_menu);
		}
		
		echo '<tr><td colspan="4">';
		PageBase::show_menu($menu);
		echo '</td></tr>';
		
		echo '<tr><td rowspan="2" valign="top" align="left" width="1">';
		echo '<table class="bordered ';
		if (($this->flags & SERIES_FLAG_CANCELED) != 0)
		{
			echo 'dark';
		}
		else
		{
			echo 'light';
		}
		echo '"><tr><td width="1" valign="top" style="padding:4px;" class="dark">';
		show_series_buttons(
			$this->id,
			$this->start_time,
			$this->duration,
			$this->flags,
			$this->owner);
		echo '</td><td width="' . ICON_WIDTH . '" style="padding: 4px;">';
		$series_pic = new Picture(SERIES_PICTURE);
		$series_pic->set($this->id, $this->name, $this->flags);
		$series_pic->show(TNAILS_DIR, false);
		echo '</td></tr></table></td>';
		
		echo '<td rowspan="2" valign="top"><h2 class="series">' . $this->name . '</h2><br><h3>' . $this->_title;
		$time = time();
		echo '</h3><p class="subtitle">' . series_period($this->start_time, $this->duration, $this->timezone) . '</p>';
		// A series with no dates is always under way, which is what series_period() already says.
		if (($this->flags & SERIES_FLAG_FINISHED) == 0 && !series_is_infinite($this->start_time))
		{
			echo '<p class="subtitle"><i>(';
			if ($this->start_time < $time)
			{
				echo get_label('playing now');
			}
			else
			{
				echo get_label('not started yet');
			}
			echo ')</i></p>';
		}
		echo '</td>';
		
		echo '<td valign="top" align="right">';
		show_back_button();
		echo '</td></tr><tr><td align="right" valign="bottom"><table><tr>';

		// The series this one is a part of, then the league or the club running it.
		$parent_pic = new SeriesPicture();
		foreach ($this->parent_series as $s)
		{
			echo '<td align="center" width="64">';
			$pic = $parent_pic->set($s->id, $s->name, $s->flags, $s->owner);
			// "<its league or club>: <parent series>". Set explicitly because the title Picture
			// builds from the fallback chain puts the two the other way round.
			$pic->custom_title = $s->name;
			if (!is_null($s->owner->name) && $s->owner->name != $s->name)
			{
				$pic->custom_title = $s->owner->name . ': ' . $s->name;
			}
			$pic->show(ICONS_DIR, true, 48);
			echo '</td>';
		}

		echo '<td align="center">';
		$this->owner_pic->show(ICONS_DIR, true, 48);
		echo '</td>';

		echo '</tr></table></td></tr>';
		
		echo '</table>';
	}
}

?>