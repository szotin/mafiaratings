<?php

require_once 'include/session.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/event.php';
require_once 'include/message.php';
require_once 'include/email.php';
require_once 'include/editor.php';
require_once 'include/ccc_filter.php';

define('FORUM_OBJ_REPLY', 0);
define('FORUM_OBJ_NO', 1);
define('FORUM_OBJ_EVENT', 2);
define('FORUM_OBJ_PHOTO', 3);
define('FORUM_OBJ_GAME', 4);
define('FORUM_OBJ_USER', 5);

define('FORUM_PAGE_SIZE', 5);
define('FORUM_MAX_REPLIES', 3);

define('FORUM_SEND_FLAG_CHOOSE_LANG', 1);
define('FORUM_SEND_FLAG_SHOW_PRIVATE', 2);
define('FORUM_SEND_FLAG_PRIVATE', 4);
define('FORUM_SEND_FLAG_PRIVATE_DISABLED', 8);

class ForumMessage
{
	public $id;
	public $obj;
	public $obj_id;
	public $club_id;
	public $viewers;
	public $body;
	public $send_time;
	public $user_id;
	public $user_name;
	public $user_flags;
	public $timezone;
	
	function __construct($input)
	{
		global $_profile;
	
		if (is_numeric($input))
		{
			$input = Db::record(
				get_label('message'), 
				'SELECT m.id, m.obj, m.obj_id, m.user_id, u.name, u.flags, m.body, m.send_time, m.viewers, m.club_id FROM messages m, users u' .
				' WHERE m.user_id = u.id AND m.id = ?', $input, ForumMessage::viewers_condition(' AND'));
		}
		
		$this->timezone = 'America/Vancouver';
		if ($_profile != NULL)
		{
			$this->timezone = $_profile->timezone;
		}
		
		list (
			$this->id, $this->obj, $this->obj_id, $this->user_id, $this->user_name, $this->user_flags,
			$this->body, $this->send_time, $this->viewers, $this->club_id) = $input;
	}
	
