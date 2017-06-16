<?php

require_once 'include/session.php';
require_once 'include/user.php';
require_once 'include/city.php';
require_once 'include/country.php';

ob_start();
$result = array();
	
try
{
	initiate_session();
	check_maintenance();

	if (isset($_POST['create_account']))
	{
		$name = trim($_POST['name']);
		$email = trim($_POST['email']);
		if ($email == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('email address')));
		}
		
		create_user($name, $email);
		
		echo
			'<p>' . get_label('Thank you for signing up on Mafia Ratings!') .
			'<br>' . get_label('We have sent you a confirmation email to [0].', $email) .
			'</p><p>' . get_label('Click on the confirmation link in the email to complete your sign up.') . '</p>';
	}
	else if (isset($_POST['reset_pwd']))
	{
		$name = $_POST['name'];
		$email = $_POST['email'];
			
		$query = new DbQuery('SELECT id FROM users WHERE name = ? AND email = ?', $name, $email);
		if (!($row = $query->next()))
		{
			throw new Exc(get_label('Your login name and email do not match. You are using different email for this account.'));
		}
		
		list ($id) = $row;
		$password = rand_string(8);
		Db::begin();
		Db::exec(get_label('user'), 'UPDATE users SET password = ? WHERE id = ?', md5($password), $id);
		if (Db::affected_rows() > 0)
		{
			db_log('user', 'Reset password', NULL, $id);
		}
		Db::commit();
		
		$body = get_label('Your password at') . ' <a href="https://www.mafiaratings.com">' . get_label('Mafia Ratings').'</a> ' . get_label('has been reset to') . ' <b>' . $password . '</b>';
		$text_body = get_label('Your password at') . ' https://www.mafiaratings.com ' . get_label('has been reset to') . ' ' . $password . "\r\n\r\n";
		send_email($email, $body, $text_body, 'Mafia');
		echo  get_label('Your password has been reset. Please check your email for the new password.');
	}
	else
	{
		if ($_profile == NULL)
		{
			throw new Exc(get_label('Unknown [0]', get_label('user')));
		}
		
		if (isset($_POST['change_email']))
		{
			$email = $_POST['email'];
			if ($email != '' && !is_email($email))
			{
				throw new Exc(get_label('[0] is not a valid email address.', $email));
			}

			$flags = $_profile->user_flags | U_FLAG_DEACTIVATED;
			Db::begin();
			Db::exec(get_label('user'), 'UPDATE users SET email = ?, flags = ? WHERE id = ?', $email, $flags, $_profile->user_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = 'email=' . $email . "<br>flags=" . $flags;
				db_log('user', 'Changed', $log_details, $_profile->user_id);
			}
			send_activation_email($_profile->user_id, $_profile->user_name, $email);
			Db::commit();
			
			$_profile->user_email = $email;
			$_profile->user_flags = $flags;
			echo get_label('Your email address has been changed. Please check your email to re-activate yor account.');
		}
		else if (isset($_POST['activate']))
		{
			$email = $_POST['email'];
			if ($email != $_profile->user_email)
			{
				if ($email == '')
				{
					throw new Exc(get_label('Please enter [0].', get_label('email address')));
				}
				
				if (!is_email($email))
				{
					throw new Exc(get_label('[0] is not a valid email address.', $email));
				}
				Db::begin();
				Db::exec(get_label('user'), 'UPDATE users SET email = ? WHERE id = ?', $email, $_profile->user_id);
				if (Db::affected_rows() > 0)
				{
					$log_details = 'email=' . $email;
					db_log('user', 'Changed', $log_details, $_profile->user_id);
				}
				$_profile->user_email = $email;
				Db::commit();
			}
			send_activation_email($_profile->user_id, $_profile->user_name, $email);
			
			echo
				'<p>' . get_label('Thank you for activating your account!') .
				'<br>' . get_label('We have sent you a confirmation email to [0].', $email) .
				'</p><p>' . get_label('Click on the confirmation link in the email to finalize your account activation.') . '</p>';
		}
		else if (isset($_POST['init']))
		{
			$already_member = (count($_profile->clubs) > 0);
		
			$flags = $_profile->user_flags;
			if ($_POST['male'])
			{
				$flags |= U_FLAG_MALE;
			}
			else
			{
				$flags &= ~U_FLAG_MALE;
			}
			
			$password = $_POST['pwd'];
			$confirm_password = $_POST['confirm'];
			$langs = $_POST['langs'];
			$phone = $_POST['phone'];
			
			check_password($password, $confirm_password);
			$flags = $flags & ~(U_FLAG_DEACTIVATED | U_FLAG_NO_PASSWORD);
			$log_message = 'Activated';
			Db::begin();
			$country_id = retrieve_country_id($_POST['country']);
			$city_id = retrieve_city_id($_POST['city'], $country_id, $_profile->timezone);
			
			$club_id = NULL;
			$club_name = 'no';
			$update_clubs = false;
			if (isset($_POST['club']))
			{
				$club_id = $_POST['club'];
				if (!isset($_profile->clubs[$club_id]))
				{
					list($club_name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $club_id);
					Db::exec(get_label('membership'), 'INSERT INTO user_clubs (user_id, club_id, flags) values (?, ?, ' . UC_NEW_PLAYER_FLAGS . ')', $_profile->user_id, $club_id);
					db_log('user', 'Joined the club', NULL, $_profile->user_id, $club_id);
					$update_clubs = true;
				}
				else
				{
					$club_name = $_profile->clubs[$club_id]->name;
				}
			}
			
			Db::exec(
				get_label('user'),
				'UPDATE users SET flags = ?, languages = ?, phone = ?, city_id = ?, club_id = ?, password = ? WHERE id = ?',
				$flags, $langs, $phone, $city_id, $club_id, md5($password), $_profile->user_id);
			if (Db::affected_rows() > 0)
			{
				list($city_name) = Db::record(get_label('city'), 'SELECT name_en FROM cities WHERE id = ?', $city_id);
				$log_details =
					'flags=' . $flags . 
					"<br>languages=" . $langs .
					"<br>pasword=...<br>city=" . $city_name . ' (' . $city_id . 
					')<br>club=' . $club_name;
				if ($club_id != NULL)
				{
					$log_details .= ' (' . $club_id . ')';
				}
				db_log('user', $log_message, $log_details, $_profile->user_id);
			}
			Db::commit();
			$_profile->city_id = $city_id;
			$_profile->country_id = $country_id;
			$_profile->user_langs = $langs;
			$_profile->user_flags = $flags;
			$_profile->user_club_id = $club_id;
			$_profile->user_phone = $phone;
			if ($update_clubs)
			{
				$_profile->update_clubs();
			}
		}
		else if (isset($_POST['change_pwd']))
		{
			$old_pwd = $_POST['old_pwd'];
			$new_pwd = $_POST['new_pwd'];
			$confirm_pwd = $_POST['confirm_pwd'];
			
			check_password($new_pwd, $confirm_pwd);
			Db::exec(get_label('user'), 'UPDATE users SET password = ? WHERE id = ? AND password = ?', md5($new_pwd), $_profile->user_id, md5($old_pwd));
			if (Db::affected_rows() != 1)
			{
				throw new Exc(get_label('Wrong password.'));
			}
			echo get_label('Your password has been changed.');
		}
		else if (isset($_POST['create_club']))
		{
			$url = check_url($_POST['url']);
			
			$name = trim($_POST['name']);
			check_club_name($name);

			$langs = $_POST['langs'];
			if ($langs == 0)
			{
				throw new Exc(get_label('Please select at least one language.'));
			}
			
			$email = trim($_POST['email']);
			if ($email != '' && !is_email($email))
			{
				throw new Exc(get_label('[0] is not a valid email address.', $email));
			}
			
			$phone = $_POST['phone'];
			
			Db::begin();
			$city_id = retrieve_city_id($_POST['city'], retrieve_country_id($_POST['country']), $_profile->timezone);
			
			Db::exec(
				get_label('club'), 
				'INSERT INTO club_requests (user_id, name, langs, web_site, email, phone, city_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
				$_profile->user_id, $name, $langs, $url, $email, $phone, $city_id);
				
			list ($request_id) = Db::record(get_label('club'), 'SELECT LAST_INSERT_ID()');
			list ($city_name) = Db::record(get_label('city'), 'SELECT name_en FROM cities WHERE id = ?', $city_id);
			$log_details = 
				'name=' . $name .
				"<br>langs=" . $langs .
				"<br>url=" . $url .
				"<br>email=" . $email .
				"<br>phone=" . $phone .
				"<br>city=" . $city_name . ' (' . $city_id . ')';
			db_log('club_request', 'Created', $log_details, $request_id);
			
			// send request to admin
			$query = new DbQuery('SELECT id, name, email, def_lang FROM users WHERE (flags & ' . U_PERM_ADMIN . ') <> 0 and email <> \'\'');
			while ($row = $query->next())
			{
				list($admin_id, $admin_name, $admin_email, $admin_def_lang) = $row;
				$lang = get_lang_code($admin_def_lang);
				list($subj, $body, $text_body) = include 'include/languages/' . $lang . '/email_create_club.php';
				
				$tags = array(
					'uname' => new Tag($admin_name),
					'sender' => new Tag($_profile->user_name));
				$body = parse_tags($body, $tags);
				$text_body = parse_tags($text_body, $tags);
				send_email($admin_email, $body, $text_body, $subj);
			}
			
			Db::commit();
			
			echo  
				'<p>' .
				get_label('Your request for creating the club has been sent to the administration. Site administrators will review your club information.') .
				'</p><p>' .
				get_label('Please wait for the confirmation email. It takes from a few hours to three days depending on administrators load.') .
				'</p>';
		}
		else if (isset($_POST['join_club']))
		{
			$id = $_POST['id'];
			list ($count) = Db::record(get_label('membership'), 'SELECT count(*) FROM user_clubs WHERE user_id = ? AND club_id = ?', $_profile->user_id, $id);
			if ($count == 0)
			{
				Db::begin();
				Db::exec(get_label('membership'), 'INSERT INTO user_clubs (user_id, club_id, flags) values (?, ?, ' . UC_NEW_PLAYER_FLAGS . ')', $_profile->user_id, $id);
				db_log('user', 'Joined the club', NULL, $_profile->user_id, $id);
				Db::commit();
				$_profile->update_clubs();
			}
		}
		else if (isset($_POST['quit_club']))
		{
			$id = $_POST['id'];
			Db::begin();
			
			Db::exec(get_label('membership'), 'DELETE FROM user_clubs WHERE user_id = ? AND club_id = ?', $_profile->user_id, $id);
			if (Db::affected_rows() > 0)
			{
				db_log('user', 'Left the club', NULL, $_profile->user_id, $id);
			}
			Db::commit();
			$_profile->update_clubs();
		}
		else if (isset($_POST['edit_account']))
		{
			$name = trim($_POST['name']);
			if ($name != $_profile->user_name)
			{
				check_user_name($name);
			}
			
			$club_id = $_POST['club'];
			if ($club_id <= 0)
			{
				$club_id = NULL;
			}
			
			$city_id = retrieve_city_id($_POST['city'], retrieve_country_id($_POST['country']), $_profile->timezone);
			$langs = $_POST['langs'];
			$phone = $_POST['phone'];
			$flags = $_profile->user_flags;
			if ($_POST['message_notify'])
			{
				$flags |= U_FLAG_MESSAGE_NOTIFY;
			}
			else
			{
				$flags &= ~U_FLAG_MESSAGE_NOTIFY;
			}
			if ($_POST['private_message_notify'])
			{
				$flags |= U_FLAG_PHOTO_NOTIFY;
			}
			else
			{
				$flags &= ~U_FLAG_PHOTO_NOTIFY;
			}
			if ($_POST['male'])
			{
				$flags |= U_FLAG_MALE;
			}
			else
			{
				$flags &= ~U_FLAG_MALE;
			}
			
			$update_clubs = false;
			Db::begin();
			Db::exec(
				get_label('user'), 
				'UPDATE users SET name = ?, flags = ?, city_id = ?, languages = ?, phone = ?, club_id = ? WHERE id = ?',
				$name, $flags, $city_id, $langs, $phone, $club_id, $_profile->user_id);
			if (Db::affected_rows() > 0)
			{
				if ($club_id != NULL && !isset($_profile->clubs[$club_id]))
				{
					Db::exec(get_label('membership'), 'INSERT INTO user_clubs (user_id, club_id, flags) values (?, ?, ' . UC_NEW_PLAYER_FLAGS . ')', $_profile->user_id, $club_id);
					db_log('user', 'Joined the club', NULL, $_profile->user_id, $club_id);
					$update_clubs = true;
				}
				
				
				$log_details = 
					'flags=' . $flags .
					"<br>name=" . $name . 
					"<br>city=" . $_POST['city'] . ' (' . $city_id . ')' .
					"<br>langs=" . $langs;
					
				if (!is_null($club_id))
				{
					list ($club_name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $club_id);
					$log_details .= '<br>club=' . $club_name . ' (' . $club_id . ')';
				}
				db_log('user', 'Changed', $log_details, $_profile->user_id);
			}
			Db::commit();
					
			$_profile->user_name = $name;
			$_profile->user_flags = $flags;
			$_profile->user_langs = $langs;
			$_profile->user_phone = $phone;
			$_profile->user_club_id = $club_id;
			if ($_profile->city_id != $city_id)
			{
				$_profile->city_id = $city_id;
				list ($_profile->country_id, $_profile->timezone) =
					Db::record(get_label('city'), 'SELECT country_id, timezone FROM cities WHERE id = ?', $city_id);
			}
			if ($update_clubs)
			{
				$_profile->update_clubs();
			}
		}
		else if (isset($_POST['change_name']))
		{
			$name = $_POST['name'];
			$password = $_POST['password'];
		}
		else
		{
			throw new Exc(get_label('Unknown [0]', get_label('request')));
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
	$result['message'] = $message;
}

echo json_encode($result);

?>