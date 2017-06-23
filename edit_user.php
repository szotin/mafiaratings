<?php

require_once 'include/page_base.php';
require_once 'include/languages.php';
require_once 'include/url.php';
require_once 'include/club.php';

define('MODER', 0);
define('MANAGER', 1);
define('ADMIN', 2);

class Page extends PageBase
{
	private $id;
	private $club_id;
	private $role;
	private $name;
	private $flags;
	private $langs;
	private $uc_flags;

	function permissions()
	{
		global $_profile;
		
		if (!$_profile->is_admin())
		{
			if ($this->club_id <= 0 || ($this->flags & U_PERM_ADMIN) != 0)
			{
				throw new FatalExc(get_label('No permissions'));
			}
			
			if (!$_profile->is_manager($this->club_id))
			{
				if (
					!$_profile->is_moder($this->club_id) ||
					($this->uc_flags & (UC_PERM_MANAGER | UC_PERM_MODER)) != 0)
				{
					throw new FatalExc(get_label('No permissions'));
				}
			}
		}
	}

	protected function prepare()
	{
		global $_profile;
		
		if (isset($_POST['cancel']))
		{
			redirect_back();
		}
		
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('user')));
		}
		$this->id = $_REQUEST['id'];
		
		$this->club_id = -1;
		if (isset($_REQUEST['club']))
		{
			$this->club_id = $_REQUEST['club'];
		}
		
		$this->role = MODER;
		if ($_profile->is_admin())
		{
			$this->role = ADMIN;
		}
		else if ($_profile->is_manager($this->club_id))
		{
			$this->role = MANAGER;
		}
	
		if ($this->club_id > 0)
		{
			list($this->name, $this->flags, $this->langs, $this->uc_flags) =
				Db::record(get_label('user'), 'SELECT u.name, u.flags, u.languages, uc.flags FROM users u JOIN user_clubs uc ON u.id = uc.user_id WHERE uc.club_id = ? AND uc.user_id = ?', $this->club_id, $this->id);
		}
		else
		{
			$this->uc_flags = 0;
			list($this->name, $this->flags, $this->langs) =
				Db::record(get_label('user'), 'SELECT u.name, u.flags, u.languages FROM users u WHERE u.id = ?', $this->id);
		}
		$this->permissions();
	
		if (isset($_POST['update']))
		{
			switch ($this->role)
			{
			case ADMIN:
				if (isset($_POST['admin']))
				{
					$this->flags |= U_PERM_ADMIN;
				}
				else
				{
					$this->flags &= ~U_PERM_ADMIN;
				}
				
			case MANAGER:
				if (isset($_POST['player']))
				{
					$this->uc_flags |= UC_PERM_PLAYER;
				}
				else
				{
					$this->uc_flags &= ~UC_PERM_PLAYER;
				}
			
				if (isset($_POST['moder']))
				{
					$this->uc_flags |= UC_PERM_MODER;
				}
				else
				{
					$this->uc_flags &= ~UC_PERM_MODER;
				}
				
				if (isset($_POST['manager']))
				{
					$this->uc_flags |= UC_PERM_MANAGER;
				}
				else
				{
					$this->uc_flags &= ~UC_PERM_MANAGER;
				}
				
			default:
				$this->langs = get_langs($this->langs);

				if ($_POST['male'])
				{
					$this->flags |= U_FLAG_MALE;
				}
				else
				{
					$this->flags &= ~U_FLAG_MALE;
				}

				if (isset($_POST['banned']))
				{
					$this->flags |= U_FLAG_BANNED;
				}
				else
				{
					$this->flags &= ~U_FLAG_BANNED;
				}
				break;
			}
		
			Db::begin();
			Db::exec(
				get_label('user'),
				'UPDATE users SET flags = ?, languages = ? WHERE id = ?',
				$this->flags, $this->langs, $this->id);
			if (Db::affected_rows() > 0)
			{
				$log_details = 'flags=' . $this->flags . "<br>langs=" . $this->langs;
				db_log('user', 'Changed', $log_details, $this->id);
			}
			if ($this->club_id > 0)
			{
				Db::exec(
					get_label('user'),
					'UPDATE user_clubs SET flags = ? WHERE user_id = ? AND club_id = ?',
					$this->uc_flags, $this->id, $this->club_id);
				if (Db::affected_rows() > 0)
				{
					$log_details = 'club-flags=' . $this->uc_flags;
					db_log('user', 'Club flags changed', $log_details, $this->id, $this->club_id);
				}
			}
			Db::commit();
			
			if ($this->id == $_profile->user_id)
			{
				$_profile->user_flags = $this->flags;
				$_profile->user_langs = $this->langs;
				if ($this->club_id > 0)
				{
					$_profile->clubs[$this->club_id]->flags = $this->uc_flags;
					$_profile->update_club_flags();
				}
			}
			redirect_back();
		}
		if ($this->club_id > 0)
		{
			$this->_title = get_label('Edit [0] in [1]', cut_long_name($this->name, 58), $_profile->clubs[$this->club_id]->name);
		}
		else
		{
			$this->_title = get_label('Edit [0]', cut_long_name($this->name, 58));
		}
	}
	
	protected function show_body()
	{
		echo '<form method="post" name="userForm">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		if ($this->club_id > 0)
		{
			echo '<input type="hidden" name="club" value="' . $this->club_id . '">';
		}
		echo '<table class="bordered" width="100%">';
		
		echo '<tr><td width="100" valign="top" class="dark">'.get_label('Languages').':</td><td class="light">';
		langs_checkboxes($this->langs);
		echo '</td></tr>';
		
		if ($this->role >= MANAGER)
		{
			echo '<tr><td valign="top" class="dark">' . get_label('Permissions') . ':</td><td class="light">';
			if ($this->role == ADMIN)
			{
				echo '<input type="checkbox" name="admin" value="1"' . ((($this->flags & U_PERM_ADMIN) != 0) ? ' checked' : '') . '> '.get_label('Admin').'<br>';
			}
			if ($this->club_id > 0)
			{
				echo '<input type="checkbox" name="manager" value="1"' . ((($this->uc_flags & UC_PERM_MANAGER) != 0) ? ' checked' : '') . '> '.get_label('Manager').'<br>';
				echo '<input type="checkbox" name="moder" value="1"' . ((($this->uc_flags & UC_PERM_MODER) != 0) ? ' checked' : '') . '> '.get_label('Moderator').'<br>';
				echo '<input type="checkbox" name="player" value="1"' . ((($this->uc_flags & UC_PERM_PLAYER) != 0) ? ' checked' : '') . '> '.get_label('Player').'</td></tr>';
			}
		}
		
		echo '<tr><td class="dark" valign="top">' . get_label('Gender') . ':</td><td class="light">';
		if ((($this->flags & U_FLAG_MALE) != 0))
		{
			echo '<input type="radio" name="male" value="1" checked/>'.get_label('male').'<br />';
			echo '<input type="radio" name="male" value="0" />'.get_label('female');
		}
		else
		{
			echo '<input type="radio" name="male" value="1"/>'.get_label('male').'<br />';
			echo '<input type="radio" name="male" value="0" checked/>'.get_label('female');
		}
		echo '</td>';

		if ($this->role == ADMIN)
		{
			echo '<tr><td class="dark">' . get_label('Ban') . ':</td><td class="light"><input type="checkbox" name="banned"' . ((($this->flags & U_FLAG_BANNED) != 0) ? ' checked' : '') . '>'.get_label('ban [0] from [1]', $this->name, PRODUCT_NAME).'</td></tr>';
		}
		echo '</table>';
		
		echo '<p><input value="'.get_label('Update').'" type="submit" class="btn norm" name="update"><input value="'.get_label('Cancel').'" type="submit" class="btn norm" name="cancel"></p></form>';
	}
}

$page = new Page();
$page->run(get_label('Edit user'), PERM_OFFICER);

?>