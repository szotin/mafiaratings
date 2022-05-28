<?php

require_once 'include/page_base.php';
require_once 'include/game.php';

function get_player_number_html($game, $num)
{
	if (!is_numeric($num) || $num < 1 || $num > 10)
	{
		return get_label('no one');
	}
	
	$player = $game->data->players[$num-1];
	$role_add = '';
	if (!isset($player->role) || $player->role == 'civ')
	{
		$role = get_label('civilian');
	}
	else if ($player->role == 'maf')
	{
		$role = get_label('maf');
		$role_add = ' (' . $role . ')';
	}
	else if ($player->role == 'don')
	{
		$role = get_label('don');
		$role_add = ' (' . $role . ')';
	}
	else if ($player->role == 'sheriff')
	{
		$role = get_label('sheriff');
		$role_add = ' (' . $role . ')';
	}
	else
	{
		$role = get_label('invalid');
	}
	return '<a href="javascript:viewPlayer(' . $num . ')" title="' . $player->name . ' (' . $role . ')">' . $num . $role_add . '</a>';
}


class Page extends PageBase
{
	function generate_title()
	{
		$title = '';
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
		if ($this->tournament_name == NULL)
		{
			$title = $this->event_name . '. ';
		}
		else
		{
			$title = $this->tournament_name . ': ' . $this->event_name . '. ';
		}
		if ($this->is_rating)
		{
			$title .= get_label('Game [0]. [1]', $this->id, $state);
		}
		else
		{
			$title .= get_label('Game [0] (non-rating). [1]', $this->id, $state);
		}
		return $title;
	}
	
