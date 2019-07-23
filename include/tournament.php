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

function show_tournament_buttons($id, $start_time, $duration, $flags, $club_id, $club_flags, $league_id)
{
	global $_profile;

	$now = time();
	
	$no_buttons = true;
	if ($_profile != NULL && $id > 0 && ($club_flags & CLUB_FLAG_RETIRED) == 0)
	{
		$can_manage = false;
		
		if ($_profile->is_club_manager($club_id))
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
			$no_buttons = false;
		}
	}
	echo '<button class="icon" onclick="window.open(\'tournament_screen.php?id=' . $id . '\' ,\'_blank\')" title="' . get_label('Open interactive standings page') . '"><img src="images/details.png" border="0"></button>';
	if ($league_id > 0 && is_permitted(PERMISSION_LEAGUE_MANAGER, $league_id))
	{
		echo '<button class="icon" onclick="mr.approveTournament(' . $id . ', ' . $league_id . ')" title="' . get_label('Open interactive standings page') . '"><img src="images/stars.png" border="0"></button>';
	}
}


class TournamentPageBase extends PageBase
{
	protected $id;
	protected $name;
	protected $request_league_id;
	protected $league_id;
	protected $league_name;
	protected $league_flags;
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
	protected $rules_code;
	protected $flags;
	protected $stars;
	
	protected function prepare()
	{
		global $_lang_code, $_profile;
		
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('tournament')));
		}
		$this->id = (int)$_REQUEST['id'];
		list(
			$this->name, $this->request_league_id, $this->league_id, $this->league_name, $this->league_flags, $this->club_id, $this->club_name, $this->club_flags, 
			$this->address_id, $this->address_name, $this->address, $this->address_url, $this->address_flags,
			$this->city_id, $this->city_name, $this->country_id, $this->country_name, $this->timezone,
			$this->start_time, $this->duration, $this->langs, $this->notes, $this->price, 
			$this->scoring_id, $this->rules_code, $this->flags, $this->stars) =
		Db::record(
			get_label('tournament'),
			'SELECT t.name, t.request_league_id, l.id, l.name, l.flags, c.id, c.name, c.flags,' . 
				' a.id, a.name, a.address, a.map_url, a.flags,' . 
				' ct.id, ct.name_' . $_lang_code . ', cr.id, cr.name_' . $_lang_code . ', ct.timezone,' . 
				' t.start_time, t.duration, t.langs, t.notes, t.price, t.scoring_id, t.rules, t.flags, t.stars' .
				' FROM tournaments t' .
				' LEFT OUTER JOIN leagues l ON l.id = t.league_id' .
				' JOIN clubs c ON c.id = t.club_id' .
				' JOIN addresses a ON a.id = t.address_id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' JOIN countries cr ON cr.id = ct.country_id' .
				' WHERE t.id = ?',
			$this->id);
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
			new MenuItem('#stats', get_label('Stats'), NULL, array
			(
				new MenuItem('tournament_stats.php?id=' . $this->id, get_label('General stats'), get_label('General statistics. How many games played, mafia winning percentage, how many players, etc.', PRODUCT_NAME)),
				new MenuItem('tournament_by_numbers.php?id=' . $this->id, get_label('By numbers'), get_label('Statistics by table numbers. What is the most winning number, or what number is shot more often.')),
				new MenuItem('tournament_nominations.php?id=' . $this->id, get_label('Nomination winners'), get_label('Custom nomination winners. For example who had most warnings, or who was checked by sheriff most often.')),
				new MenuItem('tournament_moderators.php?id=' . $this->id, get_label('Moderators'), get_label('Moderators statistics of the tournament')),
			)),
			new MenuItem('#resources', get_label('Resources'), NULL, array
			(
				new MenuItem('tournament_albums.php?id=' . $this->id, get_label('Photos'), get_label('Tournament photo albums')),
				new MenuItem('tournament_videos.php?id=' . $this->id . '&vtype=' . VIDEO_TYPE_GAME, get_label('Game videos'), get_label('Game videos from various tournaments.')),
				new MenuItem('tournament_videos.php?id=' . $this->id . '&vtype=' . VIDEO_TYPE_LEARNING, get_label('Learning videos'), get_label('Masterclasses, lectures, seminars.')),
				// new MenuItem('tournament_tasks.php?id=' . $this->id, get_label('Tasks'), get_label('Learning tasks and puzzles.')),
				// new MenuItem('tournament_articles.php?id=' . $this->id, get_label('Articles'), get_label('Books and articles.')),
				// new MenuItem('tournament_links.php?id=' . $this->id, get_label('Links'), get_label('Links to custom mafia web sites.')),
			)),
		);
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
			$this->league_id);
		echo '</td><td width="' . ICON_WIDTH . '" style="padding: 4px;">';
		$tournament_pic = new Picture(TOURNAMENT_PICTURE, new Picture(LEAGUE_PICTURE, $this->club_pic));
		$tournament_pic->
			set($this->id, $this->name, $this->flags)->
			set($this->league_id, $this->league_name, $this->league_flags)->
			set($this->club_id, $this->club_name, $this->club_flags);
		if ($this->address_url != '')
		{
			echo '<a href="address_info.php?bck=1&id=' . $this->address_id . '">';
			$tournament_pic->show(TNAILS_DIR);
			echo '</a>';
		}
		else
		{
			$tournament_pic->show(TNAILS_DIR);
		}
		echo '</td></tr></table></td>';
		$title = get_label('Tournament [0]', $this->_title);
		
		echo '<td rowspan="2" valign="top"><h2 class="tournament">' . $title . '</h2><br><h3>' . $this->name;
		$time = time();
		echo '</h3><p class="subtitle">' . format_date('l, F d, Y, H:i', $this->start_time, $this->timezone) . '</p></td>';
		
		echo '<td valign="top" align="right">';
		show_back_button();
		echo '</td></tr><tr><td align="right" valign="bottom"><table><tr><td align="center"><a href="club_main.php?bck=1&id=' . $this->club_id . '">';
		$this->club_pic->set($this->club_id, $this->club_name, $this->club_flags);
		$this->club_pic->show(ICONS_DIR, 48);
		echo '</a>';
		if ($this->league_id > 0)
		{
			echo ' <a href="league_main.php?bck=1&id=' . $this->league_id . '">';
			$league_pic = new Picture(LEAGUE_PICTURE);
			$league_pic->set($this->league_id, $this->league_name, $this->league_flags);
			$league_pic->show(ICONS_DIR, 48);
			echo '</a>';
		}
		echo '</td></tr><tr><td>';
		for ($i = 0; $i < floor($this->stars); ++$i)
		{
			echo '<img src="images/star.png">';
		}
		for (; $i < $this->stars; ++$i)
		{
			echo '<img src="images/star-half.png">';
		}
		for (; $i < 5; ++$i)
		{
			echo '<img src="images/star-empty.png">';
		}
		echo '</td></tr></table></td></tr>';
		
		echo '</table>';
	}
}
	
?>