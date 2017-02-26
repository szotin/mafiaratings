<?php 

require_once 'include/user.php';
require_once 'include/forum.php';

define("PAGE_SIZE", 5);

class Page extends UserPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->_title = get_label('[0] messages', $this->title);
		if (ForumMessage::proceed_send(FORUM_OBJ_USER, $this->id, -1, FOR_USER))
		{
			throw new RedirectExc('outbox.php');
		}
	}
	
	protected function show_body()
	{
		global $_profile;
	
		$where = new SQL('m.user_id = ?', $this->id, ForumMessage::viewers_condition(' AND'));
		list ($count) = Db::record(get_label('message'), 'SELECT count(*) FROM messages m WHERE ', $where);
		if ($count > 0)
		{
			$page = 0;
			if (isset($_REQUEST['page']))
			{
				$page = $_REQUEST['page'];
			}
			
			$base_url = 'user_messages.php?id=' . $this->id;
			
			echo '<table class="transp" width="100%"><tr><td>';
			show_pages_navigation(PAGE_SIZE, $count);
			echo '</td></tr></table>';
			
			echo '<table class="bordered" width="100%">';
			
			$query = new DbQuery(
				'SELECT m.id, m.obj, m.obj_id, m.user_id, u.name, u.flags, m.body, m.send_time, m.viewers, m.club_id FROM messages m, users u ' .
				'WHERE m.user_id = u.id AND ', 
				$where);
			$query->add(' ORDER BY m.send_time DESC LIMIT ' . ($page * PAGE_SIZE) . ',' . PAGE_SIZE);
			while ($row = $query->next())
			{
				$message = new ForumMessage($row);
				$message->show(FORUM_OBJ_NO, NULL, ($_profile != NULL), false);
			}
			echo '</table>';

			echo '<table class="transp" width="100%"><tr><td>';
			show_pages_navigation($base_url, $page, PAGE_SIZE, $count);
			echo '</td></tr></table>';
		}
		
		if ($_profile != NULL && $_profile->user_id != $this->id)
		{
			ForumMessage::show_send_form(array('id' => $this->id), get_label('Send a message to [0]', $this->name) . ':', FORUM_SEND_FLAG_SHOW_PRIVATE | FORUM_SEND_FLAG_PRIVATE);
		}
	}
}

$page = new Page();
$page->run(get_label('[0] messages', get_label('User')), PERM_ALL);

?>