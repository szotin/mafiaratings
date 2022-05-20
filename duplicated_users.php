<?php 

require_once 'include/general_page_base.php';
require_once 'include/pages.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

class Page extends GeneralPageBase
{
	private $email;
	private $email_title;
	
	protected function prepare()
	{
		parent::prepare();
		
		$this->email = NULL;
		$this->email_title = NULL;
		if (isset($_REQUEST['email']))
		{
			$this->email_title = $this->email = $_REQUEST['email'];
			if ($this->email_title == '')
			{
				$this->email_title = '[' . get_label('no email') . ']';
			}
			$this->_title = get_label('Duplicated accounts for [0]', $this->email_title);
		}
	}
	
	protected function show_body()
	{
		global $_page;
	
		check_permissions(PERMISSION_ADMIN);
		if ($this->email_title != NULL)
		{
			list ($count) = Db::record(get_label('user'), 'SELECT count(*) FROM users WHERE email = ?', $this->email);
			show_pages_navigation(PAGE_SIZE, $count);
			
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="th darker"><td width="38"></td><td>'.get_label('Players with the same email').'</td><td width="100">Rating</td><td width="100">'.get_label('Games played').'</td></tr>';
			$query = new DbQuery('SELECT id, name, rating, games, flags FROM users WHERE email = ? ORDER BY games DESC, rating DESC, name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE, $this->email);
			while ($row = $query->next())
			{
				list ($id, $name, $rating, $games, $flags) = $row;
				echo '<tr>';
				
				echo '<td>';
				$this->user_pic->set($id, $name, $flags);
				$this->user_pic->show(ICONS_DIR, false, 36);
				echo '</td>';
				
				echo '<td><a href="merge_user.php?bck=1&id=' . $id . '">' . cut_long_name($name, 80) . '</a></td>';
				echo '<td>' . $rating . '</td>';
				echo '<td>' . $games . '</td></tr>';
			}
			echo '</table>';
		}
		else
		{
			list ($count) = Db::record(get_label('user'), 'SELECT count(*) FROM (SELECT email, count(*) as cnt FROM users GROUP BY email HAVING cnt > 1) x');
			show_pages_navigation(PAGE_SIZE, $count);
			
			$query = new DbQuery(
				'SELECT email, count(*) as cnt FROM users GROUP BY email HAVING cnt > 1 ORDER BY cnt DESC, email LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="th darker"><td>' . get_label('Email') . '</td><td width="80">' . get_label('Count') . '</td></tr>';
			while ($row = $query->next())
			{
				list ($email, $count) = $row;
			
				echo '<tr>';
				echo '<td><a href="duplicated_users.php?bck=1&email=' . $email . '">' . ($email == '' ? '[' . get_label('no email') . ']' : $email) . '</a></td>';
				echo '<td>' . $count . '</td>';
				echo '</tr>';
			}
			echo '</table>';
		}
	}
}

$page = new Page();
$page->run('Duplicated accounts');

?>