	function show($view_obj, $view_obj_id, $can_send, $show_avatar = true)
	{
		echo '<tr>';
		if ($show_avatar)
		{
			echo '<td rowspan="2" width="' . ICON_WIDTH . '" valign="top" align="center">';
			echo '<a href="user_messages.php?id=' . $this->user_id . '&bck=1">';
			show_user_pic($this->user_id, $this->user_name, $this->user_flags, ICONS_DIR);
			echo '</a>';
			if ($can_send)
			{
				echo '<br><center><a href="forum.php?id=' . $this->id . '&bck=1" title="'.get_label('Reply to this message').'">'.get_label('Reply').'</a></center>';
			}
			echo '</td>';
		}
		echo '<td width="100%" class="forum_mark">';
		$reply_date = format_date('H:i, d M y', $this->send_time, $this->timezone);
		$reply_user = '<a href="user_messages.php?id=' . $this->user_id . '&bck=1">' . cut_long_name($this->user_name, 30) . '</a>';
		switch ($this->obj)
		{
			case FORUM_OBJ_REPLY:
				$reply_private = '';
				if ($this->viewers >= FOR_MANAGERS)
				{
					$reply_private = get_label('privately');
				}
				$reply_to = '';
				if ($this->obj != $view_obj || $this->obj_id != $view_obj_id)
				{
					list ($p_name, $p_send_time) = 
						Db::record(get_label('message'), 'SELECT u.name, m.send_time FROM messages m, users u WHERE m.user_id = u.id AND m.id = ?', $this->obj_id);
					$reply_to = get_label(
						' to the [0]message of [1] at [2][3]',
						'<a href="forum.php?id=' . $this->obj_id . '&rep=0&bck=1">',
						cut_long_name($p_name, 30),
						format_date('H:i, d M y', $p_send_time, $this->timezone),
						'</a>');
				}
				echo get_label('[0], [1] replied [2][3]', $reply_date, $reply_user, $reply_private, $reply_to) . ':';
				break;
		
			case FORUM_OBJ_EVENT:
				if ($this->obj == $view_obj && $this->obj_id == $view_obj_id)
				{
					echo get_label('[0], [1] commented', $reply_date, $reply_user) . ':';
				}
				else
				{
					$event = new Event();
					$event->load($this->obj_id);
					echo get_label(
						'[0], [1] commented on the [2]',
						$reply_date,
						$reply_user,
						'<a href="event_info.php?id=' . $this->obj_id . '&bck=1">' . $event->get_full_name() . '</a>') . ':';
				}
				break;
				
			case FORUM_OBJ_PHOTO:
				if ($this->obj == $view_obj && $this->obj_id == $view_obj_id)
				{
					echo get_label('[0], [1] commented', $reply_date, $reply_user) . ':';
				}
				else
				{
					echo get_label(
						'[0], [1] commented on the [2]',
						$reply_date,
						$reply_user,
						'<a href="photo.php?id=' . $this->obj_id . '&bck=1">' . get_label('photo') . '</a>') . ':';
				}
				break;
				
			case FORUM_OBJ_GAME:
				if ($this->obj == $view_obj && $this->obj_id == $view_obj_id)
				{
					echo get_label('[0], [1] commented', $reply_date, $reply_user) . ':';
				}
				else
				{
					echo get_label(
						'[0], [1] commented on the [2]',
						$reply_date,
						$reply_user,
						'<a href="view_game.php?id=' . $this->obj_id . '&bck=1">' . get_label('game') . ' ' . $this->obj_id . '</a>') . ':';
				}
				break;
				
			case FORUM_OBJ_USER:
				$reply_to = '';
				$reply_privately = '';
				list ($p_name) = Db::record(get_label('user'), 'SELECT name FROM users WHERE id = ?', $this->obj_id);
				if ($this->viewers >= FOR_MANAGERS)
				{
					$reply_privately = ' ' . get_label('privately');
				}
				echo get_label('[0], [1] said to [2][3]', $reply_date, $reply_user, $p_name, $reply_privately) . ':';
				break;
				
			case FORUM_OBJ_NO:
			default:
				echo get_label('[0], [1] said', $reply_date, $reply_user) . ':';
				break;
		}
		echo '</td></tr>';
		echo '<tr><td valign="top" style="padding:5px;">';
		if ($this->obj == FORUM_OBJ_PHOTO && $view_obj != FORUM_OBJ_PHOTO)
		{
			echo '<a href="photo.php?id=' . $this->obj_id . '&bck=1"><img border="0" src="' . PHOTOS_DIR . TNAILS_DIR . $this->obj_id . '.jpg" align="left" width="100"></a>';
		}
		echo $this->body . '</td></tr>';
		
		$additional_row = false;
		if (!$show_avatar && $can_send)
		{
			if (!$additional_row)
			{
				echo '<tr>';
				$additional_row = true;
			}
			echo '<td><a href="forum.php?id=' . $this->id . '&bck=1" title="'.get_label('Reply to this message').'">'.get_label('Reply').'</a></td>';
		}
		if (check_permissions(U_PERM_ADMIN))
		{
			if (!$additional_row)
			{
				echo '<tr>';
				$additional_row = true;
			}
			echo '<td colspan="2" align="right"><a href="delete_message.php?id=' . $this->id . '&bck=1" title="'.get_label('Delete').'"><img src="images/delete.png" border="0"></a></td>';
		}
		if ($additional_row)
		{
			echo '</tr>';
		}
	}
	
	function show_history($reply_button = false)
	{
		global $_profile;
	
		$query = new DbQuery('SELECT m.id, m.obj, m.obj_id, m.user_id, u.name, u.flags, m.body, m.send_time, m.viewers, m.club_id FROM messages m, messages_tree t, users u' .
			' WHERE m.user_id = u.id AND t.parent_id = m.id AND t.message_id = ?', $this->id);
		if ($_profile != NULL)
		{
			$query->add(' AND (m.language & ?) <> 0', $_profile->user_langs);
		}
		else
		{
			$query->add(' AND (m.language & ' . LANG_ENGLISH . ') <> 0');
		}
		$query->add(' ORDER BY t.send_time');
		
		$can_send = check_permissions(PERM_USER);

		$parent_id = -1;
		echo '<table width="100%">';
		while ($row = $query->next())
		{
			$message = new ForumMessage($row);
			$message->show(FORUM_OBJ_REPLY, $parent_id, $can_send);
			$parent_id = $message->id;
		}
		
		$this->show(FORUM_OBJ_REPLY, $parent_id, $reply_button);
		echo '</table>';
	}
	
