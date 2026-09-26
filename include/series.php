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
		if (($flags & SERIES_FLAG_FINISHED) == 0 && $now < $start_time + $duration)
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
	protected $langs;
	protected $notes;
	protected $flags;
	protected $timezone;
	protected $gaining_id;
	protected $gaining_version;
	protected $finals_id;
	
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
			$this->start_time, $this->duration, $this->langs, $this->notes, $this->flags, $this->rules, $this->gaining_id, $this->gaining_version, $this->finals_id) =
		Db::record(
			get_label('sеriеs'),
			'SELECT s.name, ' . series_owner_fields() . ', s.start_time, s.duration, s.langs, s.notes, s.flags, s.rules, s.gaining_id, s.gaining_version, s.finals_id FROM series s' .
				series_owner_join() .
				' WHERE s.id = ?',
			$this->id);
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
				new MenuItem('series_finance.php?id=' . $this->id, get_label('Financial report'), get_label('Financial report of the [0]', $this->name)),
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
		echo '</h3><p class="subtitle">' . format_date_period($this->start_time, $this->duration, $this->timezone) . '</p>';
		if (($this->flags & SERIES_FLAG_FINISHED) == 0)
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