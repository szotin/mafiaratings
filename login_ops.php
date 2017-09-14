<?php

require_once 'include/session.php';
require_once 'include/rand_str.php';
require_once 'include/log.php';

ob_start();
$result = array();
	
try
{
	initiate_session();

	if (isset($_POST['token']))
	{
		$token = md5(rand_string(8));
		$_SESSION['login_token'] = $token;
		$result['token'] = $token;
	}
	else if (isset($_POST['login']))
	{
		if (!isset($_POST['username']))
		{
			db_log('login', 'No user name', NULL);
			throw new Exc(get_label('Login attempt failed'));
		}
		$user_name = $_POST['username'];
		
		$query = new DbQuery('SELECT id, password FROM users WHERE name = ?', $user_name);
		if ($query->num_rows($query) != 1)
		{
			db_log('login', 'User not found', 'name=' . $user_name);
			throw new Exc(get_label('Login attempt failed'));
		}
		list ($user_id, $password) = $query->next();
		
		if (!isset($_SESSION['login_token']))
		{
			db_log('login', 'No token', NULL, $user_id);
			throw new Exc(get_label('Login attempt failed'));
		}
		
		if (!isset($_POST['id']))
		{
			db_log('login', 'No id', NULL, $user_id);
			throw new Exc(get_label('Login attempt failed'));
		}
		$sec_id = $_POST['id'];
		
		/*throw new Exc(
			'password: ' . $password .
			'; token: ' . $_SESSION['login_token'] .	
			'; secId: ' . md5($password . $_SESSION['login_token'] . $user_name) .
			'; clientSecId: ' . $sec_id);*/
		
		if (md5($password . $_SESSION['login_token'] . $user_name) != $sec_id)
		{
			db_log('login', 'Invalid password', NULL, $user_id);
			//$details = '<br>token = ' . $_SESSION['login_token'] . '<br>raw id = ' . $password . $_SESSION['login_token'] . $user_name . '<br>id = ' . md5($password . $_SESSION['login_token'] . $user_name) . '<br>client id = ' . $sec_id;
			throw new Exc(get_label('Login attempt failed'));
		}
		
		$remember = (isset($_POST['remember']) && $_POST['remember']) ? 1 : 0;
		if (!login($user_id, $remember))
		{
			throw new Exc(get_label('Login attempt failed'));
		}
	}
	else if (isset($_POST['logout']))
	{
		logout();
	}
	else if (isset($_REQUEST['mobile']))
	{
		switch ($_REQUEST['mobile'])
		{
			case SITE_STYLE_DESKTOP:
				if ($_agent != AGENT_BROWSER || (isset($_SESSION['mobile']) && $_SESSION['mobile']))
				{
					$_SESSION['mobile'] = false;
				}
				break;
			case SITE_STYLE_MOBILE:
				if ($_agent == AGENT_BROWSER || (isset($_SESSION['mobile']) && !$_SESSION['mobile']))
				{
					$_SESSION['mobile'] = true;
				}
				break;
		}
	}
	else if (isset($_REQUEST['browser_lang']))
	{
		$browser_lang = $_REQUEST['browser_lang'];
		$_lang_code = $_SESSION['lang_code'] = $browser_lang;
		if (isset($_profile) && $_profile != NULL)
		{
			$_profile->user_def_lang = get_lang_by_code($browser_lang);
			Db::begin();
			Db::exec(get_label('user'), 'UPDATE users SET def_lang = ? WHERE id = ?', $_profile->user_def_lang, $_profile->user_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = 'def_lang=' . $_profile->user_def_lang;
				db_log('user', 'Changed', $log_details, $_profile->user_id);
			}
			Db::commit();
			$_profile->update();
		}
	}
}
catch (Exception $e)
{
	Db::rollback();
	Exc::log($e);
	$result['error'] = $e->getMessage();
}

$message = ob_get_contents();
ob_end_clean();
if ($message != '')
{
	if (isset($result['message']))
	{
		$message = $result['message'] . '<hr>' . $message;
	}
	$result['message'] = $message;
}

echo json_encode($result);

?>