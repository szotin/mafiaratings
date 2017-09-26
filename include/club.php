<?php

require_once 'include/page_base.php';

define('ALL_CLUBS', -1);
define('MY_CLUBS', 0);

function check_manager_permission($id)
{
	global $_profile;
	if ($_profile == NULL)
	{
		return false;
	}
	
	if (check_permissions(U_PERM_ADMIN) || $_profile->is_manager($id))
	{
		return true;
	}
	
	$query = new DbQuery(
		'SELECT * FROM user_clubs WHERE user_id = ? AND club_id = ? AND (flags & ' . UC_PERM_MANAGER . ') <> 0',
		$_profile->user_id, $id);
	if ($query->next())
	{
		return true;
	}
	return false;
}

function show_club_pic($club_id, $flags, $dir, $width = 0, $height = 0, $attributes = NULL)
{
	global $_lang_code;
	$w = $width;
	$h = $height;
	if ($dir == ICONS_DIR)
	{
		if ($w <= 0)
		{
			$w = ICON_WIDTH;
		}
		if ($h <= 0)
		{
			$h = ICON_HEIGHT;
		}
	}
	else if ($dir == TNAILS_DIR)
	{
		if ($w <= 0)
		{
			$w = TNAIL_WIDTH;
		}
		if ($h <= 0)
		{
			$h = TNAIL_HEIGHT;
		}
	}
	
	if ($width <= 0 && $height <= 0)
	{
		$width = $w;
		$height = $h;
	}
	
	$origin = CLUB_PICS_DIR . $dir . $club_id . '.png';
	echo '<span style="position:relative;"><img code="' . CLUB_PIC_CODE . $club_id . '" origin="' . $origin . '" src="';
	if (($flags & CLUB_ICON_MASK) != 0)
	{
		echo $origin . '?' . (($flags & CLUB_ICON_MASK) >> CLUB_ICON_MASK_OFFSET);
	}
	else
	{
		echo 'images/' . $dir . 'club.png';
	}
	echo '" border="0"';

	if ($width > 0)
	{
		echo ' width="' . $width . '"';
	}
	if ($height > 0)
	{
		echo ' height="' . $height . '"';
	}
	if ($attributes != NULL)
	{
		echo $attributes;
	}
	echo '>';
	if ($flags & CLUB_FLAG_RETIRED)
	{
		echo '<img src="images/' . $dir . $_lang_code . '/closed.png" style="position:absolute; left:50%; margin-left:-' . ($w / 2) . 'px;"';
		if ($width > 0)
		{
			echo ' width="' . $width . '"';
		}
		if ($height > 0)
		{
			echo ' height="' . $height . '"';
		}
		echo '>';
	}
	echo '</span>';
}

function has_club_buttons($id, $flags, $memb_flags)
{
	global $_profile;
	return $_profile != NULL;
}

function show_club_buttons($id, $name, $flags, $memb_flags)
{
	global $_profile;

	$no_buttons = true;
	if ($_profile != NULL && $id > 0)
	{
		$can_manage = false;
		if (($flags & CLUB_FLAG_RETIRED) != 0)
		{
			if ($_profile->is_admin() || ($memb_flags != NULL && ($memb_flags & UC_PERM_MANAGER) != 0))
			{
				echo '<button class="icon" onclick="mr.restoreClub(' . $id . ')" title="' . get_label('Restore [0]', $name) . '"><img src="images/undelete.png" border="0"></button>';
				$no_buttons = false;
			}
		}
		else 
		{
			$can_manage = $_profile->is_admin();
			if ($memb_flags != NULL)
			{
				$quit_params = $id;
				if ($memb_flags & UC_PERM_MANAGER)
				{
					$quit_params .= ', \'' . get_label('You are a manager of this club. You loose your status once you leave it. Are you sure you want to quit?') . '\'';
				}
				else if ($memb_flags & UC_PERM_MODER)
				{
					$quit_params .= ', \'' . get_label('You are a moderator of this club. You loose your status once you leave it. Are you sure you want to quit?') . '\'';
				}
			
				echo '<button class="icon" onclick="mr.quitClub(' . $quit_params . ')" title="' . get_label('Quit [0]', $name) . '"><img src="images/accept.png" border="0"></button>';
				$no_buttons = false;
				if ($memb_flags & UC_PERM_MANAGER)
				{
					$can_manage = true;
				}
			}
			else
			{
				echo '<button class="icon" onclick="mr.joinClub(' . $id . ')" title="' . get_label('Join [0]', $name) . '"><img src="images/empty.png" border="0"></button>';
				$no_buttons = false;
			}
			
			if ($can_manage)
			{
				echo '<button class="icon" onclick="mr.editClub(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.retireClub(' . $id . ')" title="' . get_label('Retire [0]', $name) . '"><img src="images/delete.png" border="0"></button>';
				$no_buttons = false;
			}
			
			if ($_profile->is_admin() || ($memb_flags & UC_PERM_MODER) != 0)
			{
				echo '<button class="icon" onclick="mr.playClub(' . $id . ')" title="' . get_label('Play the game') . '"><img src="images/game.png" border="0"></button>';
				$no_buttons = false;
			}
		}
	}
	
	if ($no_buttons)
	{
		echo '<img src="images/transp.png" height="26">';
	}
}

