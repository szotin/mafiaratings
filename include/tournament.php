<?php

require_once __DIR__ . '/page_base.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/languages.php';
require_once __DIR__ . '/image.php';
require_once __DIR__ . '/names.php';
require_once __DIR__ . '/address.php';
require_once __DIR__ . '/club.php';
require_once __DIR__ . '/city.php';
require_once __DIR__ . '/country.php';
require_once __DIR__ . '/user.php';

define('TOURNAMENT_TYPE_CUSTOM', 0);
define('TOURNAMENT_TYPE_FIIM_ONE_ROUND', 1);
define('TOURNAMENT_TYPE_FIIM_TWO_ROUNDS_FINALS3', 2);
define('TOURNAMENT_TYPE_FIIM_TWO_ROUNDS_FINALS4', 3);
define('TOURNAMENT_TYPE_FIIM_THREE_ROUNDS_FINALS3', 4);
define('TOURNAMENT_TYPE_FIIM_THREE_ROUNDS_FINALS4', 5);
define('TOURNAMENT_TYPE_AML_ONE_ROUND', 6);
define('TOURNAMENT_TYPE_AML_TWO_ROUNDS', 7);
define('TOURNAMENT_TYPE_AML_THREE_ROUNDS', 8);
define('TOURNAMENT_TYPE_CHAMPIONSHIP', 9);
define('TOURNAMENT_TYPE_SERIES', 10);

function show_tournament_buttons($id, $start_time, $duration, $flags, $club_id, $club_flags, $is_manager = NULL)
{
	global $_profile;
	
	if ($is_manager === NULL)
	{
		$is_manager = is_permitted(PERMISSION_TOURNAMENT_MANAGER, $id);
	}

	$now = time();
	if ($is_manager && ($club_flags & CLUB_FLAG_RETIRED) == 0)
	{
		echo '<button class="icon" onclick="mr.editTournament(' . $id . ')" title="' . get_label('Edit the tournament') . '"><img src="images/edit.png" border="0"></button>';
		if ($start_time >= $now)
		{
			if (($flags & TOURNAMENT_FLAG_CANCELED) != 0)
			{
				echo '<button class="icon" onclick="mr.restoreTournament(' . $id . ')"><img src="images/undelete.png" border="0"></button>';
			}
			else
			{
				echo '<button class="icon" onclick="mr.cancelTournament(' . $id . ', \'' . get_label('Are you sure you want to cancel the tournament?') . '\')" title="' . get_label('Cancel the tournament') . '"><img src="images/delete.png" border="0"></button>';
			}
		}
		if (($flags & TOURNAMENT_FLAG_FINISHED) == 0 && $now < $start_time + $duration)
		{
			echo '<button class="icon" onclick="mr.finishTournament(' . $id . ', \'' . get_label('Are you sure you want to finish the tournament?') . '\', \'' . get_label('The tournament is finished. Results will be applyed to series within one hour') . '\')" title="' . get_label('Finish the tournament') . '"><img src="images/time.png" border="0"></button>';
		}
	}
	echo '<button class="icon" onclick="window.open(\'tournament_screen.php?id=' . $id . '\' ,\'_blank\')" title="' . get_label('Open interactive standings page') . '"><img src="images/details.png" border="0"></button>';
}


class TournamentPageBase extends PageBase
{
	protected $id;
	protected $name;
	protected $club_id;
	protected $club_name;
	protected $club_flags;
	protected $address_id;
	protected $address_name;
	protected $address;
	protected $address_url;
	protected $address_flags;
	protected $city_id;
	protected $city_name;
	protected $country_id;
	protected $country_name;
	protected $timezone;
	protected $start_time;
	protected $duration;
	protected $langs;
	protected $notes;
	protected $price;
	protected $scoring_id;
	protected $scoring_version;
	protected $scoring_options;
	protected $normalizer_id;
	protected $normalizer_version;
	protected $rules_code;
	protected $flags;
	protected $series;
	
