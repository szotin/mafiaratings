<?php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/user.php';
require_once __DIR__ . '/picture.php';

define('PROMPT_TYPE_NONE', 0);
define('PROMPT_TYPE_INFO', 1);
define('PROMPT_TYPE_ERROR', 2);
define('PROMPT_TYPE_WARNING', 3);

define('INDEXING_POLICY_NONE', 0);
define('INDEXING_POLICY_INDEX', 1);
define('INDEXING_POLICY_INDEX_FOLLOW', 2);


class PageBase
{
	private $_state;
	
	protected $_title;
	protected $_locked;
	protected $_admin;
	protected $_indexing_policy;
	
	private $_prompt;
	private $_prompt_type;
	private $_login;
	
	protected $_facebook;
	
	protected $user_pic;
	protected $club_pic;
	
	function __construct()
	{
		initiate_session();
		// Before anything is written: the tab's id goes back in a cookie.
		get_back_tab_id();
		$this->user_pic = new Picture(USER_PICTURE);
		$this->club_pic = new Picture(CLUB_PICTURE);
		$this->_indexing_policy = INDEXING_POLICY_NONE;
	}
	
	final function run($title = '')
	{
		global $_profile;
		
		if (REQUEST_PROFILING > 0)
		{
			$this->start = microtime(true);
		}
		
		$this->_prompt = NULL;
		$this->_prompt_type = PROMPT_TYPE_NONE;
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
				$this->open_content();
				$this->show_lock_page();
			}
			else
			{
				$this->show_title_band();
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
					// The plain heading rather than the page's own. A page writes its heading
					// out of what prepare() read - the name of the event, the club it belongs
					// to, the buttons its manager is given - and prepare() is what just threw,
					// so none of that is there. Asked for the heading anyway, the page wrote
					// one out of nothing: a warning in the log for every field of it, some
					// thirty of them per failed request, which is the greater part of what the
					// error log on the production server had grown to. And where such a page
					// counted one of those fields instead of printing it, PHP 8 stopped it a
					// second time - see the count() of the series in TournamentPageBase. The
					// base heading is the title the page was run under and a back button, and
					// both of those are known whatever prepare() did.
					$this->show_title_band(true);
				}
				else
				{
					$this->open_content();
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
		
		if (REQUEST_PROFILING > 0)
		{
			write_profiling_info(microtime(true) - $this->start);
		}
	}
	
	// The heading of the page goes on the dark band across the top - the page's title, or the
	// picture, name and tabs of the club, event, tournament... it belongs to - and everything
	// after it on the sheet of the page below. A page whose heading prints nothing (the game)
	// gets no band. $base_title: the plain heading of PageBase rather than the page's own; see
	// the catch in run().
	private function show_title_band($base_title = false)
	{
		ob_start();
		try
		{
			if ($base_title)
			{
				PageBase::show_title();
			}
			else
			{
				$this->show_title();
			}
		}
		catch (Exception $e)
		{
			ob_end_clean();
			throw $e;
		}
		$title = ob_get_clean();
		if (trim($title) != '')
		{
			// The menu of a club, an event, a tournament... is the first row of its heading, but in the
			// look of the site its tabs run along the bottom edge of the band: moved to the end.
			if (preg_match('#<nav class="tabs">.*?</nav>#s', $title, $matches))
			{
				$title = str_replace($matches[0], '', $title) . $matches[0];
			}
			echo '<section class="mast"><div class="wrap">' . $title . '</div></section>';
		}
		$this->open_content();
	}

	private $_content_open = false;

	private function open_content()
	{
		if (!$this->_content_open)
		{
			echo '<div class="wrap page ' . $this->content_class() . '">';
			$this->_content_open = true;
		}
	}

