<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';

define('PAGE_SIZE', 20);

class Page extends GeneralPageBase
{
	private $user_id;
	private $user_name;
	private $user_club_id;
	private $user_city_id;
	private $user_country_id;
	private $ccc_value;
	
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
			}
			else
			{
				$this->errorMessage(get_label('Player not found.'));
			}
		}
		
		if (isset($_REQUEST['ban']))
		{
			Db::exec(get_label('user'), 'UPDATE users SET flags = (flags | ' . U_FLAG_BANNED . ') WHERE id = ?', $_REQUEST['ban']);
			if (Db::affected_rows() > 0)
			{
				db_log('user', 'Banned', NULL, $_REQUEST['ban']);
			}
			throw new RedirectExc('?page=' . $_page . '&ccc=' . $this->ccc_filter->get_code());
		}
		else if (isset($_REQUEST['unban']))
		{
			Db::exec(get_label('user'), 'UPDATE users SET flags = (flags & ~' . U_FLAG_BANNED . ') WHERE id = ?', $_REQUEST['unban']);
			if (Db::affected_rows() > 0)
			{
				db_log('user', 'Unbanned', NULL, $_REQUEST['unban']);
			}
			throw new RedirectExc('?page=' . $_page . '&ccc=' . $this->ccc_filter->get_code());
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		$condition = new SQL();
		$sep = ' WHERE ';
		$ccc_id = $this->ccc_filter->get_id();
		switch ($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add($sep . 'u.club_id = ?)', $ccc_id);
				$sep = ' AND ';
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add($sep . 'u.club_id IN (SELECT club_id FROM user_clubs WHERE (flags & ' . UC_FLAG_BANNED . ') = 0 AND user_id = ?)', $_profile->user_id);
				$sep = ' AND ';
			}
			break;
		case CCCF_CITY:
			$condition->add($sep . 'u.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			$sep = ' AND ';
			break;
		case CCCF_COUNTRY:
			$condition->add($sep . 'u.city_id IN (SELECT id FROM cities WHERE country_id = ?)', $ccc_id);
			$sep = ' AND ';
			break;
		}
		
		if ($this->user_id > 0)
		{
			$pos_query = new DbQuery('SELECT count(*) FROM users u', $condition);
			$pos_query->add($sep . 'u.name < ?', $this->user_name);
			list($user_pos) = $pos_query->next();
			$_page = floor($user_pos / PAGE_SIZE);
		}
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(*) FROM users u', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered" width="100%">';
		echo '<tr class="th darker">';
		echo '<td width="58"></td>';
		echo '<td colspan="3">' . get_label('User name') . '</td><td width="40"></td></tr>';

		$query = new DbQuery(
			'SELECT u.id, u.name, u.flags, c.id, c.name, c.flags' . 
			', SUM(IF((uc.flags & ' . (UC_PERM_PLAYER | UC_PERM_MODER | UC_PERM_MANAGER) . ') = ' . UC_PERM_PLAYER . ', 1, 0))' . 
			', SUM(IF((uc.flags & ' . (UC_PERM_MODER | UC_PERM_MANAGER) . ') = ' . UC_PERM_MODER . ', 1, 0))' . 
			', SUM(IF((uc.flags & ' . UC_PERM_MANAGER . ') <> 0, 1, 0))' . 
			' FROM users u' .
			' LEFT OUTER JOIN user_clubs uc ON uc.user_id = u.id' .
			' LEFT OUTER JOIN clubs c ON c.id = u.club_id', $condition);
		$query->add(' GROUP BY u.id ORDER BY u.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			list($id, $name, $flags, $club_id, $club_name, $club_flags, $clubs_player, $clubs_moder, $clubs_manager) = $row;
		
			if ($id == $this->user_id)
			{
				echo '<tr class="dark">';
			}
			else
			{
				echo '<tr class="light">';
			}

			echo '<td class="dark">';
			$ref = '<a href ="?page=' . $_page . '&ccc=' . $this->ccc_filter->get_code();
			if ($flags & U_FLAG_BANNED)
			{
				echo '<button class="icon" onclick="mr.unbanUser(' . $id . ')" title="' . get_label('Unban [0]', $name) . '"><img src="images/undelete.png" border="0"></button>';
			}
			else
			{
				echo '<button class="icon" onclick="mr.banUser(' . $id . ')" title="' . get_label('Ban [0]', $name) . '"><img src="images/delete.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.editUser(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></button>';
			}
			echo '</td>';
			
			echo '<td width="60" align="center"><a href="user_info.php?id=' . $id . '&bck=1">';
			show_user_pic($id, $name, $flags, ICONS_DIR, 50, 50);
			echo '</a></td>';
			echo '<td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 56) . '</a></td>';
			echo '<td width="50" align="center">';
			show_club_pic($club_id, $club_name, $club_flags, ICONS_DIR, 40, 40);
			echo '</td>';
			
			echo '<td align="center">';
			$image_title = '';
			// echo $clubs_manager . ':' . $clubs_moder . ':' . $clubs_player;
			echo '<img width="32" height="32" src="images/';
			if ($flags & U_PERM_ADMIN)
			{
				echo 'admin.png';
				$image_title = get_label('Admin');
			}
			
			$all_clubs = $clubs_manager + $clubs_moder + $clubs_player;
			if ($clubs_manager > 0)
			{
				if (empty($image_title))
				{
					echo 'manager.png';
				}
				else
				{
					$image_title .= "\n";
				}
				if ($all_clubs < 2)
				{
					$image_title .= get_label('Manager');
				}
				else if ($clubs_manager < 2)
				{
					$image_title .= get_label('Manager in 1 club');
				}
				else
				{
					$image_title .= get_label('Manager in [0] clubs', $clubs_manager);
				}
			}
			
			if ($clubs_moder > 0)
			{
				if (empty($image_title))
				{
					echo 'moderator.png';
				}
				else
				{
					$image_title .= "\n";
				}
				if ($all_clubs < 2)
				{
					$image_title .= get_label('Moderator');
				}
				else if ($clubs_moder < 2)
				{
					$image_title .= get_label('Moderator in 1 club');
				}
				else
				{
					$image_title .= get_label('Moderator in [0] clubs', $clubs_moder);
				}
			}
			
			if ($clubs_player > 0)
			{
				if (empty($image_title))
				{
					echo 'player.png';
				}
				else
				{
					$image_title .= "\n";
				}
				if ($all_clubs < 2)
				{
					$image_title .= get_label('Player');
				}
				else if ($clubs_player < 2)
				{
					$image_title .= get_label('Player in 1 club');
				}
				else
				{
					$image_title .= get_label('Player in [0] clubs', $clubs_player);
				}
			}
			
			if (empty($image_title))
			{
				echo 'transp.png';
			}
			echo '" title="' . $image_title . '">';
			echo '</td></tr>';
		}
		echo '</table>';
	}
	
	protected function show_search_fields()
	{
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', '', get_label('Go to the page where a specific user is located.'));
	}
	
	protected function get_filter_js()
	{
		$result = '';
		if ($this->user_id > 0)
		{
			$result .= ' + "&page=-' . $this->user_id . '"';
		}
		return $result;
	}
}

$page = new Page();
$page->run(get_label('[0] users', PRODUCT_NAME), U_PERM_ADMIN);

?>