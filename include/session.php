<?php

require_once __DIR__ . '/branding.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/names.php';
require_once __DIR__ . '/utilities.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/languages.php';
require_once __DIR__ . '/localization.php';

$_session_state = SESSION_NO_USER;
$_agent = AGENT_BROWSER;
$_http_agent = '';
if (isset($_SERVER['HTTP_USER_AGENT']))
{
	$_http_agent = $_SERVER['HTTP_USER_AGENT'];
}

if (is_web() && !isset($_SERVER['HTTPS']) && is_production_server())
{
	$url = 'https://';
	if (isset($_SERVER['SERVER_NAME']))
	{
		$url .= $_SERVER['SERVER_NAME'];
		if ($_SERVER['SERVER_PORT'] != "80")
		{
			$url .= ':' . $_SERVER["SERVER_PORT"];
		}
	}
	else
	{
		$url .= PRODUCT_SITE;
	}
	$url .= $_SERVER['REQUEST_URI'];
	header('location: ' . $url);
}

class ProfileClub
{
	public $id;
	public $name;
	public $flags;
	public $club_flags;
	public $langs;
	public $city_id;
	public $city;
	public $country_id;
	public $country;
	public $timezone;
	public $rules_code;
	public $scoring_id;
	public $normalizer_id;
	public $fee;
	public $currency_id;
	public $parent_id;
}

class Profile
{
	public $user_id;
	public $user_name;
	public $user_langs;
	public $user_email;
	public $user_phone;
	public $user_flags;
	public $user_club_id;
	public $city_id;
	public $region_id;
	public $country_id;
	public $timezone;
	public $clubs;
	public $user_last_active;
	public $user_accounts_count;
	
	function __construct($user_id)
	{
		global $_lang;
		list(
			$this->user_id, $this->user_name, $this->user_langs, $_lang,
			$this->user_email, $this->user_phone, $this->user_flags, $this->user_club_id,
			$this->city_id, $this->region_id, $this->country_id, $this->timezone) = 
				Db::record(
					get_label('user'), 
					'SELECT u.id, nu.name, u.languages, u.def_lang, u.email, u.phone, u.flags, u.club_id, c.id, c.area_id, c.country_id, c.timezone FROM users u' .
						' JOIN cities c ON u.city_id = c.id' .
						' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
						' WHERE u.id = ?', $user_id);
		if ($this->region_id == NULL)
		{
			$this->region_id = $this->city_id;
		}
		
		list($this->user_accounts_count) = Db::record(get_label('user'), 'SELECT count(*) FROM users WHERE email = ?', $this->user_email);
			
		$this->update_clubs();
		$this->user_last_active = time();
		$this->new_event = NULL;
	}
	
	function update()
	{
		global $_lang;
		list(
			$this->user_id, $this->user_name, $this->user_langs, $_lang,
			$this->user_email, $this->user_phone, $this->user_flags, $this->user_club_id,
			$this->city_id, $this->country_id, $this->timezone) = 
				Db::record(
					get_label('user'), 
					'SELECT u.id, nu.name, u.languages, u.def_lang, u.email, u.phone, u.flags, u.club_id, c.id, c.country_id, c.timezone FROM users u' .
						' JOIN cities c ON u.city_id = c.id' .
						' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
						' WHERE u.id = ?',
					$this->user_id);
			
		$this->update_clubs();
		$this->user_last_active = time();
		$this->new_event = NULL;
	}
	