	// The class of the sheet the page's content is on. 'nl' restyles the markup every page shares -
	// the bordered tables, buttons, inputs - in the look of the site. A page with a look of its own,
	// as the game is, returns something else.
	protected function content_class()
	{
		return 'nl';
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
		global $_session_state, $_profile, $_lang;
		
		if ($this->_state != PAGE_STATE_EMPTY)
		{
			return;
		}
		
		echo '<!DOCTYPE HTML>';
		echo '<html>';
		echo '<head>';
		echo '<title>' . PRODUCT_NAME . ': ' . $this->_title . '</title>';
		echo '<META content="text/html; charset=utf-8" http-equiv=Content-Type>';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<link rel="icon" type="image/svg+xml" href="images/favicon.svg">';
		switch ($this->_indexing_policy)
		{
		case INDEXING_POLICY_NONE:
			echo '<meta name="robots" content="noindex, nofollow">';
			break;
		case INDEXING_POLICY_INDEX:
			echo '<meta name="robots" content="nofollow">';
			break;
		case INDEXING_POLICY_INDEX_FOLLOW:
		default:
			break;
		}
		echo '<script src="js/jquery.min.js"></script>';
		echo '<script src="js/jquery-ui.min.js"></script>';
		echo '<script src="js/jquery.ui.menubar.js"></script>';
		echo '<script src="' . versioned_asset('js/labels_' . get_lang_code($_lang) . '.js') . '"></script>';
		echo '<script src="' . versioned_asset('js/common.js') . '"></script>';
		echo '<script src="' . versioned_asset('js/md5.js') . '"></script>';
		echo '<script src="' . versioned_asset('js/mr.js') . '"></script>';
		echo '<link rel="stylesheet" href="jquery-ui.css" />';
		
		echo '<meta property="og:title" content="' . PRODUCT_NAME . '" />';
		echo '<meta property="og:type" content="activity" />';
		echo '<meta property="og:url" content="' . PRODUCT_URL . '" />';
		echo '<meta property="og:site_name" content="' . PRODUCT_NAME . '" />';
		echo '<meta property="fb:admins" content="' . PRODUCT_FB_ADMINS . '" />';
		
		echo '<link rel="stylesheet" href="' . versioned_asset('desktop.css') . '" type="text/css" media="screen" />';
		echo '<link rel="stylesheet" href="' . versioned_asset('common.css') . '" type="text/css" media="screen" />';
		echo '<link rel="stylesheet" href="' . versioned_asset('new_look.css') . '" type="text/css" />';
		$this->add_headers();
		show_back_tab_script();
		echo '</head>';
		
		$uri = get_page_url();
		if (count($_POST) == 0)
		{
			set_back_tab_page($this->_title, $uri);
		}

		echo '<body>';
		echo '<a class="skip" href="#main">' . get_label('Skip to content') . '</a>';

		$menu = PageBase::get_main_menu();

		echo '<header class="hd"><div class="wrap">';
		if ($this->_locked && $this->_admin)
		{
			echo '<a class="hd-lock" onclick="mr.lockSite(false)" title="The site is locked"><img src="images/repairs.png" alt=""></a>';
		}
		echo '<a class="brand" href="index.php?bck=0" title="' . get_label('Home') . '"><span class="wm-a">Mafia</span><span class="wm-b">Ratings</span></a>';

		echo '<nav class="nav" aria-label="' . get_label('Menu') . '">';
		PageBase::show_nav_items($menu);
		echo '</nav>';

		echo '<div class="hd-tools">';
		echo '<button type="button" class="search-btn" data-open-search aria-label="' . get_label('Search') . '" title="' . get_label('Search') . ' (Ctrl+K)">' . PageBase::icon('search') . '<span>' . get_label('Search') . '</span><kbd>/</kbd></button>';
		if ($_profile != NULL)
		{
			echo '<a class="btn-create" href="game.php?bck=0" title="' . get_label('The game') . '">' . PageBase::icon('play') . '<span>' . get_label('Play') . '</span></a>';
		}

		PageBase::show_lang_switch();

		if ($_session_state == SESSION_NO_USER || $_session_state == SESSION_LOGIN_FAILED || $_profile == NULL)
		{
			echo '<div class="nav-more hd-login">';
			echo '<button type="button" class="btn-login" aria-expanded="false">' . PageBase::icon('user') . '<span>' . get_label('Login') . '</span></button>';
			echo '<div class="menu menu-right login-panel" hidden>';
			echo '<form action="javascript:login()">';
			echo '<label>' . get_label('Login') . '<input id="header-username" autocomplete="username"></label>';
			echo '<label>' . get_label('Password') . '<input type="password" id="header-password" autocomplete="current-password"></label>';
			echo '<label class="chk"><input type="checkbox" id="header-remember" checked> ' . get_label('remember me') . '</label>';
			echo '<button type="submit" class="btn">' . get_label('Login') . '</button>';
			echo '</form>';
			echo '<div class="menu-sep"></div>';
			echo '<a href="javascript:mr.resetPassword()">' . get_label('I forgot my password. Please help me!') . '</a>';
			echo '<a href="javascript:mr.createAccount()">' . get_label('Create user account') . '</a>';
			echo '</div></div>';
		}
		else
		{
			echo '<div class="nav-more hd-user">';
			echo '<button type="button" class="hd-avatar" aria-expanded="false" title="' . $_profile->user_name . '">';
			$this->user_pic->set($_profile->user_id, $_profile->user_name, $_profile->user_flags);
			$this->user_pic->show(ICONS_DIR, false, 36);
			echo '</button>';
			echo '<div class="menu menu-right" hidden>';
			echo '<div class="menu-who">' . $_profile->user_name . '</div>';
			echo '<a href="user_info.php?id=' . $_profile->user_id . '">' . get_label('My statistics') . '</a>';
			echo '<a href="javascript:mr.editUser(' . $_profile->user_id . ')">' . get_label('My account') . '</a>';
			echo '<a href="javascript:mr.changePassword()">' . get_label('Change password') . '</a>';
			if ($_profile->user_accounts_count > 1)
			{
				echo '<a href="merge_user.php?id=' . $_profile->user_id . '" title="' . get_label('Merge accounts with the same email to current account.') . '">' . get_label('Merge my accounts') . '</a>';
			}
			$clubs_shown = false;
			foreach ($_profile->clubs as $c)
			{
				if (($c->club_flags & CLUB_FLAG_CLOSED) != 0)
				{
					continue;
				}
				if (!$clubs_shown)
				{
					echo '<div class="menu-sep"></div><div class="menu-label">' . get_label('My clubs') . '</div>';
					$clubs_shown = true;
				}
				echo '<a class="menu-club" href="club_main.php?id=' . $c->id . '">';
				$this->club_pic->set($c->id, $c->name, $c->club_flags);
				$this->club_pic->show(ICONS_DIR, false, 22);
				echo '<span>' . $c->name . '</span></a>';
			}
			echo '<div class="menu-sep"></div>';
			echo '<a href="javascript:logout()">' . get_label('Log out') . '</a>';
			echo '</div></div>';
		}

		echo '<button type="button" class="burger" aria-expanded="false" aria-controls="drawer" aria-label="' . get_label('Menu') . '">' . PageBase::icon('menu') . '</button>';
		echo '</div>'; // hd-tools
		echo '</div></header>';

		// The same menu, for a screen too narrow for the row in the header.
		echo '<div class="drawer" id="drawer" hidden><div class="drawer-scrim" data-close></div>';
		echo '<div class="drawer-panel" role="dialog" aria-modal="true" aria-label="' . get_label('Menu') . '" tabindex="-1">';
		echo '<div class="drawer-top"><a class="brand" href="index.php?bck=0"><span class="wm-a">Mafia</span><span class="wm-b">Ratings</span></a>';
		echo '<button type="button" class="drawer-close" data-close aria-label="' . get_label('Close') . '">' . PageBase::icon('close') . '</button></div>';
		echo '<nav class="drawer-body">';
		if ($_profile != NULL)
		{
			echo '<a class="btn drawer-create" href="game.php?bck=0">' . PageBase::icon('play') . '<span>' . get_label('Play') . '</span></a>';
		}
		PageBase::show_drawer_items($menu);
		echo '</nav>';
		echo '<div class="drawer-foot"><span>' . get_label('Language') . '</span>';
		PageBase::show_lang_switch();
		echo '</div></div></div>';

		PageBase::show_search_dialog();

		echo '<main id="main" tabindex="-1">';

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
		if ($this->_state != PAGE_STATE_HEADER)
		{
			return;
		}

		if ($this->_content_open)
		{
			echo '</div>';
			$this->_content_open = false;
		}
		echo '</main>';

		echo '<footer class="ft"><div class="wrap">';
		echo '<a class="brand" href="index.php?bck=0"><span class="wm-a">Mafia</span><span class="wm-b">Ratings</span></a>';
		echo '<nav class="ft-nav">';
		foreach (PageBase::get_main_menu() as $item)
		{
			if (!is_null($item->page) && is_null($item->submenu))
			{
				echo '<a href="' . $item->page . '">' . $item->text . '</a>';
			}
		}
		echo '</nav>';
		echo '<p class="ft-note">&copy; ' . date('Y') . ' ' . PRODUCT_NAME;
		if (REQUEST_PROFILING > 0)
		{
			echo ' &middot; ' . number_format(microtime(true) - $this->start, 3) . ' sec';
		}
		echo '</p>';
		echo '</div></footer>';

		// Keeps a row of tabs to one row, with a menu for those that do not fit - see tabs.js.
		echo '<script src="' . versioned_asset('tabs.js') . '"></script>';
		// The header: its menus, the drawer, the language - see new_look.js.
		echo '<script src="' . versioned_asset('new_look.js') . '"></script>';
		echo '<div id="dlg"></div>';
		echo '<div id="loading"><img style="margin-top:20px;z-index:2147483647;" src="images/loading.gif" alt="' . get_label('Loading..') . '"/><h4>' . get_label('Please wait..') . '</h4></div>';
		echo '</body></html>';
		$this->_state = PAGE_STATE_FOOTER;
	}

