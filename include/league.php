<?php

require_once __DIR__ . '/page_base.php';

function has_league_buttons($id, $flags)
{
	global $_profile;
	return $_profile != NULL;
}

function show_league_buttons($id, $name, $flags)
{
	global $_profile;

	$no_buttons = true;
	if ($_profile != NULL && $id > 0 && $_profile->is_league_manager($id))
	{
		$can_manage = $_profile->is_league_manager($id);
		if (($flags & LEAGUE_FLAG_RETIRED) != 0)
		{
			echo '<button class="icon" onclick="mr.restoreLeague(' . $id . ')" title="' . get_label('Restore [0]', $name) . '"><img src="images/undelete.png" border="0"></button>';
			$no_buttons = false;
		}
		else
		{
			echo '<button class="icon" onclick="mr.editLeague(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></button>';
			echo '<button class="icon" onclick="mr.retireLeague(' . $id . ')" title="' . get_label('Retire [0]', $name) . '"><img src="images/delete.png" border="0"></button>';
			$no_buttons = false;
		}
	}
	else
	{
		echo '<img src="images/transp.png" height="26">';
	}
}

class LeaguePageBase extends PageBase
{
	protected $id;
	protected $name;
	protected $flags;
	protected $langs;
	protected $url;
	protected $email;
	protected $phone;
	protected $scoring_id;
	protected $is_manager;
	protected $rules_filter;
	
	protected function prepare()
	{
		global $_lang_code, $_profile;
	
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('league')));
		}
		$this->id = $_REQUEST['id'];

		$this->is_manager = $_profile != NULL ? $_profile->is_league_manager($this->id) : false;
		
		list ($this->name, $this->flags, $this->url, $this->langs, $this->email, $this->phone, $this->scoring_id, $rules) = 
			Db::record(
				get_label('league'),
				'SELECT l.name, l.flags, l.web_site, l.langs, l.email, l.phone, l.scoring_id, l.rules FROM leagues l WHERE l.id = ?', $this->id);
		$this->rules_filter = json_decode($rules);
	}

	protected function show_title()
	{
		global $_profile;
		
		$menu = array
		(
			new MenuItem('league_main.php?id=' . $this->id, get_label('League'), get_label('[0] main page', $this->name)),
			// new MenuItem('league_standings.php?id=' . $this->id, get_label('Standings'), get_label('[0] standings', $this->name)),
			// new MenuItem('league_competition.php?id=' . $this->id, get_label('Competition chart'), get_label('How players were competing in the league.')),
			new MenuItem('league_clubs.php?id=' . $this->id, get_label('Clubs'), get_label('Member clubs of [0].', $this->name)),
			// new MenuItem('league_events.php?id=' . $this->id, get_label('Events'), get_label('[0] events history', $this->name)),
			// new MenuItem('league_games.php?id=' . $this->id, get_label('Games'), get_label('Games list of [0]', $this->name)),
			// new MenuItem('#stats', get_label('Stats'), NULL, array
			// (
				// new MenuItem('league_stats.php?id=' . $this->id, get_label('General stats'), get_label('General statistics. How many games played, mafia winning percentage, how many players, etc.', PRODUCT_NAME)),
				// new MenuItem('league_by_numbers.php?id=' . $this->id, get_label('By numbers'), get_label('Statistics by table numbers. What is the most winning number, or what number is shot more often.')),
				// new MenuItem('league_nominations.php?id=' . $this->id, get_label('Nomination winners'), get_label('Custom nomination winners. For example who had most warnings, or who was checked by sheriff most often.')),
				// new MenuItem('league_moderators.php?id=' . $this->id, get_label('Moderators'), get_label('Moderators statistics of [0]', $this->name)),
			// )),
			new MenuItem('#resources', get_label('Resources'), NULL, array
			(
				new MenuItem('league_rules.php?id=' . $this->id, get_label('Rulebook'), get_label('Rules of the game in [0]', $this->name)),
				// new MenuItem('league_albums.php?id=' . $this->id, get_label('Photos'), get_label('Photo albums')),
				// new MenuItem('league_videos.php?id=' . $this->id . '&vtype=' . VIDEO_TYPE_GAME, get_label('Game videos'), get_label('Game videos from various tournaments.')),
				// new MenuItem('league_videos.php?id=' . $this->id . '&vtype=' . VIDEO_TYPE_LEARNING, get_label('Learning videos'), get_label('Masterclasses, lectures, seminars.')),
				// new MenuItem('league_tasks.php?id=' . $this->id, get_label('Tasks'), get_label('Learning tasks and puzzles.')),
				// new MenuItem('league_articles.php?id=' . $this->id, get_label('Articles'), get_label('Books and articles.')),
				// new MenuItem('league_links.php?id=' . $this->id, get_label('Links'), get_label('Links to custom mafia web sites.')),
			)),
		);
			
		if ($this->is_manager)
		{
			$menu[] = new MenuItem('#other', get_label('Management'), NULL, array
			(
				new MenuItem('league_managers.php?id=' . $this->id, get_label('Managers'), get_label('[0] managers', $this->name)),
				new MenuItem('league_seasons.php?id=' . $this->id, get_label('Seasons'), get_label('[0] seasons', $this->name)),
				// new MenuItem('league_adverts.php?id=' . $this->id, get_label('Adverts'), get_label('[0] adverts', $this->name)),
				// new MenuItem('league_rules.php?id=' . $this->id, get_label('Rules'), get_label('[0] game rules', $this->name)),
				// new MenuItem('league_scorings.php?id=' . $this->id, get_label('Scoring systems'), get_label('Alternative methods of calculating points for [0]', $this->name)),
				// new MenuItem('league_log.php?id=' . $this->id, get_label('Log'), get_label('[0] log', $this->name)),
			));
		}
		
		echo '<table class="head" width="100%">';
		
		echo '<tr><td colspan="3">';
		PageBase::show_menu($menu);
		echo '</td></tr>';
		if (($this->flags & LEAGUE_FLAG_RETIRED) != 0)
		{
			$dark = 'darker';
			$light = 'dark';
		}
		else
		{
			$dark = 'dark';
			$light = 'light';
		}
		
		$league_pic = new Picture(LEAGUE_PICTURE);
		echo '<tr><td width="1"><table class="bordered"><tr><td class="' . $dark . '" valign="top" style="min-width:28px; padding:4px;">';
		show_league_buttons($this->id, $this->name, $this->flags);
		echo '</td><td class="' . $light . '" style="min-width:' . TNAIL_WIDTH . 'px; padding: 4px 3px 1px 4px;">';
		$league_pic->set($this->id, $this->name, $this->flags);
		if ($this->url != '')
		{
			echo '<a href="' . $this->url . '" target="blank">';
			$league_pic->show(TNAILS_DIR);
			echo '</a>';
		}
		else
		{
			$league_pic->show(TNAILS_DIR);
		}
		echo '</td></tr></table><td valign="top"><h2 class="league">' . get_label('League [0]', $this->_title) . '</h2><br><h3>' . $this->name . '</h3></td><td valign="top" align="right">';
		show_back_button();
		echo '</td></tr></table>';
	}
}

