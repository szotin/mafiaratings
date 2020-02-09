<?php

require_once __DIR__ . '/game_stats.php';
require_once __DIR__ . '/page_base.php';
require_once __DIR__ . '/club.php';
require_once __DIR__ . '/event.php';
require_once __DIR__ . '/address.php';

class ViewGamePlayer
{
	public $rating_before;
	public $rating_earned;
	public $user_flags;
}

class ViewGame
{
	public $gs;
	public $event_id;
	public $event;
	public $event_flags;
	public $tournament_id;
	public $tournament_name;
	public $tournament_flags;
	public $address_id;
	public $address;
	public $address_flags;
	public $club_id;
	public $club;
	public $club_flags;
	public $start_time;
	public $duration;
	public $language;
	public $language_code;
	public $moder;
	public $moder_flags;
	public $row;
	public $civ_odds;
	public $video;
	public $rules_code;
	public $timezone;
	
	public $players;
	
	function __construct($id)
	{
		$this->gs = NULL;
		$this->refresh($id);
	}
	
	function refresh($id = -1)
	{
		if ($id <= 0)
		{
			if (is_null($this->gs))
			{
				throw new FatalExc(get_label('Unknown [0]', get_label('game')));
			}
			$id = $this->gs->id;
		}
		
		$this->gs = new GameState();
		$this->gs->init_existing($id, NULL, false);
		
		if ($this->gs->moder_id > 0)
		{
			list ($this->moder, $this->moder_flags) = Db::record(get_label('moderator'), 'SELECT name, flags FROM users WHERE id = ?', $this->gs->moder_id);
		}
		else
		{
			list ($this->moder) = Db::record(get_label('moderator'), 'SELECT name FROM incomers WHERE id = ?', -$this->gs->moder_id);
		}
		
		$query = new DbQuery(
			'SELECT e.id, e.name, e.flags, ct.timezone, e.start_time, t.id, t.name, t.flags, c.id, c.name, c.flags, a.id, a.name, a.flags, g.start_time, g.end_time - g.start_time, g.language, g.civ_odds, g.result, g.video_id, e.rules FROM games g' .
				' JOIN events e ON e.id = g.event_id' .
				' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id' .
				' JOIN clubs c ON c.id = g.club_id' . 
				' JOIN addresses a ON a.id = e.address_id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' WHERE g.id = ?',
			$id);
		if ($row = $query->next())
		{
			list (
				$this->event_id, $event_name, $this->event_flags, $this->timezone, $event_time, $this->tournament_id, $this->tournament_name, $this->tournament_flags, 
				$this->club_id, $this->club, $this->club_flags, $this->address_id, $this->address, $this->address_flags, $start_time, $duration,
				$this->language_code, $this->civ_odds, $this->result, $this->video_id, $this->rules) = $row;

			$this->event = $event_name . format_date('. M j Y.', $event_time, $this->timezone);
		}
		else
		{
			list (
				$this->timezone, $this->club, $start_time, $duration, $this->language_code, $this->result) =
					Db::record(
						get_label('game'), 
						'SELECT ct.timezone, c.name, g.start_time, g.end_time - g.start_time, g.language, g.result FROM games g' . 
							' JOIN clubs c ON c.id = g.club_id' .
							' JOIN cities ct ON ct.id = c.city_id' .
							' WHERE g.id = ?',
						$id);
				
			$this->event = NULL;
			$this->video_id = NULL;
		}
		
		$this->start_time = format_date('M j Y H:i', $start_time, $this->timezone);
		$this->duration = format_time($duration);
		$this->language = get_lang_str($this->language_code);
		$this->players = array(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
		$query = new DbQuery('SELECT p.number, p.rating_before, p.rating_earned, u.flags FROM players p JOIN users u ON p.user_id = u.id WHERE p.game_id = ?', $id);
		while ($row = $query->next())
		{
			$player_stats = new ViewGamePlayer();
			list ($number, $player_stats->rating_before, $player_stats->rating_earned, $player_stats->user_flags) = $row;
			--$number;
			$player = $this->gs->players[$number];
			$this->players[$number] = $player_stats;
		}
	}
	
	function can_edit()
	{
		global $_profile;
		if ($_profile == NULL)
		{
			return false;
		}
		return $_profile->is_admin() || $_profile->is_club_manager($this->gs->club_id);
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
				$state = get_label('Town wins.');
				break;
			case 2:
				$state = get_label('Mafia wins.');
				break;
		}
		if ($this->gs->flags & GAME_FLAG_FUN)
		{
			return get_label('Game [0] (non-rating). [1]', $this->gs->id, $state);
		}
		return get_label('Game [0]. [1]', $this->gs->id, $state);
	}
}