	// The menu of the whole site, in the header, the drawer and the footer. Items with a submenu
	// are the dropdowns; a NULL page in a submenu is a separator.
	static function get_main_menu()
	{
		global $_profile;

		$ccc = '';
		if (isset($_REQUEST['ccc']))
		{
			$ccc = '?ccc=' . urlencode($_REQUEST['ccc']);
		}

		$menu = array
		(
			new MenuItem('ratings.php' . $ccc, get_label('Ratings'), get_label('Players ratings')),
			new MenuItem('tournaments.php' . $ccc, get_label('Tournaments'), get_label('Tournaments history')),
			new MenuItem('series.php', get_label('Series'), get_label('Series history')),
			new MenuItem('leagues.php' . $ccc, get_label('Leagues'), get_label('Leagues list')),
			new MenuItem('clubs.php' . $ccc, get_label('Clubs'), get_label('Clubs list')),
			new MenuItem('games.php' . $ccc, get_label('Games'), get_label('List of all played games')),
			new MenuItem('#more', get_label('More'), NULL, array
			(
				new MenuItem('events.php' . $ccc, get_label('Events'), get_label('Events history')),
				new MenuItem('broadcasts.php', get_label('Broadcasts'), get_label('Broadcasts from various events.')),
				new MenuItem('seatings.php', get_label('Seatings'), get_label('Seating arrangements')),
				new MenuItem(NULL, get_label('Reports'), NULL),
				new MenuItem('stats.php' . $ccc, get_label('General stats'), get_label('General statistics. How many games played, mafia winning percentage, how many players, etc.', PRODUCT_NAME)),
				new MenuItem('by_numbers.php' . $ccc, get_label('By numbers'), get_label('Statistics by table numbers. What is the most winning number, or what number is shot more often.')),
				new MenuItem('nominations.php' . $ccc, get_label('Nomination winners'), get_label('Custom nomination winners. For example who had most warnings, or who was checked by sheriff most often.')),
				new MenuItem('referees.php' . $ccc, get_label('Referees'), get_label('Referees statistics')),
				new MenuItem('competition.php' . $ccc, get_label('Competition chart'), get_label('Competition chart at the top of the rating.')),
				new MenuItem(NULL, get_label('Resources'), NULL),
				new MenuItem('rules.php', get_label('Rulebook'), get_label('Rules of the game in [0]', PRODUCT_NAME)),
				new MenuItem('photo_albums.php' . $ccc, get_label('Photos'), get_label('Photo albums')),
				new MenuItem('videos.php' . $ccc, get_label('Videos'), get_label('Videos from various events.')),
			)),
		);

		// What only an administrator sees goes into "More" as a section of its own, not into a
		// dropdown beside it: the row of the header has no room for one more.
		if ($_profile != NULL && $_profile->is_admin())
		{
			$more = $menu[count($menu) - 1];
			$more->submenu = array_merge($more->submenu, array
			(
				new MenuItem(NULL, get_label('Management'), NULL),
				new MenuItem('users.php' . $ccc, get_label('Users'), get_label('Manage [0] users', PRODUCT_NAME)),
				new MenuItem('pairs.php' . $ccc, get_label('Pairs'), get_label('Global pair policies')),
				new MenuItem('countries.php' . $ccc, get_label('Countries'), get_label('Manage countries')),
				new MenuItem('cities.php' . $ccc, get_label('Cities'), get_label('Manage cities')),
				new MenuItem('currencies.php' . $ccc, get_label('Currencies'), get_label('Manage currencies')),
				new MenuItem('scorings.php' . $ccc, get_label('Rules'), get_label('Game rules and scoring systems of [0]', PRODUCT_NAME)),
				new MenuItem('sounds.php' . $ccc, get_label('Game sounds'), get_label('Sounds in the game for prompting players on speech end.')),
				new MenuItem('game_issues.php' . $ccc, get_label('Game issues'), get_label('List of the games that have issues.')),
				new MenuItem('game_bugs.php' . $ccc, get_label('Game bugs'), get_label('List of the bug reports in the game.')),
				new MenuItem('duplicated_games.php' . $ccc, get_label('Duplicated games'), get_label('Search for duplicated games suspects.')),
				new MenuItem('duplicated_users.php' . $ccc, get_label('Duplicated users'), get_label('Merge duplicated user accounts.')),
				new MenuItem('repairs.php' . $ccc, get_label('Repairs'), get_label('Repairing broken things')),
				new MenuItem('maintenance_tasks.php' . $ccc, get_label('Maintenance tasks'), get_label('Manage recuring maintanance scripts')),
				new MenuItem('log.php' . $ccc, get_label('Log'), get_label('Log')),
			));
		}

		// A page picked from the menu of the site starts the way over: bck=0 empties the list the
		// button "Back" goes through (see include/session.php), so it does not lead back into
		// wherever one happened to be before.
		foreach ($menu as $item)
		{
			$item->page = PageBase::menu_url($item->page);
			if (!is_null($item->submenu))
			{
				foreach ($item->submenu as $sub)
				{
					$sub->page = PageBase::menu_url($sub->page);
				}
			}
		}
		return $menu;
	}

