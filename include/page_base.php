<?php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/user.php';
require_once __DIR__ . '/picture.php';

class PageBase
{
	private $_state;
	
	protected $_title;
	protected $_locked;
	protected $_admin;
	
	private $_err_message;
	private $_login;
	
	protected $_facebook;
	
	protected $user_pic;
	protected $club_pic;
	
	function __construct()
	{
		initiate_session();
		$this->user_pic = new Picture(USER_PICTURE);
		$this->club_pic = new Picture(CLUB_PICTURE);
	}
	
	final function run($title = '')
	{
		global $_profile;
		
		$this->_err_message = NULL;
		$this->_login = 0;
		if (isset($_REQUEST['_login_']))
		{
			$this->_login = $_REQUEST['_login_'];
			if (is_numeric($this->_login))
			{
				$this->_login = (int)$this->_login;
			}
		}
		$this->_facebook = true;
		$this->_title = $title;
		$this->_state = PAGE_STATE_EMPTY;
		$this->_locked = is_site_locked();
		$this->_admin = ($_profile != NULL && $_profile->is_admin());
		
		$title_shown = false;
		$header_shown = false;
		$footer_shown = false;
		
		try
		{
			$this->prepare();
			$this->show_header();
			$header_shown = true;
			// We are not showing lock page for administrators. They should be able to work even in the locked state.
			// They have fully functional site with the icon in the corner signalling that the site is locked.
			if ($this->_locked && !$this->_admin)
			{
				$title_shown = true;
				$this->show_lock_page();
			}
			else
			{
				$this->show_title();
				$title_shown = true;
				$this->show_body();
			}
			
			$this->show_footer();
			$footer_shown = true;
		}
		catch (RedirectExc $e)
		{
			$url = $e->get_url();
			header('location: ' . $url);
			try
			{
				if (!$header_shown)
				{
					$this->show_header();
				}
				echo '<a href="' . $url . '">' . get_label('Click here if you were not redirected.') . '</a>';
				if (!$footer_shown)
				{
					$this->show_footer();
				}
			}
			catch (Exception $e)
			{
				Exc::log($e);
			}
		}
		catch (Exception $e)
		{
			Db::rollback();
			Exc::log($e);
			$this->error($e);
			try
			{
				if (!$header_shown)
				{
					$this->show_header();
				}
				if (!$title_shown)
				{
					$this->show_title();
				}
				if (!$footer_shown)
				{
					$this->show_footer();
				}
			}
			catch (Exception $e)
			{
				Exc::log($e);
			}
		}
		
		// Javascript
		try
		{
			$this->_js();
		}
		catch (Exception $e)
		{
			Exc::log($e);
		}
	}
	
	private function show_lock_page()
	{
		global $_profile;
		
		echo '<h3>' . get_label('[0] is under maintenance. Please come again later.', PRODUCT_NAME) . '</h3>';
		echo '<img src="images/repairs.png" width="160">';
		if ($_profile != NULL && $_profile->is_admin())
		{
			echo '<p><input type="submit" class="btn norm" value="Unlock the site" onclick="mr.lockSite(false)"></p>';
		}
	}
	