	function update_clubs()
	{
		global $_lang;
	
		$this->clubs = array();
		$sep = '';
		if ($this->is_admin())
		{
			$query = new DbQuery(
				'SELECT c.id, c.name, ' . (USER_PERM_PLAYER | USER_PERM_REFEREE | USER_PERM_MANAGER) . ', c.flags, c.langs, i.id, ni.name, i.country_id, no.name, i.timezone, c.rules, c.scoring_id, c.normalizer_id, c.fee, c.currency_id, c.parent_id FROM clubs c' .
					' JOIN cities i ON c.city_id = i.id ' .
					' JOIN names ni ON i.name_id = ni.id AND (ni.langs & '.$_lang.') <> 0' .
					' JOIN countries o ON i.country_id = o.id ' .
					' JOIN names no ON o.name_id = no.id AND (no.langs & '.$_lang.') <> 0' .
					' ORDER BY c.name');
		}
		else
		{
			$query = new DbQuery(
				'SELECT c.id, c.name, uc.flags, c.flags, c.langs, i.id, ni.name, i.country_id, no.name, i.timezone, c.rules, c.scoring_id, c.normalizer_id, c.fee, c.currency_id, c.parent_id FROM club_regs uc' .
					' JOIN clubs c ON c.id = uc.club_id' .
					' JOIN cities i ON i.id = c.city_id' .
					' JOIN names ni ON i.name_id = ni.id AND (ni.langs & '.$_lang.') <> 0' .
					' JOIN countries o ON i.country_id = o.id ' .
					' JOIN names no ON o.name_id = no.id AND (no.langs & '.$_lang.') <> 0' .
					' WHERE uc.user_id = ?' .
					' ORDER BY c.name', $this->user_id);
		}
		if ($query)
		{
			while ($row = $query->next())
			{
				$pc = new ProfileClub();
				list($pc->id, $pc->name, $pc->flags, $pc->club_flags, $pc->langs, $pc->city_id, $pc->city, $pc->country_id, $pc->country, $pc->timezone, $pc->rules_code, $pc->scoring_id, $pc->normalizer_id, $pc->fee, $pc->currency_id, $pc->parent_id) = $row;
				$this->clubs[$pc->id] = $pc;
			}
		}
	}
	
	function is_admin()
	{
		return ($this->user_flags & USER_PERM_ADMIN) != 0;
	}
	
	function has_club_perm($perm, $club_id)
	{
		return isset($this->clubs[$club_id]) && ($this->clubs[$club_id]->flags & $perm) != 0;
	}
	
	function is_club_referee($club_id)
	{
		return $this->has_club_perm(USER_PERM_REFEREE, $club_id);
	}
	
	function is_club_manager($club_id)
	{
		return $this->has_club_perm(USER_PERM_MANAGER, $club_id);
	}
	
	function is_club_player($club_id)
	{
		return $this->has_club_perm(USER_PERM_PLAYER, $club_id);
	}
	
	function get_clubs_count($permission = 0)
	{
		if ($permission == 0)
		{
			return count($this->clubs);
		}
		$count = 0;
		foreach ($this->clubs as $club)
		{
			if (($club->flags & $permission) != 0)
			{
				++$count;
			}
		}
		return $count;
	}
	
	function get_comma_sep_clubs($permission = 0)
	{
		$csc = '';
		$sep = '';
		$count = 0;
		foreach ($this->clubs as $club)
		{
			if ($permission == 0 || ($club->flags & $permission) != 0)
			{
				$csc .= $sep . $club->id;
				$sep = ', ';
			}
		}
		if ($sep == '')
		{
			return -1;
		}
		return $csc;
	}
	
	function is_league_manager($league_id)
	{
		if ($this->is_admin())
		{
			return true;
		}
		
		if (!is_numeric($league_id) || $league_id <= 0)
		{
			return false;
		}
		
		list ($count) = Db::record(get_label('league'), 'SELECT count(*) FROM league_managers WHERE league_id = ? AND user_id = ?', $league_id, $this->user_id);
		return $count > 0;
	}
	
	function is_event_player($event_id)
	{
		if ($this->is_admin())
		{
			return true;
		}

		if (is_numeric($event_id) && $event_id > 0)
		{
			$query = new DbQuery('SELECT flags FROM event_regs WHERE event_id = ? AND user_id = ?', $event_id, $this->user_id);
			if ($row = $query->next())
			{
				list($flags) = $row;
				return ($flags & USER_PERM_PLAYER) != 0;
			}
		}
		return false;
	}
	
	function is_event_regeree($event_id)
	{
		if ($this->is_admin())
		{
			return true;
		}

		if (is_numeric($event_id) && $event_id > 0)
		{
			$query = new DbQuery('SELECT flags FROM event_regs WHERE event_id = ? AND user_id = ?', $event_id, $this->user_id);
			if ($row = $query->next())
			{
				list($flags) = $row;
				return ($flags & USER_PERM_REFEREE) != 0;
			}
		}
		return false;
	}
	
