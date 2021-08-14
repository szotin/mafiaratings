<?php

require_once 'include/league.php';
require_once 'include/club.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/image.php';
require_once 'include/user_location.php';

define('COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

define('FLAG_SHOW_ACTIVE', 1);
define('FLAG_SHOW_RETIRED', 2);

class Page extends LeaguePageBase
{
	private $view_flags;
	
	protected function prepare()
	{
		parent::prepare();
		
		$this->view_flags = FLAG_SHOW_ACTIVE;
		if (isset($_REQUEST['flags']))
		{
			$this->view_flags = (int)$_REQUEST['flags'];
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_lang_code;
		
		$condition = new SQL();
		if (($this->view_flags & FLAG_SHOW_RETIRED) == 0)
		{
			$condition->add(' AND (c.flags & ' . CLUB_FLAG_RETIRED . ') = 0');
		}
		if (($this->view_flags & FLAG_SHOW_ACTIVE) == 0)
		{
			$condition->add(' AND (c.flags & ' . CLUB_FLAG_RETIRED . ') <> 0');
		}
		
		$managed_clubs_not_in_league_count = 0;
		if ($_profile != NULL)
		{
			foreach ($_profile->clubs as $club)
			{
				if (($club->flags & USER_CLUB_PERM_MANAGER) != 0 && ($club->club_flags & CLUB_FLAG_RETIRED) == 0)
				{
					++$managed_clubs_not_in_league_count;
				}
			}
		}
		
		$clubs = array();
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, c.web_site, l.flags, i.name_' . $_lang_code . ', (SELECT count(*) FROM games g WHERE g.club_id = c.id) as games FROM league_clubs l' .
				' JOIN clubs c ON l.club_id = c.id' .
				' JOIN cities i ON c.city_id = i.id' .
				' WHERE l.league_id = ?',
			$this->id, $condition);
		$query->add(' ORDER BY l.flags DESC, games DESC, c.name');
		while ($row = $query->next())
		{
			list ($id, $name, $flags, $url, $league_flags, $city_name) = $row;
			$is_club_manager = is_permitted(PERMISSION_CLUB_MANAGER, $id);
			if ($is_club_manager)
			{
				--$managed_clubs_not_in_league_count;
				$clubs[] = $row;
			}
			else if ($this->is_manager || $league_flags == 0)
			{
				$clubs[] = $row;
			}
		}
		$can_add = ($this->is_manager || $managed_clubs_not_in_league_count > 0) && ($this->view_flags & FLAG_SHOW_ACTIVE) != 0;
		
		$column_count = 0;
		$clubs_count = 0;
		if ($can_add)
		{
			++$column_count;
			++$clubs_count;
		}
		
		echo '<table class="transp"><tr><td><input type="checkbox" id="retired" onclick="filter()"';
		if ($this->view_flags & FLAG_SHOW_RETIRED)
		{
			echo ' checked';
		}
		echo '> ' . get_label('Show retired clubs') . '</td></tr></table>';
		
		if ($can_add)
		{
			echo '<table class="bordered" width="100%"><tr>';
			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="top" class="light">';	
			echo '<table class="transp" width="100%">';
			echo '<tr><td align="left" class="light wide">';
			echo '<img src="images/transp.png" height="26">';
			echo '</td></tr><tr><td align="center"><a href="#" onclick="mr.addLeagueClub(' . $this->id . ')">' . get_label('Add [0]', get_label('club'));
			echo '<br><img src="images/create_big.png" border="0" width="' . ICON_WIDTH . '" height="' . ICON_HEIGHT . '">';
			echo '</td></tr></table>';
			echo '</td>';
		}
		foreach ($clubs as $row)
		{
			list ($id, $name, $flags, $url, $league_flags, $city_name) = $row;
			if ($column_count == 0)
			{
				if ($clubs_count == 0)
				{
					echo '<table class="bordered" width="100%">';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}

			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="top" class="light">';
			
			echo '<table class="transp" width="100%"><tr class="darker"><td align="left" style="padding:2px;">';
			if ($this->is_manager)
			{
				if ($league_flags & LEAGUE_CLUB_FLAGS_LEAGUE_APROVEMENT_NEEDED)
				{
					echo '<button class="icon" onclick="mr.removeLeagueClub(' . $this->id . ', ' . $id . ')" title="' . get_label('Decline request') . '"><img src="images/delete.png" border="0"></button>';
					echo '<button class="icon" onclick="mr.acceptLeagueClub(' . $this->id . ', ' . $id . ')" title="' . get_label('Approve request') . '"><img src="images/accept.png" border="0"></button>';
					echo '</td><td><b>' . get_label('Wants to join') . '</b>';
				}
				else
				{
					echo '<button class="icon" onclick="mr.removeLeagueClub(' . $this->id . ', ' . $id . ')" title="' . get_label('Remove [0] from [1]', $name, $this->name) . '"><img src="images/delete.png" border="0"></button>';
					if ($league_flags & LEAGUE_CLUB_FLAGS_CLUB_APROVEMENT_NEEDED)
					{
						if (is_permitted(PERMISSION_CLUB_MANAGER, $id))
						{
							echo '<button class="icon" onclick="mr.acceptLeagueClub(' . $this->id . ', ' . $id . ')" title="' . get_label('Accept invitation') . '"><img src="images/accept.png" border="0"></button>';
						}
						echo '</td><td><b>' . get_label('Invited') . '</b>';
					}
				}
			}
			else if (is_permitted(PERMISSION_CLUB_MANAGER, $id))
			{
				echo '<button class="icon" onclick="mr.removeLeagueClub(' . $this->id . ', ' . $id . ')" title="' . get_label('Remove [0] from [1]', $name, $this->name) . '"><img src="images/delete.png" border="0"></button>';
				if ($league_flags & LEAGUE_CLUB_FLAGS_CLUB_APROVEMENT_NEEDED)
				{
					echo '<button class="icon" onclick="mr.acceptLeagueClub(' . $this->id . ', ' . $id . ')" title="' . get_label('Accept invitation') . '"><img src="images/accept.png" border="0"></button>';
					echo '</td><td><b>' . get_label('Invited') . '</b>';
				}
				else if ($league_flags & LEAGUE_CLUB_FLAGS_LEAGUE_APROVEMENT_NEEDED)
				{
					echo '</td><td><b>' . get_label('Wants to join') . '</b>';
				}
			}
			else
			{
				echo '<img src="images/transp.png" height="26">';
			}
			echo '</td></tr>';
			
			echo '<tr><td colspan="2" align="center"><a href="club_main.php?bck=1&id=' . $id . '">';
			echo '<b>' . $name . '</b><br>';
			$this->club_pic->set($id, $name, $flags);
			$this->club_pic->show(ICONS_DIR, false);
			echo '<br></a>' . $city_name . '<br>';
			
			echo '</td></tr></table>';
			echo '</td>';
			
			++$clubs_count;
			++$column_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		if ($clubs_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td colspan="' . (COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
			}
			echo '</tr></table>';
		}
	}
	
	protected function js()
	{
		parent::js();
?>
		function filter()
		{
			var flags = <?php echo $this->view_flags; ?>;
			if ($("#retired").attr("checked"))
			{
				flags |= <?php echo FLAG_SHOW_RETIRED; ?>;
			}
			else
			{
				flags &= ~<?php echo FLAG_SHOW_RETIRED; ?>;
			}
			goTo({ flags: flags, page: 0 });
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('League clubs'));

?>