	final function show_header()
	{
		global $_session_state, $_profile, $_lang_code;
		
		if ($this->_state != PAGE_STATE_EMPTY)
		{
			return;
		}
		
		echo '<!DOCTYPE HTML>';
		echo '<html>';
		echo '<head>';
		echo '<title>' . PRODUCT_NAME . ': ' . $this->_title . '</title>';
		echo '<META content="text/html; charset=utf-8" http-equiv=Content-Type>';
		echo '<script src="js/jquery.min.js"></script>';
		echo '<script src="js/jquery-ui.min.js"></script>';
		echo '<script src="js/jquery.ui.menubar.js"></script>';
		echo '<script src="js/labels_' . $_lang_code . '.js"></script>';
		echo '<script src="js/common.js"></script>';
		echo '<script src="js/md5.js"></script>';
		echo '<script src="js/mr.js"></script>';
		echo '<link rel="stylesheet" href="jquery-ui.css" />';
		
		echo '<meta property="og:title" content="' . PRODUCT_NAME . '" />';
		echo '<meta property="og:type" content="activity" />';
		echo '<meta property="og:url" content="' . PRODUCT_URL . '" />';
		echo '<meta property="og:site_name" content="' . PRODUCT_NAME . '" />';
		echo '<meta property="fb:admins" content="' . PRODUCT_FB_ADMINS . '" />';
		
		echo '<link rel="stylesheet" href="desktop.css" type="text/css" media="screen" />';
		echo '<link rel="stylesheet" href="common.css" type="text/css" media="screen" />';
		$this->add_headers();
		echo '</head>';
		
		$uri = get_page_url();
		if (count($_POST) == 0)
		{
			$_SESSION['last_page'] = array($this->_title, $uri);
			if (isset($_SESSION['back_list']))
			{
				$list = $_SESSION['back_list'];
				$last_back = count($list) - 1;
				if ($last_back >= 0 && $list[$last_back][1] == $uri)
				{
					unset($_SESSION['back_list'][$last_back]);
				}
	//			print_r($_SESSION['back_list']);
			}
		}

		echo '<body>';
		
		echo '<table border="0" cellpadding="5" cellspacing="0" width="' . PAGE_WIDTH . '" align="center">';
		echo '<tr class="header">';
		if ($this->_locked && $this->_admin)
		{
			echo '<td class="header" width="48"><a onclick="mr.lockSite(false)"><img src="images/repairs.png" title="The site is locked" width="48"/></a></td>';
		}
		if (is_ratings_server())
		{
			echo '<td class="header"><img src="images/title_r.png" /></td>';
		}
		else
		{
			echo '<td class="header"><img src="images/title.png" /></td>';
		}
		if ($_session_state == SESSION_NO_USER || $_session_state == SESSION_LOGIN_FAILED)
		{
			echo '<td class="header" align="right"><form action="javascript:login()">';
			echo get_label('User name') . ':&nbsp;<input id="header-username" class="in-header short">&nbsp;';
			echo get_label('Password') . ':&nbsp;<input type="password" id="header-password" class="in-header short">&nbsp;';
			echo '<input value="Login" class="in-header" type="submit"><br>';
			echo '<input class="in-header" type="checkbox" id="header-remember" checked>'.get_label('remember me').'</form></td>';
			echo '<td  class="header" align="right">';
			echo '<a href="index.php" onMouseEnter="javascript:setCurrentMenu(null)" title="' . get_label('Home') . '"><img src="images/home.png" /></a></>';
			echo '<a href="javascript:mr.resetPassword()" title="' . get_label('I forgot my password. Please help me!') . '"><img src="images/password.png"></a>';
			echo '<a href="javascript:mr.createAccount()" title="' . get_label('Create user account') . '"><img src="images/create_user.png"></a>';
		}
		else if ($_profile != NULL)
		{
			$club = NULL;
			if ($_profile->user_club_id != NULL && isset($_profile->clubs[$_profile->user_club_id]))
			{
				$club = $_profile->clubs[$_profile->user_club_id];
			}
			
			echo '<td valign="middle" align="right">';
			echo '<a href="index.php" onMouseEnter="javascript:setCurrentMenu(null)" title="' . get_label('Home') . '"><img src="images/home.png" /></a></>';
			// echo '<a href="ratings.php" onMouseEnter="javascript:setCurrentMenu(null)" title="' . get_label('Ratings') . '"><img src="images/clubs.png" /></a>';
			echo '<a href="game.php" onMouseEnter="javascript:setCurrentMenu(null)" title="' . get_label('The game') . '"><img src="images/thegame.png" /></a>';
			if ($club != NULL)
			{
				echo ' <a href="club_main.php?id=' . $club->id . '" title="' . $club->name . '" id="header-club"  onMouseEnter="javascript:';
				if (count($_profile->clubs) > 1)
				{
					echo 'showClubMenu()">';
				}
				else
				{
					echo 'setCurrentMenu(null)">';
				}
				$this->club_pic->set($club->id, $club->name, $club->club_flags);
				$this->club_pic->show(ICONS_DIR, false, 48); //, ' class="round"');
				echo '</a> ';
			}
			echo ' <a id="header-user" onMouseEnter="javascript:showUserMenu()" href="user_info.php?id=' . $_profile->user_id . '" title="' . $_profile->user_name . '">';
			$this->user_pic->set($_profile->user_id, $_profile->user_name, $_profile->user_flags);
			$this->user_pic->show(ICONS_DIR, false, 48); //, ' class="round"');
			echo '</a> ';
			
			echo '<ul id="header-user-menu" style="display:none;position:absolute;width:150px;text-align:left;z-index:2147483647;">';
			echo '<li><a href="user_info.php?id=' . $_profile->user_id . '" title="' . get_label('Statistics for [0]', $_profile->user_name) . '"><img src="images/user.png" class="menu_image"> ' . get_label('My statistics') . '</a></li>';
			echo '<li><a href="javascript:mr.editAccount()" title="' . get_label('Account settings') . '"><img src="images/settings.png" class="menu_image"> ' . get_label('My account') . '</a></li>';
			echo '<li><a href="javascript:mr.changePassword()" title="' . get_label('Change password') . '"><img src="images/key.png" class="menu_image"> ' . get_label('Change password') . '</a></li>';
			if ($_profile->user_accounts_count > 1)
			{
				echo '<li><a href="merge_user.php?id=' . $_profile->user_id . '" title="' . get_label('Merge accounts with the same email to current account.') . '"><img src="images/merge.png" class="menu_image"> ' . get_label('Merge my accounts') . '</a></li>';
			}
			echo '<li><a href="javascript:logout()" title="' . get_label('Logout from [0]', PRODUCT_NAME) . '"><img src="images/logout.png" class="menu_image"> ' . get_label('Log out') . '</a></li>';
			echo '</ul>';

			if (count($_profile->clubs) > 1)
			{
				echo '<ul id="header-club-menu" style="display:none;position:absolute;text-align:left;z-index:2147483647;">';
				foreach ($_profile->clubs as $c)
				{
					if ($c->id != $club->id && ($c->club_flags & CLUB_FLAG_RETIRED) == 0)
					{
						echo '<li><a href="club_main.php?id=' . $c->id . '">';
						$this->club_pic->set($c->id, $c->name, $c->club_flags);
						$this->club_pic->show(ICONS_DIR, false, 48, 48, ' class="menu_image"');
						echo ' ' . $c->name . '</a></li>';
					}
				}
				echo '</ul>';
			}
		}
		echo '</td><td width="32"><a id="header-lang" onMouseEnter="setCurrentMenu(null)" href="javascript:mr.browserLangChange(\'';
		if ($_lang_code == 'ru')
		{
			echo 'en';
		}
		else
		{
			echo 'ru';
		}
		echo '\')" title="' . get_label('Change language') . '"><img src="images/' . $_lang_code . '.png" width="32"></a>';
		
		echo '</td></tr></table>';
		echo '<table class="main" border="0" cellpadding="5" cellspacing="0" width="' . PAGE_WIDTH . '" align="center">';
		echo '<tr>';
		echo '<td valign="top">';

		$this->_state = PAGE_STATE_HEADER;

		switch ($_session_state)
		{
/*			case SESSION_TIMEOUT:
				echo get_label('[0], your session has been expired. Please login to continue', cut_long_name($_profile->user_name, 110)) . ':<br>';
				echo '<input type="hidden" id="header-username" value="' . $_profile->user_name . '">';
				echo 'Password:&nbsp;<input type="password" id="header-password"><br>';
				echo '<input type="checkbox" id="header-remember" checked> ' . get_label('remember me') . '<br>';
				echo '<input value="Login" type="submit" class="btn norm" onclick="login()">';
				return false;*/
			case SESSION_LOGIN_FAILED:
				throw new FatalExc(get_label('Login attempt failed. Wrong username or password.'));
		}
	}