	function is_event_manager($event_id)
	{
		if ($this->is_admin())
		{
			return true;
		}

		if (is_numeric($event_id) && $event_id > 0)
		{
			$query = new DbQuery('SELECT flags FROM event_regs WHERE event_id = ? AND user_id = ?', $event_id, $this->user_id);
			if ($row = $query->next())
			{
				list($flags) = $row;
				return ($flags & USER_PERM_MANAGER) != 0;
			}
		}
		return false;
	}
	
	function is_tournament_player($tournament_id)
	{
		if ($this->is_admin())
		{
			return true;
		}

		if (is_numeric($tournament_id) && $tournament_id > 0)
		{
			$query = new DbQuery('SELECT flags FROM tournament_regs WHERE tournament_id = ? AND user_id = ?', $tournament_id, $this->user_id);
			if ($row = $query->next())
			{
				list($flags) = $row;
				return ($flags & USER_PERM_PLAYER) != 0;
			}
		}
		return false;
	}
	
	function is_tournament_regeree($tournament_id)
	{
		if ($this->is_admin())
		{
			return true;
		}

		if (is_numeric($tournament_id) && $tournament_id > 0)
		{
			$query = new DbQuery('SELECT flags FROM tournament_regs WHERE tournament_id = ? AND user_id = ?', $tournament_id, $this->user_id);
			if ($row = $query->next())
			{
				list($flags) = $row;
				return ($flags & USER_PERM_REFEREE) != 0;
			}
		}
		return false;
	}
	
	function is_tournament_manager($tournament_id)
	{
		if ($this->is_admin())
		{
			return true;
		}

		if (is_numeric($tournament_id) && $tournament_id > 0)
		{
			$query = new DbQuery('SELECT flags FROM tournament_regs WHERE tournament_id = ? AND user_id = ?', $tournament_id, $this->user_id);
			if ($row = $query->next())
			{
				list($flags) = $row;
				return ($flags & USER_PERM_MANAGER) != 0;
			}
		}
		return false;
	}
}

// $remember: 0 - do not remember; >0 - remember; <0 - leave as is
function remember_user($remember = 1)
{
	global $_profile;
	
	if ($remember < 0)
	{
		list($auth_key) = Db::record(get_label('user'), 'SELECT auth_key FROM users WHERE id = ?', $_profile->user_id);
		if ($auth_key == '')
		{
			$remember = 0;
		}
	}
	
	if ($remember == 0)
	{
		$auth_key = '';
		setcookie("auth_key", $auth_key);
	}
	else
	{
		// Generate new auth key for each log in (so old auth key can not be used multiple times in case 
		// of cookie hijacking)
		$auth_key = md5(rand_string(10) . $_profile->user_name . 'mafia');
		setcookie("auth_key", $auth_key, time() + 60 * 60 * 24 * 365);
	}
	Db::exec(get_label('user'), 'UPDATE users SET auth_key = ? WHERE id = ?', $auth_key, $_profile->user_id);
}

// $remember: 0 - do not remember; >0 - remember; <0 - leave as is
function login($user_id, $remember = 1)
{
	global $_profile, $_lang;

	$_profile = new Profile($user_id);
	remember_user($remember);
	
	session_unset();
	// Assign variables to session
	session_regenerate_id(true);
	
	$_SESSION['profile'] = $_profile;
	$_SESSION['lang_code'] = get_lang_code($_lang);
	
	if (defined('REDIRECT_ON_LOGIN'))
	{
		header('location: index.php');
	}
	return true;
}

function logout()
{
	global $_profile;
	// Need to delete auth key from database so cookie can no longer be used
	setcookie("auth_key", "", time() - 3600);
	if ($_profile != NULL)
	{
		Db::exec(get_label('user'), 'UPDATE users SET auth_key = \'\' WHERE id = ?', $_profile->user_id);
	}

	$_profile = NULL;
    unset($_SESSION['profile']);
    unset($_SESSION['lang_code']);
	session_unset();
	session_destroy(); 
	$_SESSION = array();
}

