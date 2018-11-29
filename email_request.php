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
		
		if (isset($_REQUEST['user_id']))
		{
			$user_id = $_REQUEST['user_id'];
		}
		else if (isset($_REQUEST['uid'])) // Remove it. It is a temporary fix, because some emails with uid are already sent in October 2018. If you are reading it in November 2018 or later, remove it.
		{
			$user_id = $_REQUEST['uid'];
		}
		else
		{
			throw new Exc(get_label('Unknown [0]', get_label('user')));
		}

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
		
		switch ($obj)
		{
			case EMAIL_OBJ_EVENT:
				if (isset($_REQUEST['decline']))
				{
					$query1 = new DbQuery('SELECT event_id FROM event_emails WHERE id = ?', $obj_id);
					if ($row1 = $query1->next())
					{
						throw new RedirectExc('event_info.php?decline&id=' . $row1[0]);
					}
				}
				else if (isset($_REQUEST['accept']))
				{
					$query1 = new DbQuery('SELECT event_id FROM event_emails WHERE id = ?', $obj_id);
					if ($row1 = $query1->next())
					{
						throw new RedirectExc('event_info.php?attend&id=' . $row1[0]);
					}
				}
				throw new RedirectExc('event_info.php?id=' . $obj_id);
				
			case EMAIL_OBJ_GAME:
				throw new RedirectExc('view_game.php?id=' . $obj_id);
				
			case EMAIL_OBJ_PHOTO:
				if (isset($_REQUEST['pid']))
				{
					throw new RedirectExc('photo.php?id=' . $_REQUEST['pid']);
				}
				throw new RedirectExc('photo.php?id=' . $obj_id);
				
			case EMAIL_OBJ_VIDEO:
				if (isset($_REQUEST['pid']))
				{
					throw new RedirectExc('video.php?id=' . $_REQUEST['pid']);
				}
				throw new RedirectExc('video.php?id=' . $obj_id);
				
			case EMAIL_OBJ_SIGN_IN:
				if (isset($_REQUEST['email']))
				{
					$email = urldecode($_REQUEST['email']);
					if ( $email != $_profile->user_email)
					{
						Db::begin();
						Db::exec(get_label('user'), 'UPDATE users SET email = ? WHERE id = ?', $email, $_profile->user_id);
						if (Db::affected_rows() > 0)
						{
							$log_details = new stdClass();
							$log_details->email = $email;
							db_log(LOG_OBJECT_USER, 'changed', $log_details, $_profile->user_id);
						}
						$_profile->user_email = $email;
						Db::commit();
						$this->message = '<p>' . get_label('Your email has been changed to [0]', $email) . '</p>';
					}
				}
				if ($this->message == NULL)
				{
					throw new RedirectExc('index.php');
				}
				break;
				
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
			echo 'dlg.info("' . $this->message . '", null, null, function() { window.location.replace("index.php"); });';
		}
	}
}

$page = new Page();
$page->run(get_label('Email request'));

?>