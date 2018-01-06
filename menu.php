<?php

require_once 'include/page_base.php';

class Page extends PageBase
{
	protected function prepare()
	{
		if (!is_mobile())
		{
			throw new RedirectExc('index.php');
		}
	}

	protected function show_body()
	{
		global $_profile, $_session_state, $_lang_code, $_agent;
		
		$permissions = PERM_STRANGER;
		if ($_session_state == SESSION_OK)
		{
			$permissions = PERM_USER | ($_profile->user_flags & U_PERM_MASK) | ($_profile->user_club_flags & UC_PERM_MASK);
		}
		
		echo '<table class="bordered" width="100%">';
		if (($permissions & PERM_STRANGER) != 0)
		{
			// General
			echo '<tr><th class="menu dark" colspan="2">' . get_label('General') . '</th></tr>';
			echo '<tr><td colspan="2"><table class="transp" width="100%"><tr>';
			
			echo '<td width="17%" align="center"><a href="index.php?bck=0">' . get_label('Home');
			echo '<br><img src="images/blank.png" border="0"></a></td>';
			
			echo '<td width="17%" align="center"><a href="calendar.php?bck=0" title="' . get_label('Where and when can I play') . '">' . get_label('Calendar');
			echo '<br><img src="images/calendar_big.png" border="0"></a></td>';
			
			echo '<td width="17%" align="center"><a href="ratings.php?bck=0" title="' . get_label('Players ratings') . '">' . get_label('Ratings');
			echo '<br><img src="images/ratings_big.png" border="0"></a></td>';
			
			echo '<td width="17%" align="center"><a href="clubs.php?bck=0" title="' . get_label('Clubs list') . '">' . get_label('Clubs');
			echo '<br><img src="images/clubs_big.png" border="0"></a></td>';
			
			echo '<td width="16%" align="center"><a href="photo_albums.php?bck=0" title="' . get_label('Photo albums') . '">' . get_label('Photo albums');
			echo '<br><img src="images/album_big.png" border="0"></a></td>';
			
			echo '<td width="17%" align="center"><a href="events.php?bck=0" title="' . get_label('Events history') . '">' . get_label('History');
			echo '<br><img src="images/history_big.png" border="0"></a></td>';
			
//			echo '<td width="16%" align="center"><a href="welcome.php?bck=0" title="' . get_label('About Mafia: rules, tactics, general information.') . '">' . get_label('About');
//			echo '<br><img src="images/about_big.png" border="0"></a></td>';
			
			echo '</tr></table></td></tr>';
		}
		else
		{
			// User preferences and Game
			if (count($_profile->clubs) > 0)
			{
				echo '<tr><th class="menu dark" width="67%">' . get_label('Game') . '</th><th class="menu dark" width="33%">' . $_profile->user_name . '</th></tr>';
				echo '<tr><td><table class="transp" width="100%"><tr>';
			
				echo '<td width="33%" align="center"><a href="game.php?bck=0" title="' . get_label('Start the game') . '">' . get_label('The game');
				echo '<br><img src="images/play_big.png" border="0"></a></td>';
				
				echo '<td>&nbsp;</td></tr></table></td>';
				echo '<td><table class="transp" width="100%"><tr>';
				
				echo '<td width="50%" align="center"><a href="profile.php?bck=0" title="' . get_label('Change my profile options') . '">' . get_label('My profile');
				echo '<br><img src="images/profile_big.png" border="0"></a></td>';
				
				echo '<td width="50%" align="center"><a href="#" onclick="logout()" title="' . get_label('Logout from [0]', PRODUCT_NAME) . '">' . get_label('Log out');
				echo '<br><img src="images/logout_big.png" border="0"></a></td>';
				
				echo '<td>&nbsp;</td></tr></table></td></tr>';
			}
			else
			{
				echo '<tr><th class="menu dark" width="50%">' . $_profile->user_name . '</th></tr>';
				echo '<tr>';
				echo '<td><table class="transp" width="100%"><tr>';
				
				echo '<td width="17%" align="center"><a href="profile.php?bck=0" title="' . get_label('Change my profile options') . '">' . get_label('My profile');
				echo '<br><img src="images/profile_big.png" border="0"></a></td>';
				
				echo '<td>&nbsp;</td>';
				
				echo '<td width="17%" align="center"><a href="#" onclick="logout()" title="' . get_label('Logout from [0]', PRODUCT_NAME) . '">' . get_label('Log out');
				echo '<br><img src="images/logout_big.png" border="0"></a></td>';
				
				echo '</tr></table></td></tr>';
			}
			
			// General
			echo '<tr><th class="menu dark" colspan="2">' . get_label('General') . '</th></tr>';
			echo '<tr><td colspan="2"><table class="transp" width="100%"><tr>';
			
			echo '<td width="17%" align="center"><a href="index.php?bck=0">' . get_label('Home');
			echo '<br><img src="images/blank.png" border="0"></a></td>';
			
			echo '<td width="17%" align="center"><a href="calendar.php?bck=0" title="' . get_label('Where and when can I play') . '">' . get_label('Calendar');
			echo '<br><img src="images/calendar_big.png" border="0"></a></td>';
			
			echo '<td width="17%" align="center"><a href="ratings.php?bck=0" title="' . get_label('Players ratings') . '">' . get_label('Ratings');
			echo '<br><img src="images/ratings_big.png" border="0"></a></td>';
			
			echo '<td width="17%" align="center"><a href="clubs.php?bck=0" title="' . get_label('Clubs list') . '">' . get_label('Clubs');
			echo '<br><img src="images/clubs_big.png" border="0"></a></td>';
			
			echo '<td width="16%" align="center"><a href="photo_albums.php?bck=0" title="' . get_label('Photo albums') . '">' . get_label('Photo albums');
			echo '<br><img src="images/album_big.png" border="0"></a></td>';
			
			echo '<td width="17%" align="center"><a href="events.php?bck=0" title="' . get_label('Events history') . '">' . get_label('History');
			echo '<br><img src="images/history_big.png" border="0"></a></td>';
			
//			echo '<td width="16%" align="center"><a href="welcome.php?bck=0" title="' . get_label('About Mafia: rules, tactics, general information.') . '">' . get_label('About');
//			echo '<br><img src="images/about_big.png" border="0"></a></td>';
			
			echo '</tr></table></td></tr>';
		}

		echo '</table>';
	}
	
	function show_title()
	{
	}
}

$page = new Page();
$page->run(get_label('Menu'), PERM_ALL);

?>