class ClubPageBase extends PageBase
{
	protected $id;
	protected $name;
	protected $flags;
	protected $langs;
	protected $url;
	protected $email;
	protected $phone;
	protected $price;
	protected $country;
	protected $city;
	protected $scoring_id;
	protected $memb_flags;
	protected $is_manager;
	protected $is_moder;
	protected $timezone;
	
	protected function prepare()
	{
		global $_lang_code, $_profile;
	
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('club')));
		}
		$this->id = $_REQUEST['id'];

		$this->is_manager = false;
		$this->is_moder = false;
		$user_id = -1;
		if ($_profile != NULL)
		{
			$user_id = $_profile->user_id;
			$this->is_manager = $_profile->is_manager($this->id);
			$this->is_moder = $_profile->is_moder($this->id);
		}
		
		list ($this->name, $this->flags, $this->url, $this->langs, $this->email, $this->phone, $this->price, $this->country, $this->city, $this->memb_flags, $this->scoring_id, $this->timezone) = 
			Db::record(
				get_label('club'),
				'SELECT c.name, c.flags, c.web_site, c.langs, c.email, c.phone, c.price, cr.name_' . $_lang_code . ', ct.name_' . $_lang_code . ', u.flags, c.scoring_id, ct.timezone FROM clubs c ' .
					'JOIN cities ct ON ct.id = c.city_id ' .
					'JOIN countries cr ON cr.id = ct.country_id ' .
					'LEFT OUTER JOIN user_clubs u ON u.club_id = c.id AND u.user_id = ? ' .
					'WHERE c.id = ?',
				$user_id, $this->id);
	}

	protected function show_title()
	{
		global $_profile;
		
		$menu = array(
			new MenuItem('club_main.php?id=' . $this->id, get_label('Club'), get_label('[0] main page', $this->name)),
			new MenuItem('club_standings.php?id=' . $this->id, get_label('Standings'), get_label('[0] standings', $this->name)),
			new MenuItem('club_stats.php?id=' . $this->id, get_label('Stats'), get_label('Per year stats for the past years', $this->name)),
			new MenuItem('club_events.php?id=' . $this->id, get_label('Events'), get_label('[0] events history', $this->name)),
			new MenuItem('club_albums.php?id=' . $this->id, get_label('Photos'), get_label('[0] photo albums', $this->name)),
			new MenuItem('club_moderators.php?id=' . $this->id, get_label('Moderators'), get_label('Moderators statistics of [0]', $this->name)),
			new MenuItem('club_games.php?id=' . $this->id, get_label('Games'), get_label('Games list of [0]', $this->name)));
			
		if ($this->is_manager || $this->is_moder)
		{
			$managment_menu = array(new MenuItem('club_players.php?id=' . $this->id, get_label('Members'), get_label('[0] members', $this->name)));
			if ($this->is_manager)
			{
				$managment_menu[] = new MenuItem('club_upcoming.php?id=' . $this->id, get_label('Events'), get_label('[0] upcoming events', $this->name));
				$managment_menu[] = new MenuItem('club_addresses.php?id=' . $this->id, get_label('Addresses'), get_label('[0] addresses', $this->name));
				$managment_menu[] = new MenuItem('club_seasons.php?id=' . $this->id, get_label('Seasons'), get_label('[0] seasons', $this->name));
				$managment_menu[] = new MenuItem('club_adverts.php?id=' . $this->id, get_label('Adverts'), get_label('[0] adverts', $this->name));
				$managment_menu[] = new MenuItem('club_rules.php?id=' . $this->id, get_label('Rules'), get_label('[0] game rules', $this->name));
				$managment_menu[] = new MenuItem('club_scorings.php?id=' . $this->id, get_label('Scoring systems'), get_label('Alternative methods of calculating points for [0]', $this->name));
				$managment_menu[] = new MenuItem('club_emails.php?id=' . $this->id, get_label('Emails'), get_label('[0] email templates', $this->name));
				$managment_menu[] = new MenuItem('club_log.php?id=' . $this->id, get_label('Log'), get_label('[0] log', $this->name));
			}
			$menu[] = new MenuItem('#other', get_label('Management'), NULL, $managment_menu);
		}
		
		echo '<table class="head" width="100%">';
		
		echo '<tr><td colspan="3">';
		PageBase::show_menu($menu);
		echo '</td></tr>';
		if (($this->flags & CLUB_FLAG_RETIRED) != 0)
		{
			$dark = 'darker';
			$light = 'dark';
		}
		else
		{
			$dark = 'dark';
			$light = 'light';
		}
		
		echo '<tr><td width="1"><table class="bordered"><tr><td class="' . $dark . '" valign="top" style="min-width:28px; padding:4px;">';
		show_club_buttons($this->id, $this->name, $this->flags, $this->memb_flags);
		echo '</td><td class="' . $light . '" style="min-width:' . TNAIL_WIDTH . 'px; padding: 4px 3px 1px 4px;">';
		if ($this->url != '')
		{
			echo '<a href="' . $this->url . '" target="blank">';
			show_club_pic($this->id, $this->flags, TNAILS_DIR);
			echo '</a>';
		}
		else
		{
			show_club_pic($this->id, $this->flags, TNAILS_DIR);
		}
		echo '</td></tr></table><td valign="top">' . $this->standard_title() . '<p class="subtitle">' . $this->city . ', ' . $this->country . '</p></td><td valign="top" align="right">';
		show_back_button();
		echo '</td></tr></table>';
	}
}

