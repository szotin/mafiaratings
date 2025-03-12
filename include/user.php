<?php

require_once __DIR__ . '/page_base.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/names.php';
require_once __DIR__ . '/club.php';

function send_activation_email($user_id, $name, $email)
{
	global $_lang;

	if ($email == '')
	{
		return true;
	}

	$email_code = md5(rand_string(8));
	$tags = array(
		'root' => new Tag(get_server_url()),
		'user_name' => new Tag($name),
		'url' => new Tag(get_server_url() . '/email_request.php?user_id=' . $user_id . '&code=' . $email_code . '&email=' . urlencode($email)));
	
	list($subj, $body, $text_body) = include __DIR__ .  '/languages/' . get_lang_code($_lang) . '/email/user_activation.php';
	$body = parse_tags($body, $tags);
	$text_body = parse_tags($text_body, $tags);
	send_notification($email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_SIGN_IN, 0, $email_code);
}

function create_user($names, $email, $club_id, $city_id, $flags = NEW_USER_FLAGS)
{
	$name_id = $names->get_id();
	if ($name_id <= 0)
	{
		throw new Exc(get_label('Please enter [0].', get_label('user name')));
	}
	
	if (empty($email))
	{
		throw new Exc(get_label('Please enter [0].', get_label('email address')));
	}
	else if (!is_email($email))
	{
		throw new Exc(get_label('[0] is not a valid email address.', $email));
	}
	
	$langs = LANG_ALL;
	if ($club_id <= 0)
	{
		if ($city_id < 0)
		{
			throw new Exc(get_label('Please enter user city.'));
		}
		$query = new DbQuery('SELECT c.id, c.langs, i.country_id FROM clubs c JOIN cities i ON i.id = c.city_id JOIN cities ui ON ui.id = ? WHERE i.area_id = ui.area_id', $city_id);
		if ($row = $query->next())
		{
			list($cid, $clangs, $country_id) = $row;
			if (!$query->next())
			{
				$club_id = (int)$cid;
				$langs = (int)$clangs;
			}
		}
		else
		{
			list ($country_id) = Db::record(get_label('city'), 'SELECT country_id FROM cities WHERE id = ?', $city_id);
		}
		
		if ($club_id <= 0)
		{
			$club_id = NULL;
		}
	}
	else if ($city_id < 0)
	{
		list ($city_id, $langs, $country_id) = Db::record(get_label('club'), 
			'SELECT c.city_id, c.langs, i.country_id'.
			' FROM clubs c'.
			' JOIN cities i ON i.id = c.city_id'.
			' WHERE c.id = ?', $club_id);
	}
	else
	{
		list ($langs, $country_id) = Db::record(get_label('club'), 
			'SELECT c.langs, i.country_id'.
			' FROM clubs c'.
			' JOIN cities i ON i.id = c.city_id'.
			' WHERE c.id = ?', $club_id);
	}
	
	$lang = LANG_NO;
	if (isset($_SESSION['lang_code']))
	{
		$lang = get_lang_by_code($_SESSION['lang_code']);
	}
	
	if (!is_valid_lang($lang))
	{
		// Hardcoded country ids. Please make sure they always match the countries
		switch ($country_id)
		{
			case 2: // Russia
			case 3: // Belarus
				$lang = LANG_RUSSIAN;
				break;
			case 8: // Ukraine
				$lang = LANG_UKRAINIAN;
				break;
			default:
				$lang = LANG_ENGLISH;
				break;
		}
	}

	if (($langs & $lang) == 0)
	{
		$langs |= $lang;
	}
	
	Db::exec(
		get_label('user'), 
		'INSERT INTO users (name_id, password, auth_key, email, flags, club_id, languages, reg_time, def_lang, city_id, games, games_won, rating) ' .
			'VALUES (?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), ?, ?, 0, 0, ' . USER_INITIAL_RATING . ')',
		$name_id, md5(rand_string(8)), '', $email, $flags, $club_id, $langs, $lang, $city_id);
	list ($user_id) = Db::record(get_label('user'), 'SELECT LAST_INSERT_ID()');
	
	$name = $names->to_string();
	$log_details = new stdClass();
	$log_details->name = $name;
	$log_details->email = $email;
	$log_details->flags = $flags;
	$log_details->langs = $langs;
	$log_details->def_lang = $lang;
	$log_details->city_id = $city_id;
	db_log(LOG_OBJECT_USER, 'created', $log_details, $user_id);
	
	if ($club_id != NULL)
	{
		Db::exec(get_label('user'), 'INSERT INTO club_users (user_id, club_id, flags) VALUES (?, ?, ' . USER_CLUB_NEW_PLAYER_FLAGS . ')', $user_id, $club_id);
		db_log(LOG_OBJECT_USER, 'joined club', NULL, $user_id, $club_id);
	}
	
	send_activation_email($user_id, $name, $email);
	return $user_id;
}

