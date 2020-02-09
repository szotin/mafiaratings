<?php

require_once __DIR__ . '/page_base.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/names.php';
require_once __DIR__ . '/club.php';

function send_activation_email($user_id, $name, $email)
{
	global $_lang_code;

	if ($email == '')
	{
		return true;
	}

	$email_code = md5(rand_string(8));
	$tags = array(
		'root' => new Tag(get_server_url()),
		'user_name' => new Tag($name),
		'url' => new Tag(get_server_url() . '/email_request.php?user_id=' . $user_id . '&code=' . $email_code . '&email=' . urlencode($email)));
	
	list($subj, $body, $text_body) = include __DIR__ .  '/languages/' . $_lang_code . '/email/user_activation.php';
	$body = parse_tags($body, $tags);
	$text_body = parse_tags($text_body, $tags);
	send_notification($email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_SIGN_IN, 0, $email_code);
}

function create_user($name, $email, $flags = NEW_USER_FLAGS, $club_id = NULL, $city_id = -1, $lang = 0)
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
		'INSERT INTO users (name, password, auth_key, email, flags, club_id, languages, reg_time, def_lang, city_id, games, games_won, rating, max_rating, max_rating_time) ' .
			'VALUES (?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), ?, ?, 0, 0, ' . USER_INITIAL_RATING . ', ' . USER_INITIAL_RATING . ', UNIX_TIMESTAMP())',
		$name, md5(rand_string(8)), '', $email, $flags, $club_id, $langs, $lang, $city_id);
	list ($user_id) = Db::record(get_label('user'), 'SELECT LAST_INSERT_ID()');
	
	$log_details = new stdClass();
	$log_details->name = $name;
	$log_details->email = $email;
	$log_details->flags = $flags;
	$log_details->langs = $langs;
	$log_details->def_lang = $lang;
	$log_details->city = $city_name;
	$log_details->city_id = $city_id;
	db_log(LOG_OBJECT_USER, 'created', $log_details, $user_id);
	
	if ($club_id != NULL)
	{
		Db::exec(get_label('user'), 'INSERT INTO user_clubs (user_id, club_id, flags) VALUES (?, ?, ' . USER_CLUB_NEW_PLAYER_FLAGS . ')', $user_id, $club_id);
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
	
	protected function prepare()
	{
		global $_lang_code;
	
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('user')));
		}
		$this->id = $_REQUEST['id'];

		list ($this->name, $this->email, $this->flags, $this->games_moderated, $this->reg_date, $this->langs, $this->city, $this->country, $this->club_id, $this->club, $this->club_flags) = 
			Db::record(get_label('user'),
				'SELECT u.name, u.email, u.flags, u.games_moderated, u.reg_time, u.languages, i.name_' . $_lang_code . ', o.name_' . $_lang_code . ', c.id, c.name, c.flags FROM users u' .
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
		
		$menu = array
		(
			new MenuItem('user_info.php?id=' . $this->id, get_label('Player'), get_label('User information')),
			new MenuItem('user_competition.php?id=' . $this->id, get_label('Competition chart'), get_label('How [0] competes with the other players', $this->title)),
			new MenuItem('user_clubs.php?id=' . $this->id, get_label('Clubs'), get_label('[0] clubs', $this->title)),
			new MenuItem('user_events.php?id=' . $this->id, get_label('Events'), get_label('[0] events history', $this->title)),
			new MenuItem('user_games.php?id=' . $this->id, get_label('Games'), get_label('Games list of [0]', $this->title)),
			new MenuItem('#stats', get_label('Reports'), NULL, array
			(
				new MenuItem('user_stats.php?id=' . $this->id, get_label('Stats'), get_label('General statistics. How many games played, winning percentage, nominating/voting, etc.')),
				new MenuItem('user_by_numbers.php?id=' . $this->id, get_label('By numbers'), get_label('Statistics by table numbers. What is the most winning number, or what number is shot more often.')),
				//new MenuItem('player_compare_select.php?id=' . $this->id, get_label('Compare'), get_label('Compare [0] with other players', $this->title)),
				new MenuItem('user_moderators.php?id=' . $this->id, get_label('Moderators'), get_label('How [0] played with different moderators', $this->title)),
			)),
			new MenuItem('#resources', get_label('Resources'), NULL, array
			(
				new MenuItem('user_photos.php?id=' . $this->id, get_label('Photos'), get_label('Photos of [0]', $this->title)),
				new MenuItem('user_videos.php?id=' . $this->id . '&vtype=' . VIDEO_TYPE_GAME, get_label('Game videos'), get_label('Game videos from various tournaments.')),
				new MenuItem('user_videos.php?id=' . $this->id . '&vtype=' . VIDEO_TYPE_LEARNING, get_label('Learning videos'), get_label('Masterclasses, lectures, seminars.')),
				// new MenuItem('club_tasks.php?id=' . $this->id, get_label('Tasks'), get_label('Learning tasks and puzzles.')),
				// new MenuItem('club_articles.php?id=' . $this->id, get_label('Articles'), get_label('Books and articles.')),
				// new MenuItem('club_links.php?id=' . $this->id, get_label('Links'), get_label('Links to custom mafia web sites.')),
			))
		);
		if ($_profile != NULL && $_profile->user_id == $this->id)
		{
			$menu[] = new MenuItem('user_albums.php?id=' . $this->id, get_label('Photo albums'), get_label('Photo albums of [0]', $this->title));
		}
			
		echo '<table class="head" width="100%">';

		echo '<tr><td colspan="3">';
		PageBase::show_menu($menu);
		echo '</td></tr>';	
		
		echo '<tr><td rowspan="2" width="' . TNAIL_WIDTH . '"><table class="bordered light"><tr><td class="dark" valign="top" style="min-width:28px; padding:4px;">';
		if ($_profile != NULL && $_profile->user_id == $this->id)
		{
			echo '<button class="icon" onclick="mr.editAccount()" title="' . get_label('Account settings') . '"><img src="images/settings.png" border="0"></button>';
		}
		echo '</td><td style="padding: 4px 2px 4px 1px;">';
		$user_pic = new Picture(USER_PICTURE);
		$user_pic->set($this->id, $this->name, $this->flags);
		$user_pic->show(TNAILS_DIR, false);
		echo '</td></tr></table><td valign="top"><h2 class="user">' . get_label('Player [0]', $this->_title) . '</h2><br><h3>' . $this->title . '</h3><p class="subtitle">';
		echo $this->city . ', ' . $this->country . '</p></td><td valign="top" align="right">';
		show_back_button();
		echo '</td></tr><tr><td align="right" valign="bottom" colspan="2">';
		if ($this->club != NULL)
		{
			echo '<table><tr><td align="center"><a href="club_main.php?bck=1&id=' . $this->club_id . '">' . $this->club . '</a></td></tr><tr><td align="center">';
			$this->club_pic->set($this->club_id, $this->club, $this->club_flags);
			$this->club_pic->show(ICONS_DIR, true);
			echo '</td></tr></table>';
		}
		echo '</td></tr></table>';
	}
}

function show_user_input($name, $value, $condition, $title, $js_function = 'mr.gotoFind')
{
	global $_profile, $_lang_code;

	echo '<input type="text" id="' . $name . '" value="' . $value . '" title="' . $title . '"/>';
	$url = 'api/control/user.php?';
	if (!empty($condition))
	{
		$url .= $condition . '&';
	}
	$url .= 'term=';
?>
		<script>
		$("#<?php echo $name; ?>").autocomplete(
		{ 
			source: function( request, response )
			{
				$.getJSON("<?php echo $url; ?>" + $("#<?php echo $name; ?>").val(), null, response);
			}
			, select: function(event, ui) { <?php echo $js_function; ?>(ui.item); }
			, minLength: 0
		})
		.on("focus", function () { $(this).autocomplete("search", ''); });
		</script>
<?php
}

?>