function get_session_state()
{
//    echo '<pre>';
//    print_r($_POST);
//    echo '</pre>';

	if (isset($_SESSION['profile']))
	{
        // already logged on
        // now check if the session is expired
		// !!! I have commented this code, because session expiration is anoying.
		// !!! Let's see how it goes without it.
/*	    
		$_profile = $_SESSION['profile'];
		if (!isset($_COOKIE['auth_key']))
	    {
		    $oldtime = $_profile->user_last_active;
            $currenttime = time();
		    if (!empty($oldtime))
            {
                // this is equivalent to 30 minutes
			    if ($oldtime + 30 * 60 < $currenttime)
                { 
				    return SESSION_TIMEOUT;
			    }
		    }
		    $_profile->user_last_active = $currenttime;
	    }*/
		return SESSION_OK;
	}
 
    // Check that cookie is set
    if (isset($_COOKIE['auth_key']))
    {
        $auth_key = $_COOKIE['auth_key'];
 
        // Select user from database where auth key matches (auth keys are unique)
		$query = new DbQuery('SELECT id FROM users WHERE auth_key = ?', $auth_key);
		if ($query->num_rows($query) == 1)
		{
			list ($user_id) = $query->next();
			if (login($user_id, 1))
			{
				return SESSION_OK;
			}
		}

        // not found
        setcookie("auth_key", "", time() - 3600);
    }
    return SESSION_NO_USER;
}

function initiate_session($lang_code = NULL)
{
	global $_session_state, $_profile, $_agent, $_lang;
	global $_default_date_translations, $_http_agent, $labelMenu;

	$session_timeout = 60 * 60 * 24 * 90; // 90 days
	ini_set('session.gc_maxlifetime', $session_timeout);
	ini_set('session.cookie_lifetime', $session_timeout);
	ini_set('session.gc_probability', 0);
	
    session_start();
	// localization
	if (isset($_SESSION['lang_code']))
	{
		$lang_code = $_SESSION['lang_code'];
		$_lang = get_lang_by_code($lang_code);
	}
	else
	{
		$_lang = get_browser_lang();
		$lang_code = $_SESSION['lang_code'] = get_lang_code($_lang);
	}
	
	if (!is_valid_lang_code($lang_code))
	{
		$_lang = LANG_ENGLISH;
		$lang_code = $_SESSION['lang_code'] = get_lang_code(LANG_ENGLISH);
	}

	$_session_state = get_session_state();
	$_profile = NULL;
	if (isset($_SESSION['profile']))
	{
		$_profile = $_SESSION['profile'];
	}

	if (isset($_REQUEST['bck']))
	{
		$back_value = $_REQUEST['bck'];
		if ($back_value == 0)
		{
			$_SESSION['back_list'] = array();
		}
		else if (isset($_SESSION['last_page']))
		{
			if (!isset($_SESSION['back_list']))
			{
				$_SESSION['back_list'] = array();
			}
			$_SESSION['back_list'][] = $_SESSION['last_page'];
		}
		
		$uri = get_page_url();
		$back_beg = strpos($uri, '&bck=');
		if ($back_beg === false)
		{
			$back_beg = strpos($uri, '?bck=');
		}
		if ($back_beg !== false)
		{
			$back_end = strpos($uri, '&', $back_beg + 5);
			if ($back_end !== false)
			{
				$uri = substr($uri, 0, $back_beg + 1) . substr($uri, $back_end + 1);
			}
			else
			{
				$uri = substr($uri, 0, $back_beg);
			}
		}
		header('location: ' . $uri);
	}

	require_once __DIR__ . '/languages/' . $lang_code . '/labels.php';
	$_default_date_translations = include(__DIR__ . '/languages/' . $lang_code . '/date.php');

	if (stripos($_http_agent,"iPod"))
	{
		$_agent = AGENT_IPOD;
	}
	else if(stripos($_http_agent,"iPhone"))
	{
		$_agent = AGENT_IPHONE;
	}
	else if(stripos($_http_agent,"iPad"))
	{
		$_agent = AGENT_IPAD;
	}
	else if(stripos($_http_agent,"Android"))
	{
		$_agent = AGENT_ANDROID;
	}
	else if(stripos($_http_agent,"webOS"))
	{
		$_agent = AGENT_WEBOS;
	}

	/*echo '<pre>';
	print_r($_SESSION);
	echo '</pre>';*/
}

