<?php

require_once 'include/club.php';
require_once 'include/pages.php';

define('PAGE_SIZE', 20);

class Page extends ClubPageBase
{
	private $user_id;
	private $user_name;
	private $user_club_id;
	private $user_city_id;
	private $user_country_id;
	
	protected function prepare()
	{
		global $_profile, $_page;
	
		parent::prepare();
		
		$this->user_id = 0;
		if ($_page < 0)
		{
			$this->user_id = -$_page;
			$_page = 0;
			$query = new DbQuery('SELECT u.name, u.club_id, u.city_id, c.country_id FROM users u JOIN cities c ON c.id = u.city_id WHERE u.id = ?', $this->user_id);
			if ($row = $query->next())
			{
				list($this->user_name, $this->user_club_id, $this->user_city_id, $this->user_country_id) = $row;
				list($is_member) = Db::record(get_label('user'), 'SELECT count(*) FROM user_clubs WHERE user_id = ? AND club_id = ?', $this->user_id, $this->id);
				if ($is_member <= 0)
				{
					$this->errorMessage(get_label('[0] is not a member of [1].', $this->user_name, $this->name));
					$this->user_id = 0;
				}
			}
			else
			{
				$this->errorMessage(get_label('Player not found.'));
				$this->user_id = 0;
			}
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
		
		$condition = new SQL('u.id = uc.user_id AND uc.club_id = ?', $this->id);
		if ($this->user_id > 0)
		{
			$pos_query = new DbQuery('SELECT count(*) FROM user_clubs uc JOIN users u ON uc.user_id = u.id WHERE uc.club_id = ? AND u.name < ?', $this->id, $this->user_name);
			list($user_pos) = $pos_query->next();
			$_page = floor($user_pos / PAGE_SIZE);
		}
		
		$is_admin = check_permissions(U_PERM_ADMIN);
		echo '<form method="get" name="viewForm">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<table class="transp" width="100%"><tr><td align="right">';
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, 'club=' . $this->id, get_label('Go to the page where a specific user is located.'));
		echo '</td></tr></table></form>';
		
		$is_manager = $_profile->is_manager($this->id);
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(*) FROM users u, user_clubs uc WHERE ', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td width="58"></td>';
		echo '<td colspan="3">' . get_label('User name') . '</td><td width="130">' . get_label('Permissions') . '</td></tr>';

		$query = new DbQuery(
			'SELECT u.id, u.name, u.flags, uc.flags, c.id, c.name, c.flags' .
			' FROM user_clubs uc' .
			' JOIN users u ON uc.user_id = u.id' .
			' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
			' WHERE uc.club_id = ?' .
			' ORDER BY u.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE,
			$this->id);
		while ($row = $query->next())
		{
			list($id, $name, $flags, $uc_flags, $club_id, $club_name, $club_flags) = $row;
		
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
			
			if ($id == $this->user_id)
			{
				echo '<tr class="dark">';
			}
			else
			{
				echo '<tr class="light">';
			}
			echo '<td class="dark">';
			if ($can_edit)
			{
				// $ref = '<a href ="?id=' . $this->id . '&page=' . $_page;
				// if ($uc_flags & UC_FLAG_BANNED)
				// {
					// echo $ref . '&unban=' . $id . '" title="' .get_label('Unban [0]', $name) . '"><img src="images/undelete.png" border="0"></a>';
				// }
				// else
				// {
					// echo $ref . '&ban=' . $id . '" title="' .get_label('Ban [0]', $name) . '"><img src="images/delete.png" border="0"></a>';
					// echo ' <a href ="edit_user.php?id=' . $id . '&club=' . $this->id . '&bck=1" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></a>';
				// }
				if ($uc_flags & UC_FLAG_BANNED)
				{
					echo '<button class="icon" onclick="mr.unbanUser(' . $id . ', ' . $this->id . ')" title="' . get_label('Unban [0]', $name) . '"><img src="images/undelete.png" border="0"></button>';
				}
				else
				{
					echo '<button class="icon" onclick="mr.banUser(' . $id . ', ' . $this->id . ')" title="' . get_label('Ban [0]', $name) . '"><img src="images/ban.png" border="0"></button>';
					echo '<button class="icon" onclick="mr.editUserAccess(' . $id . ', ' . $this->id . ')" title="' . get_label('Set [0] permissions.', $name) . '"><img src="images/access.png" border="0"></button>';
				}
			}
			else
			{
				echo '<img src="images/transp.png" height="32" border="0">';
			}
			echo '</td>';
			
			echo '<td width="60" align="center"><a href="user_info.php?id=' . $id . '&bck=1">';
			show_user_pic($id, $name, $flags, ICONS_DIR, 50, 50);
			echo '</a></td>';
			echo '<td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 56) . '</a></td>';
			echo '<td width="50" align="center">';
			show_club_pic($club_id, $club_name, $club_flags, ICONS_DIR, 40, 40);
			echo '</td>';
			
			echo '<td>';
			if ($uc_flags & UC_FLAG_SUBSCRIBED)
			{	
				echo '<img src="images/email.png" width="24" title="' . get_label('Subscribed') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="24">';
			}
			if ($uc_flags & UC_PERM_PLAYER)
			{
				echo '<img src="images/player.png" width="32" title="' . get_label('Player') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="32">';
			}
			if ($uc_flags & UC_PERM_MODER)
			{
				echo '<img src="images/moderator.png" width="32" title="' . get_label('Moderator') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="32">';
			}
			if ($uc_flags & UC_PERM_MANAGER)
			{
				echo '<img src="images/manager.png" width="32" title="' . get_label('Manager') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="32">';
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(NULL, UC_PERM_MODER | UC_PERM_MANAGER);

?>