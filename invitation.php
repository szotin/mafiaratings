<?php

require_once 'include/page_base.php';
require_once 'include/url.php';
require_once 'include/user.php';
require_once 'include/event.php';

class Page extends PageBase
{
	private $event;
	
	private $name;
	private $nick;
	private $email;
	private $phone;
	private $password;
	private $confirm;
	private $uploaded;
	private $club_id;
	private $created;
	
	protected function prepare()
	{
		global $_profile;
	
		if (!isset($_REQUEST['event']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('event')));
		}
		
		$this->event = new Event();
		$this->event->load($_REQUEST['event']);
		if ($this->event->timestamp < time())
		{
			throw new FatalExc(get_label('Too late to accept the event invitation.'));
		}
		
		if ($_profile != NULL)
		{
			throw new RedirectExc('event_info.php?id=' . $this->event->id);
		}
		
		$this->name = '';
		if (isset($_REQUEST['name']))
		{
			$this->name = $_REQUEST['name'];
		}
		
		$this->nick = '';
		if (isset($_REQUEST['nick']))
		{
			$this->nick = $_REQUEST['nick'];
		}
		
		$this->phone = '';
		if (isset($_REQUEST['phone']))
		{
			$this->phone = $_REQUEST['phone'];
		}
		
		$this->email = '';
		if (isset($_REQUEST['email']))
		{
			$this->email = $_REQUEST['email'];
		}
		
		$this->password = '';
		if (isset($_REQUEST['pwd']))
		{
			$this->password = $_REQUEST['pwd'];
		}
		
		$this->confirm = '';
		if (isset($_REQUEST['confirm']))
		{
			$this->confirm = $_REQUEST['confirm'];
		}
		
		$this->uploaded = NULL;
		if (isset($_REQUEST['uploaded']))
		{
			$this->uploaded = $_REQUEST['uploaded'];
		}
		
		if (isset($_FILES['photo']) && $_FILES['photo']['error'] <= 0)
		{
			if ($this->uploaded != NULL)
			{
				unlink(USER_PICS_DIR . TNAILS_DIR . $this->uploaded . '.png');
				unlink(USER_PICS_DIR . $this->uploaded . '.png');
				$this->uploaded = NULL;
			}
			$uploaded = 'tmp_' . md5(rand_string(10));
			upload_pic('photo', USER_PICS_DIR, $uploaded);
			$this->uploaded = $uploaded;
		}
		
		$this->club_id = $this->event->club_id;
		if (isset($_REQUEST['club']))
		{
			$this->club_id = $_REQUEST['club'];
		}
		
		$this->created = false;
		
