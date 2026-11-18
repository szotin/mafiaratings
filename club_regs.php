<?php

require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/cards.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

class Page extends ClubPageBase
{
	private $user_id;
	private $user_name;
	
	protected function prepare()
	{
		global $_profile, $_page, $_lang;
	
		parent::prepare();
		
		if (!$this->is_manager)
		{
			check_permissions(PERMISSION_CLUB_REFEREE, $this->id);
		}
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
				list($is_member) = Db::record(get_label('user'), 'SELECT count(*) FROM club_regs WHERE user_id = ? AND club_id = ?', $this->user_id, $this->id);
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
	}
	
	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		$condition = new SQL('u.id = uc.user_id AND uc.club_id = ?', $this->id);
		if ($this->user_id > 0)
		{
			$pos_query = new DbQuery(
				'SELECT count(*)'.
				' FROM club_regs uc'.
				' JOIN users u ON uc.user_id = u.id'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' WHERE uc.club_id = ? AND nu.name < ?', $this->id, $this->user_name);
			list($user_pos) = $pos_query->next();
			$_page = floor($user_pos / PAGE_SIZE);
		}
		
		$club_reg_pic = new Picture(USER_CLUB_PICTURE, $this->user_pic);
		$club_pic = new Picture(CLUB_PICTURE);
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(*) FROM users u, club_regs uc WHERE ', $condition);
		
		// The list of the v3 design, after ratings.php: the search and the count in a toolbar, the
		// button that adds a member at its end, the table in a sheet.
		echo '<form method="get" name="viewForm">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<div class="toolbar"><div class="field grow">' . PageBase::icon('search');
		show_user_input('page', $this->user_name, 'club=' . $this->id, get_label('Go to the page where a specific user is located.'));
		echo '</div><span class="count">' . get_label('[0] members', number_format($count)) . '</span>';
		if ($this->is_manager)
		{
			echo '<a class="btn tb-create" href="#" onclick="addMember(); return false;">' . PageBase::icon('plus') . '<span>' . get_label('Add club member') . '</span></a>';
		}
		echo '</div></form>';
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<div class="sheet"><table class="tbl">';
		echo '<thead><tr>';
		if ($this->is_manager)
		{
			echo '<th scope="col"></th>';
		}
		echo '<th scope="col">' . get_label('User') . '</th>';
		if ($this->is_manager)
		{
			echo '<th class="p2" scope="col">' . get_label('Email') . '</th>';
		}
		echo '<th class="p3" scope="col">' . get_label('Club') . '</th>';
		echo '<th class="num" scope="col">' . get_label('Permissions') . '</th></tr></thead><tbody>';

		$query = new DbQuery(
			'SELECT u.id, nu.name, u.email, u.flags, uc.flags, c.id, nc.name, c.flags, ni.name' .
			' FROM club_regs uc' .
			' JOIN users u ON uc.user_id = u.id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' JOIN cities i ON u.city_id = i.id'.
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
			' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0'.
			' WHERE uc.club_id = ?' .
			' ORDER BY nu.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE,
			$this->id);
		while ($row = $query->next())
		{
			list($id, $name, $email, $flags, $club_reg_flags, $club_id, $club_name, $club_flags, $city) = $row;
		
			echo '<tr' . ($id == $this->user_id ? ' class="me"' : '') . '>';
			if ($this->is_manager)
			{
				echo '<td class="tb-btns">';
				echo '<button class="icon" onclick="removeMember(' . $id . ', \'' . $name . '\')" title="' . get_label('Remove [0] from club members.', $name) . '"><img src="images/delete.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.editClubAccess(' . $id . ', ' . $this->id . ')" title="' . get_label('Set [0] permissions.', $name) . '"><img src="images/access.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.clubUserPhoto(' . $id . ', ' . $this->id . ')" title="' . get_label('Set [0] photo for [1].', $name, $this->name) . '"><img src="images/photo.png" border="0"></button>';
				if ($club_id == $this->id)
				{
					echo '<button class="icon" onclick="mr.editUser(' . $id . ')" title="' . get_label('Edit [0] profile.', $name) . '"><img src="images/edit.png" border="0"></button>';
				}
				echo '</td>';
			}
			
			$club_reg_pic->set($id, $name, $club_reg_flags, 'c' . $this->id)->set($id, $name, $flags);
			echo '<td><a class="who" href="user_info.php?id=' . $id . '&bck=1">' . card_avatar_of($club_reg_pic, $id, $name, '') .
				'<span class="who-txt"><span class="who-name">' . $name . '</span><span class="who-sub">' . $city . '</span></span></a></td>';
			if ($this->is_manager)
			{
				echo '<td class="p2 dim">';
				if ($club_id == $this->id)
				{
					echo $email;
				}
				echo '</td>';
			}
			echo '<td class="p3">' . card_club($club_pic, $club_id, $club_name, $club_flags) . '</td>';
			
			echo '<td class="num perms">';
			if ($club_reg_flags & USER_CLUB_FLAG_SUBSCRIBED)
			{	
				echo '<img src="images/email.png" width="24" title="' . get_label('Subscribed') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="24">';
			}
			if ($club_reg_flags & USER_PERM_PLAYER)
			{
				echo '<img src="images/player.png" width="32" title="' . get_label('Player') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="32">';
			}
			if ($club_reg_flags & USER_PERM_REFEREE)
			{
				echo '<img src="images/referee.png" width="32" title="' . get_label('Referee') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="32">';
			}
			if ($club_reg_flags & USER_PERM_MANAGER)
			{
				echo '<img src="images/manager.png" width="32" title="' . get_label('Manager') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="32">';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
		show_pages_navigation(PAGE_SIZE, $count);
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
		
		function removeMember(userId, userName)
		{
			let msg = "<?php echo get_label('Are you sure you want to remove {name}?'); ?>";
			dlg.yesNo(msg.replaceAll("{name}", userName), null, null, function()
			{
				json.post("api/ops/club.php", { op: "remove_member", club_id: <?php echo $this->id; ?>, user_id: userId }, function() { goTo({ page: undefined }); });
			});
		}
		
<?php
	}
}

$page = new Page();
$page->run(get_label('Members'));

?>