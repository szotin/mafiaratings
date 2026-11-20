<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/cards.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

class Page extends GeneralPageBase
{
	private $user_id;
	private $user_name;
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
				'SELECT nu.name'.
				' FROM users u'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' WHERE u.id = ?', $this->user_id);
			if ($row = $query->next())
			{
				list($this->user_name) = $row;
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
				$condition->add($sep . 'u.club_id IN (SELECT club_id FROM club_regs WHERE user_id = ?)', $_profile->user_id);
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

		// The list of the v3 design, after ratings.php: the filter and the search in a toolbar, the
		// table in a sheet.
		// The toolbar of a list (see new_look.css): the search first, then the place.
		echo '<div class="toolbar"><div class="field grow">' . PageBase::icon('search');
		show_user_input('page', $this->user_name, '', get_label('Go to the page where a specific user is located.'));
		echo '</div><div class="tb-field">';
		$this->ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('users')));
		echo '</div><span class="count">' . get_label('[0] users', number_format($count)) . '</span></div>';
		show_pages_navigation(PAGE_SIZE, $count);

		echo '<div class="sheet"><table class="tbl">';
		echo '<thead><tr><th scope="col"></th>';
		echo '<th scope="col">' . get_label('User name') . '</th>';
		echo '<th class="p2" scope="col">' . get_label('Email') . '</th>';
		echo '<th class="p3" scope="col">' . get_label('Club') . '</th>';
		echo '<th class="num" scope="col"></th></tr></thead><tbody>';
		$club_pic = new Picture(CLUB_PICTURE);

		$query = new DbQuery(
			'SELECT u.id, nu.name, u.email, u.flags, c.id, nc.name, c.flags, ni.name' .
			', SUM(IF((uc.flags & ' . (USER_PERM_PLAYER | USER_PERM_REFEREE | USER_PERM_MANAGER) . ') = ' . USER_PERM_PLAYER . ', 1, 0))' . 
			', SUM(IF((uc.flags & ' . (USER_PERM_REFEREE | USER_PERM_MANAGER) . ') = ' . USER_PERM_REFEREE . ', 1, 0))' . 
			', SUM(IF((uc.flags & ' . USER_PERM_MANAGER . ') <> 0, 1, 0))' . 
			' FROM users u' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' JOIN cities i ON i.id = u.city_id'.
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN club_regs uc ON uc.user_id = u.id' .
			' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
			' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0', $condition);
		$query->add(' GROUP BY u.id ORDER BY nu.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			list($id, $name, $email, $flags, $club_id, $club_name, $club_flags, $city, $clubs_player, $clubs_moder, $clubs_manager) = $row;
		
			echo '<tr' . ($id == $this->user_id ? ' class="me"' : '') . '>';
			echo '<td class="tb-btns">';
			echo '<button class="icon" onclick="mr.editUserAccess(' . $id . ')" title="' . get_label('Set [0] permissions.', $name) . '"><img src="images/access.png" border="0"></button>';
			echo '<button class="icon" onclick="mr.editUser(' . $id . ')" title="' . get_label('Edit [0] profile.', $name) . '"><img src="images/edit.png" border="0"></button>';
			echo '<button class="icon" onclick="resetPassword(' . $id . ')" title="' . get_label('Reset [0] password.', $name) . '"><img src="images/password.png" width="24" border="0"></button>';
			echo '</td>';

			echo '<td><a class="who" href="user_info.php?id=' . $id . '&bck=1">' . card_avatar($this->user_pic, $id, $name, $flags, '') .
				'<span class="who-txt"><span class="who-name">' . $name . '</span><span class="who-sub">' . $city . '</span></span></a></td>';
			echo '<td class="p2 dim">' . $email . '</td>';
			echo '<td class="p3">' . card_club($club_pic, $club_id, $club_name, $club_flags) . '</td>';

			echo '<td class="num">';
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
		echo '</tbody></table></div>';
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