	static function viewers_condition($prefix)
	{
		global $_profile;
		
		$condition = new SQL();
		if ($_profile != NULL)
		{
			if (!$_profile->is_admin())
			{
				$condition->add(
					$prefix .
					' (m.viewers = ' . FOR_EVERYONE .
					' OR m.user_id = ?' .
					' OR (m.obj = ' . FORUM_OBJ_USER . ' AND m.obj_id = ?)' .
					' OR (m.obj = ' . FORUM_OBJ_REPLY . ' AND (SELECT m1.user_id FROM messages m1 WHERE m1.id = m.obj_id) = ?)',
					$_profile->user_id, $_profile->user_id, $_profile->user_id);
				if (count($_profile->clubs) > 0)
				{
					$condition->add(
						' OR (m.viewers = ' . FOR_MEMBERS .
						' AND m.club_id IN (SELECT club_id FROM user_clubs WHERE user_id = ?))',
						$_profile->user_id);
					if ($_profile->is_manager())
					{
						$condition->add(
							' OR (m.viewers = ' . FOR_MANAGERS .
							' AND m.club_id IN (SELECT club_id FROM user_clubs WHERE user_id = ?' .
							' AND (flags & ' . UC_PERM_MANAGER . ') <> 0))',
							$_profile->user_id);
					}
				}
				$condition->add(')');
			}
		}
		else
		{
			$condition->add($prefix . ' m.viewers = ' . FOR_EVERYONE);
		}
		return $condition;
	}
	