		if (isset($_REQUEST['create']))
		{
			check_user_name($this->name);
			check_password($this->password, $this->confirm);
			
			if ($this->email == '')
			{
				throw new Exc(get_label('Please enter [0].', get_label('email address')));
			}
			
			if (!is_email($this->email))
			{
				throw new Exc(get_label('[0] is not a valid email address.', $this->email));
			}
			
			if ($this->uploaded == NULL)
			{
				throw new Exc(get_label('Please enter [0].', get_label('your photo')));
			}
			
			$nick = $this->nick;
			if ($nick == '')
			{
				$nick = $this->name;
			}
			check_nickname($nick, $this->event->id);
			
			$lang = get_next_lang(LANG_NO, $this->event->langs);
			if (!is_valid_lang($lang))
			{
				$lang = LANG_RUSSIAN;
			}
			
			list ($city_id) = Db::record(get_label('club'), 'SELECT city_id FROM clubs WHERE id = ?', $this->club_id);
			
			$flags = U_FLAG_MESSAGE_NOTIFY | U_FLAG_PHOTO_NOTIFY | U_FLAG_DEACTIVATED | 0x800; // 0x800 - indicates that user has photo
			if (isset($_REQUEST['male']) && $_REQUEST['male'])
			{
				$flags |= U_FLAG_MALE;
			}
			
			Db::begin();
			
			Db::exec(
				get_label('user'), 
				'INSERT INTO users (name, password, email, phone, club_id, flags, languages, reg_time, def_lang, city_id) ' .
					'VALUES (?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), ?, ?)', 
				$this->name, md5($this->password), $this->email, $this->phone, $this->club_id, $flags, $this->event->langs, $lang, $city_id);
			list ($user_id) = Db::record(get_label('user'), 'SELECT LAST_INSERT_ID()');
			$log_details = 
				'name=' . $this->name .
				"<br>email=" . $this->email .
				"<br>flags=" . $flags .
				"<br>langs=" . $this->event->langs .
				"<br>def_lang=" . $lang;
			db_log('user', 'Created and activated', $log_details, $user_id);
			
			Db::exec(get_label('user'), 'INSERT INTO user_clubs (user_id, club_id, flags) VALUES (?, ?, ' . UC_NEW_PLAYER_FLAGS . ')', $user_id, $this->club_id);
			db_log('user', 'Joined the club', NULL, $user_id, $this->club_id);
			
			Db::exec(
				get_label('registration'), 
				'INSERT INTO event_users (event_id, user_id, coming_odds, people_with_me, late) ' .
					'VALUES (?, ?, 100, 0, 0)',
				$this->event->id, $user_id);
			Db::exec(
				get_label('registration'), 
				'INSERT INTO registrations (club_id, user_id, nick_name, duration, start_time, event_id) ' .
					'SELECT club_id, ?, ?, duration, start_time, id FROM events WHERE id = ?',
				$user_id, $nick, $this->event->id);
			
			rename(USER_PICS_DIR . TNAILS_DIR . $this->uploaded . '.png', USER_PICS_DIR . TNAILS_DIR . $user_id . '.png');
			rename(USER_PICS_DIR . ICONS_DIR . $this->uploaded . '.png', USER_PICS_DIR . ICONS_DIR . $user_id . '.png');
			rename(USER_PICS_DIR . $this->uploaded . '.png', USER_PICS_DIR . $user_id . '.png');
			
			send_activation_email($user_id, $this->name, $this->email);
			Db::commit();
			
			$this->created = true;
		}
	}
	
	protected function show_title()
	{
		echo '<table class="head" width="100%"><tr>';
		echo '<td valign="top">' . $this->standard_title() . '<br><h4>' . format_date('l, F d, Y, H:i', $this->event->timestamp, $this->event->timezone) . '</h4>';
		echo '<br><h4>' . $this->event->addr . ', ' . $this->event->city . ', ' . $this->event->country . '</h4>';
		echo '</td><td valign="top" align="right">';
		$this->event->show_pic(TNAILS_DIR, 0, 0, false);
		echo '</td></tr></table>';
	}
	
	protected function show_body()
	{
		echo '<table class="transp" width="100%"><tr><td align="right">';
		echo '<input type="submit" class="btn norm" value="' . get_label('Who\'s coming') . '" onclick="whoComing()">';
		echo '</td></tr></table>';
		
		echo '<form method="post" name="createForm" enctype="multipart/form-data">';
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td colspan="2" align="center"><br>' . get_label('Hello! You are invited to participate in the [0] in [1]. Please create an account in [2] in order to accept the invitation.', $this->event->name, $this->event->club_name, PRODUCT_NAME) . '<br><br></td></tr>';
		echo '<tr><td class="dark" width="160">'.get_label('User name').':</td><td>' . get_label('User name must be unique. If someone is already using your favourite name, use something else and enter it as your nick-name.') . '<br><input name="name" value="' . $this->name . '"></td></tr>';
		echo '<tr><td class="dark">' . get_label('Nick name') . ':</td><td>' . get_label('Please enter the nick-name you want to use. Leave it empty if it is the same as user name.') . '<br><input name="nick" value="' . $this->nick . '"></td></tr>';
		echo '<tr><td class="dark">' . get_label('Email') . ':</td><td>' . get_label('Please give your real email. It will be used for your account activation.') . '<br><input name="email" value="' . $this->email . '"></td></tr>';
		echo '<tr><td class="dark">' . get_label('Phone') . ':</td><td>' . get_label('Phone is optional. You can give us your phone if you do not mind us calling you.') . '<br><input name="phone" value="' . $this->phone . '"></td></tr>';
		
		echo '<tr><td class="dark">'.get_label('Photo').':</td><td>';
		if ($this->uploaded != NULL)
		{
			echo '<img src="' . USER_PICS_DIR . TNAILS_DIR . $this->uploaded . '.png' .'"><br><input type="hidden" name="uploaded" value="' . $this->uploaded . '">';
		}
		echo '<input type="file" name="photo"></td></tr>';
		
		echo '<tr><td class="dark">' . get_label('Gender') . ':</td><td>';
		echo '<input type="radio" name="male" value="1" checked/>'.get_label('male').'<br>';
		echo '<input type="radio" name="male" value="0"/>'.get_label('female');
		echo '</td></tr>';
		
		echo '<tr><td class="dark">' . get_label('Club') . ':</td><td>' . get_label('Please enter your favourite club. The club you want to represent on championships.') . '<br><select name="club">';
		$query = new DbQuery('SELECT id, name FROM clubs ORDER BY name');
		while ($row = $query->next())
		{
			show_option($row[0], $this->club_id, $row[1]);
		}
		echo '</select></td></tr>';

		echo '<tr><td class="dark">'.get_label('Password').':</td><td><input type="password" name="pwd" value="' . $this->password . '"></td></tr>';
		echo '<tr><td class="dark">'.get_label('Confirm password').':</td><td><input type="password" name="confirm" value="' . $this->confirm . '"></td></tr>';
		
		echo '</table>';
		echo '<p><input value="'.get_label('Accept').'" name="create" type="submit" class="btn norm"></p></form>';
	}
	
	protected function js_on_load()
	{
		if ($this->created)
		{
			$message = get_label('Thank you for registering in [1] and for attending [0]. The activation email has been sent to you. Please click the link in this email to complete your registration.', $this->event->name, PRODUCT_NAME);
?>
			var message = "<?php echo $message; ?>";
			var redir = "event_info.php?id=<?php echo $this->event->id; ?>";
			dlg.info(message, null, null, function() { window.location.replace(redir); });
<?php			
		}
	}
	
	protected function js()
	{
?>
		function whoComing()
		{
			dlg.info('<?php $this->event->show_details(true, false); ?>', null, 800);
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Invitation'), PERM_ALL);

?>
