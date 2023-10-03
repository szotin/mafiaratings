<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

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
		global $_profile, $_page, $_lang;
	
		parent::prepare();
		
		$this->ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		check_permissions(PERMISSION_ADMIN);
		$this->user_id = 0;
		if ($_page < 0)
		{
			$this->user_id = -$_page;
			$_page = 0;
			$query = new DbQuery(
				'SELECT nu.name, u.club_id, u.city_id, c.country_id'.
				' FROM users u'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN cities c ON c.id = u.city_id'.
				' WHERE u.id = ?', $this->user_id);
			if ($row = $query->next())
			{
				list($this->user_name, $this->user_club_id, $this->user_city_id, $this->user_country_id) = $row;
			}
			else
			{
				$this->errorMessage(get_label('Player not found.'));
			}
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		$this->ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('users')));
		echo '</td><td align="right">';
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, '', get_label('Go to the page where a specific user is located.'));
		echo '</td></tr></table></p>';
		
		$condition = new SQL();
		$sep = ' WHERE ';
		$ccc_id = $this->ccc_filter->get_id();
		switch ($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add($sep . 'u.club_id = ?', $ccc_id);
				$sep = ' AND ';
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add($sep . 'u.club_id IN (SELECT club_id FROM club_users WHERE user_id = ?)', $_profile->user_id);
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
			$pos_query = new DbQuery('SELECT count(*) FROM users u JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0', $condition);
			$pos_query->add($sep . 'nu.name < ?', $this->user_name);
			list($user_pos) = $pos_query->next();
			$_page = floor($user_pos / PAGE_SIZE);
		}
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(*) FROM users u', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered" width="100%">';
		echo '<tr class="th darker">';
		echo '<td width="87"></td>';
		echo '<td colspan="4">' . get_label('User name') . '</td><td width="40"></td></tr>';

		$query = new DbQuery(
			'SELECT u.id, nu.name, u.email, u.flags, c.id, c.name, c.flags' . 
			', SUM(IF((uc.flags & ' . (USER_PERM_PLAYER | USER_PERM_REFEREE | USER_PERM_MANAGER) . ') = ' . USER_PERM_PLAYER . ', 1, 0))' . 
			', SUM(IF((uc.flags & ' . (USER_PERM_REFEREE | USER_PERM_MANAGER) . ') = ' . USER_PERM_REFEREE . ', 1, 0))' . 
			', SUM(IF((uc.flags & ' . USER_PERM_MANAGER . ') <> 0, 1, 0))' . 
			' FROM users u' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN club_users uc ON uc.user_id = u.id' .
			' LEFT OUTER JOIN clubs c ON c.id = u.club_id', $condition);
		$query->add(' GROUP BY u.id ORDER BY nu.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			list($id, $name, $email, $flags, $club_id, $club_name, $club_flags, $clubs_player, $clubs_moder, $clubs_manager) = $row;
		
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
			echo '<button class="icon" onclick="mr.editUserAccess(' . $id . ')" title="' . get_label('Set [0] permissions.', $name) . '"><img src="images/access.png" border="0"></button>';
			echo '<button class="icon" onclick="mr.editUser(' . $id . ')" title="' . get_label('Edit [0] profile.', $name) . '"><img src="images/edit.png" border="0"></button>';
			echo '<button class="icon" onclick="resetPassword(' . $id . ')" title="' . get_label('Reset [0] password.', $name) . '"><img src="images/password.png" width="24" border="0"></button>';
			echo '</td>';
			
			echo '<td width="60" align="center">';
			$this->user_pic->set($id, $name, $flags);
			$this->user_pic->show(ICONS_DIR, true, 50);
			echo '</td>';
			echo '<td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 56) . '</a></td>';
			echo '<td width="200">' . $email . '</td>';
			echo '<td width="50" align="center">';
			$this->club_pic->set($club_id, $club_name, $club_flags);
			$this->club_pic->show(ICONS_DIR, true, 40);
			echo '</td>';
			
			echo '<td align="center">';
			$image_title = '';
			// echo $clubs_manager . ':' . $clubs_moder . ':' . $clubs_player;
			echo '<img width="32" height="32" src="images/';
			if ($flags & USER_PERM_ADMIN)
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
					echo 'referee.png';
				}
				else
				{
					$image_title .= "\n";
				}
				if ($all_clubs < 2)
				{
					$image_title .= get_label('Referee');
				}
				else if ($clubs_moder < 2)
				{
					$image_title .= get_label('Referee in 1 club');
				}
				else
				{
					$image_title .= get_label('Referee in [0] clubs', $clubs_moder);
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
		show_pages_navigation(PAGE_SIZE, $count);
	}
	
	protected function js()
	{
?>		
		function resetPassword(userId)
		{
			json.post("api/ops/user.php",
			{
				op: 'reset'
				, user_id: userId
			}, function(resp)
			{
				navigator.clipboard.writeText(resp.url);
				dlg.info('Reset URL is copied to the clipboard:<p><a href="' + resp.url + '" target="blank_">' + resp.url + '</a></p>', "'Activation URL", 400);
			});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('[0] users', PRODUCT_NAME));

?>