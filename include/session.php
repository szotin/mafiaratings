<?php

require_once 'include/branding.php';
require_once 'include/db.php';
require_once 'include/names.php';
require_once 'include/rand_str.php';
require_once 'include/constants.php';
require_once 'include/languages.php';
require_once 'include/localization.php';

$_session_state = SESSION_NO_USER;
$_agent = AGENT_BROWSER;
$_http_agent = '';
if (isset($_SERVER['HTTP_USER_AGENT']))
{
	$_http_agent = $_SERVER['HTTP_USER_AGENT'];
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
	public $rules_id;
	public $scoring_id;
	public $price;
}

class Profile
{
	public $user_id;
	public $user_name;
	public $user_langs;
	public $user_def_lang;
	public $user_email;
	public $user_phone;
	public $user_flags;
	public $user_club_id;
	public $city_id;
	public $city;
	public $region_id;
	public $region;
	public $country_id;
	public $country;
	public $timezone;
	public $user_club_flags; // combination of all clubs flags that this user is member of. This is just an optimization. If we want to know if user is moderator in one of the clubs we check it here instead of looping through all his clubs.
	public $clubs;
	public $forum_last_view;
	public $user_last_active;
	
	function __construct($user_id)
	{
		global $_lang_code;
		list(
			$this->user_id, $this->user_name, $this->user_langs, $this->user_def_lang,
			$this->user_email, $this->user_phone, $this->user_flags, $this->user_club_id, $this->forum_last_view,
			$this->city_id, $this->city, $this->region_id, $this->region, $this->country_id, $this->country, $this->timezone) = 
				Db::record(
					get_label('user'), 
					'SELECT u.id, u.name, u.languages, u.def_lang, u.email, u.phone, u.flags, u.club_id, u.forum_last_view, i.id, i.name_' . $_lang_code .
						', r.id, r.name_' . $_lang_code .
						', i.country_id, o.name_' . $_lang_code . ', i.timezone FROM users u' .
						' JOIN cities i ON u.city_id = i.id' .
						' LEFT OUTER JOIN cities r ON i.area_id = r.id' .
						' JOIN countries o ON o.id = i.country_id' .
						' WHERE u.id = ?', 
					$user_id);
		if ($this->region_id == NULL)
		{
			$this->region_id = $this->city_id;
			$this->region = $this->city;
		}
			
		$this->update_clubs();
		$this->user_last_active = time();
		$this->new_event = NULL;
	}
	
	function update()
	{
		global $_lang_code;
		list(
			$this->user_id, $this->user_name, $this->user_langs, $this->user_def_lang,
			$this->user_email, $this->user_phone, $this->user_flags, $this->user_club_id, $this->forum_last_view,
			$this->city_id, $this->city, $this->country_id, $this->country, $this->timezone) = 
				Db::record(
					get_label('user'), 
					'SELECT u.id, u.name, u.languages, u.def_lang, u.email, u.phone, u.flags, u.club_id, u.forum_last_view, i.id, i.name_' . $_lang_code . ', i.country_id, o.name_' . $_lang_code . ', i.timezone FROM users u' .
						' JOIN cities i ON u.city_id = i.id' .
						' JOIN countries o ON o.id = i.country_id' .
						' WHERE u.id = ?',
					$this->user_id);
			
		$this->update_clubs();
		$this->user_last_active = time();
		$this->new_event = NULL;
	}
	
	function update_clubs()
	{
		global $_lang_code;
	
		$this->user_club_flags = 0;
		$this->clubs = array();
		$sep = '';
		if ($this->is_admin())
		{
			$query = new DbQuery(
				'SELECT c.id, c.name, ' . (UC_PERM_PLAYER | UC_PERM_MODER| UC_PERM_MANAGER) . ', c.flags, c.langs, i.id, i.name_' . $_lang_code . ', i.country_id, o.name_' . $_lang_code . ', i.timezone, c.rules_id, c.scoring_id, c.price FROM clubs c' .
					' JOIN cities i ON c.city_id = i.id ' .
					' JOIN countries o ON i.country_id = o.id ' .
					' ORDER BY c.name');
		}
		else
		{
			$query = new DbQuery(
				'SELECT c.id, c.name, uc.flags, c.flags, c.langs, i.id, i.name_' . $_lang_code . ', i.country_id, o.name_' . $_lang_code . ', i.timezone, c.rules_id, c.scoring_id, c.price FROM user_clubs uc' .
					' JOIN clubs c ON c.id = uc.club_id' .
					' JOIN cities i ON i.id = c.city_id' .
					' JOIN countries o ON i.country_id = o.id ' .
					' WHERE uc.user_id = ?' .
					' AND (uc.flags & ' . UC_FLAG_BANNED . ') = 0' .
					' ORDER BY c.name',
				$this->user_id, $this->user_club_id);
		}
		if ($query)
		{
			while ($row = $query->next())
			{
				$pc = new ProfileClub();
				list($pc->id, $pc->name, $pc->flags, $pc->club_flags, $pc->langs, $pc->city_id, $pc->city, $pc->country_id, $pc->country, $pc->timezone, $pc->rules_id, $pc->scoring_id, $pc->price) = $row;
				$this->clubs[$pc->id] = $pc;
				$this->user_club_flags |= $pc->flags;
			}
		}
	}
	
