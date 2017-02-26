<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/forum.php';

class Page extends EventPageBase
{
	protected function prepare()
	{
		parent::prepare();
		ForumMessage::proceed_send(FORUM_OBJ_EVENT, $this->event->id, $this->event->club_id);
	}
	
	protected function show_body()
	{
		global $_profile;
		
		if ($_profile != NULL && ($this->event->flags & EVENT_FLAG_CANCELED) == 0 && time() < $this->event->timestamp + $this->event->duration)
		{
			echo '<table class="transp" width="100%"><tr>';
			echo '<td><input type="submit" value="'.get_label('Attend').'" class="btn norm" onclick="mr.attendEvent(' . $this->event->id . ')">';
			echo '<input type="submit" value="'.get_label('Pass').'" class="btn norm" onclick="mr.passEvent(' . $this->event->id . ')"></td>';
			echo '</tr></table>';
		}
		
		$this->event->show_details();
		
		$params = array('id' => $this->event->id);
		ForumMessage::show_messages($params, FORUM_OBJ_EVENT, $this->event->id);
		ForumMessage::show_send_form($params, get_label('Comment this event') . ':');
	}
}

$page = new Page();
$page->run(get_label('Event info'), PERM_ALL);

?>