	protected function prepare()
	{
		$this->id = -1;
		if (isset($_REQUEST['id']))
		{
			$this->id = (int)$_REQUEST['id'];
		}
		if ($this->id <= 0)
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('game')));
		}
		
		list (
			$this->user_id, $this->event_id, $this->event_name, $this->event_flags, $this->timezone, $this->event_time, $this->tournament_id, $this->tournament_name, $this->tournament_flags, 
			$this->club_id, $this->club_name, $this->club_flags, $this->address_id, $this->address, $this->address_flags, 
			$this->moder_id, $this->moder_name, $this->moder_flags, $this->event_moder_nickname, $this->event_moder_flags, $this->tournament_moder_flags, $this->club_moder_flags,
			$this->start_time, $this->duration, $this->language_code, $this->civ_odds, $this->result, $this->video_id, $this->rules, $this->is_canceled, $this->is_rating, $json) =
		Db::record(
			get_label('game'),
			'SELECT g.user_id, e.id, e.name, e.flags, ct.timezone, e.start_time, t.id, t.name, t.flags,' .
			' c.id, c.name, c.flags, a.id, a.name, a.flags,' .
			' m.id, m.name, m.flags, eu.nickname, eu.flags, tu.flags, cu.flags,' .
			' g.start_time, g.end_time - g.start_time, g.language, g.civ_odds, g.result, g.video_id, e.rules, g.is_canceled, g.is_rating, g.json' .
				' FROM games g' .
				' JOIN events e ON e.id = g.event_id' .
				' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id' .
				' JOIN clubs c ON c.id = g.club_id' . 
				' JOIN addresses a ON a.id = e.address_id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' JOIN users m ON m.id = g.moderator_id' .
				' LEFT OUTER JOIN event_users eu ON eu.user_id = m.id AND eu.event_id = g.event_id' .
				' LEFT OUTER JOIN tournament_users tu ON tu.user_id = m.id AND tu.tournament_id = g.tournament_id' .
				' LEFT OUTER JOIN club_users cu ON cu.user_id = m.id AND cu.club_id = g.club_id' .
				' WHERE g.id = ?',
			$this->id);
		
		$this->player_pic =
			new Picture(USER_EVENT_PICTURE, 
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			new Picture(USER_PICTURE))));
		$this->event_pic = new Picture($this->tournament_id == NULL ? EVENT_PICTURE : TOURNAMENT_PICTURE);
		$this->address_pic = new Picture(ADDRESS_PICTURE);
		
		$this->game = new Game($json);
		
		$this->start_time = format_date('M j Y H:i', $this->start_time, $this->timezone);
		$this->duration = format_time($this->duration);
		$this->language = get_lang_str($this->language_code);
		
		if ($this->is_canceled)
		{
			$this->_title = '<s>' . $this->generate_title() . '</s> <big><span style="color:blue;">' . get_label('Game canceled') . '.</span></big>';
		}
		else
		{
			$this->_title = $this->generate_title();
		}
		
		// Players
		$this->players = array();
		$query = new DbQuery(
			'SELECT u.id, u.name, u.flags, eu.nickname, eu.flags, tu.flags, cu.flags FROM players p' . 
			' JOIN users u ON u.id = p.user_id' . 
			' LEFT OUTER JOIN event_users eu ON eu.user_id = u.id AND eu.event_id = ?' . 
			' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = ?' . 
			' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = ?' . 
			' WHERE p.game_id = ?', $this->event_id, $this->tournament_id, $this->club_id, $this->id);
		while ($row = $query->next())
		{
			$this->players[$row[0]] = $row;
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
		
		if (isset($_REQUEST['tournament_id']))
		{
			$tournament_id = (int)$_REQUEST['tournament_id'];
			$condition->add(' AND g.tournament_id = ?', $tournament_id);
			$this->url_base .= $separator . 'tournament_id=' . $tournament_id;
			$separator = '&';
		}
		
		if (isset($_REQUEST['address_id']))
		{
			$address_id = (int)$_REQUEST['address_id'];
			$condition->add(' AND g.event_id IN (SELECT id FROM events WHERE address_id = ?)', $address_id);
			$this->url_base .= $separator . 'address_id=' . $address_id;
			$separator = '&';
		}
		
		if (isset($_REQUEST['club_id']))
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
		$this->prev_game_id = $this->next_game_id = 0;
		$query = new DbQuery('SELECT g.id FROM games g WHERE g.id <> ? AND g.start_time <= ? AND g.result > 0', $this->id, $this->game->data->startTime, $condition);
		$query->add(' ORDER BY g.start_time DESC, g.id DESC');
		if ($row = $query->next())
		{
			list($this->prev_game_id) = $row;
		}
		
		$query = new DbQuery('SELECT g.id FROM games g WHERE g.id <> ? AND g.start_time >= ? AND g.result > 0', $this->id, $this->game->data->startTime, $condition);
		$query->add(' ORDER BY g.start_time, g.id');
		if ($row = $query->next())
		{
			list($this->next_game_id) = $row;
		}
	}
	
	private function show_bonus($bonus, $comment)
	{
		if (is_numeric($bonus))
		{
			if ($bonus > 0)
			{
				echo '<td align="right" title="' . $comment . '"><big><b>+' . $bonus . '</b></big></td>';
			}
			else if ($bonus < 0)
			{
				echo '<td align="right" title="' . $comment . '"><big><b>' . $bonus . '</b></big></td>';
			}
		}
		else if ($bonus == 'bestPlayer')
		{
			echo '<td align="right" width="24" title="' . $comment . '"><img src="images/best_player.png"></td>';
		}
		else if ($bonus == 'bestMove')
		{
			echo '<td align="right" width="24" title="' . $comment . '"><img src="images/best_move.png"></td>';
		}
	}
	
	private function show_player_html($num)
	{
		$player = $this->game->data->players[$num-1];
		echo '<table class="transp" width="100%"><tr><td width="54"><a href="javascript:viewPlayer(' . $num . ')">';
		if (isset($player->id) && isset($this->players[$player->id]))
		{
			list($player_id, $player_name, $player_flags, $event_player_nickname, $event_player_flags, $tournament_player_flags, $club_player_flags) = $this->players[$player->id];
			if ($player_name != $player->name)
			{
				$player_name = $player->name . ' (' . $player_name . ')';
			}
			
			$this->player_pic->
				set($player_id, $event_player_nickname, $event_player_flags, 'e' . $this->event_id)->
				set($player_id, $player_name, $tournament_player_flags, 't' . $this->tournament_id)->
				set($player_id, $player_name, $club_player_flags, 'c' . $this->club_id)->
				set($player_id, $player_name, $player_flags);
			$this->player_pic->show(ICONS_DIR, false, 48);
			echo '</a>';
			
			$player_name = '' . $player_name . '</a>';
		}
		else
		{
			echo '<img src="images/icons/user_null.png" width="48" height="48">';
			$player_name = $player->name;
		}
		echo '</a></td><td><a href="javascript:viewPlayer(' . $num . ')">' . $player_name . '</a></td><td align="right"';

		$comment = isset($player->comment) ? str_replace('"', '&quot;', $player->comment) : '';
		if (isset($player->bonus))
		{
			if (is_array($player->bonus))
			{
				foreach ($player->bonus as $bonus)
				{
					$this->show_bonus($bonus, $comment);
				}
			}
			else
			{
				$this->show_bonus($player->bonus, $comment);
			}
		}
		echo '</td></tr></table>';
	}
	
	protected function show_body()
	{
		global $_profile;
		
		echo '<table class="head" width="100%"><tr>';
		
		// Prev game button
		if ($this->prev_game_id > 0)
		{
			echo '<td width="24"><a href="' . $this->url_base . $this->prev_game_id . '" title="' . get_label('Previous game #[0]', $this->prev_game_id) . '"><img src="images/prev.png"></a></td>';
		}
		echo '<td>'; 
		
		// Game info icons
		echo '<table class="transp" width="100%"><tr>';
		echo '<td rowspan="2"><table class="bordered">';
		echo '<tr align="center" class="th dark" padding="5px"><td width="90">' . get_label('Club') . '</td><td width="90">' . ($this->tournament_id == NULL ? get_label('Event') : get_label('Tournament')) . '</td><td width="90">' . get_label('Address') . '</td><td width="90">' . get_label('Referee') . '</td><td width="90">'.get_label('Time').'</td><td width="90">'.get_label('Duration').'</td><td width="90">'.get_label('Language').'</td>';
		if ($this->civ_odds >= 0 && $this->civ_odds <= 1)
		{
			echo '<td width="90">'.get_label('Civs odds').'</td>';
		}
		if ($this->video_id != NULL)
		{
			echo '<td width="90">'.get_label('Video').'</td>';
		}
		echo '</tr><tr align="center" class="dark"><td>';
		$this->club_pic->set($this->club_id, $this->club_name, $this->club_flags);
		$this->club_pic->show(ICONS_DIR, true, 48);
		echo '</td><td>';
		
		if ($this->tournament_id == NULL)
		{
			$this->event_pic->set($this->event_id, $this->event_name, $this->event_flags);
		}
		else
		{
			$this->event_pic->set($this->tournament_id, $this->tournament_name, $this->tournament_flags);
		}
		$this->event_pic->show(ICONS_DIR, true, 48);
		
		echo '</td><td>';
		$this->address_pic->set($this->address_id, $this->address, $this->address_flags);
		$this->address_pic->show(ICONS_DIR, true, 48);
		echo '</td><td>';
		$this->player_pic->
			set($this->moder_id, $this->event_moder_nickname, $this->event_moder_flags, 'e' . $this->event_id)->
			set($this->moder_id, $this->moder_name, $this->tournament_moder_flags, 't' . $this->tournament_id)->
			set($this->moder_id, $this->moder_name, $this->club_moder_flags, 'c' . $this->club_id)->
			set($this->moder_id, $this->moder_name, $this->moder_flags);
		$this->player_pic->show(ICONS_DIR, true, 48);
		echo '</td><td>' . $this->start_time . '</td><td>' . $this->duration . '</td><td>';
		show_language_picture($this->language_code, ICONS_DIR, 48, 48);
		if ($this->civ_odds >= 0 && $this->civ_odds <= 1)
		{
			$odds_text = number_format($this->civ_odds * 100, 1) . '%';
			$text = get_label('The chances to win for the town estimated by [0] before the game were [1].', PRODUCT_NAME, $odds_text);
			$red_width = round(48 * $this->civ_odds);
			echo '</td><td>' . $odds_text . '<br><img src="images/red_dot.png" width="' . $red_width . '" height="12" title="' . $text . '"><img src="images/black_dot.png" width="' . (48 - $red_width) . '" height="12" title="' . $text . '">';
		}
		if ($this->video_id != NULL)
		{
			echo '<td><a href="javascript:mr.watchGameVideo(' . $this->id . ')">';
			echo '<img src="images/video.png" width="48" height="48" title="' . get_label('Watch game [0] video', $this->id) . '">';
			echo '</td>';
		}
		echo '</td></tr></table></td>';
		
		// Buttons to manage the game
		echo '<td align="right" valign="top">';
		if (is_permitted(PERMISSION_USER))
		{
			echo '<button class="icon" onclick="mr.gotoObjections(' . $this->id . ')" title="' . get_label('File an objection to the game [0] results.', $this->id) . '">';
			echo '<img src="images/objection.png" border="0"></button>';
			if (is_permitted(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $this->user_id, $this->club_id, $this->event_id, $this->tournament_id))
			{
				echo '<button class="icon" onclick="deleteGame(' . $this->id . ')" title="' . get_label('Delete game [0]', $this->id) . '"><img src="images/delete.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.editGame(' . $this->id . ')" title="' . get_label('Edit game [0]', $this->id) . '"><img src="images/edit.png" border="0"></button>';
				if ($this->video_id == NULL)
				{
					echo '<button class="icon" onclick="mr.setGameVideo(' . $this->id . ')" title="' . get_label('Add game [0] video', $this->id) . '"><img src="images/film-add.png" border="0"></button>';
				}
				else
				{
					echo '<button class="icon" onclick="mr.deleteVideo(' . $this->video_id . ', \'' . get_label('Are you sure you want to remove video from the game [0]?', $this->id) . '\')" title="' . get_label('Remove game [0] video', $this->id) . '"><img src="images/film-delete.png" border="0"></button>';
				}
			}
		}
		echo '<button class="icon" onclick="mr.fiimGameForm(' . $this->id . ')" title="' . get_label('FIIM game [0] form', $this->id) . '"><img src="images/fiim.png" border="0"></button>';
		echo '</td></tr><tr><td align="right" valign="bottom"></td></tr></table>';
		
		// Next game button
		echo '</td><td align="right" valign="top">';
		if ($this->next_game_id > 0)
		{
			echo '<td width="24"><a href="' . $this->url_base . $this->next_game_id . '" title="' . get_label('Next game #[0]', $this->next_game_id) . '"><img src="images/next.png"></a></td>';
		}
		echo '</tr></table>';
		
		echo '<table class="bordered" width="100%"><tr height="1"><td width="600" valign="top">';
		// Players
		echo '<table class="bordered light" width="100%">';
		for ($i = 1; $i <= 10; ++$i)
		{
			$player = $this->game->data->players[$i-1];
			echo '<td width="20" class="darker" align="center">' . $i . '</td>';
			echo '<td>';
			$this->show_player_html($i);
			echo '</td><td width="100">';
			if (isset($player->death))
			{
				echo '<table class="transp"><tr><td width="30"><img src="images/dead.png" width="24" height="24"></td><td>';
				$death_round = -1;
				$death_type = '';
				if (is_numeric($player->death))
				{
					$death_round = $player->death;
				}
				else if (is_string($player->death))
				{
					$death_type = $player->death;
				}
				else 
				{
					if (isset($player->death->round))
					{
						$death_round = $player->death->round;
					}
					if (isset($player->death->type))
					{
						$death_type = $player->death->type;
					}
				}
				
				switch ($death_type)
				{
				case DEATH_TYPE_GIVE_UP:
					echo get_label('gave up [0]', $death_round >= 0 ? get_label('in round [0]', $death_round) : '' );
					break;
				case DEATH_TYPE_WARNINGS:
					echo get_label('four warnings [0]', $death_round >= 0 ? get_label('in round [0]', $death_round) : '' );
					break;
				case DEATH_TYPE_KICK_OUT:
					echo get_label('kicked out [0]', $death_round >= 0 ? get_label('in round [0]', $death_round) : '' );
					break;
				case DEATH_TYPE_TEAM_KICK_OUT:
					echo get_label('team defeat [0]', $death_round >= 0 ? get_label('in round [0]', $death_round) : '' );
					break;
				case DEATH_TYPE_NIGHT:
					echo get_label('in night [0]', $death_round >= 0 ? $death_round : '' );
					break;
				case DEATH_TYPE_DAY:
					echo get_label('in day [0]', $death_round >= 0 ? $death_round : '' );
					break;
				default:
					if ($death_round > 0)
					{
						echo get_label('[0] round', $player->death);
					}
					break;
				}
				echo '</td></tr></table>';
			}
			echo '</td><td align="center" width="36">';
			if (isset($player->role))
			{
				switch ($player->role)
				{
					case 'sheriff':
						echo '<img src="images/sheriff.png" title="' . get_label('sheriff') . '" style="opacity: 0.5;">';
						break;
					case 'don':
						echo '<img src="images/don.png" title="' . get_label('don') . '" style="opacity: 0.5;">';
						break;
					case 'maf':
						echo '<img src="images/maf.png" title="' . get_label('mafia') . '" style="opacity: 0.5;">';
						break;
				}
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</table>';
		
		echo '</td><td rowspan="2" valign="top">';
		// Game
		$alive = array(true, true, true, true, true, true, true, true, true, true);
		$maf_alive = 3;
		$civ_alive = 7;
		$warnings = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
		$round = -1;
		$is_night = true;
		$actions = $this->game->get_actions();
		$players = $this->game->data->players;
		foreach ($actions as $action)
		{
			$night = Game::is_night($action);
			if ($action->round != $round || $is_night != $night)
			{
				if ($round >= 0)
				{
					echo '</ul></td></tr>';
				}
				else
				{
					echo '<table class="bordered light" width="100%">';
				}
				if ($night)
				{
					echo '<tr class="dark"><td><a href="javascript:viewNight(' . $action->round . ')"><b>' . get_label('Night [0]', $action->round) . '</b></a><ul>';
				}
				else
				{
					echo '<tr><td valign="top"><a href="javascript:viewDay(' . $action->round . ')"><b>' . get_label('Day [0]', $action->round) . '</b></a><ul>';
				}
				$round = $action->round;
				$is_night = $night;
			}
			echo '<li>';
			switch ($action->action)
			{
				case GAME_ACTION_ARRANGEMENT:
					$arrangement = '';
					for ($i = 0; $i < count($action->players); ++$i)
					{
						if ($i > 0)
						{
							$arrangement .= get_label(', then ');
						}
						$arrangement .= get_player_number_html($this->game, $action->players[$i]);
					}
					echo get_label('Mafia arranges to kill [0].', $arrangement);
					break;
				case GAME_ACTION_LEAVING:
					$alive[$action->player-1] = false;
					$player = $players[$action->player-1];
					$is_maf = false;
					if (isset($player->role) && ($player->role == 'maf' || $player->role == 'don'))
					{
						$is_maf = true;
						--$maf_alive;
					}
					else
					{
						--$civ_alive;
					}
					$info = '';
					if ($maf_alive <= 0)
					{
						$info = get_label('Town wins.');
					}
					else if ($maf_alive >= $civ_alive)
					{
						$info = get_label('Mafia wins.');
					}
					else
					{
						for ($i = 0; $i < 10; ++$i)
						{
							if (!$alive[$i])
							{
								continue;
							}
							if (!empty($info))
							{
								$info .= ', ';
							}
							$info .= get_player_number_html($this->game, $i + 1);
						}
						$info = get_label('[0] are still playing.', $info);
					}
					if (isset($player->death) && isset($player->death->type) && ($player->death->type != DEATH_TYPE_NIGHT && $player->death->type != DEATH_TYPE_DAY))
					{
						switch ($player->death->type)
						{
							case DEATH_TYPE_GIVE_UP:
								echo get_label('[0] gives up and leaves the game [2]. [1]', get_player_number_html($this->game, $action->player), $info, $this->game->get_gametime_text($action));
								break;
							case DEATH_TYPE_WARNINGS:
								echo get_label('[0] gets fourth warning and leaves the game. [1]', get_player_number_html($this->game, $action->player), $info);
								break;
							case DEATH_TYPE_KICK_OUT:
								echo get_label('[0] is kicked out from the game [2]. [1]', get_player_number_html($this->game, $action->player), $info, $this->game->get_gametime_text($action));
								break;
							case DEATH_TYPE_TEAM_KICK_OUT:
								echo get_label('[0] is kicked out from the game with team defeat [2]. [1]', get_player_number_html($this->game, $action->player), $is_maf ? get_label('Town wins.') : get_label('Mafia wins.'), $this->game->get_gametime_text($action));
								break;
							default:
								echo get_label('[0] leaves the game. [1]', get_player_number_html($this->game, $action->player), $info);
								break;
						}
					}
					else
					{
						echo get_label('[0] leaves the game. [1]', get_player_number_html($this->game, $action->player), $info);
					}
					break;
				case GAME_ACTION_WARNING:
					switch (++$warnings[$action->player-1])
					{
						case 2:
							echo get_label('[0] gets second warning [1].', get_player_number_html($this->game, $action->player), $this->game->get_gametime_text($action));
							break;
						case 3:
							echo get_label('[0] gets third warning [1].', get_player_number_html($this->game, $action->player), $this->game->get_gametime_text($action));
							break;
						case 4:
							echo get_label('[0] gets fourth warning [1].', get_player_number_html($this->game, $action->player), $this->game->get_gametime_text($action));
							break;
						default:
							echo get_label('[0] gets a warning [1].', get_player_number_html($this->game, $action->player), $this->game->get_gametime_text($action));
							break;
					}
					break;
				case GAME_ACTION_DON:
					echo get_label('Don checks [0].', get_player_number_html($this->game, $action->player));
					break;
				case GAME_ACTION_SHERIFF:
					echo get_label('Sheriff checks [0].', get_player_number_html($this->game, $action->player));
					break;
				case GAME_ACTION_LEGACY:
					$legacy = '';
					foreach ($action->legacy as $leg)
					{
						if (!empty($legacy))
						{
							$legacy .= ', ';
						}
						$legacy .= get_player_number_html($this->game, $leg);
					}
					echo get_label('[0]\'s legacy is [1].', get_player_number_html($this->game, $action->player), $legacy);
					break;
				case GAME_ACTION_NOMINATING:
					echo get_label('[0] nominates [1].', get_player_number_html($this->game, $action->speaker), get_player_number_html($this->game, $action->nominee));
					break;
				case GAME_ACTION_VOTING:
					switch (count($action->votes))
					{
						case 0:
							echo get_label('No one votes for [0].', get_player_number_html($this->game, $action->nominee));
							break;
						case 1:
							echo get_label('[0] votes for [1].', get_player_number_html($this->game, $action->votes[0]), get_player_number_html($this->game, $action->nominee));
							break;
						default:
							$voters = '';
							foreach ($action->votes as $vote)
							{
								if (!empty($voters))
								{
									$voters .= ', ';
								}
								$voters .= get_player_number_html($this->game, $vote);
							}
							echo get_label('[0] vote for [1].', $voters, get_player_number_html($this->game, $action->nominee));
					}
					break;
				case GAME_ACTION_SHOOTING:
					if (count($action->shooting) == 1)
					{
						$shooting = key($action->shooting);
						if (empty($shooting))
						{
							echo get_label('Mafia does not shoot.');
						}
						else
						{
							echo get_label('Mafia shoots [0].', get_player_number_html($this->game, $shooting));
						}
					}
					else
					{
						$miss_details = '';
						foreach ($action->shooting as $victim => $shot)
						{
							if (!empty($miss_details))
							{
								$miss_details .= '; ';
							}
							if (empty($victim))
							{
								if (count($shot) == 1)
								{
									$miss_details .= get_label('[0] does not shoot', get_player_number_html($this->game, $shot[0]));
								}
								else
								{
									$miss_details .= get_label('[0] and [1] do not shoot', get_player_number_html($this->game, $shot[0]), get_player_number_html($this->game, $shot[1]));
								}
							}
							else if (count($shot) == 1)
							{
								$miss_details .= get_label('[0] shoots [1]', get_player_number_html($this->game, $shot[0]), get_player_number_html($this->game, $victim));
							}
							else
							{
								$miss_details .= get_label('[0] and [1] shoot [2]', get_player_number_html($this->game, $shot[0]), get_player_number_html($this->game, $shot[1]), get_player_number_html($this->game, $victim));
							}
						}
						echo get_label('Mafia misses. [0].', $miss_details);
					}
					break;
			}
			echo '</li>';
		}
		if ($round >= 0)
		{
			echo '</ul></td></tr></table>';
		}
		
		echo '</td></tr><tr><td valign="top" id="comments"></td></tr></table>';
	}
	
	protected function js_on_load()
	{
		if (isset($this->id) && $this->id > 0)
		{
?>
			mr.showComments("game", <?php echo $this->id; ?>, 20, false, "wider_comment");
<?php
		}
	}
	
	protected function js()
	{
		if (isset($this->id) && $this->id > 0)
		{
?>
			function viewPlayer(num)
			{
				html.get("form/game_player_view.php?game_id=<?php echo $this->id; ?>&player_num=" + num, function(html)
				{
					dlg.info(html, "<?php echo get_label('Game [0]', $this->id); ?>", 600);
				});
			}
			
			function viewDay(round)
			{
				html.get("form/game_round_view.php?game_id=<?php echo $this->id; ?>&round=" + round, function(html)
				{
					dlg.info(html, "<?php echo get_label('Game [0]', $this->id); ?>", 800);
				});
			}
			
			function viewNight(round)
			{
				html.get("form/game_round_view.php?game_id=<?php echo $this->id; ?>&night&round=" + round, function(html)
				{
					dlg.info(html, "<?php echo get_label('Game [0]', $this->id); ?>", 800);
				});
			}
<?php
		}
	}
}

$page = new Page();
$page->run(get_label('Game'));

?>