class ViewGamePageBase extends PageBase
{
	protected $vg;
	protected $gametime;
	private $last_gametime;
	
	private $prev_game_id;
	private $next_game_id;
	private $url_base;
	
	protected $user_pic;
	protected $event_pic;
	protected $address_pic;
	
	protected function show_player_name($player, $player_score)
	{
		$gs = $this->vg->gs;
		echo '<td width="48"><a href="view_game_stats.php?id=' . $gs->id . '&num=' . $player->number . '&bck=1" title="' . $player->nick . '">';
		if ($player_score != NULL)
		{
			$this->user_pic->set($player->id, $player->nick, $player_score->user_flags);
			$this->user_pic->show(ICONS_DIR, false, 48);
		}
		else if ($player->id < 0)
		{
			echo '<img src="images/create_user.png" width="48" height="48">';
		}
		else
		{
			echo '<img src="images/transp.png" width="48" height="48">';
		}
		echo '</a></td><td>';
		if ($gs->best_player == $player->number)
		{
			echo '<table class="transp" width="100%"><tr><td>'. cut_long_name($player->nick, 50) . '</td><td align="right"><img src="images/best_player.png" title="' . get_label('Best player') . '"></td></tr></table>';
		}
		else if ($gs->best_move == $player->number)
		{
			echo '<table class="transp" width="100%"><tr><td>'. cut_long_name($player->nick, 50) . '</td><td align="right"><img src="images/best_move.png" title="' . get_label('Best move') . '"></td></tr></table>';
		}
		else if ($player->extra_points > 0)
		{
			echo '<table class="transp" width="100%"><tr><td>'. cut_long_name($player->nick, 50) . '</td><td align="right" title="' . $player->extra_points_reason . '"><big><b>+' . $player->extra_points . '</b></big></td></tr></table>';
		}
		else if ($player->extra_points < 0)
		{
			echo '<table class="transp" width="100%"><tr><td>'. cut_long_name($player->nick, 50) . '</td><td align="right" title="' . $player->extra_points_reason . '"><big><b>' . $player->extra_points . '</b></big></td></tr></table>';
		}
		else
		{
			echo cut_long_name($player->nick, 50);
		}
		echo '</td>';
	}
	
	protected function show_player_role($player)
	{
		echo '<td align="center">';
        switch ($player->role)
        {
            case PLAYER_ROLE_SHERIFF:
				echo '<img src="images/sheriff.png" title="' . get_label('sheriff') . '" style="opacity: 0.5;">';
				break;
            case PLAYER_ROLE_DON:
				echo '<img src="images/don.png" title="' . get_label('don') . '" style="opacity: 0.5;">';
				break;
            case PLAYER_ROLE_MAFIA:
				echo '<img src="images/maf.png" title="' . get_label('mafia') . '" style="opacity: 0.5;">';
				break;
        }
		echo '</td>';
	}
	