	protected function prepare()
	{
		global $_lang, $_profile;
		
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('tournament')));
		}
		$this->id = (int)$_REQUEST['id'];
		list(
			$this->name, $this->club_id, $this->club_name, $this->club_flags, 
			$this->address_id, $this->address_name, $this->address, $this->address_url, $this->address_flags,
			$this->city_id, $this->city_name, $this->country_id, $this->country_name, $this->timezone,
			$this->start_time, $this->duration, $this->langs, $this->notes, $this->price, 
			$this->scoring_id, $this->scoring_version, $this->normalizer_id, $this->normalizer_version, $this->scoring_options, $this->rules_code, $this->flags) =
		Db::record(
			get_label('tournament'),
			'SELECT t.name, c.id, c.name, c.flags,' . 
				' a.id, a.name, a.address, a.map_url, a.flags,' . 
				' ct.id, nct.name, cr.id, ncr.name, ct.timezone,' . 
				' t.start_time, t.duration, t.langs, t.notes, t.price, t.scoring_id, t.scoring_version, t.normalizer_id, t.normalizer_version, t.scoring_options, t.rules, t.flags' .
				' FROM tournaments t' .
				' JOIN clubs c ON c.id = t.club_id' .
				' JOIN addresses a ON a.id = t.address_id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' JOIN countries cr ON cr.id = ct.country_id' .
				' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & ?) <> 0' .
				' JOIN names ncr ON ncr.id = cr.name_id AND (ncr.langs & ?) <> 0' .
				' WHERE t.id = ?',
			$_lang, $_lang, $this->id);
			
		$this->series = array();
		$query = new DbQuery('SELECT s.id, s.name, s.flags, st.stars, l.id, l.name, l.flags FROM series_tournaments st JOIN series s ON s.id = st.series_id JOIN leagues l ON l.id = s.league_id WHERE st.tournament_id = ?', $this->id);
		while ($row = $query->next())
		{
			$s = new stdClass();
			list($s->series_id, $s->series_name, $s->series_flags, $s->stars, $s->league_id, $s->league_name, $s->league_flags) = $row;
			$this->series[] = $s;
		}
	}
	
	protected function show_title()
	{
		echo '<table class="head" width="100%">';

		$is_manager = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $this->club_id, $this->id);
		$menu = array
		(
			new MenuItem('tournament_info.php?id=' . $this->id, get_label('Tournament'), get_label('General tournament information')),
			new MenuItem('tournament_rounds.php?id=' . $this->id, get_label('Rounds'), get_label('Tournament rounds')),
			new MenuItem('tournament_standings.php?id=' . $this->id, get_label('Standings'), get_label('Tournament standings')),
			new MenuItem('tournament_competition.php?id=' . $this->id, get_label('Competition chart'), get_label('How players were competing on this tournament.')),
			new MenuItem('tournament_games.php?id=' . $this->id, get_label('Games'), get_label('Games list of the tournament')),
			new MenuItem('#stats', get_label('Reports'), NULL, array
			(
				new MenuItem('tournament_stats.php?id=' . $this->id, get_label('General stats'), get_label('General statistics. How many games played, mafia winning percentage, how many players, etc.', PRODUCT_NAME)),
				new MenuItem('tournament_by_numbers.php?id=' . $this->id, get_label('By numbers'), get_label('Statistics by table numbers. What is the most winning number, or what number is shot more often.')),
				new MenuItem('tournament_nominations.php?id=' . $this->id, get_label('Nomination winners'), get_label('Custom nomination winners. For example who had most warnings, or who was checked by sheriff most often.')),
				new MenuItem('tournament_referees.php?id=' . $this->id, get_label('Referees'), get_label('Statistics of the tournament referees')),
				new MenuItem('tournament_fiim_form.php?tournament_id=' . $this->id, get_label('FIIM'), get_label('PDF report for sending to FIIM Mafia World Tour'), NULL, true),
			)),
			new MenuItem('#resources', get_label('Resources'), NULL, array
			(
				new MenuItem('tournament_rules.php?id=' . $this->id, get_label('Rulebook'), get_label('Rules of the game in [0]', $this->name)),
				new MenuItem('tournament_albums.php?id=' . $this->id, get_label('Photos'), get_label('Tournament photo albums')),
				new MenuItem('tournament_videos.php?id=' . $this->id, get_label('Videos'), get_label('Videos from the tournament.')),
				// new MenuItem('tournament_tasks.php?id=' . $this->id, get_label('Tasks'), get_label('Learning tasks and puzzles.')),
				// new MenuItem('tournament_articles.php?id=' . $this->id, get_label('Articles'), get_label('Books and articles.')),
				// new MenuItem('tournament_links.php?id=' . $this->id, get_label('Links'), get_label('Links to custom mafia web sites.')),
			)),
		);
		if ($is_manager)
		{
			$manager_menu = array
			(
				new MenuItem('tournament_users.php?id=' . $this->id, get_label('Registrations'), get_label('Manage registrations for [0]', $this->name)),
				new MenuItem('tournament_extra_points.php?id=' . $this->id, get_label('Extra points'), get_label('Add/remove extra points for players of [0]', $this->name)),
				new MenuItem('tournament_standings_edit.php?id=' . $this->id, get_label('Edit standings'), get_label('You can edit tournament standings manually. These stanings will count for series even if there is no information about the specific games.')),
				new MenuItem('javascript:mr.tournamentObs(' . $this->id . ')', get_label('OBS Studio integration'), get_label('Instructions how to add game informaton to OBS Studio.')),
			);
			$menu[] = new MenuItem('#management', get_label('Management'), NULL, $manager_menu);
		}
		
		echo '<tr><td colspan="4">';
		PageBase::show_menu($menu);
		echo '</td></tr>';
		
		echo '<tr><td rowspan="2" valign="top" align="left" width="1">';
		echo '<table class="bordered ';
		if (($this->flags & TOURNAMENT_FLAG_CANCELED) != 0)
		{
			echo 'dark';
		}
		else
		{
			echo 'light';
		}
		echo '"><tr><td width="1" valign="top" style="padding:4px;" class="dark">';
		show_tournament_buttons(
			$this->id,
			$this->start_time,
			$this->duration,
			$this->flags,
			$this->club_id,
			$this->club_flags);
		echo '</td><td width="' . ICON_WIDTH . '" style="padding: 4px;">';
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$tournament_pic->set($this->id, $this->name, $this->flags);
		if ($this->address_url != '')
		{
			echo '<a href="address_info.php?bck=1&id=' . $this->address_id . '">';
			$tournament_pic->show(TNAILS_DIR, false);
			echo '</a>';
		}
		else
		{
			$tournament_pic->show(TNAILS_DIR, false);
		}
		echo '</td></tr></table></td>';
		
		echo '<td rowspan="2" valign="top"><h2 class="tournament">' . $this->name . '</h2><br><h3>' . $this->_title;
		$time = time();
		echo '</h3><p class="subtitle">' . format_date('l, F d, Y, H:i', $this->start_time, $this->timezone) . '</p>';
		if (!empty($this->price))
		{
			echo '<p class="subtitle"><b>' . get_label('Participation fee: [0]', $this->price) . '</b></p>';
		}
		echo '</td>';
		
		echo '<td valign="top" align="right">';
		show_back_button();
		echo '</td></tr><tr><td align="right" valign="bottom"><table><tr><td align="center">';
		
		$this->club_pic->set($this->club_id, $this->club_name, $this->club_flags);
		$this->club_pic->show(ICONS_DIR, true, 54);
		
		if (count($this->series) > 0)
		{
			$series_pic = new Picture(SERIES_PICTURE, new Picture(LEAGUE_PICTURE));
			foreach ($this->series as $s)
			{
				echo '<td align="center" width="64">';
				$series_pic->set($s->series_id, $s->series_name, $s->series_flags)->set($s->league_id, $s->league_name, $s->league_flags);
				$series_pic->show(ICONS_DIR, true, 42);
				echo '<br><font style="color:#B8860B; font-size:12px;">' . tournament_stars_str($s->stars) . '</font>';
				// for ($i = 0; $i < floor($s->stars); ++$i)
				// {
					// echo '<img src="images/star.png" width="12">';
				// }
				// for (; $i < $s->stars; ++$i)
				// {
					// echo '<img src="images/star-half.png" width="12">';
				// }
				// for (; $i < 5; ++$i)
				// {
					// echo '<img src="images/star-empty.png" width="12">';
				// }
				echo '</td>';
			}
		}
		echo '</td></tr></table></td></tr>';
		
		echo '</table>';
	}
}

function tournament_stars_str($stars, $max_stars = 5)
{
	$stars_str = '';
	for ($i = 0; $i < floor($stars) && $i < $max_stars; ++$i)
	{
		$stars_str .= '★';
	}
	for (; $i < $stars && $i < $max_stars; ++$i)
	{
		$stars_str .= '✯';
	}
//	for (; $i < $max_stars; ++$i)
//	{
//		$stars_str .= '☆';
//	}
	return $stars_str;
}

?>