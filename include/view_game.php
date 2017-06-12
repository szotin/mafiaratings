<?php

require_once 'include/game_stats.php';
require_once 'include/page_base.php';
require_once 'include/club.php';

class ViewGame
{
	public $gs;
	public $mark_player;
	public $event_id;
	public $event;
	public $club_id;
	public $club;
	public $club_flags;
	public $start_time;
	public $duration;
	public $language;
	public $moder;
	public $row;
	
	function __construct($id)
	{
		global $_profile;
	
		if ($_profile != NULL)
		{
			$this->mark_player = $_profile->user_id;
		}
		else
		{
			$this->mark_player = 'n';
		}
		$this->refresh($id);
	}
	
	function refresh($id = -1)
	{
		if ($id <= 0)
		{
			$id = $this->gs->id;
		}
		
		$this->gs = new GameState();
		$this->gs->init_existing($id);
		
		if ($this->gs->moder_id > 0)
		{
			list ($this->moder) = Db::record(get_label('moderator'), 'SELECT name FROM users WHERE id = ?', $this->gs->moder_id);
		}
		else
		{
			list ($this->moder) = Db::record(get_label('moderator'), 'SELECT name FROM incomers WHERE id = ?', -$this->gs->moder_id);
		}
		
		$query = new DbQuery(
			'SELECT e.id, e.name, ct.timezone, e.start_time, c.id, c.name, c.flags, g.start_time, g.end_time - g.start_time, g.language, g.result FROM games g' .
				' JOIN events e ON e.id = g.event_id' .
				' JOIN clubs c ON c.id = g.club_id' . 
				' JOIN addresses a ON a.id = e.address_id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' WHERE g.id = ?',
			$id);
		if ($row = $query->next())
		{
			list (
				$this->event_id, $event_name, $timezone, $event_time, $this->club_id, $this->club, $this->club_flags, $start_time, $duration,
				$language, $this->result) = $row;
			
			$this->event = $event_name . format_date('. M j Y.', $event_time, $timezone);
		}
		else
		{
			list (
				$timezone, $this->club, $start_time, $duration, $language, $this->result) =
					Db::record(
						get_label('game'), 
						'SELECT ct.timezone, c.name, g.start_time, g.end_time - g.start_time, g.language, g.result FROM games g' . 
							' JOIN clubs c ON c.id = g.club_id' .
							' JOIN cities ct ON ct.id = c.city_id' .
							' WHERE g.id = ?',
						$id);
				
			$this->event = NULL;
		}
		
		$this->start_time = format_date('M j Y H:i', $start_time, $timezone);
		$this->duration = format_time($duration);
		$this->language = get_lang_str($language);
	}
	
	function show_details()
	{
		global $_profile;
	
		echo '<table class="transp" width="100%">';
		echo '<tr><td width="120">'.get_label('Club').':</td><td><a href="club_main.php?id=' . $this->club_id . '&bck=1">' . $this->club . '</a></td></tr>';
		if ($this->event != NULL)
		{
			echo '<tr><td>'.get_label('Event').':</td><td><a href="event_players.php?id=' . $this->event_id . '&bck=1">' . $this->event . '</a></td></tr>';
		}
		echo '<tr><td>'.get_label('Start time').':</td><td>' . $this->start_time . '</td></tr>';
		echo '<tr><td>'.get_label('Duration').':</td><td>' . $this->duration . '</td></tr>';
		echo '<tr><td>'.get_label('Moderator') . ':</td><td>';
		if ($this->gs->moder_id > 0)
		{
			echo '<a href="user_info.php?id=' . $this->gs->moder_id . '&bck=1">' . $this->moder . '</a>';
		}
		else
		{
			echo $this->moder;
		}
		echo '</td></tr>';
		echo '<tr><td>'.get_label('Language').':</td><td>' . $this->language . '</td></tr>';
		if ($this->gs->best_player >= 0 && $this->gs->best_player < 10)
		{
			echo '<tr><td>'.get_label('Best player').':</td><td>' . ($this->gs->best_player + 1) . ': ' . $this->gs->players[$this->gs->best_player]->nick . '</td></tr>';
		}
		if ($this->gs->best_move >= 0 && $this->gs->best_move < 10)
		{
			echo '<tr><td>'.get_label('Best move').':</td><td>' . ($this->gs->best_move + 1) . ': ' . $this->gs->players[$this->gs->best_move]->nick . '</td></tr>';
		}
		if ($_profile != NULL && $_profile->is_admin() && $this->gs->error != NULL)
		{
			echo '<tr><td>'.get_label('Error').':</td><td>' . $this->gs->error . '</td></tr>';
		}
		echo '</table>';
	}
	
	function can_terminate()
	{
		global $_profile;
		return $_profile->is_admin() || $_profile->is_manager($this->gs->club_id);
	}
	
	function get_title()
	{
		$state = '';
		switch ($this->result)
		{
			case 0:
				$state = get_label('Still playing.');
				break;
			case 1:
				$state = get_label('Civilians won.');
				break;
			case 2:
				$state = get_label('Mafia won.');
				break;
			case 3:
				$state = get_label('Terminated.');
				break;
		}
		return get_label('Game [0]. [1]', $this->gs->id, $state);
	}
}