	static function proceed_send($obj, $obj_id, $club_id = -1, $viewers = FOR_EVERYONE)
	{
		global $_profile;

		if (!check_permissions(PERM_USER))
		{
			return;
		}
			
		$message = '';
		if (isset($_REQUEST['fbody']))
		{
			$message = $_REQUEST['fbody'];
		}
		
		if ($club_id <= 0 || $club_id == NULL)
		{
			switch ($obj)
			{
				case FORUM_OBJ_REPLY:
					list ($club_id) = Db::record(get_label('message'), 'SELECT club_id FROM messages WHERE id = ?', $obj_id);
					break;
				case FORUM_OBJ_EVENT:
					list ($club_id) = Db::record(get_label('event'), 'SELECT club_id FROM events WHERE id = ?', $obj_id);
					break;
				case FORUM_OBJ_PHOTO:
					list ($club_id) = Db::record(get_label('photo'), 'SELECT a.club_id FROM photos p JOIN photo_albums a ON a.id = p.album_id WHERE p.id = ?', $obj_id);
					break;
				case FORUM_OBJ_GAME:
					list ($club_id) = Db::record(get_label('game'), 'SELECT club_id FROM games WHERE id = ?', $obj_id);
					break;
				default:
					$club_id = NULL;
					break;
			}
		}
		
		$lang = 0;
		if (isset($_REQUEST['flang']))
		{
			$lang = $_REQUEST['flang'];
			while (($lang & ($lang - 1)) != 0)
			{
				$lang &= ($lang - 1);
			}
		}
		
		if (isset($_REQUEST['fpriv']))
		{
			switch ($obj)
			{
				case FORUM_OBJ_REPLY:
				case FORUM_OBJ_USER:
					$viewers = FOR_USER;
					break;
				case FORUM_OBJ_EVENT:
				case FORUM_OBJ_PHOTO:
				case FORUM_OBJ_GAME:
					$viewers = FOR_MEMBERS;
					break;
			}
		}
		
		if (isset($_REQUEST['send']))
		{
			if (trim($message) == '')
			{
				throw new Exc(get_label('Please enter the [0]', get_label('message')));
			}
			
			$tags = get_bbcode_tags();
			$message = prepare_message($message, $tags);
			if ($lang == LANG_NO)
			{
				$lang = detect_lang($message);
				if ($lang == LANG_NO)
				{
					$lang = $_profile->user_def_lang;
				}
			}
			
			$timestamp = time();

			Db::begin();
			Db::exec(
				get_label('message'), 
				'INSERT INTO messages (obj, obj_id, viewers, club_id, user_id, body, language, send_time, update_time) ' .
				'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
				$obj, $obj_id, $viewers, $club_id, $_profile->user_id, $message, $lang, $timestamp, $timestamp);
			
			list ($message_id) = Db::record(get_label('message'), 'SELECT LAST_INSERT_ID()');
			
			if ($obj == FORUM_OBJ_REPLY)
			{
				$parent_id = $obj_id;
				while ($parent_id != NULL)
				{
					Db::exec(get_label('message'), 'INSERT INTO messages_tree (message_id, parent_id, send_time) VALUES (?, ?, ?)', $message_id, $parent_id, $timestamp);
					Db::exec(get_label('message'), 'UPDATE messages SET update_time = ? WHERE id = ?', $timestamp, $parent_id);
					
					$query = new DbQuery('SELECT obj_id FROM messages WHERE obj = ' . FORUM_OBJ_REPLY . ' AND id = ?', $parent_id);
					if ($row = $query->next())
					{
						$parent_id = $row[0];
					}
					else
					{
						$parent_id = NULL;
					}
				}
			}
			Db::commit();
			
			if ($obj == FORUM_OBJ_REPLY)
			{
				list($id, $name, $email, $def_lang, $body, $user_flags) = 
					Db::record(
						get_label('message'), 
						'SELECT u.id, u.name, u.email, u.def_lang, m.body, u.flags FROM users u, messages m' . 
						' WHERE m.user_id = u.id AND m.id = ?', $obj_id);
				if ($id != $_profile->user_id && $email != '' && ($user_flags & U_FLAG_MESSAGE_NOTIFY) != 0)
				{
					$lang = get_lang_code($def_lang);
					$code = generate_email_code();
					$request_base = get_server_url() . '/email_request.php?code=' . $code . '&uid=' . $id;
					
					$tags = array(
						'uid' => new Tag($id),
						'code' => new Tag($code),
						'uname' => new Tag($name),
						'sender' => new Tag($_profile->user_name),
						'message' => new Tag($message),
						'original' => new Tag($body),
						'url' => new Tag($request_base),
						'unsub' => new Tag('<a href="' . $request_base . '&unsub=1" target="_blank">', '</a>'));
						
					list($subj, $body, $text_body) = include 'include/languages/' . $lang . '/email_forum_reply.php';
					$body = parse_tags($body, $tags);
					$text_body = parse_tags($text_body, $tags);
					send_notification($email, $body, $text_body, $subj, $id, EMAIL_OBJ_MESSAGE, $message_id, $code);
				}
			}
			else if ($obj == FORUM_OBJ_USER && $obj_id != $_profile->user_id)
			{
				list($id, $name, $email, $def_lang, $user_flags) =
					Db::record(
						get_label('user'), 
						'SELECT id, name, email, def_lang, flags FROM users' . 
						' WHERE id = ?', $obj_id);
						
				if ($email != '' && ($user_flags & U_FLAG_MESSAGE_NOTIFY) != 0)
				{
					$lang = get_lang_code($def_lang);
					$code = generate_email_code();
					$request_base = get_server_url() . '/email_request.php?code=' . $code . '&uid=' . $id;
					
					$tags = array(
						'uid' => new Tag($id),
						'code' => new Tag($code),
						'uname' => new Tag($name),
						'sender' => new Tag($_profile->user_name),
						'message' => new Tag($message),
						'url' => new Tag($request_base),
						'unsub' => new Tag('<a href="' . $request_base . '&unsub=1" target="_blank">', '</a>'));
					
					list($subj, $body, $text_body) = include 'include/languages/' . $lang . '/email_forum_private.php';
					$body = parse_tags($body, $tags);
					$text_body = parse_tags($text_body, $tags);
					send_notification($email, $body, $text_body, $subj, $id, EMAIL_OBJ_MESSAGE, $message_id, $code);
				}
			}
			else if ($obj == FORUM_OBJ_EVENT && $viewers <= FOR_MEMBERS)
			{
				$query = new DbQuery(
					'SELECT u.id, u.name, u.email, u.def_lang, e.id, e.name, e.start_time, c.timezone, a.address FROM users u' .
					' JOIN event_users eu ON u.id = eu.user_id' .
					' JOIN events e ON e.id = eu.event_id' .
					' JOIN addresses a ON a.id = e.address_id' . 
					' JOIN cities c ON c.id = a.city_id' . 
					' WHERE eu.coming_odds > 0 AND eu.event_id = ? AND (u.flags & ' . U_FLAG_MESSAGE_NOTIFY .
					') <> 0 AND u.email <> \'\' AND u.id <> ?',
					$obj_id, $_profile->user_id);
				if ($viewers == FOR_MEMBERS)
				{
					$query->add(' AND e.club IN (SELECT club_id FROM user_clubs WHERE user_id = u.id)');
				}
			
				Db::begin();
				while ($row = $query->next())
				{
					list($user_id, $user_name, $user_email, $user_lang, $event_id, $event_name, $event_start_time, $event_timezone, $event_addr) = $row;
				
					$code = generate_email_code();
					$request_base = get_server_url() . '/email_request.php?code=' . $code . '&uid=' . $user_id;
					$tags = array(
						'uid' => new Tag($user_id),
						'eid' => new Tag($event_id),
						'ename' => new Tag($event_name),
						'edate' => new Tag(format_date('l, F d, Y', $event_start_time, $event_timezone, $user_lang)),
						'etime' => new Tag(format_date('H:i', $event_start_time, $event_timezone, $user_lang)),
						'addr' => new Tag($event_addr),
						'code' => new Tag($code),
						'uname' => new Tag($user_name),
						'sender' => new Tag($_profile->user_name),
						'message' => new Tag($message),
						'url' => new Tag($request_base),
						'unsub' => new Tag('<a href="' . $request_base . '&unsub=1" target="_blank">', '</a>'));
					
					list($subj, $body, $text_body) = include 'include/languages/' . get_lang_code($user_lang) . '/email_forum_event.php';
					$body = parse_tags($body, $tags);
					$text_body = parse_tags($text_body, $tags);
					send_notification($user_email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_MESSAGE, $message_id, $code);
				}
				Db::commit();
			}
			else if ($obj == FORUM_OBJ_PHOTO)
			{
				Db::begin();
				$query = new DbQuery('SELECT u.id, u.name, u.email, u.def_lang, p.photo_id FROM user_photos p JOIN users u ON u.id = p.user_id WHERE p.tag > 0 AND p.photo_id = ?', $obj_id);
				while ($row = $query->next())
				{
					list($user_id, $user_name, $user_email, $user_lang, $photo_id) = $row;
				
					$code = generate_email_code();
					$server = get_server_url() . '/';
					$request_base = $server . 'email_request.php?code=' . $code . '&uid=' . $user_id;
					$image_url = $server . PHOTOS_DIR . TNAILS_DIR . $photo_id . '.jpg';
					
					$tags = array(
						'uid' => new Tag($user_id),
						'code' => new Tag($code),
						'uname' => new Tag($user_name),
						'sender' => new Tag($_profile->user_name),
						'message' => new Tag($message),
						'url' => new Tag($request_base . '/email_request.php?code=' . $code . '&uid=' . $user_id),
						'photo' => new Tag('<a href="' . $request_base . '&pid=' . $photo_id . '"><img src="' . $image_url . '" border="0" width="' . EVENT_PHOTO_WIDTH . '"></a>'),
						'unsub' => new Tag('<a href="' . $request_base . '/email_request.php?code=' . $code . '&uid=' . $user_id . '&unsub=1" target="_blank">', '</a>'));
					
					list($subj, $body, $text_body) = include 'include/languages/' . get_lang_code($user_lang) . '/email_forum_photo.php';
					$body = parse_tags($body, $tags);
					$text_body = parse_tags($text_body, $tags);
					send_notification($user_email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_MESSAGE, $message_id, $code);
				}
				Db::commit();
			}
			return true;
		}
		return false;
	}
	