function get_current_league_season($league_id)
{
	$condition = new SQL();
	if ($league_id > 0)
	{
		$query = new DbQuery('SELECT id, name, start_time, end_time FROM league_seasons WHERE league_id = ? AND start_time < UNIX_TIMESTAMP() ORDER BY end_time DESC', $league_id);
		while ($row = $query->next())
		{
			return (int)$row[0];
		}
		$condition->add(' AND g.league_id = ?', $league_id);
	}
	
	$query = new DbQuery('SELECT g.end_time, c.timezone FROM games g JOIN events e ON e.id = g.event_id JOIN addresses a ON a.id = e.address_id WHERE g.canceled = FALSE AND g.result > 0', $condition);
	$query->add(' ORDER BY g.end_time DESC LIMIT 1');
	if ($row = $query->next())
	{
		list($timestamp, $timezone) = $row;
		date_default_timezone_set($timezone);
		$last_year = (int)date('Y', $timestamp);
		return -$last_year;
	}
	return -date('Y');
}

function show_league_seasons_select($league_id, $option, $on_change, $title)
{
	$seasons = array();
	$condition = new SQL();
	if ($league_id > 0)
	{
		$query = new DbQuery('SELECT id, name, start_time, end_time FROM league_seasons WHERE league_id = ? AND start_time < UNIX_TIMESTAMP() ORDER BY end_time DESC', $league_id);
		while ($row = $query->next())
		{
			$seasons[] = $row;
		}
		$condition->add(' AND g.league_id = ?', $league_id);
	}
	
	if ($option == SEASON_LATEST && count($seasons) > 0)
	{
		$option = $seasons[0][0];
	}
	echo '<select name="season" id="season" onChange="' . $on_change . '" title="' . $title . '">';
	show_option(SEASON_ALL_TIME, $option, get_label('All time'));
	if (count($seasons) > 0)
	{
		foreach ($seasons as $season)
		{
			list($id, $name, $start, $end) = $season;
			show_option($id, $option, $name);
		}
	}
	else
	{
		$query = new DbQuery('SELECT g.start_time, c.timezone FROM games g JOIN events e ON e.id = g.event_id JOIN addresses a ON a.id = e.address_id WHERE g.canceled = FALSE AND g.result > 0', $condition);
		$query->add(' ORDER BY g.start_time LIMIT 1');
		if ($row = $query->next())
		{
			list($timestamp, $timezone) = $row;
			date_default_timezone_set($timezone);
			$first_year = (int)date('Y', $timestamp);
			
			$query = new DbQuery('SELECT g.end_time, c.timezone FROM games g JOIN events e ON e.id = g.event_id JOIN addresses a ON a.id = e.address_id WHERE g.canceled = FALSE AND g.result > 0', $condition);
			$query->add(' ORDER BY g.end_time DESC LIMIT 1');
			if ($row = $query->next())
			{
				list($timestamp, $timezone) = $row;
				date_default_timezone_set($timezone);
				$last_year = (int)date('Y', $timestamp);
				if ($option == 0)
				{
					$option = -$last_year;
				}
				for ($y = $last_year; $y >= $first_year; --$y)
				{
					show_option(-$y, $option, $y);
				}
			}
		}
	}
	echo '</select> ';
	return $option;
}

function get_league_season_condition($season, $start_field, $end_field)
{
	$condition = new SQL('');
	if ($season > SEASON_LATEST)
	{
		$condition->add(' AND EXISTS(SELECT _s.id FROM league_seasons _s WHERE _s.start_time <= ' . $end_field . ' AND _s.end_time > ' . $start_field . ' AND _s.id = ?)', $season);
	}
	else if ($season < SEASON_ALL_TIME)
	{
		$start = mktime(0, 0, 0, 1, 1, -$season);
		$end = mktime(0, 0, 0, 1, 1, 1 - $season);
		$condition->add(' AND ' . $end_field . ' >= ? AND ' . $start_field . ' < ?', $start, $end);
	}
	return $condition;
}

?>