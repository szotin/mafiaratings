<?php

require_once 'include/club.php';
require_once 'include/pages.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

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
		
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_MODERATOR, $this->id);
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
			Db::exec(get_label('user'), 'UPDATE user_clubs SET flags = (flags | ' . USER_CLUB_FLAG_BANNED . ') WHERE user_id = ? AND club_id = ?', $_REQUEST['ban'], $this->id);
			if (Db::affected_rows() > 0)
			{
				db_log(LOG_OBJECT_USER, 'banned', NULL, $_REQUEST['ban'], $this->id);
			}
		}
		else if (isset($_REQUEST['unban']))
		{
			Db::exec(get_label('user'), 'UPDATE user_clubs SET flags = (flags & ~' . USER_CLUB_FLAG_BANNED . ') WHERE user_id = ? AND club_id = ?', $_REQUEST['unban'], $this->id);
			if (Db::affected_rows() > 0)
			{
				db_log(LOG_OBJECT_USER, 'unbanned', NULL, $_REQUEST['unban'], $this->id);
			}
		}
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
		
		echo '<form method="get" name="viewForm">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<table class="transp" width="100%"><tr><td align="right">';
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, 'club=' . $this->id, get_label('Go to the page where a specific user is located.'));
		echo '</td></tr></table></form>';
		
		$can_edit = $_profile->is_club_manager($this->id);
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(*) FROM users u, user_clubs uc WHERE ', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td width="116">';
		if ($can_edit)
		{
			echo '<button class="icon" onclick="addMember()" title="' . get_label('Add club member') . '"><img src="images/create.png" border="0"></button>';
		}
		echo '</td>';
		echo '<td colspan="4">' . get_label('User') . '</td><td width="130">' . get_label('Permissions') . '</td></tr>';

		$query = new DbQuery(
			'SELECT u.id, u.name, u.email, u.flags, uc.flags, c.id, c.name, c.flags' .
			' FROM user_clubs uc' .
			' JOIN users u ON uc.user_id = u.id' .
			' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
			' WHERE uc.club_id = ?' .
			' ORDER BY u.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE,
			$this->id);
		while ($row = $query->next())
		{
			list($id, $name, $email, $flags, $user_club_flags, $club_id, $club_name, $club_flags) = $row;
		
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
				echo '<button class="icon" onclick="mr.removeClubMember(' . $id . ', ' . $this->id . ')" title="' . get_label('Remove [0] from club members.', $name) . '"><img src="images/delete.png" border="0"></button>';
				if ($user_club_flags & USER_CLUB_FLAG_BANNED)
				{
					echo '<button class="icon" onclick="mr.unbanUser(' . $id . ', ' . $this->id . ')" title="' . get_label('Unban [0]', $name) . '"><img src="images/undelete.png" border="0"></button>';
				}
				else
				{
					echo '<button class="icon" onclick="mr.banUser(' . $id . ', ' . $this->id . ')" title="' . get_label('Ban [0]', $name) . '"><img src="images/ban.png" border="0"></button>';
					echo '<button class="icon" onclick="mr.editUserAccess(' . $id . ', ' . $this->id . ')" title="' . get_label('Set [0] permissions.', $name) . '"><img src="images/access.png" border="0"></button>';
					if ($club_id == $this->id)
					{
						echo '<button class="icon" onclick="mr.editUser(' . $id . ', ' . $this->id . ')" title="' . get_label('Edit [0] profile.', $name) . '"><img src="images/edit.png" border="0"></button>';
					}
				}
			}
			else
			{
				echo '<img src="images/transp.png" height="32" border="0">';
			}
			echo '</td>';
			
			echo '<td width="60" align="center">';
			$this->user_pic->set($id, $name, $flags);
			$this->user_pic->show(ICONS_DIR, true, 50);
			echo '</td>';
			echo '<td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 56) . '</a></td>';
			echo '<td width="200">';
			if ($club_id == $this->id)
			{
				echo $email;
			}
			echo '</td>';
			echo '<td width="50" align="center">';
			if (!is_null($club_id))
			{
				$this->club_pic->set($club_id, $club_name, $club_flags);
				$this->club_pic->show(ICONS_DIR, true, 40);
			}
			echo '</td>';
			
			echo '<td>';
			if ($user_club_flags & USER_CLUB_FLAG_SUBSCRIBED)
			{	
				echo '<img src="images/email.png" width="24" title="' . get_label('Subscribed') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="24">';
			}
			if ($user_club_flags & USER_CLUB_PERM_PLAYER)
			{
				echo '<img src="images/player.png" width="32" title="' . get_label('Player') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="32">';
			}
			if ($user_club_flags & USER_CLUB_PERM_MODER)
			{
				echo '<img src="images/moderator.png" width="32" title="' . get_label('Moderator') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="32">';
			}
			if ($user_club_flags & USER_CLUB_PERM_MANAGER)
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
	
	protected function js()
	{
?>		
		function addMember()
		{
			mr.addClubMember(<?php echo $this->id; ?>, function(data)
			{
				goTo({ id: data.club_id, page: -data.user_id });
			});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Members'));

?>