	final function show_footer()
	{
		global $_lang_code, $_agent;
		
		if ($this->_state != PAGE_STATE_HEADER)
		{
			return;
		}
		
		echo '</td></tr></table>';
			
		echo '<table border="0" cellpadding="5" cellspacing="0" width="' . PAGE_WIDTH . '" align="center">';
		echo '<tr><td class="header">';
		
		echo '<img src="images/transp.png" width="1" height="24">';
			
		// facebook like button
/*		if ($this->_facebook)
		{
			// echo '<script src="http://connect.facebook.net/en_US/all.js#xfbml=1"></script><fb:like href="' . PRODUCT_URL . '" layout="button_count" show_faces="false" width="' . MENU_WIDTH . '"></fb:like>';
			echo '<iframe src="http://www.facebook.com/plugins/like.php?href=www.mafiaworld.ca&amp;layout=button_count&amp;show_faces=false&amp;width=450&amp;action=like&amp;font&amp;colorscheme=light&amp;height=21" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:450px; height:21px;" allowTransparency="true"></iframe>';
		}*/
		
		if (empty($_POST) && !isset($this->no_selectors))
		{
			echo '<td class="header" align="right" valign="top">';
			foreach ($_GET as $key => $value)
			{
				echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
			}
		}
		
		echo '</td></tr></table>';
		// Correct fb_xd_fragment bug in IE
		// echo '<script>document.getElementsByTagName(\'html\')[0].style.display=\'block\';</script>';
		// ...
		
		echo '<div id="dlg"></div>';
		echo '<div id="loading"><img style="margin-top:20px;z-index:2147483647;" src="images/loading.gif" alt="' . get_label('Loading..') . '"/><h4>' . get_label('Please wait..') . '</h4></div>';
		echo '</body></html>';
		$this->_state = PAGE_STATE_FOOTER;
	}
	