	private static function menu_url($page)
	{
		if (is_null($page) || $page == '' || $page[0] == '#')
		{
			return $page;
		}
		return $page . (strpos($page, '?') === false ? '?' : '&') . 'bck=0';
	}

	// Is this item the page being shown? Compares the script names, not the parameters.
	private static function is_current_page($page)
	{
		if (is_null($page) || $page == '' || $page[0] == '#')
		{
			return false;
		}
		$script = basename(strtolower(get_page_name()));
		$pos = strpos($page, '?');
		$item_script = strtolower($pos === false ? $page : substr($page, 0, $pos));
		return $item_script == $script;
	}

	private static function is_current_menu($item)
	{
		if (PageBase::is_current_page($item->page))
		{
			return true;
		}
		if (!is_null($item->submenu))
		{
			foreach ($item->submenu as $sub)
			{
				if (PageBase::is_current_page($sub->page))
				{
					return true;
				}
			}
		}
		return false;
	}

	private static function show_menu_link($item, $class = NULL)
	{
		echo '<a href="' . $item->page . '"';
		if ($class != NULL)
		{
			echo ' class="' . $class . '"';
		}
		if ($item->title != NULL)
		{
			echo ' title="' . htmlspecialchars($item->title, ENT_QUOTES) . '"';
		}
		if ($item->new_window)
		{
			echo ' target="_blank"';
		}
		if (PageBase::is_current_page($item->page))
		{
			echo ' aria-current="page"';
		}
		echo '><span>' . $item->text . '</span></a>';
	}