	static function show_send_form($hidden_fields, $title, $flags = 0)
	{
		global $_profile;
		
		if (check_permissions(PERM_USER))
		{
			echo '<p><form method="post">';
			echo '<table class="transp" width="100%"><tr><td>' . $title . '</td><td align="right">';
			if ($hidden_fields != NULL)
			{
				foreach ($hidden_fields as $name => $value)
				{
					echo '<input type="hidden" name="' . $name . '" value="' . $value . '">';
				}
			}
			
			if ($flags & FORUM_SEND_FLAG_CHOOSE_LANG)
			{
				switch (get_langs_count($_profile->user_langs))
				{
					case 0:
						echo '<input type="hidden" name="flang" value="0">';
						break;
					case 1:
						echo '<input type="hidden" name="flang" value="' . $_profile->user_langs . '">';
						break;
					
					default:
						echo get_label('Language');
						echo ': <select name="flang"><option value="0">'.get_label('Auto-detect').'</option>';
						
						$lang = LANG_NO;
						while (($lang = get_next_lang($lang, $_profile->user_langs)) != LANG_NO)
						{
							echo '<option value="' . $lang . '">' . get_lang_str($lang) . '</option>';
						}
						echo '</select>';
						break;
				}
			}
			else
			{
				echo '<input type="hidden" name="flang" value="0">';
			}
			
			if ($flags & FORUM_SEND_FLAG_SHOW_PRIVATE)
			{
				echo '<input type="checkbox" name="fpriv"';
				if ($flags & FORUM_SEND_FLAG_PRIVATE)
				{
					echo ' checked';
				}
				if ($flags & FORUM_SEND_FLAG_PRIVATE_DISABLED)
				{
					echo ' disabled';
				}
				echo '> ' . get_label('privately');
			}
			else if ($flags & FORUM_SEND_FLAG_PRIVATE)
			{
				echo '<input type="hidden" name="fpriv" value="1">';
			}
			echo '</td></tr></table>';
			
//			show_single_editor('fbody', '');
			echo '<textarea name="fbody" cols="60" rows="4"></textarea><br>';
			echo '<input type="submit" name="send" value="'.get_label('Send').'" class="btn norm">';
			echo '</form></p>';
		}
	}
	
