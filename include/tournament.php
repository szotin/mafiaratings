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
require_once __DIR__ . '/currency.php';

define('TOURNAMENT_TYPE_CUSTOM', 0);
define('TOURNAMENT_TYPE_FIIM_ONE_ROUND', 1);
define('TOURNAMENT_TYPE_FIIM_TWO_ROUNDS_FINALS3', 2);
define('TOURNAMENT_TYPE_FIIM_TWO_ROUNDS_FINALS4', 3);
define('TOURNAMENT_TYPE_FIIM_THREE_ROUNDS_FINALS3', 4);
define('TOURNAMENT_TYPE_FIIM_THREE_ROUNDS_FINALS4', 5);
define('TOURNAMENT_TYPE_CHAMPIONSHIP', 6);

function show_tournament_buttons($id, $start_time, $duration, $flags, $club_id, $club_flags, $is_manager = NULL, $tournament_page = false)
{
	global $_profile;
	
	if ($is_manager === NULL)
	{
		$is_manager = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $id);
	}

	$now = time();
	if ($is_manager && ($club_flags & CLUB_FLAG_RETIRED) == 0)
	{
		if ($tournament_page)
		{
			$back_url = get_back_page();
			if (empty($back_url))
			{
				$back_url = 'tournaments.php';
			}
			$back_url = '\''.$back_url.'\'';
		}
		else
		{
			$back_url = 'undefined';
		}
		
		echo '<button class="icon" onclick="mr.editTournament(' . $id . ')" title="' . get_label('Edit the tournament') . '"><img src="images/edit.png" border="0"></button>';
		if (($flags & TOURNAMENT_FLAG_CANCELED) != 0)
		{
			echo '<button class="icon" onclick="mr.restoreTournament(' . $id . ')"><img src="images/undelete.png" border="0"></button>';
		} 
		echo '<button class="icon" onclick="mr.deleteTournament(' . $id . ', ' . $back_url . ')" title="' . get_label('Delete the tournament') . '"><img src="images/delete.png" border="0"></button>';
		
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
	protected $fee;
	protected $currency_id;
	protected $currency_pattern;
	protected $scoring_id;
	protected $scoring_version;
	protected $scoring_options;
	protected $normalizer_id;
	protected $normalizer_version;
	protected $rules_code;
	protected $flags;
	protected $mwt_id;
	protected $series;
	protected $num_players;
	
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
			$this->start_time, $this->duration, $this->langs, $this->notes, $this->fee, $this->currency_id, $this->currency_pattern,
			$this->scoring_id, $this->scoring_version, $this->normalizer_id, $this->normalizer_version, $this->scoring_options, 
			$this->rules_code, $this->flags, $this->mwt_id, $this->num_players) =
		Db::record(
			get_label('tournament'),
			'SELECT t.name, c.id, c.name, c.flags,' . 
				' a.id, a.name, a.address, a.map_url, a.flags,' . 
				' ct.id, nct.name, cr.id, ncr.name, ct.timezone,' . 
				' t.start_time, t.duration, t.langs, t.notes, t.fee, t.currency_id, cu.pattern,'.
				' t.scoring_id, t.scoring_version, t.normalizer_id, t.normalizer_version, t.scoring_options,'.
				' t.rules, t.flags, t.mwt_id, t.num_players' .
				' FROM tournaments t' .
				' JOIN clubs c ON c.id = t.club_id' .
				' JOIN addresses a ON a.id = t.address_id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' JOIN countries cr ON cr.id = ct.country_id' .
				' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0' .
				' JOIN names ncr ON ncr.id = cr.name_id AND (ncr.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN currencies cu ON cu.id = t.currency_id' .
				' WHERE t.id = ?',
			$this->id);
			
		$this->series = array();
		$query = new DbQuery('SELECT s.id, s.name, s.flags, st.stars, l.id, l.name, l.flags FROM series_tournaments st JOIN series s ON s.id = st.series_id JOIN leagues l ON l.id = s.league_id WHERE st.tournament_id = ?', $this->id);
		while ($row = $query->next())
		{
			$s = new stdClass();
			list($s->series_id, $s->series_name, $s->series_flags, $s->stars, $s->league_id, $s->league_name, $s->league_flags) = $row;
			$this->series[] = $s;
		}
		
		$this->is_manager = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $this->club_id, $this->id);
		if ($this->is_manager && isset($_REQUEST['show_all']))
		{
			$this->show_all = '&show_all';
			$this->flags &= ~(TOURNAMENT_HIDE_TABLE_MASK | TOURNAMENT_HIDE_BONUS_MASK);
		}
		else
		{
			$this->show_all = '';
		}
	}
	
	protected function show_title()
	{
		echo '<table class="head" width="100%">';

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
		if ($this->is_manager)
		{
			$manager_menu = array
			(
				new MenuItem('tournament_users.php?id=' . $this->id, get_label('Registrations'), get_label('Manage registrations for [0]', $this->name)),
				new MenuItem('tournament_extra_points.php?id=' . $this->id, get_label('Extra points'), get_label('Add/remove extra points for players of [0]', $this->name)),
				new MenuItem('tournament_standings_edit.php?id=' . $this->id, get_label('Edit standings'), get_label('You can edit tournament standings manually. These stanings will count for series even if there is no information about the specific games.')),
				new MenuItem('javascript:mr.tournamentObs(' . $this->id . ')', get_label('OBS Studio integration'), get_label('Instructions how to add game informaton to OBS Studio.')),
				new MenuItem('tournament_mwt.php?id=' . $this->id, get_label('MWT integration'), get_label('Synchronize tournament with MWT site. Receive seating, send games, etc..')),
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
			$this->club_flags,
			NULL,
			true);
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
		echo '</h3><p class="subtitle">' . format_date('l, F d, Y', $this->start_time, $this->timezone) . '</p>';
		if (!is_null($this->currency_pattern) && !is_null($this->fee))
		{
			echo '<p class="subtitle"><b>'.get_label('Admission rate').': '.format_currency($this->fee, $this->currency_pattern).'</b></p>';
		}
		if (!is_null($this->mwt_id))
		{
			echo '<p class="subtitle"><a href="https://mafiaworldtour.com/tournaments/' . $this->mwt_id . '" target="_blank"><img src="images/fiim.png" title="' . get_label('MWT link') . '"></a></p>';
		}
		if (($this->flags & TOURNAMENT_FLAG_FINISHED) == 0)
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
	
	protected function show_hidden_table_message($condition = NULL)
	{
		$result = true;
		$text = NULL;
		if (($this->flags & TOURNAMENT_FLAG_FINISHED) == 0 && ($this->flags & (TOURNAMENT_HIDE_TABLE_MASK | TOURNAMENT_HIDE_BONUS_MASK)) != 0)
		{
			$hide_table = ($this->flags & TOURNAMENT_HIDE_TABLE_MASK) >> TOURNAMENT_HIDE_TABLE_MASK_OFFSET;
			$hide_bonus = ($this->flags & TOURNAMENT_HIDE_BONUS_MASK) >> TOURNAMENT_HIDE_BONUS_MASK_OFFSET;
			$delimiter = '';
			switch ($hide_table)
			{
			case 1:
				$text = get_label('Tournament tables are hidden for this tournament until it ends.');
				$hide_bonus = 0;
				$result = false;
				break;
			case 2:
				list ($hidden_games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g JOIN events e ON e.id = g.event_id WHERE g.tournament_id = ? AND e.round = 1', $this->id, $condition);
				if ($hidden_games_count > 0)
				{
					$text = get_label('Tournament tables of the finals are hidden for this tournament until it ends.');
					$delimiter = '<br>';
				}
				break;
			case 3:
				list ($hidden_games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g JOIN events e ON e.id = g.event_id WHERE g.tournament_id = ? AND e.round IN (1, 2)', $this->id, $condition);
				if ($hidden_games_count > 0)
				{
					$text = get_label('Tournament tables of the finals and semi-finals are hidden for this tournament until it ends.');
					$delimiter = '<br>';
				}
				break;
			}
			switch ($hide_bonus)
			{
			case 1:
				$text .= $delimiter . get_label('Bonus points are hidden for this tournament until it ends.');
				break;
			case 2:
				list ($hidden_games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g JOIN events e ON e.id = g.event_id WHERE g.tournament_id = ? AND e.round = 1', $this->id, $condition);
				if ($hidden_games_count > 0)
				{
					$text .= $delimiter . get_label('Bonus points of the finals are hidden for this tournament until it ends.');
				}
				break;
			case 3:
				list ($hidden_games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g JOIN events e ON e.id = g.event_id WHERE g.tournament_id = ? AND e.round IN (1, 2)', $this->id, $condition);
				if ($hidden_games_count > 0)
				{
					$text .= $delimiter . get_label('Bonus points of the finals and semi-finals are hidden for this tournament until it ends.');
				}
				break;
			}
		}
		
		if (!is_null($text))
		{
			echo '<p><table class="transp" width="100%"><tr><td width="32">';
			if ($this->is_manager)
			{
				echo '<button onclick="goTo({show_all: null})" title="' . get_label('Show the actual scoring tables.') . '"><img src="images/attention.png"></button>';
			}
			else
			{
				echo '<img src="images/attention.png">';
			}
			echo '</td><td><h3>' . $text . '</h3></td></tr></table></p>';
		}
		return $result;
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

function get_round_name($round_num)
{
	switch ($round_num)
	{
		case 0:
			return get_label('main round');
		case 1:
			return get_label('final');
		case 2:
			return get_label('semi-final');
		case 3:
			return get_label('quarter-final');
		default:
			return get_label('1/[0] final', pow(2, $round_num - 1));
	}
}

?>