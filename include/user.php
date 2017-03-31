<?php

require_once 'include/page_base.php';
require_once 'include/email.php';
require_once 'include/localization.php';
require_once 'include/names.php';
require_once 'include/club.php';

function send_activation_email($user_id, $name, $email)
{
	global $_lang_code;

	if ($email == '')
	{
		return true;
	}

	$email_code = md5(rand_string(8));
	$tags = array(
		'uname' => new Tag($name),
		'url' => new Tag('http://' . get_server_url() . '/email_request.php?uid=' . $user_id . '&code=' . $email_code));
	
	list($subj, $body, $text_body) = include 'include/languages/' . $_lang_code . '/email_user_activation.php';
	$body = parse_tags($body, $tags);
	$text_body = parse_tags($text_body, $tags);
	send_notification($email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_SIGN_IN, 0, $email_code);
}

function create_user($name, $email, $flags = U_NEW_PLAYER_FLAGS, $club_id = NULL, $city_id = -1, $lang = 0)
{
	if ($club_id != NULL && $club_id <= 0)
	{
		$club_id = NULL;
	}

	check_user_name($name);
	if ($email != '' && !is_email($email))
	{
		throw new Exc(get_label('[0] is not a valid email address.', $email));
	}
	
	$langs = LANG_ALL;
	if ($lang <= 0 && isset($_SESSION['lang_code']))
	{
		$lang = get_lang_by_code($_SESSION['lang_code']);
	}
	
	if ($city_id < 0)
	{
		if ($club_id != NULL)
		{
			list ($city_id, $city_name, $langs, $club_name) = Db::record(get_label('club'), 'SELECT ct.id, ct.name_en, c.langs, c.name FROM clubs c JOIN cities ct ON ct.id = c.city_id WHERE c.id = ?', $club_id);
		}
		else
		{
			if ($lang == LANG_ENGLISH)
			{
				$country_name = 'Canada';
			}
			else
			{
				$country_name = 'Russia';
			}
			$query = new DbQuery('SELECT c.id, c.name_en FROM cities c JOIN countries ct ON ct.id = c.country_id WHERE ct.name_en = ? ORDER BY c.id LIMIT 1', $country_name);
			if (!($row = $query->next()))
			{
				$row = Db::record(get_label('city'), 'SELECT id, name_en FROM cities ORDER BY c.id LIMIT 1');
			}
			list ($city_id, $city_name) = $row;
		}
	}
	else
	{
		list ($city_name) = Db::record(get_label('city'), 'SELECT name_en FROM cities WHERE id = ?', $city_id);
		if ($club_id != NULL)
		{
			list ($langs) = Db::record(get_label('club'), 'SELECT langs FROM clubs WHERE id = ?', $club_id);
		}
	}
	
	if (($langs & $lang) == 0)
	{
		$lang = get_next_lang(LANG_NO, $langs);
	}
	
	Db::exec(
		get_label('user'), 
		'INSERT INTO users (name, password, auth_key, email, flags, club_id, languages, reg_time, def_lang, city_id) ' .
			'VALUES (?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), ?, ?)',
		$name, md5(rand_string(8)), '', $email, $flags, $club_id, $langs, $lang, $city_id);
	list ($user_id) = Db::record(get_label('user'), 'SELECT LAST_INSERT_ID()');
	$log_details = 
		'name=' . $name .
		"<br>email=" . $email .
		"<br>flags=" . $flags .
		"<br>langs=" . $langs .
		"<br>def_lang=" . $lang .
		"<br>city=" . $city_name .' (' . $city_id . ')';
	db_log('user', 'Created', $log_details, $user_id);
	
	if ($club_id != NULL)
	{
		Db::exec(get_label('user'), 'INSERT INTO user_clubs (user_id, club_id, flags) VALUES (?, ?, ' . UC_NEW_PLAYER_FLAGS . ')', $user_id, $club_id);
		db_log('user', 'Joined the club', NULL, $user_id, $club_id);
	}
	
	send_activation_email($user_id, $name, $email);
	return $user_id;
}