function show_seasons_select($club_id, $option, $on_change, $title)
{
	$seasons = array();
	$now = time();
	if ($club_id > 0)
	{
		$query = new DbQuery('SELECT id, name, start_time, end_time FROM seasons WHERE club_id = ? AND start_time < UNIX_TIMESTAMP() ORDER BY end_time DESC', $club_id);
		while ($row = $query->next())
		{
			$seasons[] = $row;
		}
	}
	
	if ($option == 0)
	{
		if (count($seasons) > 0)
		{
			$option = $seasons[0][0];
		}
		else
		{
			$option = -1;
		}
	}
	echo '<select name="season" id="season" onChange="' . $on_change . '" title="' . $title . '">';
	show_option(-1, $option, get_label('All time'));
	show_option(-2, $option, get_label('Last year'), get_label('Sinse the same day a year ago.'));
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
		if ($club_id > 0)
		{
			$query = new DbQuery('SELECT g.start_time, c.timezone FROM games g JOIN events e ON e.id = g.event_id JOIN addresses a ON a.id = e.address_id JOIN cities c ON c.id = a.city_id WHERE g.club_id = ? and result > 0 ORDER BY g.start_time LIMIT 1', $club_id);
		}
		else
		{
			$query = new DbQuery('SELECT g.start_time, c.timezone FROM games g JOIN events e ON e.id = g.event_id JOIN addresses a ON a.id = e.address_id JOIN cities c ON c.id = a.city_id WHERE result > 0 ORDER BY g.start_time LIMIT 1');
		}
		if ($row = $query->next())
		{
			list($first_game_time, $first_game_timezone) = $row;
			date_default_timezone_set($first_game_timezone);
			$first_year = (int)date('Y', $first_game_time);
			$this_year = (int)date('Y', $now);
			for ($y = $this_year; $y >= $first_year; --$y)
			{
				show_option(-$y, $option, $y);
			}
		}
	}
	echo '</select> ';
	return $option;
}

function get_season_condition($season, $start_field, $end_field)
{
	$condition = new SQL('');
	if ($season > 0)
	{
		$condition->add(' AND EXISTS(SELECT _s.id FROM seasons _s WHERE _s.start_time <= ' . $end_field . ' AND _s.end_time > ' . $start_field . ' AND _s.id = ?)', $season);
	}
	else if ($season < -1)
	{
		if ($season == -2)
		{
			$condition->add(' AND ' . $end_field . ' >= UNIX_TIMESTAMP() - 31536000');
		}
		else
		{
			$start = mktime(0, 0, 0, 1, 1, -$season);
			$end = mktime(0, 0, 0, 1, 1, 1 - $season);
			$condition->add(' AND ' . $end_field . ' >= ? AND ' . $start_field . ' < ?', $start, $end);
		}
	}
	return $condition;
}

// define('CLUB_FROM_PROFILE', 0);
// define('CLUB_DETECT', 1);
// function show_club_input($name, $value, $city_id = -1)
// {
	// global $_profile, $_lang_code;

	// if ($value === CLUB_FROM_PROFILE)
	// {
		// $value = $_profile->clubs[$_profile->user_club]->name;
	// }

	// echo '<input type="text" id="' . $name . '" value="' . $value . '"/>';
	// if (is_numeric($city_id))
	// {
// ***>
		// <script>
		// $("#<***php echo $name; ***>").autocomplete(
		// { 
			// source: function(request, response)
			// {
				// $.getJSON("club_ops.php",
				// {
					// list: "",
					// term: $("#<***php echo $name; ***>").val(),
					// cid: <***php echo $city_id; ***>
				// }, response);
			// }
			// , minLength: 0
		// });
		// </script>
// <***php
	// }
	// else
	// {
// ***>
		// <script>
		// $("#<***php echo $name; ***>").autocomplete(
		// { 
			// source: function(request, response)
			// {
				// $.getJSON("club_ops.php",
				// {
					// list: ""
					// term: $("#<***php echo $name; ***>").val(),
					// cname: $("#<***php echo $city_id; ***>").val()
				// }, response);
			// }
			// , select: function(event, ui) { $("#<***php echo $city_id; ***>").val(ui.item.city); }
			// , minLength: 0
		// });
		// </script>
// <***php
	// }

?>