	// The items of a dropdown: links, and separators that may carry the name of the group after them.
	private static function show_dropdown_items($submenu)
	{
		$first = true;
		foreach ($submenu as $sub)
		{
			if (is_null($sub->page))
			{
				if (!$first)
				{
					echo '<div class="menu-sep" role="separator"></div>';
				}
				if (!empty($sub->text))
				{
					echo '<div class="menu-label">' . $sub->text . '</div>';
				}
			}
			else
			{
				PageBase::show_menu_link($sub);
			}
			$first = false;
		}
	}

	// A row of links with dropdowns: the header's navigation, and the tabs of the clubs, events,
	// tournaments... (see show_menu()).
	static function show_nav_items($menu)
	{
		foreach ($menu as $item)
		{
			if (is_null($item->page))
			{
				continue;
			}
			if (is_null($item->submenu))
			{
				PageBase::show_menu_link($item);
				continue;
			}
			echo '<div class="nav-more' . (PageBase::is_current_menu($item) ? ' is-active' : '') . '">';
			echo '<button type="button" aria-expanded="false">' . $item->text . PageBase::icon('chevron') . '</button>';
			echo '<div class="menu" hidden>';
			PageBase::show_dropdown_items($item->submenu);
			echo '</div></div>';
		}
	}

	// The menu on a narrow screen. "More" exists only because the row of the header has no room: in the
	// drawer, which has, its first items join the sections of the site, and each label inside it -
	// "Reports", "Resources", "Management" - starts a group of its own.
	private static function show_drawer_items($menu)
	{
		echo '<div class="drawer-group">';
		echo '<a class="dl" href="index.php"><span>' . get_label('Home') . '</span></a>';
		foreach ($menu as $item)
		{
			if (!is_null($item->page) && is_null($item->submenu))
			{
				PageBase::show_menu_link($item, 'dl');
			}
		}
		foreach ($menu as $item)
		{
			if (is_null($item->submenu))
			{
				continue;
			}
			// The items of "More" before its first label are sections of the site like the others;
			// any other submenu opens a group under its own name.
			if ($item->page != '#more')
			{
				echo '</div><div class="drawer-group"><div class="drawer-label">' . $item->text . '</div>';
			}
			foreach ($item->submenu as $sub)
			{
				if (!is_null($sub->page))
				{
					PageBase::show_menu_link($sub, 'dl');
				}
				else if (!empty($sub->text))
				{
					echo '</div><div class="drawer-group"><div class="drawer-label">' . $sub->text . '</div>';
				}
			}
		}
		echo '</div>';
	}

