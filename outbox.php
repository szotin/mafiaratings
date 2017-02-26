<?php 

require_once 'include/page_base.php';
require_once 'include/forum.php';

define("PAGE_SIZE", 15);

class Page extends MailPageBase
{
	protected function show_body()
	{
		global $_profile;
	
		list ($count) = Db::record(get_label('message'), 'SELECT count(*) FROM messages m WHERE obj = ' . FORUM_OBJ_USER . ' AND user_id = ?', $_profile->user_id);
		if ($count > 0)
		{
			$page = 0;
			if (isset($_REQUEST['page']))
			{
				$page = $_REQUEST['page'];
			}
			
			echo '<table class="transp" width="100%"><tr><td>';
			show_pages_navigation('outbox.php?a=', $page, PAGE_SIZE, $count);
			echo '</td></tr></table>';
			
			echo '<table class="bordered" width="100%">';
			
			$query = new DbQuery(
				'SELECT m.id, m.obj, m.obj_id, m.user_id, u.name, u.flags, m.body, m.send_time, m.viewers, m.club_id FROM messages m, users u ' .
					'WHERE m.user_id = u.id AND obj = ' . FORUM_OBJ_USER . ' AND user_id = ? ORDER BY m.send_time DESC LIMIT ' . ($page * PAGE_SIZE) . ',' . PAGE_SIZE,
				$_profile->user_id);
			while ($row = $query->next())
			{
				$message = new ForumMessage($row);
				$message->show(FORUM_OBJ_NO, NULL, true);
			}
			echo '</table>';

			echo '<table class="transp" width="100%"><tr><td>';
			show_pages_navigation('outbox.php?a=', $page, PAGE_SIZE, $count);
			echo '</td></tr></table>';
		}
	}
}

$page = new Page();
$page->run(get_label('Outbox'), PERM_USER);

?>