	protected function prepare()
	{
		$id = -1;
		if (isset($_REQUEST['id']))
		{
			$id = (int)$_REQUEST['id'];
		}
		if ($id <= 0)
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('game')));
		}
		
		$this->user_pic = new Picture(USER_PICTURE);
		$this->event_pic = new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE));
		$this->address_pic = new Picture(ADDRESS_PICTURE);
		
		$this->vg = new ViewGame($id);
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
		if ($this->vg->gs->is_canceled)
		{
			$this->_title = '<s>' . $this->vg->get_title() . '</s> <big><span style="color:blue;">' . get_label('Game canceled') . '.</span></big>';
		}
		else
		{
			$this->_title = $this->vg->get_title();
		}
		
		if ($this->gametime > $this->last_gametime)
		{
			$this->gametime = $this->last_gametime;
		}
		
		if ($this->gametime == 0)
		{
			$right_page = 'view_game.php';
		}
		else if ($this->gametime < 0)
		{
			$right_page = 'view_game_objections.php';
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
			throw new RedirectExc($right_page . '?id=' . $id . '&gametime=' . $this->gametime);
		}
		
		// Find next and prev games
		$this->url_base = 'view_game.php';
		$separator = '?';
		$condition = new SQL();
		if (isset($_REQUEST['event_id']))
		{
			$event_id = (int)$_REQUEST['event_id'];
			$condition->add(' AND g.event_id = ?', $event_id);
			$this->url_base .= $separator . 'event_id=' . $event_id;
			$separator = '&';
		}
		else if (isset($_REQUEST['address_id']))
		{
			$address_id = (int)$_REQUEST['address_id'];
			$condition->add(' AND g.event_id IN (SELECT id FROM events WHERE address_id = ?)', $address_id);
			$this->url_base .= $separator . 'address_id=' . $address_id;
			$separator = '&';
		}
		else if (isset($_REQUEST['club_id']))
		{
			$club_id = (int)$_REQUEST['club_id'];
			$condition->add(' AND g.club_id = ?', $club_id);
			$this->url_base .= $separator . 'club_id=' . $club_id;
			$separator = '&';
		}
		
		if (isset($_REQUEST['user_id']))
		{
			$user_id = (int)$_REQUEST['user_id'];
			$condition->add(' AND g.id IN (SELECT game_id FROM players WHERE user_id = ?)', $user_id);
			$this->url_base .= $separator . 'user_id=' . $user_id;
			$separator = '&';
		}
		
		if (isset($_REQUEST['moderator_id']))
		{
			$moderator_id = (int)$_REQUEST['moderator_id'];
			$condition->add(' AND g.moderator_id = ?', $moderator_id);
			$this->url_base .= $separator . 'moderator_id=' . $moderator_id;
			$separator = '&';
		}
		
		$this->url_base .= $separator . 'id=';
		$gs = $this->vg->gs;
		$this->prev_game_id = $this->next_game_id = 0;
		$query = new DbQuery('SELECT g.id FROM games g WHERE g.id <> ? AND g.start_time <= ? AND g.result > 0', $gs->id, $gs->start_time, $condition);
		$query->add(' ORDER BY g.start_time DESC, g.id DESC');
		if ($row = $query->next())
		{
			list($this->prev_game_id) = $row;
		}
		
		$query = new DbQuery('SELECT g.id FROM games g WHERE g.id <> ? AND g.start_time >= ? AND g.result > 0', $gs->id, $gs->start_time, $condition);
		$query->add(' ORDER BY g.start_time, g.id');
		if ($row = $query->next())
		{
			list($this->next_game_id) = $row;
		}
	}
	
	protected function show_title()
	{
		global $_profile;
		
		if (!isset($this->vg))
		{
			return;
		}
		
		$vg = $this->vg;
		$gs = $vg->gs;
		echo '<table class="head" width="100%"><tr>';
		if ($this->prev_game_id > 0)
		{
			echo '<td width="24"><a href="' . $this->url_base . $this->prev_game_id . '" title="' . get_label('Previous game #[0]', $this->prev_game_id) . '"><img src="images/prev.png"></a></td>';
		}
		echo '<td>' . $this->standard_title() . '</td><td align="right" valign="top">';
		show_back_button();
		if ($this->next_game_id > 0)
		{
			echo '<td width="24"><a href="' . $this->url_base . $this->next_game_id . '" title="' . get_label('Next game #[0]', $this->next_game_id) . '"><img src="images/next.png"></a></td>';
		}
		echo '</tr></table>';
		
//		parent::show_title();
		
		if ($_profile != NULL && $_profile->is_admin() && $gs->error != NULL)
		{
			echo '<p><b>'.get_label('Error').': ' . $gs->error . '</b></p>';
		}
		
		echo '<p><table class="transp" width="100%"><tr>';
		echo '<td rowspan="2"><table class="bordered">';
		echo '<tr align="center" class="th light" padding="5px"><td width="90">' . get_label('Club') . '</td><td width="90">' . get_label('Event') . '</td><td width="90">' . get_label('Address') . '</td><td width="90">' . get_label('Moderator') . '</td><td width="90">'.get_label('Time').'</td><td width="90">'.get_label('Duration').'</td><td width="90">'.get_label('Language').'</td>';
		if ($vg->civ_odds >= 0 && $vg->civ_odds <= 1)
		{
			echo '<td width="90">'.get_label('Civs odds').'</td>';
		}
		if ($vg->video_id != NULL)
		{
			echo '<td width="90">'.get_label('Video').'</td>';
		}
		echo '</tr><tr align="center" class="light"><td>';
		$this->club_pic->set($vg->club_id, $vg->club, $vg->club_flags);
		$this->club_pic->show(ICONS_DIR, true, 48);
		echo '</td><td>';
		
		$this->event_pic->
			set($vg->event_id, $vg->event, $vg->event_flags)->
			set($vg->tournament_id, $vg->tournament_name, $vg->tournament_flags);
		$this->event_pic->show(ICONS_DIR, true, 48);
		
		echo '</td><td>';
		$this->address_pic->set($vg->address_id, $vg->address, $vg->address_flags);
		$this->address_pic->show(ICONS_DIR, true, 48);
		echo '</td><td>';
		$this->user_pic->set($gs->moder_id, $vg->moder, $vg->moder_flags);
		$this->user_pic->show(ICONS_DIR, true, 48);
		echo '</td><td>' . $vg->start_time . '</td><td>' . $vg->duration . '</td><td>';
		show_language_picture($vg->language_code, ICONS_DIR, 48, 48);
		if ($vg->civ_odds >= 0 && $vg->civ_odds <= 1)
		{
			$odds_text = number_format($vg->civ_odds * 100, 1) . '%';
			$text = get_label('The chances to win for the town estimated by [0] before the game were [1].', PRODUCT_NAME, $odds_text);
			$red_width = round(48 * $vg->civ_odds);
			echo '</td><td>' . $odds_text . '<br><img src="images/red_dot.png" width="' . $red_width . '" height="12" title="' . $text . '"><img src="images/black_dot.png" width="' . (48 - $red_width) . '" height="12" title="' . $text . '">';
		}
		if ($vg->video_id != NULL)
		{
			echo '<td><a href="javascript:mr.watchGameVideo(' . $gs->id . ')">';
			echo '<img src="images/video.png" width="48" height="48" title="' . get_label('Watch game [0] video', $gs->id) . '">';
			echo '</td>';
		}
		echo '</td></tr></table></td><td align="right" valign="top">';
		if ($_profile != NULL)
		{
			echo '<button class="icon" onclick="';
			if ($this->gametime >= 0)
			{
				echo 'mr.gotoObjections(' . $gs->id . ', false)';
			}
			else
			{
				echo 'mr.createObjection(' . $gs->id . ')';
			}
			echo '" title="' . get_label('File an objection to the game [0] results.', $gs->id) . '">';
			echo '<img src="images/objection.png" border="0"></button>';
		}
		if ($vg->can_edit())
		{
			echo '<button class="icon" onclick="deleteGame(' . $gs->id . ')" title="' . get_label('Delete game [0]', $gs->id) . '"><img src="images/delete.png" border="0"></button>';
			echo '<button class="icon" onclick="mr.editGame(' . $gs->id . ')" title="' . get_label('Edit game [0]', $gs->id) . '"><img src="images/edit.png" border="0"></button>';
			if ($vg->video_id == NULL)
			{
				echo '<button class="icon" onclick="mr.setGameVideo(' . $gs->id . ')" title="' . get_label('Add game [0] video', $gs->id) . '"><img src="images/film-add.png" border="0"></button>';
			}
			else
			{
				echo '<button class="icon" onclick="mr.deleteVideo(' . $vg->video_id . ', \'' . get_label('Are you sure you want to remove video from the game [0]?', $gs->id) . '\')" title="' . get_label('Remove game [0] video', $gs->id) . '"><img src="images/film-delete.png" border="0"></button>';
			}
		}
		echo '<button class="icon" onclick="mr.figmGameForm(' . $gs->id . ')" title="' . get_label('FIGM game [0] form', $gs->id) . '"><img src="images/figm.png" border="0"></button>';
		echo '</td></tr><tr><td align="right" valign="bottom"><form method="get" name="gotoForm" action="' . get_page_name() . '">';
		echo '<input type="hidden" name="id" value="' . $gs->id . '">';
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
		show_option(-1, $this->gametime, get_label('Objections'));
		echo '</select></form></td></tr></table></p>';
	}
	
	protected function show_body()
	{
		echo '<p><form method="post" action="' . get_page_name() . '">';
		echo '<input type="hidden" name="gametime" value="' . $this->gametime . '">';
		echo '<input type="hidden" name="id" value="' . $this->vg->gs->id . '">';
		echo '<table class="transp" width="100%">';
		echo '<tr><td valign="top"><input value="'.get_label('Prev').'" class="btn norm" type="submit" name="prev"';
		if ($this->gametime < 0)
		{
			echo ' disabled';
		}
		echo '></td><td align="right"><input value="'.get_label('Next').'" class="btn norm" type="submit" name="next"';
		if ($this->gametime >= $this->last_gametime)
		{
			echo ' disabled';
		}
		echo '></td></tr></table>';
		echo '</form></p>';
		echo '<table width="100%"><tr valign="top"><td>';
		echo '</td><td id="comments"></td></tr></table>';
	}
	
	protected function js_on_load()
	{
		if (isset($this->vg->gs))
		{
?>
			mr.showComments("game", <?php echo $this->vg->gs->id; ?>, 20, false, "wide_comment");
<?php
		}
	}
	
	protected function js()
	{
		if (isset($this->vg->gs))
		{
?>
			function deleteGame(gameId)
			{
				mr.deleteGame(gameId, "<?php echo get_label('Are you sure you want to delete the game [0]?', $this->vg->gs->id); ?>", function(){
					window.location.replace("<?php echo get_back_page(); ?>");
				});
			}
<?php
		}
	}
}

?>