	function update_club_flags()
	{
		$this->user_club_flags = 0;
		foreach ($this->clubs as $club)
		{
			$this->user_club_flags |= $club->flags;
		}
	}
	
	function is_admin()
	{
		return ($this->user_flags & U_PERM_ADMIN) != 0;
	}
	
	function has_perm($perm, $club_id = -1)
	{
		if ($club_id > 0)
		{
			if (isset($this->clubs[$club_id]))
			{
				return ($this->clubs[$club_id]->flags & $perm) != 0;
			}
			return false;
		}
		return ($this->user_club_flags & $perm) != 0;
	}
	
	function is_moder($club_id = -1)
	{
		return $this->has_perm(UC_PERM_MODER, $club_id);
	}
	
	function is_manager($club_id = -1)
	{
		return $this->has_perm(UC_PERM_MANAGER, $club_id);
	}
	
	function is_player($club_id = -1)
	{
		return $this->has_perm(UC_PERM_PLAYER, $club_id);
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
		setcookie("auth_key", $auth_key, time() - 360);
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
	global $_profile;

	try
	{
		$_profile = new Profile($user_id);
		remember_user($remember);
		
		session_unset();
		// Assign variables to session
		session_regenerate_id(true);
		
		$_SESSION['profile'] = $_profile;
		$_SESSION['lang_code'] = get_lang_code($_profile->user_def_lang);
		
		if (defined('REDIRECT_ON_LOGIN'))
		{
			header('location: index.php');
		}
	}
	catch (Exception $e)
	{
		Exc::log($e, true, 'login');
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
		Db::exec(get_label('user'), 'UPDATE users SET auth_key = \'\' WHERE name = ?', $_profile->user_name);
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

function initiate_session()
{
	global $_session_state, $_profile, $_agent, $_lang_code;
	global $_default_date_translations, $_http_agent, $labelMenu;

    session_start();
	// localization
	if (!isset($_SESSION['lang_code']))
	{
		$_SESSION['lang_code'] = get_lang_code(get_browser_lang());
	}
	$_lang_code = $_SESSION['lang_code'];

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

	require_once 'include/languages/' . $_lang_code . '/labels.php';
	$_default_date_translations = include('include/languages/' . $_lang_code . '/date.php');

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

function is_mobile()
{
	global $_agent;
	
	if (isset($_SESSION['mobile']))
	{
		return $_SESSION['mobile'];
	}
	return $_agent != AGENT_BROWSER;
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
	
	if (is_mobile())
	{
		return 'menu.php';
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
	
	if (is_mobile())
	{
		echo '<a class="back" href="menu.php" onclick="this.blur();"><span>' . get_label('Back to the [[0]]', get_label('Menu')) . '</span></a>';
	}
}

function can_go_back()
{
	return is_mobile() || (isset($_SESSION['back_list']) && count($_SESSION['back_list']) >= 1);
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

function check_permissions($permissions, $club_id = -1)
{
	global $_profile;

	if ($_profile == NULL)
	{
		return ($permissions & PERM_STRANGER) != 0;
	}
	
	if (($permissions & PERM_USER) != 0)
	{
		return true;
	}
	
	if (($permissions & $_profile->user_flags & U_PERM_MASK) != 0)
	{
		return true;
	}
	return $_profile->has_perm($permissions & UC_PERM_MASK, $club_id);
}

function show_option($option_value, $current_value, $text)
{
	$result = false;
	echo '<option value="' . $option_value . '"';
	if ($option_value == $current_value)
	{
		echo ' selected';
		$result = true;
	}
	echo '>' .  $text . '</option>';
	return $result;
}

function dialog_title($title)
{
	echo '<title=' . $title . '>';
}

function check_maintenance()
{
	if (is_dir('lock'))
	{
		throw new FatalExc(get_label('[0] is under maintenance. Please repeat the request later.', PRODUCT_NAME));
	}
}

function lock_site($lock)
{
	if ($lock)
	{
		mkdir('lock');
	}
	else
	{
		rmdir('lock');
	}
}

?>