function show_user_pic($user_id, $user_flags, $dir, $width = 0, $height = 0)
{
	if ($width <= 0 && $height <= 0)
	{
		if ($dir == ICONS_DIR)
		{
			$width = ICON_WIDTH;
			$height = ICON_HEIGHT;
		}
		else if ($dir == TNAILS_DIR)
		{
			$width = TNAIL_WIDTH;
			$height = TNAIL_HEIGHT;
		}
	}

	$origin = USER_PICS_DIR . $dir . $user_id . '.png';
	echo '<img code="' . USER_PIC_CODE . $user_id .  '" origin="' . $origin . '" src="';
	if (($user_flags & U_ICON_MASK) != 0)
	{
		echo $origin . '?' . (($user_flags & U_ICON_MASK) >> U_ICON_MASK_OFFSET);
	}
	else if (($user_flags & U_FLAG_MALE) != 0)
	{
		echo 'images/' . $dir . 'male.png';
	}
	else
	{
		echo 'images/' . $dir . 'female.png';
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
	echo '>';
}

class UserPageBase extends PageBase
{
	protected $id;
	protected $name;
	protected $flags;
	protected $games_moderated;
	protected $reg_date;
	protected $langs;
	protected $title;
	protected $city;
	protected $country;
	protected $club_id;
	protected $club;
	protected $club_flags;
	
	protected function prepare()
	{
		global $_lang_code;
	
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('user')));
		}
		$this->id = $_REQUEST['id'];

		list ($this->name, $this->flags, $this->games_moderated, $this->reg_date, $this->langs, $this->city, $this->country, $this->club_id, $this->club, $this->club_flags) = 
			Db::record(get_label('user'),
				'SELECT u.name, u.flags, u.games_moderated, u.reg_time, u.languages, i.name_' . $_lang_code . ', o.name_' . $_lang_code . ', c.id, c.name, c.flags FROM users u' .
					' JOIN cities i ON i.id = u.city_id' .
					' JOIN countries o ON o.id = i.country_id' .
					' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
					' WHERE u.id = ?',
				$this->id);
		$this->title = $this->name;
	}

	protected function show_title()
	{
		global $_profile;
		
		$menu = array(
			new MenuItem('user_info.php?id=' . $this->id, get_label('Player'), get_label('User information')),
			new MenuItem('user_stats.php?id=' . $this->id, get_label('Stats'), get_label('[0] statistics', $this->title)),
			new MenuItem('player_compare_select.php?id=' . $this->id, get_label('Compare'), get_label('Compare [0] with other players', $this->title)),
			new MenuItem('user_photos.php?id=' . $this->id, get_label('Photos'), get_label('Photos of [0]', $this->title)),
			new MenuItem('user_messages.php?id=' . $this->id, get_label('Messages'), get_label('Forum messages of [0]', $this->title)));
		if ($_profile != NULL && $_profile->user_id == $this->id)
		{
			$menu[] = new MenuItem('user_albums.php?id=' . $this->id, get_label('Photo albums'), get_label('Photo albums of [0]', $this->title));
		}
		$menu[] = new MenuItem('#history', get_label('History'), NULL, array(
			new MenuItem('user_events.php?id=' . $this->id, get_label('Events'), get_label('[0] events history', $this->title)),
			new MenuItem('user_games.php?id=' . $this->id, get_label('Games'), get_label('Games list of [0]', $this->title)),
			new MenuItem('user_moderators.php?id=' . $this->id, get_label('Moderators'), get_label('How [0] played with different moderators', $this->name))));
			
		echo '<table class="head" width="100%">';

		echo '<tr><td colspan="3">';
		PageBase::show_menu($menu);
		echo '</td></tr>';	
		
		echo '<tr><td rowspan="2" width="' . TNAIL_WIDTH . '"><table class="bordered light"><tr><td class="dark" valign="top" style="min-width:28px; padding:4px;"></td><td style="padding: 4px 2px 4px 1px;">';
		show_user_pic($this->id, $this->flags, TNAILS_DIR);
		echo '</td></tr></table><td valign="top"rowspan="2" >' . $this->standard_title() . '<p class="subtitle">';
		echo $this->city . ', ' . $this->country . '</p></td><td valign="top" align="right">';
		show_back_button();
		echo '</td></tr><tr><td align="right" valign="bottom"><a href="club_main.php?bck=1&id=' . $this->club_id . '">';
		if ($this->club != NULL)
		{
			echo '<table><tr><td align="center">' . $this->club . '</td></tr><tr><td align="center">';
			show_club_pic($this->club_id, $this->club_flags, ICONS_DIR);
			echo '</td></tr></table>';
		}
		echo '</a></td></tr></table>';
	}
}

?>