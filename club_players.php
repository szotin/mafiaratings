<?php

require_once 'include/club.php';
require_once 'include/pages.php';

define('PAGE_SIZE', 20);

class Page extends ClubPageBase
{
	private $filter;

	protected function prepare()
	{
		global $_profile;
	
		parent::prepare();
		
		$this->filter = NULL;
		if (isset($_REQUEST['filter']))
		{
			$this->filter = $_REQUEST['filter'];
		}
		
		if (isset($_REQUEST['ban']))
		{
			Db::exec(get_label('user'), 'UPDATE user_clubs SET flags = (flags | ' . UC_FLAG_BANNED . ') WHERE user_id = ? AND club_id = ?', $_REQUEST['ban'], $this->id);
			if (Db::affected_rows() > 0)
			{
				db_log('user', 'Banned', NULL, $_REQUEST['ban'], $this->id);
			}
		}
		else if (isset($_REQUEST['unban']))
		{
			Db::exec(get_label('user'), 'UPDATE user_clubs SET flags = (flags & ~' . UC_FLAG_BANNED . ') WHERE user_id = ? AND club_id = ?', $_REQUEST['unban'], $this->id);
			if (Db::affected_rows() > 0)
			{
				db_log('user', 'Unbanned', NULL, $_REQUEST['unban'], $this->id);
			}
		}
		$this->_title = get_label('[0] members', $this->name);
	}
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		$is_admin = check_permissions(U_PERM_ADMIN);
		
		echo '<form method="get" name="viewForm">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<table class="transp" width="100%"><tr><td>';
		echo get_label('Filter') . ':&nbsp;<input name="filter" value="' . $this->filter . '" onChange="onChange="document.viewForm.submit()">';
		echo '</td></tr></table></form>';
		
		$is_manager = $_profile->is_manager($this->id);
		
		$condition = new SQL('u.id = uc.user_id AND uc.club_id = ?', $this->id);
		if ($this->filter != NULL)
		{
			$condition->add(' AND u.name LIKE ?', $this->filter . '%');
		}
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(*) FROM users u, user_clubs uc WHERE ', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker">';
		echo '<td width="52"></td>';
		echo '<td>' . get_label('User name') . '</td><td width="100">' . get_label('Subscribed') . '</td><td width="160">' . get_label('Permissions') . '</td></tr>';

		$query = new DbQuery('SELECT u.id, u.name, u.flags, uc.flags FROM users u, user_clubs uc WHERE ', $condition);
		$query->add(' ORDER BY u.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			list($id, $name, $flags, $uc_flags) = $row;
		
			if ($is_admin)
			{
				$can_edit = true;
			}
			else if ($is_manager)
			{
				$can_edit = (($flags & U_PERM_ADMIN) == 0);
			}
			else
			{
				$can_edit = (($uc_flags & PERM_OFFICER) == 0);
			}
			
			echo '<tr><td class="dark">';
			if ($can_edit)
			{
				$ref = '<a href ="?id=' . $this->id . '&page=' . $_page;
				if ($this->filter != NULL)
				{
					$ref .= '&filter=' . $this->filter;
				}
				if ($uc_flags & UC_FLAG_BANNED)
				{
					echo $ref . '&unban=' . $id . '" title="' .get_label('Unban [0]', $name) . '"><img src="images/undelete.png" border="0"></a>';
				}
				else
				{
					echo $ref . '&ban=' . $id . '" title="' .get_label('Ban [0]', $name) . '"><img src="images/delete.png" border="0"></a>';
					echo ' <a href ="edit_user.php?id=' . $id . '&club=' . $this->id . '&bck=1" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></a>';
				}
			}
			else
			{
				echo '<img src="images/transp.png" height="24" border="0">';
			}
			echo '</td>';
			
			echo '<td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 56) . '</a></td>';
			
			echo '<td>';
			if ($uc_flags & UC_FLAG_SUBSCRIBED)
			{	
				echo get_label('yes');
			}
			else
			{	
				echo '&nbsp;';
			}
			echo '</td>';
			
			echo '<td>';
			$sep = '';
			if ($uc_flags & UC_PERM_PLAYER)
			{
				echo $sep . get_label('player');
				$sep = ', ';
			}
			if ($uc_flags & UC_PERM_MODER)
			{
				echo $sep . get_label('moderator');
				$sep = ', ';
			}
			if ($uc_flags & UC_PERM_MANAGER)
			{
				echo $sep . get_label('manager');
				$sep = ', ';
			}
			if ($flags & U_PERM_ADMIN)
			{
				echo $sep . get_label('admin');
				$sep = ', ';
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(NULL, UC_PERM_MODER | UC_PERM_MANAGER);

?>