	private static function show_lang_switch()
	{
		global $_lang;

		echo '<div class="lang" role="group" aria-label="' . get_label('Language') . '">';
		$lang = LANG_NO;
		while (($lang = get_next_lang($lang, LANG_ALL_VISUAL)) != LANG_NO)
		{
			$code = get_lang_code($lang);
			echo '<button type="button" onclick="mr.browserLangChange(\'' . $code . '\')" aria-pressed="' . ($lang == $_lang ? 'true' : 'false') . '" title="' . get_lang_str($lang) . '">' . get_short_lang_str($lang) . '</button>';
		}
		echo '</div>';
	}

	// The search of the header: a dialog with one field, filled as one types from
	// api/control/search.php by new_look.js. The words the script needs are given to it here, in
	// the language of the page.
	private static function show_search_dialog()
	{
		$words = array(
			'player' => get_label('Players'),
			'club' => get_label('Clubs'),
			'tournament' => get_label('Tournaments'),
			'league' => get_label('Leagues'),
			'series' => get_label('Series'),
			'none' => get_label('Nothing found.'),
			'short' => get_label('Type at least two letters.'),
			'error' => get_label('The search failed. Please try again.'));
		echo '<div class="search-dlg" id="search" hidden data-words="' . htmlspecialchars(json_encode($words), ENT_QUOTES, 'UTF-8') . '">';
		echo '<div class="drawer-scrim" data-close></div>';
		echo '<div class="search-box" role="dialog" aria-modal="true" aria-label="' . get_label('Search') . '">';
		echo '<div class="search-field">' . PageBase::icon('search');
		echo '<input type="search" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="search-results" autocomplete="off" spellcheck="false"';
		echo ' placeholder="' . get_label('Find a player, club or tournament') . '" aria-label="' . get_label('Find a player, club or tournament') . '">';
		echo '<button type="button" class="search-close" data-close aria-label="' . get_label('Close') . '">Esc</button></div>';
		echo '<ul class="search-results" id="search-results" role="listbox" aria-label="' . get_label('Search') . '"></ul>';
		echo '<div class="search-hint">' . get_label('[0] to choose, Enter to open, Esc to close', '&uarr;&darr;') . '</div>';
		echo '</div></div>';
	}

	// The few line icons the header needs, drawn inline so that they take the colour of the text.
	static function icon($name)
	{
		switch ($name)
		{
		case 'search':
			$path = '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/>';
			break;
		case 'play':
			$path = '<path d="M7 5v14l11-7z"/>';
			break;
		case 'user':
			$path = '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>';
			break;
		case 'menu':
			$path = '<path d="M4 7h16M4 12h16M4 17h16"/>';
			break;
		case 'close':
			$path = '<path d="M6 6l12 12M18 6L6 18"/>';
			break;
		case 'chevron':
			$path = '<path d="M6 9l6 6 6-6"/>';
			break;
		case 'chev-r':
			$path = '<path d="M9 6l6 6-6 6"/>';
			break;
		case 'cal':
			$path = '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>';
			break;
		case 'pin':
			$path = '<path d="M12 21s-6.5-6-6.5-11a6.5 6.5 0 0 1 13 0c0 5-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.3"/>';
			break;
		case 'users':
			$path = '<circle cx="9" cy="8.5" r="3.3"/><path d="M2.8 19.5c0-3.3 2.8-5.3 6.2-5.3s6.2 2 6.2 5.3"/><path d="M15.5 5.6a3.2 3.2 0 0 1 0 6M17.6 14.5c2.2.6 3.6 2.4 3.6 5"/>';
			break;
		case 'layers':
			$path = '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>';
			break;
		case 'trophy':
			$path = '<path d="M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 6H4v1.5A3.5 3.5 0 0 0 7.5 11M17 6h3v1.5a3.5 3.5 0 0 1-3.5 3.5M12 14v3.5M8.5 20.5h7"/>';
			break;
		case 'link':
			$path = '<path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1"/>';
			break;
		case 'coin':
			$path = '<circle cx="12" cy="12" r="8.5"/><path d="M14.8 9.2c-.5-1-1.6-1.6-2.8-1.6-1.6 0-2.8.9-2.8 2.2 0 3 5.8 1.5 5.8 4.4 0 1.3-1.3 2.2-3 2.2-1.3 0-2.5-.6-3-1.7M12 6v1.6M12 16.4V18"/>';
			break;
		case 'lang':
			$path = '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.4 2.4 3.5 5.3 3.5 8.5s-1.1 6.1-3.5 8.5c-2.4-2.4-3.5-5.3-3.5-8.5s1.1-6.1 3.5-8.5z"/>';
			break;
		case 'status':
			$path = '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>';
			break;
		case 'video':
			$path = '<rect x="3" y="6" width="13" height="12" rx="2.5"/><path d="M16 10.5l5-3v9l-5-3z"/>';
			break;
		case 'plus':
			$path = '<path d="M12 5v14M5 12h14"/>';
			break;
		case 'globe':
			$path = '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.4 2.4 3.5 5.3 3.5 8.5s-1.1 6.1-3.5 8.5c-2.4-2.4-3.5-5.3-3.5-8.5s1.1-6.1 3.5-8.5z"/>';
			break;
		default:
			return '';
		}
		return '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true">' . $path . '</svg>';
	}