	static function show_messages($params, $obj = FORUM_OBJ_NO, $obj_id = -1, $ccc_filter = NULL, $distinct = false)
	{
		global $_profile, $_page;
		
		if (isset($_REQUEST['fsent']))
		{
			$_page = 0;
			unset($_REQUEST['fsent']);
		}

		$user_id = -1;
		if ($_profile != NULL)
		{
			$user_id = $_profile->user_id;
		}
		
/*		if ($_profile != NULL)
		{
			$condition1->add(' AND (m.language & ?) <> 0', $_profile->user_langs);
		}*/
		
		if ($obj != FORUM_OBJ_NO)
		{
			$condition = new SQL('m.obj = ? AND m.obj_id = ?', $obj, $obj_id);
		}
		else
		{
			$condition = new SQL('m.obj > 0');
		}
		$viewers_condition = ForumMessage::viewers_condition(' AND');
		$condition->add($viewers_condition);
		
		$ccc_id = -1;
		if ($ccc_filter != NULL)
		{
			$ccc_id = $ccc_filter->get_id();
		}
		
		if ($ccc_id < 0)
		{
			if ($distinct)
			{
				$condition->add(' AND m.club_id IS NULL');
			}
		}
		else switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				if ($distinct)
				{
					$condition->add(' AND m.club_id = ?', $ccc_id);
				}
				else
				{
					$condition->add(' AND (m.club_id IS NULL OR m.club_id = ?)', $ccc_id);
				}
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				if ($distinct)
				{
					$condition->add(' AND m.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
				}
				else
				{
					$condition->add(' AND (m.club_id IS NULL OR m.club_id IN (' . $_profile->get_comma_sep_clubs() . '))');
				}
			}
			break;
		case CCCF_CITY:
			if ($distinct)
			{
				$condition->add(' AND m.club_id IN (SELECT id FROM clubs WHERE city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?))', $ccc_id, $ccc_id);
			}
			else
			{
				$condition->add(' AND (m.club_id IS NULL OR m.club_id IN (SELECT id FROM clubs WHERE city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)))', $ccc_id, $ccc_id);
			}
			break;
		case CCCF_COUNTRY:
			if ($distinct)
			{
				$condition->add(' AND m.club_id IN (SELECT c.id FROM clubs c JOIN cities ct ON ct.id = c.city_id WHERE ct.country_id = ?)', $ccc_id);
			}
			else
			{
				$condition->add(' AND (m.club_id IS NULL OR m.club_id IN (SELECT c.id FROM clubs c JOIN cities ct ON ct.id = c.city_id WHERE ct.country_id = ?))', $ccc_id);
			}
			break;
		}
		
		// get rid of this query if there are performance issues (use less convinient prev/next)
		list ($count) = Db::record(get_label('message'), 'SELECT count(*) FROM messages m WHERE ', $condition);
		
