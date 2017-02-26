<?php

require_once 'include/page_base.php';
require_once 'include/event.php';
require_once 'include/pages.php';

define("PAGE_SIZE", 20);

class Page extends MailPageBase
{
	private $name;

	protected function prepare()
	{
		$this->name = '';
		if (isset($_REQUEST['name']))
		{
			$this->name = $_REQUEST['name'];
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_page;
	
		echo '<form method="get" name="form" action="send_private.php">';
		echo '<table class="transp" width="100%"><tr><td>';
		echo get_label('Filter').':&nbsp;<input name="name" value="' . $this->name . '" onChange="document.form.submit()"></td></tr></table></form>';
		
		$where_str = new SQL('WHERE (flags & ' . U_FLAG_BANNED . ') = 0 ');
		if ($this->name != '')
		{
			$where_str->add('AND name LIKE ?', $this->name . '%');
		}
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(*) FROM users ', $where_str);
		show_pages_navigation(PAGE_SIZE, $count);

		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td>'.get_label('Player').'</td></tr>';
		
		$query = new DbQuery('SELECT id, name FROM users ', $where_str);
		$query->add(' ORDER BY name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			echo '<tr><td><a href="user_messages.php?id=' . $row[0] . '&bck=1">' . cut_long_name($row[1], 110) . '</a></td></tr>';
		}
		echo '</table></form>';
	}
}

$page = new Page();
$page->run(get_label('Send a private message'), PERM_USER);

?>