function parse_request_params($params)
{
	$array = array();
	$pos = 0;
	do
	{
		$next = strpos($params, '&', $pos);
		if ($next === false)
		{
			$pair = substr($params, $pos);
		}
		else
		{
			$pair = substr($params, $pos, $next - $pos);
			++$next;
		}
		
		$eq_pos = strpos($pair, '=');
		if ($eq_pos === false)
		{
			$array[$pair] = '';
		}
		else
		{
			$array[substr($pair, 0, $eq_pos)] = substr($pair, $eq_pos + 1);
		}
		$pos = $next;
		
	} while ($pos !== false);
	
	return $array;
}

function add_request_params($request, $params)
{
	$pos = strpos($request, '?');
	if ($pos === false)
	{
		return $request . '?' . $params;
	}
	
	++$pos;
	$row = substr($request, 0, $pos);
	$array1 = parse_request_params(substr($request, $pos));
	$array2 = parse_request_params($params);
	
	foreach ($array2 as $key => $value)
	{
		$array1[$key] = $value;
	}
	
	$sep = '';
	foreach ($array1 as $key => $value)
	{
		$row .= $sep . $key . '=' . $value;
		$sep = '&';
	}
	return $row;
}

function get_back_page($params = NULL)
{
	if (isset($_SESSION['back_list']))
	{
		$list = $_SESSION['back_list'];
		$current_back = count($list) - 1;
		if ($current_back >= 0)
		{
			if ($params != NULL)
			{
				$list[$current_back][1] = add_request_params($list[$current_back][1], $params);
				$_SESSION['back_list'] = $list;
			}
			return $list[$current_back][1];
		}
	}
	return '';
}

function show_back_button($params = NULL)
{
	if (isset($_SESSION['back_list']))
	{
		$list = $_SESSION['back_list'];
		$current_back = count($list) - 1;
		if ($current_back >= 0)
		{
			if ($params != NULL)
			{
				$list[$current_back][1] = add_request_params($list[$current_back][1], $params);
				$_SESSION['back_list'] = $list;
			}
			echo '<a class="back" href="' . $list[$current_back][1] . '" onclick="this.blur();" title="' . get_label('Back to the [[0]]', $list[$current_back][0]) . '"><span>' . get_label('Back') . '</span></a>';
			return;
		}
	}
}

function can_go_back()
{
	return isset($_SESSION['back_list']) && count($_SESSION['back_list']) >= 1;
}

function redirect_back($params = NULL)
{
	if (!isset($_SESSION['back_list']))
	{
		return;
	}
	
	$list = $_SESSION['back_list'];
	$current_back = count($list) - 1;
	if ($current_back < 0)
	{
		return;
	}
	
	if ($params != NULL)
	{
		$list[$current_back][1] = add_request_params($list[$current_back][1], $params);
		$_SESSION['back_list'] = $list;
	}
	throw new RedirectExc($list[$current_back][1]);
}

function show_option($option_value, $current_value, $text, $title = NULL)
{
	$result = false;
	echo '<option value="' . $option_value . '"';
	if ($title != NULL)
	{
		echo ' title="' . $title . '"';
	}
	if ($option_value == $current_value)
	{
		echo ' selected';
		$result = true;
	}
	echo '>' .  $text . '</option>';
	return $result;
}

function get_timezone()
{
	global $_profile;
	if ($_profile != NULL)
	{
		return $_profile->timezone;
	}
	return 'America/Vancouver'; // Later we can do something smarter
}

function dialog_title($title)
{
	echo '<title=' . $title . '>';
}

function get_lock_path()
{
	return $_SERVER['DOCUMENT_ROOT'] . '/lock';
}

function is_site_locked()
{
	return is_dir(get_lock_path());
}

function check_maintenance()
{
	if (is_site_locked())
	{
		throw new FatalExc(get_label('[0] is under maintenance. Please repeat the request later.', PRODUCT_NAME));
	}
}

function lock_site($lock)
{
	$path = get_lock_path();
	if ($lock)
	{
		if (!is_dir($path))
		{
			mkdir($path);
		}
	}
	else if (is_dir($path))
	{
		rmdir($path);
	}
}

?>