class ViewGamePageBase extends PageBase
{
	protected $vg;
	protected $gametime;
	private $last_gametime;
	
	protected function prepare()
	{
		$id = -1;
		if (isset($_REQUEST['id']))
		{
			$id = $_REQUEST['id'];
		}
		
		$this->vg = NULL;
		if (isset($_SESSION['view_game']))
		{
			$this->vg = $_SESSION['view_game'];
			if ($id > 0 && $this->vg->gs->id != $id)
			{
				$this->vg = new ViewGame($id);
				$_SESSION['view_game'] = $this->vg;
			}
			else if ($this->vg->result == 0)
			{
				$this->vg->refresh();
			}
		}
		else if ($id > 0)
		{
			$this->vg = new ViewGame($id);
			$_SESSION['view_game'] = $this->vg;
		}
		
		if ($this->vg == NULL)
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('game')));
		}
		
		if (isset($_REQUEST['end']) && $this->vg->can_terminate())
		{
			if ($this->vg->gs->gamestate != GAME_MAFIA_WON && $this->vg->gs->gamestate != GAME_CIVIL_WON)
			{
				$this->vg->gs->terminate();
			}
			save_game_results($this->vg->gs);
			if (isset($_SESSION['game_state']))
			{
				unset($_SESSION['game_state']);
			}
			$this->vg->refresh();
			redirect_back();
		}
		
		$this->gametime = 0;
		if (isset($_REQUEST['gametime']))
		{
			$this->gametime = $_REQUEST['gametime'];
		}
		
		if (isset($_REQUEST['next']))
		{
			++$this->gametime;
		}
		else if (isset($_REQUEST['prev']))
		{
			--$this->gametime;
		}
		
		$this->last_gametime = $this->vg->gs->get_last_gametime();
		$this->_title = $this->vg->get_title();
		
		if ($this->gametime < 0)
		{
			$this->gametime = 0;
		}
		else if ($this->gametime > $this->last_gametime)
		{
			$this->gametime = $this->last_gametime;
		}
		
		if ($this->gametime == 0)
		{
			$right_page = 'view_game.php';
		}
		else if ($this->gametime == 1)
		{
			$right_page = 'view_game_start.php';
		}
		else if (($this->gametime & 1) == 0)
		{
			$right_page = 'view_game_day.php';
		}
		else
		{
			$right_page = 'view_game_night.php';
		}
		
		$page_name = get_page_name();
		$page_name_len = strlen($page_name);
		$right_page_len = strlen($right_page);
		if ($right_page_len > $page_name_len || substr_compare($page_name, $right_page, $page_name_len - $right_page_len, $right_page_len) !== 0)
		{
			throw new RedirectExc($right_page . '?gametime=' . $this->gametime);
		}
	}
	
	protected function show_title()
	{
		echo '<table class="head" width="100%"><tr><td width="1">';
		echo '<a href="club_main.php?id=' . $this->vg->club_id . '&bck=1">';
		show_club_pic($this->vg->club_id, $this->vg->club_flags, ICONS_DIR);
		echo '</a></td><td valign="top">' . $this->standard_title() . '</td><td align="right" valign="top">';
		show_back_button();
		echo '</tr></table>';
		
//		parent::show_title();
		
		echo '<table class="transp" width="100%"><tr><td>';
		$this->vg->show_details();
		echo '</td>';
		echo '<td align="right" valign="bottom">';
		if ($this->vg->result == 0 && $this->vg->can_terminate())
		{
			echo '<a href="view_game.php?end=1">';
			if ($this->vg->gs->gamestate == GAME_MAFIA_WON || $this->vg->gs->gamestate == GAME_CIVIL_WON)
			{
				echo get_label('End game');
			}
			else
			{
				echo get_label('Terminate game');
			}
			echo '</a>';
		}
		echo '<br><form method="get" name="gotoForm" action="' . get_page_name() . '">';
		echo '<select name="gametime" onChange="document.gotoForm.submit()">';
		show_option(0, $this->gametime, get_label('Game results'));
		show_option(1, $this->gametime, get_label('Initial Night'));
		for ($i = 2; $i <= $this->last_gametime; ++$i)
		{
			if (($i & 1) == 0)
			{
				show_option($i, $this->gametime, get_label('Day [0]', ($i>>1)));
			}
			else
			{
				show_option($i, $this->gametime, get_label('Night [0]', ($i>>1)));
			}
		}
		echo '</select></form>';
		echo '</td></tr></table>';
	}
	
	protected function show_body()
	{
		echo '<form method="post" action="' . get_page_name() . '">';
		echo '<input type="hidden" name="gametime" value="' . $this->gametime . '">';
		echo '<table class="transp" width="100%">';
		echo '<tr><td valign="top"><input value="'.get_label('Prev').'" class="btn norm" type="submit" name="prev"';
		if ($this->gametime <= 0)
		{
			echo ' disabled';
		}
		echo '></td><td align="right"><input value="'.get_label('Next').'" class="btn norm" type="submit" name="next"';
		if ($this->gametime >= $this->last_gametime)
		{
			echo ' disabled';
		}
		echo '></td></tr></table>';
		echo '</form>';
	}
}

?>