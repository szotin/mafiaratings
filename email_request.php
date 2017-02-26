<?php

require_once 'include/page_base.php';
require_once 'include/email.php';

class Page extends PageBase
{
	private $message;

	protected function prepare()
	{
		global $_profile;
		
		$this->message = NULL;
	
		if (!isset($_REQUEST['code']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('email code')));
		}
		$code = $_REQUEST['code'];

		if (!isset($_REQUEST['uid']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('user')));
		}
		$user_id = $_REQUEST['uid'];

		$query = new DbQuery(
			'SELECT e.obj, e.obj_id, u.flags, u.auth_key FROM emails e, users u WHERE e.user_id = u.id AND u.id = ? AND e.code = ? AND e.send_time >= UNIX_TIMESTAMP() - ' . EMAIL_EXPIRATION_TIME, 
			$user_id, $code);
		if (!($row = $query->next()))
		{
			throw new FatalExc(get_label('Your email has expired.'));
		}
		list ($obj, $obj_id, $flags, $auth_key) = $row;
		
		if (!login($user_id))
		{
			throw new Exc('login failed');
		}

		if (isset($_REQUEST['unsub']))
		{
			throw new RedirectExc('unsubscribe.php');
		}
		
		if ($_profile->user_flags & U_FLAG_DEACTIVATED)
		{
			$flags = $_profile->user_flags & ~U_FLAG_DEACTIVATED;
			Db::exec(get_label('user'), 'UPDATE users SET flags = ? WHERE id = ?', $flags, $_profile->user_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = 'flags=' . $flags;
				db_log('user', 'Activated', $log_details, $_profile->user_id);
			}
			$_profile->user_flags = $flags;
			$this->message = get_label('Hi [0]! Thank you for activating your account. Click Ok to start using Mafia Ratings.', $_profile->user_name);
		}
	
		switch ($obj)
		{
			case EMAIL_OBJ_EVENT:
				if (isset($_REQUEST['decline']))
				{
					$query1 = new DbQuery('SELECT event_id FROM event_emails WHERE id = ?', $obj_id);
					if ($row1 = $query1->next())
					{
						throw new RedirectExc('pass.php?id=' . $row1[0]);
					}
				}
				else if (isset($_REQUEST['accept']))
				{
					$query1 = new DbQuery('SELECT event_id FROM event_emails WHERE id = ?', $obj_id);
					if ($row1 = $query1->next())
					{
						throw new RedirectExc('attend.php?id=' . $row1[0]);
					}
				}
				throw new RedirectExc('/');
				
			case EMAIL_OBJ_MESSAGE:
				throw new RedirectExc('forum.php?id=' . $obj_id);
			
			case EMAIL_OBJ_PHOTO:
				if (isset($_REQUEST['pid']))
				{
					throw new RedirectExc('photo.php?id=' . $_REQUEST['pid']);
				}
				throw new RedirectExc('user_photos.php?id=' . $user_id);
				
			case EMAIL_OBJ_SIGN_IN:
				if ($this->message == NULL)
				{
					throw new RedirectExc('/');
				}
				break;
				
			case EMAIL_OBJ_CREATE_CLUB:
				throw new RedirectExc('clubs.php');
				
			case EMAIL_OBJ_CONFIRM_EVENT:
				$url = 'event_confirm.php?event=' . $obj_id;
				if (isset($_REQUEST['yes']))
				{
					$url .= '&yes=';
					if (isset($_REQUEST['join']))
					{
						$url .= '&join=';
					}
				}
				throw new RedirectExc($url);
				break;
				
			case EMAIL_OBJ_EVENT_NO_USER:
				throw new RedirectExc('event_correct_players.php?event=' . $obj_id);
				break;
			
			default:
				throw new FatalExc(get_label('Invalid request.'));
		}
	}
	
	protected function js_on_load()
	{
		if ($this->message != NULL)
		{
			echo 'dlg.info("' . $this->message . '", null, null, function() { window.location.replace("/"); });';
		}
	}
}

$page = new Page();
$page->run(get_label('Email request'), PERM_ALL);

?>