class UserPageBase extends PageBase
{
	protected $id;
	protected $name;
	protected $email;
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
	protected $mwt_id;
	
	protected function prepare()
	{
		global $_lang;
	
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('user')));
		}
		$this->id = $_REQUEST['id'];

		list ($this->name, $this->email, $this->flags, $this->games_moderated, $this->reg_date, $this->langs, $this->city, $this->country, $this->club_id, $this->club, $this->club_flags, $this->mwt_id) = 
			Db::record(get_label('user'),
				'SELECT nu.name, u.email, u.flags, u.games_moderated, u.reg_time, u.languages, ni.name, no.name, c.id, c.name, c.flags, u.mwt_id FROM users u' .
					' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
					' JOIN cities i ON i.id = u.city_id' .
					' JOIN countries o ON o.id = i.country_id' .
					' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
					' JOIN names no ON no.id = o.name_id AND (no.langs & '.$_lang.') <> 0' .
					' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
					' WHERE u.id = ?',
				$this->id);
		$this->title = $this->name;
	}

	protected function show_title()
	{
		global $_profile;
		
		$menu = array
		(
			new MenuItem('user_info.php?id=' . $this->id, get_label('Player'), get_label('User information')),
			new MenuItem('user_competition.php?id=' . $this->id, get_label('Competition chart'), get_label('How [0] competes with the other players', $this->title)),
			new MenuItem('user_clubs.php?id=' . $this->id, get_label('Clubs'), get_label('[0] clubs', $this->title)),
			new MenuItem('user_series.php?id=' . $this->id, get_label('Series'), get_label('[0] series history', $this->title)),
			new MenuItem('user_tournaments.php?id=' . $this->id, get_label('Tournaments'), get_label('[0] tournaments history', $this->title)),
			new MenuItem('user_events.php?id=' . $this->id, get_label('Events'), get_label('[0] events history', $this->title)),
			new MenuItem('user_games.php?id=' . $this->id, get_label('Games'), get_label('Games list of [0]', $this->title)),
			new MenuItem('#stats', get_label('Reports'), NULL, array
			(
				new MenuItem('user_stats.php?id=' . $this->id, get_label('Stats'), get_label('General statistics. How many games played, winning percentage, nominating/voting, etc.')),
				new MenuItem('user_by_numbers.php?id=' . $this->id, get_label('By numbers'), get_label('Statistics by table numbers. What is the most winning number, or what number is shot more often.')),
				//new MenuItem('player_compare_select.php?id=' . $this->id, get_label('Compare'), get_label('Compare [0] with other players', $this->title)),
				new MenuItem('user_referees.php?id=' . $this->id, get_label('Referees'), get_label('How [0] played with different referees', $this->title)),
			)),
			new MenuItem('#resources', get_label('Resources'), NULL, array
			(
				new MenuItem('user_photos.php?id=' . $this->id, get_label('Photos'), get_label('Photos of [0]', $this->title)),
				new MenuItem('user_videos.php?id=' . $this->id, get_label('Videos'), get_label('Videos with [0].', $this->title)),
				// new MenuItem('club_tasks.php?id=' . $this->id, get_label('Tasks'), get_label('Learning tasks and puzzles.')),
				// new MenuItem('club_articles.php?id=' . $this->id, get_label('Articles'), get_label('Books and articles.')),
				// new MenuItem('club_links.php?id=' . $this->id, get_label('Links'), get_label('Links to custom mafia web sites.')),
			))
		);
		if ($_profile != NULL && $_profile->user_id == $this->id)
		{
			$menu[] = new MenuItem('#site', get_label('Management'), NULL, array
			(
				new MenuItem('user_albums.php?id=' . $this->id, get_label('Photo albums'), get_label('Photo albums of [0]', $this->title)),
				new MenuItem('user_sounds.php?id=' . $this->id, get_label('Game sounds'), get_label('Sounds in the game for prompting players on speech end.')),
			));
		}
			
		echo '<table class="head" width="100%">';

		echo '<tr><td colspan="3">';
		PageBase::show_menu($menu);
		echo '</td></tr>';	
		
		echo '<tr><td rowspan="2" width="' . TNAIL_WIDTH . '"><table class="bordered light"><tr><td class="dark" valign="top" style="min-width:28px; padding:4px;">';
		if (is_permitted(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, $this->id, $this->club_id))
		{
			echo '<button class="icon" onclick="mr.editUser(' . $this->id . ')" title="' . get_label('Account settings') . '"><img src="images/edit.png" border="0"></button>';
		}
		echo '</td><td style="padding: 4px 2px 4px 1px;">';
		$user_pic = new Picture(USER_PICTURE);
		$user_pic->set($this->id, $this->name, $this->flags);
		$user_pic->show(TNAILS_DIR, false);
		echo '</td></tr></table><td valign="top"><h2 class="user">' . $this->title . '</h2><br><h3>' . $this->_title . '</h3><p class="subtitle">';
		echo $this->city . ', ' . $this->country . '</p>';
		if (!is_null($this->mwt_id))
		{
			echo '<p class="subtitle"><a href="https://mafiaworldtour.com/user/' . $this->mwt_id . '/show" target="_blank"><img src="images/fiim.png" title="' . get_label('MWT link') . '"></a></p>';
		}
		echo '</td><td valign="top" align="right">';
		show_back_button();
		echo '</td></tr><tr><td align="right" valign="bottom" colspan="2">';
		if ($this->club != NULL)
		{
			echo '<table><tr><td align="center">';
			$this->club_pic->set($this->club_id, $this->club, $this->club_flags);
			$this->club_pic->show(ICONS_DIR, true, 48);
			echo '</td></tr></table>';
		}
		echo '</td></tr></table>';
	}
}

function show_user_input($name, $value, $condition, $title, $js_function = 'mr.gotoFind')
{
	global $_profile;

	echo '<input type="text" id="' . $name . '" placeholder="' . get_label('Select player') . '" title="' . $title . '"/>';
	echo '<button class="small_icon" onclick="$(&quot;#' . $name . '&quot;).val(\'\')"><img src="images/clear.png" width="12"></button>';
	$url = 'api/control/user.php?control=' . $name;
	if (!empty($condition))
	{
		$url .= '&' . $condition;
	}
	$url .= '&term=';
?>
		<script>
		$("#<?php echo $name; ?>").autocomplete(
		{ 
			source: function(request, response)
			{
				$.getJSON("<?php echo $url; ?>" + $("#<?php echo $name; ?>").val(), null, response);
			}
			, select: function(event, ui) { <?php echo $js_function; ?>(ui.item); }
			, minLength: 0
		})
		.on("focus", function () { $(this).autocomplete("search", ''); })
		.val("<?php echo $value; ?>");
		</script>
<?php
}

?>