	// service functions
	static function show_menu($menu, $id = 'menubar')
	{
		$url = substr(strtolower(get_page_name()), 1);
		if ($id != NULL)
		{
			echo '<ul id="' . $id . '" style="display:none;">';
		}
		else
		{
			echo '<ul style="display:none;">';
		}
		
		foreach ($menu as $item)
		{
			echo '<li';
			if (strpos($item->page, $url) !== false)
			{
				echo ' class="ui-state-disabled"';
			}
			echo '><a href="' . $item->page . '"';
			if ($item->title != NULL)
			{
				echo ' title="' . $item->title . '"';
			}
			if ($item->new_window)
			{
				echo ' target="_blank"';
			}
			echo '>' . $item->text . '</a>';
			if ($item->submenu != NULL)
			{
				PageBase::show_menu($item->submenu, NULL);
			}
			echo '</li>';
		}
		echo '</ul>';
	}
	
	// virtual section these functions should be overriden
	protected function prepare()
	{
	}
	
	protected function standard_title()
	{
		return '<h3>' . $this->_title . '</h3>';
	}
	
	protected function show_title()
	{
		echo '<table class="head" width="100%"><tr><td>' . $this->standard_title() . '</td><td align="right" valign="top">';
		show_back_button();
		echo '</td></tr></table>';	
	}
	
	protected function show_body()
	{
	}
	
	protected function error($exc)
	{
		$message = str_replace('"', '\\"', $exc->getMessage());
		$login = 0;
		if ($exc instanceof LoginExc)
		{
			$login = $exc->user_name;
			if ($login == NULL)
			{
				$login = -1;
			}
		}
		$this->errorMessage($message, $login);
	}
	
	protected function errorMessage($message, $login = 0)
	{
		$this->_err_message = $message;
		if ($this->_login == 0)
		{
			$this->_login = $login;
		}
	}
	
	private function _js()
	{
		global $_profile;
	
		echo "\n<script>\n";
?>
		var currentMenu = null;
		var setCurrentMenu = function(menu)
		{
			if (currentMenu != null)
			{
				$(currentMenu).hide();
			}
			currentMenu = menu;
		}
		
<?php		
		if ($_profile != NULL)
		{
?>
			var showUserMenu = function()
			{
				setCurrentMenu('#header-user-menu');
				var userMenu = $('#header-user-menu').menu();
				userMenu.show(0, function()
				{
					userMenu.position(
					{
						my: "right top",
						at: "right bottom",
						of: $('#header-user')
					});
					$(document).one("click", function() { setCurrentMenu(null); });
				});
			}
<?php
			if (count($_profile->clubs) > 1)
			{
?>
				var showClubMenu = function()
				{
					setCurrentMenu('#header-club-menu');
					var clubMenu = $('#header-club-menu').menu();
					clubMenu.show(0, function()
					{
						clubMenu.position(
						{
							my: "right top",
							at: "right bottom",
							of: $('#header-club')
						});
						$(document).one("click", function() { setCurrentMenu(null); });
					});
				}
<?php
			}
		}
		echo "\n\t$(function()";
		echo "\n\t{\n";
		if ($this->_login && ($_profile == NULL || ($_profile->user_id != $this->_login && strtolower($_profile->user_name) != strtolower($this->_login))))
		{
			$login_name = '';
			if (is_string($this->_login))
			{
				$login_name = $this->_login;
			}
			else if ($this->_login > 0)
			{
				$query = new DbQuery('SELECT name FROM users WHERE id = ?', $this->_login);
				if ($row = $query->next())
				{
					list ($login_name) = $row;
				}
			}
			echo "\n\t\tloginDialog(\"" . $this->_err_message . '", "' . $login_name . '");';
		}
		else if ($this->_err_message != NULL)
		{
			echo "\n\t\tdlg.error(\"" . $this->_err_message . "\");";
		}
		echo "\n\t\tshowMenuBar();\n\n";
		if ($_profile != NULL && ($_profile->user_flags & USER_FLAG_NO_PASSWORD) != 0)
		{
			echo "\n\t\tmr.initProfile();\n\n";
		}
		$this->js_on_load();
		echo "\n\t});\n";
		$this->js();
		echo "\n</script>";
	}
	
	protected function js_on_load()
	{
	}
	
	protected function js()
	{
	}
	
	protected function add_headers()
	{
	}
}

class MenuItem
{
	public $page;
	public $title;
	public $text;
	public $submenu;
	public $new_window;
	
	function __construct($page, $text, $title, $submenu = NULL, $new_window = false)
	{
		$this->page = $page;
		$this->title = $title;
		$this->text = $text;
		$this->submenu = $submenu;
		$this->new_window = $new_window;
	}
}

/* template

class Page extends PageBase
{
	protected function prepare()
	{
		global $_profile;
	}
	
	protected function show_body()
	{
		global $_profile;
	}
}

$page = new Page();
$page->run(get_label(''));

*/

?>