		echo '<table class="transp" width="100%"><tr><td>';
		show_pages_navigation(FORUM_PAGE_SIZE, $count, 'fsent');
		echo '</td></tr></table>';
		
		echo '<table width="100%">';
		
		$query = new DbQuery('SELECT m.id, m.obj, m.obj_id, m.user_id, u.name, u.flags, m.body, m.send_time, m.viewers, m.club_id FROM messages m, users u WHERE ', $condition);
		$query->add(' AND m.user_id = u.id ORDER BY m.update_time DESC LIMIT ' . ($_page * FORUM_PAGE_SIZE) . ',' . FORUM_PAGE_SIZE);
		while ($row = $query->next())
		{
			$message = new ForumMessage($row);
			$message->show($obj, $obj_id, ($user_id > 0));
			echo '<tr><td width="' . ICON_WIDTH . '"></td><td width="100%"><table width="100%">';
		
			$replies = array();
			$query1 = new DbQuery(
				'SELECT m.id, m.obj, m.obj_id, m.user_id, u.name, u.flags, m.body, m.send_time, m.viewers, m.club_id FROM messages m, messages_tree t, users u WHERE ' .
				'm.user_id = u.id AND t.message_id = m.id', 
				$viewers_condition);
			$query1->add(' AND t.parent_id = ? ORDER BY t.send_time DESC LIMIT ' . (FORUM_MAX_REPLIES + 1), $message->id);
			for ($reply_count = 0; $reply_count < FORUM_MAX_REPLIES && $row1 = $query1->next(); ++$reply_count)
			{
				$replies[] = new ForumMessage($row1);
			}
			
			if ($query1->next())
			{
				echo '<tr><td colspan="2"><a href="forum.php?id=' . $message->id . '&rep=0&bck=1" title="'.get_label('View all replies to this message.').'">'.get_label('View all replies...').'</a></td></tr>';
			}
			
			for ($i = count($replies) - 1; $i >= 0; --$i)
			{
				$replies[$i]->show(FORUM_OBJ_REPLY, $message->id, ($user_id > 0));
			}
			
			echo '</table></td></tr>';
		}
		echo '</table>';

		echo '<table class="transp" width="100%"><tr><td>';
		show_pages_navigation(FORUM_PAGE_SIZE, $count, 'fsent');
		echo '</td></tr></table>';
	}

	
	static function delete($id)
	{
		$query = new DbQuery('SELECT message_id FROM messages_tree WHERE parent_id = ?', $id);
		$children = array();
		while ($row = $query->next())
		{
			$child_id = $row[0];
			if ($child_id != $id)
			{
				$children[] = $child_id;
			}
		}
		
		foreach($children as $child_id)
		{
			ForumMessage::delete($child_id);
		}
		
		Db::exec(get_label('message'), 'DELETE FROM messages_tree WHERE message_id = ?', $id);
		Db::exec(get_label('message'), 'DELETE FROM messages WHERE id = ?', $id);
	}
	
	static function set_viewers($id, $viewers, $old_viewers = -1)
	{
		if ($old_viewers < 0)
		{
			list ($old_viewers) = Db::record(get_label('message'), 'SELECT viewers FROM messages WHERE id = ?', $id);
		}
		
		if ($viewers == $old_viewers)
		{
			return;
		}
	
		$query = new DbQuery('SELECT m.id, m.viewers FROM messages_tree t JOIN messages m ON m.id = t.message_id WHERE t.parent_id = ?', $id);
		$children = array();
		while ($row = $query->next())
		{
			$child_id = $row[0];
			if ($child_id != $id)
			{
				$children[] = $row;
			}
		}

		if ($viewers < $old_viewers)
		{
			foreach($children as $child)
			{
				list ($child_id, $child_viewers) = $child;
				if ($child_viewers == $old_viewers)
				{
					ForumMessage::set_viewers($child_id, $viewers, $child_viewers);
				}
			}
		}
		else
		{
			foreach($children as $child)
			{
				list ($child_id, $child_viewers) = $child;
				if ($child_viewers < $viewers)
				{
					ForumMessage::set_viewers($child_id, $viewers, $child_viewers);
				}
			}
		}
		
		Db::exec(get_label('message'), 'UPDATE messages SET viewers = ? WHERE id = ?', $viewers, $id);
	}
}

?>