	// The menu of a club, an event, a tournament... shown as the row of tabs under its name.
	// $id is kept for the callers; nothing looks for it any more.
	static function show_menu($menu, $id = 'menubar')
	{
		echo '<nav class="tabs"><div class="tabs-inner">';
		PageBase::show_nav_items($menu);
		echo '</div></nav>';
	}

	// What a piece of code prints, as a string - for the parts of an entity's heading drawn by
	// functions that print (Picture::show, the buttons of a club or a tournament).
	static function capture($print)
	{
		ob_start();
		try
		{
			$print();
		}
		catch (Exception $e)
		{
			ob_end_clean();
			throw $e;
		}
		return ob_get_clean();
	}

	// The site a web address is on, for showing the address short: "mafiaclub.com".
	static function url_host($url)
	{
		$host = parse_url($url, PHP_URL_HOST);
		if (empty($host))
		{
			return $url;
		}
		return preg_replace('#^www\.#', '', $host);
	}

	// A small picture with a name, linking to its page: the club of a tournament, the series it
	// is in. $name defaults to the picture's own.
	static function entity_link($pic, $name = NULL, $url = NULL)
	{
		if (is_null($name))
		{
			$name = $pic->title();
		}
		if (is_null($url))
		{
			$url = $pic->hyperlink();
		}
		return '<a class="ent" href="' . $url . '">' . PageBase::capture(function() use ($pic) { $pic->show(ICONS_DIR, false, 22, 22); }) . '<span>' . $name . '</span></a>';
	}

	// The heading of a club, an event, a tournament, a series, a league, a player or an address on
	// the band: where it is on the site, its picture and name, a line of facts about it, what can
	// be done with it, and its tabs. $h is an EntityHeader.
	protected function show_entity_header($h, $menu)
	{
		echo '<div class="eh-top"><nav class="crumbs" aria-label="' . get_label('Breadcrumb') . '">';
		if (!empty($h->section))
		{
			echo '<a href="' . $h->section_url . '">' . $h->section . '</a>' . PageBase::icon('chev-r');
		}
		// The name of the page after the name of the entity - unless this is its main page, the
		// first of its tabs, where the name says it all.
		$main_page = count($menu) > 0 ? basename(parse_url($menu[0]->page, PHP_URL_PATH)) : '';
		if (!empty($this->_title) && $this->_title != $h->name && $main_page != '' && $main_page != basename($_SERVER['SCRIPT_NAME']))
		{
			echo '<a href="' . $menu[0]->page . '">' . $h->name . '</a>' . PageBase::icon('chev-r') . '<span aria-current="page">' . $this->_title . '</span>';
		}
		else
		{
			echo '<span aria-current="page">' . $h->name . '</span>';
		}
		echo '</nav>';
		show_back_button();
		echo '</div>';

		echo '<header class="eh' . ($h->off ? ' is-off' : '') . '">';
		echo '<div class="eh-art' . ($h->round ? ' round' : '') . ($h->cover ? ' cover' : '') . '">' . $h->art . '</div>';
		echo '<div class="eh-main"><h1>' . $h->name . '</h1>';
		if (!empty($h->sub))
		{
			echo '<p class="eh-sub">' . $h->sub . '</p>';
		}
		echo '</div>';

		// Buttons with nothing to do print a transparent picture to hold their place; there is no
		// place to hold here.
		$actions = trim(preg_replace('#<img src="images/transp.png"[^>]*>#', '', $h->actions));
		if (!empty($h->side) || !empty($actions))
		{
			echo '<div class="eh-side">' . $h->side;
			if (!empty($actions))
			{
				echo '<div class="eh-actions">' . $actions . '</div>';
			}
			echo '</div>';
		}
		if (count($h->facts) > 0)
		{
			echo '<dl class="eh-facts">';
			foreach ($h->facts as $fact)
			{
				echo '<div><dt>' . PageBase::icon($fact[0]) . '</dt><dd>' . $fact[1] . '</dd></div>';
			}
			echo '</dl>';
		}
		echo '</header>';

		if (count($menu) > 0)
		{
			PageBase::show_menu($menu);
		}
	}

