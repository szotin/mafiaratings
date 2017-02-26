<?php

require_once 'include/page_base.php';
require_once 'include/event.php';

class Page extends PageBase
{
	private $event;

	protected function prepare()
	{
		global $_profile;
		
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('event')));
		}
		$event_id = $_REQUEST['id'];

		if (isset($_REQUEST['yes']))
		{
			$update = false;

			Db::begin();
			$query = new DbQuery('SELECT user_id FROM event_users WHERE event_id = ? AND user_id = ?', $event_id, $_profile->user_id);
			if ($query->next())
			{
				$update = true;
			}

			if ($update)
			{
				Db::exec(get_label('event'), 'UPDATE event_users SET coming_odds = 0 WHERE event_id = ? AND user_id = ?', $event_id, $_profile->user_id);
			}
			else
			{
				Db::exec(get_label('event'), 'INSERT INTO event_users (event_id, user_id, coming_odds, people_with_me) VALUES (?, ?, 0, 0)', $event_id, $_profile->user_id);
			}
			
			Db::exec(get_label('registration'), 'DELETE FROM registrations WHERE event_id = ? AND user_id = ?', $event_id, $_profile->user_id);
			
			Db::commit();
			throw new RedirectExc('event_info.php?id=' . $event_id);
		}
		else if (isset($_REQUEST['no']))
		{
			throw new RedirectExc('event_info.php?id=' . $event_id);
		}
		
		$this->event = new Event();
		$this->event->load($event_id);
		$this->_title = get_label('Pass on [0]', $this->event->name);
	}
	
	protected function show_body()
	{
		echo get_label('Are you sure you are not attending?').'<br>';
		echo '<form method="post" name="qForm" action="pass.php">';
		echo '<input type="hidden" name="id" value="' . $this->event->id . '">';
		echo '<input type="submit" name="yes" value="'.get_label('Yes').'" class="btn norm">';
		echo '<input type="submit" name="no" value="'.get_label('No').'" class="btn norm">';
		echo '</form>';
		
		$this->event->show_details();
	}
}

$page = new Page();
$page->run(get_label('Pass'), PERM_USER);

?>