	// virtual section these functions should be overriden
	protected function prepare()
	{
	}
	
	protected function standard_title()
	{
		// The titles are written as sentences - "Ratings." - for the title of the window; a heading
		// on the band goes without the full stop. Only a single one goes: "..." stays.
		$title = $this->_title;
		if (substr($title, -1) == '.' && substr($title, -2) != '..')
		{
			$title = substr($title, 0, -1);
		}
		return '<h1>' . $title . '</h1>';
	}
	
	protected function show_title()
	{
		echo '<div class="page-hd"><div>' . $this->standard_title() . '</div><div class="page-hd-side">';
		show_back_button();
		echo '</div></div>';
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
	
	protected function setUserPrompt($message, $prompt_type = PROMPT_TYPE_INFO)
	{
		$this->_prompt = $message;
		$this->_prompt_type = $prompt_type;
	}
	
	protected function getUserPrompt($prompt_type = PROMPT_TYPE_NONE)
	{
		if ($prompt_type == PROMPT_TYPE_NONE || $this->_prompt_type == $prompt_type)
		{
			return $this->_prompt;
		}
		return null;
	}
	
	protected function hasUserPrompt($prompt_type = PROMPT_TYPE_NONE)
	{
		if ($prompt_type == PROMPT_TYPE_NONE || $this->_prompt_type == $prompt_type)
		{
			return !empty($this->_prompt);
		}
		return false;
	}
	
	protected function errorMessage($message)
	{
		$this->setUserPrompt($message, PROMPT_TYPE_ERROR);
	}
	
	protected function hasError()
	{
		return $this->hasUserPrompt(PROMPT_TYPE_ERROR);
	}
	
	protected function getErrorMessage()
	{
		return $this->getUserPrompt(PROMPT_TYPE_ERROR);
	}
	
	private function _js()
	{
		global $_profile, $_lang;
	
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
		
		var showLangMenu = function()
		{
			console.log(1);
			setCurrentMenu('#header-lang-menu');
			var langMenu = $('#header-lang-menu').menu();
			langMenu.show(0, function()
			{
				langMenu.position(
				{
					my: "right top",
					at: "right bottom",
					of: $('#header-lang')
				});
				$(document).one("click", function() { setCurrentMenu(null); });
			});
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
		if ($this->_login > 0 && ($_profile == NULL || ($_profile->user_id != $this->_login && strtolower($_profile->user_name) != strtolower($this->_login))))
		{
			$login_name = '';
			if (is_string($this->_login))
			{
				$login_name = $this->_login;
			}
			else
			{
				$query = new DbQuery('SELECT nu.name FROM users u JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0 WHERE u.id = ?', $this->_login);
				if ($row = $query->next())
				{
					list ($login_name) = $row;
				}
			}
			echo "\n\t\tloginDialog(\"" . $this->_prompt . '", "' . $login_name . '");';
		}
		switch ($this->_prompt_type)
		{
		case PROMPT_TYPE_INFO:
			echo "\n\t\tdlg.info(\"" . $this->_prompt . "\");";
			break;
		case PROMPT_TYPE_ERROR: 
			echo "\n\t\tdlg.error(\"" . $this->_prompt . "\");";
			break;
		case PROMPT_TYPE_WARNING: 
			echo "\n\t\tdlg.warning(\"" . $this->_prompt . "\");";
			break;
		}
		echo "\n\t\tshowMenuBar();\n\n";
		if ($_profile != NULL && ($_profile->user_flags & USER_FLAG_NO_PASSWORD) != 0)
		{
			echo "\n\t\tmr.initProfile();\n\n";
		}
		$this->js_on_load();
		echo "\n\t});\n";
		if (is_null($this->_prompt))
		{
			$this->js();
		}
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

// What PageBase::show_entity_header() draws. Everything that is html is printed as it is.
class EntityHeader
{
	public $section;          // the part of the site the entity is listed in, for the crumbs
	public $section_url;
	public $name;
	public $art = '';         // the picture
	public $round = false;    // a person's picture is round
	public $cover = false;    // the art is a cover (card_cover()), wider than a logo
	public $sub = '';         // a line under the name: where it is up to, its stars
	public $facts = array();  // array(icon, html) each, see fact()
	public $side = '';        // to the right of the name: a rating, a button to apply
	public $actions = '';     // the icon buttons of the managers
	public $off = false;      // closed or canceled

	function __construct($name, $section, $section_url)
	{
		$this->name = $name;
		$this->section = $section;
		$this->section_url = $section_url;
	}

	function fact($icon, $html)
	{
		if (!is_null($html) && $html !== '')
		{
			$this->facts[] = array($icon, $html);
		}
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