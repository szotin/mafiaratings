<?php

require_once 'include/page_base.php';
require_once 'include/game.php';
require_once 'include/series.php';

define('VIEW_GENERAL', 0);
define('VIEW_LOG', 1);
define('VIEW_ROUND', 2);
define('VIEW_PLAYER', 3);
define('VIEW_MR_POINTS', 4);
define('VIEW_MR_POINTS_LOG', 5);
define('VIEW_COUNT', 6);

define('REDNESS_WIDTH', 150);

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
	return '<a href="javascript:viewPlayer(' . $num . ')" title="' . (isset($player->name) ? $player->name : $num) . ' (' . $role . ')">' . $num . $role_add . '</a>';
}

function get_list_string($list)
{
	$str = '';
	$delim = '';
	foreach ($list as $num)
	{
		$str .= $delim . $num;
		$delim = ', ';
	}
	return $str;
}

function get_on_rec_string($list)
{
	$str = '';
	$delim = '';
	foreach ($list as $num)
	{
		$str .= $delim;
		if ($num < 0)
		{
			$str .= get_label('[0] black', -$num);
		}
		else
		{
			$str .= get_label('[0] red', $num);
		}
		$delim = ', ';
	}
	return $str;
}

class Page extends PageBase
{
	function generate_title()
	{
		$title = '';
		$state = '';
		switch ($this->result)
		{
			case GAME_RESULT_TOWN:
				$state = get_label('Town wins.');
				break;
			case GAME_RESULT_MAFIA:
				$state = get_label('Mafia wins.');
				break;
			case GAME_RESULT_TIE:
				$state = get_label('Tie.');
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
		
		$game_num = is_null($this->game_num) ? ('#' . $this->id) : $this->game_num;
		$rating = $this->is_rating ? '.' : (' (' . get_label('non-rating') . ').');
		if (is_null($this->table_num))
		{
			$title .= get_label('Game [0]. [1][2]', $game_num, $state, $rating);
		}
		else
		{
			$title .= get_label('Table [0], game [1][2] [3]', $this->table_num, $game_num, $rating, $state);
		}
		return $title;
	}
	
	protected function prepare()
	{
		global $_lang, $_profile;
		
		$this->id = -1;
		if (isset($_REQUEST['id']))
		{
			$this->id = (int)$_REQUEST['id'];
		}
		
		$this->view = VIEW_GENERAL;
		if (isset($_REQUEST['view']))
		{
			$this->view = (int)$_REQUEST['view'];
			if ($this->view < 0 || $this->view >= VIEW_COUNT)
			{
				$this->view = VIEW_GENERAL;
			}
		}
		
		$this->round = -1;
		if (isset($_REQUEST['round']))
		{
			$this->round = (int)$_REQUEST['round'];
		}
		
		$this->player_num = 0;
		if (isset($_REQUEST['player_num']))
		{
			$this->player_num = (int)$_REQUEST['player_num'];
		}
		
		$this->url_params = '';
		$this->show_all = '';
		$this->on_delete = '';
		
		if ($this->id <= 0)
		{
			if (!isset($_REQUEST['event_id']) || !isset($_REQUEST['table_num']) || !isset($_REQUEST['game_num']))
			{
				throw new FatalExc(get_label('Unknown [0]', get_label('game')));
			}
			
			$this->event_id = (int)$_REQUEST['event_id'];
			$this->table_num = (int)$_REQUEST['table_num'];
			$this->game_num = (int)$_REQUEST['game_num'];
			
			$query = new DbQuery('SELECT id FROM games WHERE event_id = ? AND table_num = ? AND game_num = ?', $this->event_id, $this->table_num, $this->game_num);
			if ($row = $query->next())
			{
				list ($this->id) = $row;
				$this->id = (int)$this->id;
			}
			
			if ($this->id <= 0)
			{
				$sql = 	'SELECT g.user_id, e.name, e.flags, ct.timezone, e.start_time, t.id, t.name, t.flags, e.round,'.
						' c.id, c.name, c.flags, a.id, a.name, a.flags, g.game, g.game_num'.
						' FROM current_games g'.
						' JOIN events e ON e.id = g.event_id' .
						' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' .
						' JOIN clubs c ON c.id = e.club_id' . 
						' JOIN addresses a ON a.id = e.address_id' .
						' JOIN cities ct ON ct.id = a.city_id' .
						' WHERE g.event_id = ? AND g.table_num = ?';
				list (
					$this->user_id, $this->event_name, $this->event_flags, $this->timezone, $this->event_time, $this->tournament_id, $this->tournament_name, $this->tournament_flags, $this->round_num, 
					$this->club_id, $this->club_name, $this->club_flags, $this->address_id, $this->address, $this->address_flags, $json, $this->game_num) =
				is_null($this->game_num) ? 
					Db::record(get_label('game'),  $sql . '  ORDER BY g.game_num DESC LIMIT 1', $this->event_id, $this->table_num) :
					Db::record(get_label('game'),  $sql . ' AND g.game_num = ?', $this->event_id, $this->table_num, $this->game_num);
					
				$feature_flags = GAME_FEATURE_MASK_ALL;
				$this->video_id = NULL;
				$this->flags = false;
				$this->civ_odds = -1; // for the future calculate it when roles are shown
			}
		}
		
		if ($this->id > 0)
		{
			list (
				$this->user_id, $this->event_id, $this->event_name, $this->event_flags, $this->timezone, $this->event_time, $this->tournament_id, $this->tournament_name, $this->tournament_flags, $this->round_num, 
				$this->club_id, $this->club_name, $this->club_flags, $this->address_id, $this->address, $this->address_flags, 
				$this->moder_id, $this->moder_name, $this->moder_flags, $this->event_moder_nickname, $this->event_moder_flags, $this->tournament_moder_flags, $this->club_moder_flags,
				$this->civ_odds, $this->video_id, $this->flags, $json, $this->game_num, $this->table_num, $feature_flags) =
			Db::record(
				get_label('game'),
				'SELECT g.user_id, e.id, e.name, e.flags, ct.timezone, e.start_time, t.id, t.name, t.flags, e.round,' .
				' c.id, c.name, c.flags, a.id, a.name, a.flags,' .
				' m.id, nm.name, m.flags, eu.nickname, eu.flags, tu.flags, cu.flags,' .
				' g.civ_odds, g.video_id, g.flags, g.json, g.game_num, g.table_num, g.feature_flags' .
					' FROM games g' .
					' JOIN events e ON e.id = g.event_id' .
					' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id' .
					' JOIN clubs c ON c.id = g.club_id' . 
					' JOIN addresses a ON a.id = e.address_id' .
					' JOIN cities ct ON ct.id = a.city_id' .
					' JOIN users m ON m.id = g.moderator_id' .
					' JOIN names nm ON nm.id = m.name_id AND (nm.langs & '.$_lang.') <> 0'.
					' LEFT OUTER JOIN event_regs eu ON eu.user_id = m.id AND eu.event_id = g.event_id' .
					' LEFT OUTER JOIN tournament_regs tu ON tu.user_id = m.id AND tu.tournament_id = g.tournament_id' .
					' LEFT OUTER JOIN club_regs cu ON cu.user_id = m.id AND cu.club_id = g.club_id' .
					' WHERE g.id = ?',
				$this->id);
			$this->url_params = '?game_id=' . $this->id;
		}
		else
		{
			$this->url_params = '?event_id=' . $this->event_id . '&table_num=' . $this->table_num . '&game_num=' . $this->game_num;
		}
			
		$this->is_editor = is_permitted(PERMISSION_OWNER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $this->user_id, $this->club_id, $this->event_id, $this->tournament_id);
		if ($this->is_editor && isset($_REQUEST['show_all']))
		{
			$this->show_all = '&show_all';
			$this->tournament_flags &= ~(TOURNAMENT_HIDE_TABLE_MASK | TOURNAMENT_HIDE_BONUS_MASK);
		}
		
		$this->hide_bonus = false;
		if (($this->tournament_flags & TOURNAMENT_FLAG_FINISHED) == 0)
		{
			switch (($this->tournament_flags & TOURNAMENT_HIDE_BONUS_MASK) >> TOURNAMENT_HIDE_BONUS_MASK_OFFSET)
			{
			case 1:
				$this->hide_bonus = true;
				break;
			case 2:
				$this->hide_bonus = ($this->round_num == 1);
				break;
			case 3:
				$this->hide_bonus = ($this->round_num == 1 || $this->round_num == 2);
				break;
			}
		}
		
		$this->player_pic =
			new Picture(USER_EVENT_PICTURE, 
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			new Picture(USER_PICTURE))));
		$this->event_pic = new Picture($this->tournament_id == NULL ? EVENT_PICTURE : TOURNAMENT_PICTURE);
		$this->address_pic = new Picture(ADDRESS_PICTURE);
		
		$this->game = new Game($json, $feature_flags);
		
		if (!isset($this->moder_id) && isset($this->game->data->moderator->id))
		{
			list ($this->moder_id, $this->moder_name, $this->moder_flags, $this->event_moder_nickname, $this->event_moder_flags, $this->tournament_moder_flags, $this->club_moder_flags) = Db::record(get_label('user'), 
				'SELECT u.id, nu.name, u.flags, eu.nickname, eu.flags, tu.flags, cu.flags'.
				' FROM users u'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN event_regs eu ON eu.user_id = u.id AND eu.event_id = ?' .
				' LEFT OUTER JOIN tournament_regs tu ON tu.user_id = u.id AND tu.tournament_id = ?' .
				' LEFT OUTER JOIN club_regs cu ON cu.user_id = u.id AND cu.club_id = ?' .
				' WHERE u.id = ?',
				$this->event_id, $this->tournament_id, $this->club_id, $this->game->data->moderator->id);
		}
		
		$this->start_time = format_date($this->game->data->startTime, $this->timezone, true);
		$this->duration = '';
		if (isset($this->game->data->endTime))
		{
			$this->duration = format_time($this->game->data->endTime - $this->game->data->startTime);
		}
		$this->lang = get_lang_by_code($this->game->data->language);
		$this->is_rating = isset($this->game->data->rating) ? $this->game->data->rating : true;
		
		$this->result = 0;
		if (isset($this->game->data->winner))
		{
			if ($this->game->data->winner == 'maf')
			{
				$this->result = GAME_RESULT_MAFIA;
			}
			else if ($this->game->data->winner == 'civ')
			{
				$this->result = GAME_RESULT_TOWN;
			}
			else if ($this->game->data->winner == 'tie')
			{
				$this->result = GAME_RESULT_TIE;
			}
		}
		
		if ($this->flags & GAME_FLAG_CANCELED)
		{
			$this->_title = '<s>' . $this->generate_title() . '</s> <big><span style="color:blue;">' . get_label('Game canceled') . '.</span></big>';
		}
		else
		{
			$this->_title = $this->generate_title();
		}
		
		// Players
		$plist = '';
		$delim = '';
		foreach ($this->game->data->players as $player)
		{
			if (isset($player->id) && $player->id > 0)
			{
				$plist .= $delim . $player->id;
				$delim = ',';
			}
		}
		
		$this->my_user_id = 0;
		$this->players = array();
		if (!empty($plist))
		{
			$query = new DbQuery(
				'SELECT u.id, nu.name, u.flags, eu.nickname, eu.flags, tu.flags, cu.flags' . 
					' FROM users u' .
					' JOIN events e ON e.id = ?'.
					' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
					' LEFT OUTER JOIN event_regs eu ON eu.user_id = u.id AND eu.event_id = e.id' . 
					' LEFT OUTER JOIN tournament_regs tu ON tu.user_id = u.id AND tu.tournament_id = e.tournament_id' . 
					' LEFT OUTER JOIN club_regs cu ON cu.user_id = u.id AND cu.club_id = e.club_id' . 
					' WHERE u.id IN (' . $plist . ')', $this->event_id);
			while ($row = $query->next())
			{
				$players[$row[0]] = $row;
				$uid = (int)$row[0];
				$this->players[$uid] = $row;
				if (!is_null($_profile) && $_profile->user_id == $uid)
				{
					$this->my_user_id = $uid;
				}
			}
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
			if (isset($_REQUEST['table']))
			{
				$table_num = (int)$_REQUEST['table'];
				$condition->add(' AND g.table_num = ?', $table_num);
				$this->url_base .= $separator . 'table=' . $table_num;
			}
		}
		
		if (isset($_REQUEST['tournament_id']))
		{
			$tournament_id = (int)$_REQUEST['tournament_id'];
			$condition->add(' AND g.tournament_id = ?', $tournament_id);
			$this->url_base .= $separator . 'tournament_id=' . $tournament_id;
			$separator = '&';
		}
		
		if (isset($_REQUEST['series_id']))
		{
			$series_id = (int)$_REQUEST['series_id'];
			$subseries_csv = get_subseries_csv($series_id);
			$condition->add(' AND g.tournament_id IN (SELECT tournament_id FROM series_tournaments WHERE series_id IN ('.$subseries_csv.'))');
			$this->url_base .= $separator . 'series_id=' . $series_id;
			$separator = '&';
		}
		
		if (isset($_REQUEST['league_id']))
		{
			$league_id = (int)$_REQUEST['league_id'];
			$condition->add(' AND g.tournament_id IN (SELECT st.tournament_id FROM series_tournaments st JOIN series s ON s.id = st.series_id WHERE s.league_id = ?)', $league_id);
			$this->url_base .= $separator . 'league_id=' . $league_id;
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
		$query = new DbQuery('SELECT g.id FROM games g WHERE g.id <> ? AND g.end_time <= ?', $this->id, $this->game->data->endTime, $condition);
		$query->add(' ORDER BY g.end_time DESC, g.id DESC');
		if ($row = $query->next())
		{
			list($this->prev_game_id) = $row;
		}
		
		$query = new DbQuery('SELECT g.id FROM games g WHERE g.id <> ? AND g.end_time >= ?', $this->id, $this->game->data->endTime, $condition);
		$query->add(' ORDER BY g.end_time, g.id');
		if ($row = $query->next())
		{
			list($this->next_game_id) = $row;
		}
		
		if ($this->next_game_id > 0)
		{
			$this->on_delete = 'goTo({"id":' . $this->next_game_id . '});';
		}
		else if ($this->prev_game_id > 0)
		{
			$this->on_delete = 'goTo({"id":' . $this->prev_game_id . '});';
		}
		else
		{
			$back_url = get_back_page();
			if (empty($back_url))
			{
				$back_url = 'games.php';
			}
			$this->on_delete = 'goTo("' . $back_url . '");';
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
		else if ($bonus == 'worstMove')
		{
			echo '<td align="right" width="24" title="' . get_label('Auto-bonus removed') . ': ' . $comment . '"><img src="images/worst_move.png"></td>';
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
		}
		else
		{
			echo '<img src="images/icons/user_null.png" width="48" height="48">';
			$player_name = '';
			if (isset($player->name))
			{
				$player_name = $player->name;
			}
		}
		echo '</a></td><td><a href="javascript:viewPlayer(' . $num . ')">' . $player_name . '</a></td><td align="right"';

		$comment = isset($player->comment) ? str_replace('"', '&quot;', $player->comment) : '';
		if (isset($player->bonus) && (!$this->hide_bonus || $this->my_user_id == $player->id))
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
		echo '</td>';
		if ($this->is_editor)
		{
			echo '<td width="40" align="center"><button class="icon" onclick="';
			if ($this->id > 0)
			{
				echo 'mr.gameBonus(' . $this->id . ', ' . $num . ')';
			}
			else
			{
				echo 'mr.currentGameBonus(' . $this->event_id . ', ' . $this->table_num . ', ' . $this->game_num . ', ' . $num . ')';
			}
			echo '" title="' . get_label('Set bonus for [0]', $player_name) . '"><img src="images/award.png" width="24"></button></td>';
		}
		echo '</tr></table>';
	}
	
	protected function show_body()
	{
		global $_profile;
		
		echo '<table class="head" width="100%"><tr>';
		
		// Prev game button
		if ($this->prev_game_id > 0)
		{
			echo '<td width="24"><button class="icon" onclick="goTo({id:' . $this->prev_game_id . '})" title="' . get_label('Previous game #[0]', $this->prev_game_id) . '"><img src="images/prev.png"></button></td>';
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
		if (isset($this->moder_id))
		{
			$this->player_pic->
				set($this->moder_id, $this->event_moder_nickname, $this->event_moder_flags, 'e' . $this->event_id)->
				set($this->moder_id, $this->moder_name, $this->tournament_moder_flags, 't' . $this->tournament_id)->
				set($this->moder_id, $this->moder_name, $this->club_moder_flags, 'c' . $this->club_id)->
				set($this->moder_id, $this->moder_name, $this->moder_flags);
			$this->player_pic->show(ICONS_DIR, true, 48);
		}
		echo '</td><td>' . $this->start_time . '</td><td>' . $this->duration . '</td><td>';
		echo '<span class="lang">' . get_short_lang_str($this->lang) . '</span>';
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
		if ($this->id > 0)
		{
			if (is_permitted(PERMISSION_USER))
			{
				echo '<button class="icon" onclick="mr.gotoObjections(' . $this->id . ')" title="' . get_label('File an objection to the game [0] results.', $this->id) . '">';
				echo '<img src="images/objection.png" border="0"></button>';
				if ($this->is_editor)
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
			//echo '<button class="icon" onclick="mr.fiimGameForm(' . $this->id . ')" title="' . get_label('FIIM game [0] form', $this->id) . '"><img src="images/fiim.png" border="0"></button>';
		}
		echo '</td></tr><tr><td align="right" valign="bottom"></td></tr></table>';
		
		// Next game button
		echo '</td><td align="right" valign="top">';
		if ($this->next_game_id > 0)
		{
			echo '<td width="24"><button class="icon" onclick="goTo({id:' . $this->next_game_id . '})" title="' . get_label('Next game #[0]', $this->next_game_id) . '"><img src="images/next.png"></button></td>';
		}
		echo '</tr></table>';
		
		$hidden = false;
		if (($this->tournament_flags & TOURNAMENT_FLAG_FINISHED) == 0)
		{
			switch (($this->tournament_flags & TOURNAMENT_HIDE_TABLE_MASK) >> TOURNAMENT_HIDE_TABLE_MASK_OFFSET)
			{
			case 1:
				$hidden = true;
				break;
			case 2:
				$hidden = ($this->round_num == 1);
				break;
			case 3:
				$hidden = ($this->round_num == 1 || $this->round_num == 2);
				break;
			}
			if ($hidden)
			{
				if ($this->my_user_id <= 0)
				{
					echo '<p><table class="transp" width="100%"><tr><td width="32">';
					if ($this->is_editor)
					{
						echo '<button onclick="goTo({show_all: null})" title="' . get_label('Show all about this game.') . '"><img src="images/attention.png"></button>';
					}
					else
					{
						echo '<img src="images/attention.png">';
					}
					echo '</td><td><h3>' . get_label('This game is hidden until the tournament ends.') . '</h3></td></tr></table></p>';
					return;
				}
				$this->hide_bonus = true;
			}
			
			if ($this->hide_bonus)
			{
				echo '<p><table class="transp" width="100%"><tr><td width="32">';
				if ($this->is_editor)
				{
					echo '<button onclick="goTo({show_all: null})" title="' . get_label('Show all about this game.') . '"><img src="images/attention.png"></button>';
				}
				else
				{
					echo '<img src="images/attention.png">';
				}
				echo '</td><td><h3>' . get_label('Bonus points are hidden for this game until the tournament ends.') . '</h3></td></tr></table></p>';
			}
		}
		
		echo '<div class="tab">';
		echo '<button ' . ($this->view == VIEW_GENERAL ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_GENERAL . '})">' . get_label('General info') . '</button>';
		echo '<button ' . ($this->view == VIEW_LOG ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_LOG . '})">' . get_label('Log') . '</button>';
		echo '<button ' . ($this->view == VIEW_ROUND ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_ROUND . '})">' . get_label('Per round') . '</button>';
		echo '<button ' . ($this->view == VIEW_PLAYER ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_PLAYER . '})">' . get_label('Per player') . '</button>';
		echo '<button ' . ($this->view == VIEW_MR_POINTS ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_MR_POINTS . '})">' . get_label('MR points') . '</button>';
		echo '<button ' . ($this->view == VIEW_MR_POINTS_LOG ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_MR_POINTS_LOG . '})">' . get_label('MR points log') . '</button>';
		echo '</div><p>';
		
		switch ($this->view)
		{
		case VIEW_GENERAL:
			$this->show_general();
			break;
		case VIEW_LOG:
			$this->show_log();
			break;
		case VIEW_ROUND:
			$this->show_round();
			break;
		case VIEW_PLAYER:
			$this->show_player();
			break;
		case VIEW_MR_POINTS:
			$this->show_mr_points();
			break;
		case VIEW_MR_POINTS_LOG:
			$this->show_mr_points_log();
			break;
		}
		echo '</p><div id="comments"></div>';
	}
	
	function show_general()
	{
		echo '<table class="bordered" width="100%">';
		$comment = '';
		if (!$this->hide_bonus)
		{
			if (isset($this->game->data->comment))
			{
				$comment = str_replace("\n", '<br>', $this->game->data->comment);
			}
			for ($i = 1; $i <= 10; ++$i)
			{
				$player = $this->game->data->players[$i-1];
				if (!isset($player->comment) || empty($player->comment))
				{
					continue;
				}
				if (!empty($comment))
				{
					$comment .= '<br>';
				}
				$comment .= $i . ': ' . $player->comment;
			}
		}
		else if ($this->my_user_id > 0)
		{
			for ($i = 1; $i <= 10; ++$i)
			{
				$player = $this->game->data->players[$i-1];
				if ($player->id == $this->my_user_id && isset($player->comment) && !empty($player->comment))
				{
					$comment = $i . ': ' . $player->comment;
					break;
				}
			}
		}
		if (!empty($comment))
		{
			echo '<tr><td colspan="2"><table class="bordered light" width="100%"><tr><td>' . $comment . '</td></tr></table></td></tr>';
		}
			
		echo '<tr height="1"><td width="600" valign="top">';
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
				else if (isset($player->death->round))
				{
					$death_round = $player->death->round;
				}
				if (isset($player->death->type))
				{
					$death_type = $player->death->type;
				}
				
				switch ($death_type)
				{
				case DEATH_TYPE_GIVE_UP:
					echo get_label('gave up [0]', $death_round >= 0 ? get_label('in round [0]', $death_round) : '' );
					break;
				case DEATH_TYPE_WARNINGS:
					echo get_label('four warnings [0]', $death_round >= 0 ? get_label('in round [0]', $death_round) : '' );
					break;
				case DEATH_TYPE_TECH_FOULS:
					echo get_label('two technical fouls [0]', $death_round >= 0 ? get_label('in round [0]', $death_round) : '' );
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
						echo get_label('[0] round', $death_round);
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
		
		echo '</td></tr></table>';
	}
	
	function show_log()
	{
		$alive = array(true, true, true, true, true, true, true, true, true, true);
		$maf_alive = 3;
		$civ_alive = 7;
		$warnings = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
		$techFouls = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
		$round = -1;
		$is_night = true;
		$actions = $this->game->get_actions();
		//print_json($actions);
		$players = $this->game->data->players;
		foreach ($actions as $action)
		{
			$night = Game::is_night($action);
			if (isset($action->round))
			{
				$action_round = $action->round;
			}
			else if ($round >= 0)
			{
				$action_round = $round;
			}
			else
			{
				$action_round = 0;
			}
			if ($action_round != $round || $is_night != $night)
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
					echo '<tr class="dark"><td><a href="javascript:viewNight(' . $action_round . ')"><b>' . get_label('Night [0]', $action_round) . '</b></a><ul>';
				}
				else
				{
					echo '<tr><td valign="top"><a href="javascript:viewDay(' . $action_round . ')"><b>' . get_label('Day [0]', $action_round) . '</b></a><ul>';
				}
				$round = $action_round;
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
					if (isset($player->death) && isset($player->death->type))
					{
						switch ($player->death->type)
						{
							case DEATH_TYPE_GIVE_UP:
								echo get_label('[0] gives up and leaves the game [2]. [1]', get_player_number_html($this->game, $action->player), $info, $this->game->get_gametime_text($action));
								break;
							case DEATH_TYPE_WARNINGS:
								echo get_label('[0] gets fourth warning and leaves the game. [1]', get_player_number_html($this->game, $action->player), $info);
								break;
							case DEATH_TYPE_TECH_FOULS:
								echo get_label('[0] gets the second technical foul and leaves the game. [1]', get_player_number_html($this->game, $action->player), $info);
								break;
							case DEATH_TYPE_KICK_OUT:
								echo get_label('[0] is kicked out from the game [2]. [1]', get_player_number_html($this->game, $action->player), $info, $this->game->get_gametime_text($action));
								break;
							case DEATH_TYPE_TEAM_KICK_OUT:
								echo get_label('[0] is kicked out from the game with team defeat [2]. [1]', get_player_number_html($this->game, $action->player), $is_maf ? get_label('Town wins.') : get_label('Mafia wins.'), $this->game->get_gametime_text($action));
								break;
							case DEATH_TYPE_NIGHT:
								echo get_label('[0] is shot and leaves the game. [1]', get_player_number_html($this->game, $action->player), $info);
								break;
							case DEATH_TYPE_DAY:
								echo get_label('[0] is voted out and leaves the game. [1]', get_player_number_html($this->game, $action->player), $info);
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
				case GAME_ACTION_TECH_FOUL:
					switch (++$techFouls[$action->player-1])
					{
						case 2:
							echo get_label('[0] gets the second technical foul [1].', get_player_number_html($this->game, $action->player), $this->game->get_gametime_text($action));
							break;
						default:
							echo get_label('[0] gets a technical foul [1].', get_player_number_html($this->game, $action->player), $this->game->get_gametime_text($action));
							break;
					}
					break;
				case GAME_ACTION_ON_RECORD:
					$r = '';
					foreach ($action->record as $rec)
					{
						if (!empty($r))
						{
							$r .= ', ';
						}
						if ($rec < 0)
						{
							$r .= get_label('[0] black', get_player_number_html($this->game, -$rec));
						}
						else
						{
							$r .= get_label('[0] red', get_player_number_html($this->game, $rec));
						}
					}
					echo get_label('[0] leaves on record: [1]', get_player_number_html($this->game, $action->speaker), $r);
					break;
				case GAME_ACTION_KILL_ALL:
					$v = '';
					foreach ($action->votes as $vote)
					{
						if (!empty($v))
						{
							$v .= ', ';
						}
						$v .= get_player_number_html($this->game, $vote);
					}
					if (empty($v))
					{
						echo get_label('Nobody voted to kill all');
					}
					else
					{
						echo get_label('Voted to kill all: [0].', $v);
					}
					break;
				case GAME_ACTION_DON:
					echo get_label('Don checks [0].', get_player_number_html($this->game, $action->player));
					break;
				case GAME_ACTION_SHERIFF:
					echo get_label('Sheriff checks [0].', get_player_number_html($this->game, $action->player));
					break;
				case GAME_ACTION_LEGACY:
					if (is_array($action->legacy))
					{
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
					}
					else
					{
						$action_text = get_label('[0] leaves [1] mafs in the legacy.', get_player_number_html($this->game, $action->player), (int)$action->legacy);
					}
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
					if (!is_array($action->shooting))
					{
						echo get_label('Mafia shoots [0].', $action->shooting);
					}
					else if (count($action->shooting) == 1)
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
	}

	function show_round()
	{
		$is_day = isset($_REQUEST['day']);
		
		if ($is_day)
		{
			$start = new stdClass();
			$start->round = $this->round;
			$start->time = GAMETIME_DAY_START;
			
			$end = new stdClass();
			$end->round = $this->round + 1;
			$end->time = GAMETIME_SHOOTING;
		}
		else
		{
			$start = new stdClass();
			$start->round = $this->round;
			$start->time = GAMETIME_SHOOTING;
			
			$end = new stdClass();
			$end->round = $this->round;
			$end->time = GAMETIME_DAY_START;
		}
		
		echo '<table class="transp" width="100%"><tr><td width="48">';
		if ($is_day)
		{
			echo '<button class="icon" onclick="goTo({day:undefined})"><img src="images/prev.png"></button>';
		}
		else if ($this->round > 0)
		{
			echo '<button class="icon" onclick="goTo({day:1,round:' . ($this->round - 1) . '})"><img src="images/prev.png"></button>';
		}
		echo '</td><td align="center"><h3>';
		if ($is_day)
		{
			echo get_label('Day [0].', $this->round);
		}
		else
		{
			echo get_label('Night [0].', $this->round);
		}
		echo '</h3></td><td align="right" width="48">';
		if ($this->game->compare_gametimes($this->game->get_last_gametime(true), $end) >= 0)
		{
			if ($is_day)
			{
				echo '<button class="icon" onclick="goTo({day:undefined,round:' . ($this->round + 1) . '})"><img src="images/next.png"></button>';
			}
			else
			{
				echo '<button class="icon" onclick="goTo({day:1})"><img src="images/next.png"></button>';
			}
		}
		echo '</td></tr></table>';
		
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="header" align="center"><td colspan="2">';
		if ($is_day)
		{
			echo '<td width="80"><b>' . get_label('Nominated') . '</b></td><td width="80"><b>' . get_label('Voted') . '</b></td>';
		}
		else if ($this->round > 0)
		{
			echo '<td width="80"><b>' . get_label('Shot') . '</b></td><td width="80"><b>' . get_label('Checked') . '</b></td>';
		}
		else
		{
			echo '<td width="160"><b>' . get_label('Arranged') . '</b></td>';
		}
		echo '<td width="100"><b>' . get_label('Warnings') . '</b></td><td width="100"><b>' . get_label('Killed') . '</b></td><td width="36"><b>' . get_label('Role') . '</b></td></tr>';
		for ($i = 1; $i <= 10; ++$i)
		{
			$player = $this->game->data->players[$i-1];
			$death_time = $this->game->get_player_death_time($i);
			$dead_already = $death_time != NULL && $this->game->compare_gametimes($death_time, $start) < 0;
			if ($dead_already)
			{
				echo '<tr class="darker"><td width="20" class="darkest" align="center">' . $i . '</td>';
			}
			else
			{
				echo '<tr><td width="20" class="darker" align="center">' . $i . '</td>';
			}
			
			echo '<td>';
			$this->show_player_html($i);
			echo '</td>';
			
			if ($is_day)
			{
				echo '<td align="center">';
				if (isset($player->nominating[$this->round]))
				{
					echo $player->nominating[$this->round];
				}
				echo '</td>';
				
				echo '<td align="center">';
				if (isset($player->voting[$this->round]))
				{
					if (is_array($player->voting[$this->round]))
					{
						$delim = '';
						foreach ($player->voting[$this->round] as $vote)
						{
							echo $delim;
							$delim = ', ';
							if (is_bool($vote))
							{
								echo get_label('kill');
							}
							else
							{
								echo $vote;
							}
						}
					}
					else
					{
						echo $player->voting[$this->round];
					}
				}
				echo '</td>';
			}
			else if ($this->round > 0)
			{
				echo '<td align="center">';
				if (isset($player->shooting[$this->round - 1]))
				{
					echo $player->shooting[$this->round - 1];
				}
				echo '</td>';
				
				echo '<td align="center">';
				if (isset($player->role))
				{
					if ($player->role == 'don')
					{
						for ($j = 0; $j < 10; ++$j)
						{
							$p = $this->game->data->players[$j];
							if (isset($p->don) && $p->don == $this->round)
							{
								echo $j + 1;
								break;
							}
						}
					}
					else if ($player->role == 'sheriff')
					{
						for ($j = 0; $j < 10; ++$j)
						{
							$p = $this->game->data->players[$j];
							if (isset($p->sheriff) && $p->sheriff == $this->round)
							{
								echo $j + 1;
								break;
							}
						}
					}
				}
				echo '</td>';
			}
			else 
			{
				echo '<td align="center">';
				if (isset($player->arranged))
				{
					echo get_label('In round [0]', $player->arranged);
				}
				echo '</td>';
			}
			
			echo '<td>';
			if (isset($player->warnings))
			{
				$prev_rounds = 0;
				$this_round = 0;
				if (is_numeric($player->warnings))
				{
					$prev_rounds = $player->warnings;
				}
				else foreach ($player->warnings as $warning)
				{
					if ($this->game->compare_gametimes($warning, $start) < 0)
					{
						++$prev_rounds;
					}
					else if ($this->game->compare_gametimes($warning, $end) < 0)
					{
						++$this_round;
					}
				}
				echo '<big><table class="transp" width="100%"><tr><td>';
				for ($j = 0; $j < $prev_rounds; ++$j)
				{
					echo '✔';
				}
				echo '</td><td align="right">';
				for ($j = 0; $j < $this_round; ++$j)
				{
					echo '✔';
				}
				echo '</td></tr></table></big>';
			}
			if (isset($player->techFouls))
			{
				$prev_rounds = 0;
				$this_round = 0;
				if (is_numeric($player->techFouls))
				{
					$prev_rounds = $player->techFouls;
				}
				else foreach ($player->techFouls as $techFoul)
				{
					if ($this->game->compare_gametimes($techFoul, $start) < 0)
					{
						++$prev_rounds;
					}
					else if ($this->game->compare_gametimes($techFoul, $end) < 0)
					{
						++$this_round;
					}
				}
				echo '<big><table class="transp" width="100%"><tr><td>';
				for ($j = 0; $j < $prev_rounds; ++$j)
				{
					echo '✘';
				}
				echo '</td><td align="right">';
				for ($j = 0; $j < $this_round; ++$j)
				{
					echo '✘';
				}
				echo '</td></tr></table></big>';
			}
			echo '</td>';
			
			echo '<td>';
			if ($dead_already || $this->game->compare_gametimes($death_time, $end) < 0)
			{
				echo '<table class="transp"><tr><td width="30"><img src="images/dead.png" width="24" height="24"></td><td>';
				$death_round = -1;
				$death_type = '';
				if (isset($player->death))
				{
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
				}
				
				switch ($death_type)
				{
				case DEATH_TYPE_GIVE_UP:
					echo get_label('gave up [0]', $death_round >= 0 ? get_label('in round [0]', $death_round) : '' );
					break;
				case DEATH_TYPE_WARNINGS:
					echo get_label('four warnings [0]', $death_round >= 0 ? get_label('in round [0]', $death_round) : '' );
					break;
				case DEATH_TYPE_TECH_FOULS:
					echo get_label('two technical fouls [0]', $death_round >= 0 ? get_label('in round [0]', $death_round) : '' );
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
			echo '</td>';
			
			echo '<td align="center">';
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
	}

	function show_player()
	{
		global $_lang, $_profile;
		
		$this->player_num = min(max($this->player_num,1),10);
		
		echo '<table class="transp" width="100%"><tr>';
		if ($this->player_num > 1)
		{
			echo '<td><button class="icon" onclick="goTo({player_num:'.($this->player_num-1).'})" title="' . get_label('Player #[0]', $this->player_num - 1) . '"><img src="images/prev.png"></button></td>';
		}
		if ($this->player_num < 10)
		{
			echo '<td align="right"><button class="icon" onclick="goTo({player_num:'.($this->player_num+1).'})" title="' . get_label('Player #[0]', $this->player_num + 1) . '"><img src="images/next.png"></button></td>';
		}
		echo '</tr></table>';
		
		$player = $this->game->data->players[$this->player_num-1];
		$player_id = 0;
		$full_player_name = isset($player->name) ? $player->name : '';
		$player_name = '<b>' . $full_player_name . '</b>';
		$player_flags = 0; 
		if (isset($player->id) && $player->id > 0)
		{
			list($player_id, $pname, $player_flags, $event_player_nickname, $event_player_flags, $tournament_player_flags, $club_player_flags) = Db::record(get_label('user'), 
				'SELECT u.id, nu.name, u.flags, eu.nickname, eu.flags, tu.flags, cu.flags' . 
					' FROM users u' .
					' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
					' JOIN events e ON e.id = ?' . 
					' LEFT OUTER JOIN event_regs eu ON eu.user_id = u.id AND eu.event_id = e.id' . 
					' LEFT OUTER JOIN tournament_regs tu ON tu.user_id = u.id AND tu.tournament_id = e.tournament_id' . 
					' LEFT OUTER JOIN club_regs cu ON cu.user_id = u.id AND cu.club_id = e.club_id' . 
					' WHERE u.id = ?', $this->event_id, $player->id);
			if (empty($player_name))
			{
				$full_player_name = $player_name = $pname;
			}
			else if ($pname != $full_player_name)
			{
				$full_player_name .= ' (' . $pname . ')';
			}
		}
		if (empty($player_name))
		{
			$full_player_name = $player_name = $this->player_num;
		}
		
		if (isset($_REQUEST['show_all']) && 
			is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $tournament_id))
		{
			$this->tournament_flags &= ~(TOURNAMENT_HIDE_TABLE_MASK | TOURNAMENT_HIDE_BONUS_MASK);
		}
		
		$show_bonus = true;
		if (($this->tournament_flags & TOURNAMENT_FLAG_FINISHED) == 0 && ($_profile == NULL || $_profile->user_id != $player_id))
		{
			switch (($this->tournament_flags & TOURNAMENT_HIDE_TABLE_MASK) >> TOURNAMENT_HIDE_TABLE_MASK_OFFSET)
			{
				case 1:
					return;
				case 2:
					if ($round_num == 1)
					{
						return;
					}
					break;
				case 3:
					if ($round_num == 1 || $round_num == 2)
					{
						return;
					}
					break;
			}
			switch (($this->tournament_flags & TOURNAMENT_HIDE_BONUS_MASK) >> TOURNAMENT_HIDE_BONUS_MASK_OFFSET)
			{
				case 1:
					$show_bonus = false;
					break;
				case 2:
					$show_bonus = ($round_num != 1);
					break;
				case 3:
					$show_bonus = ($round_num != 1 && $round_num != 2);
					break;
			}
		}
		
		echo '<table class="bordered" width="100%"><tr><td width="1">';
		if ($player_id > 0)
		{
			$user_pic =
				new Picture(USER_EVENT_PICTURE, 
				new Picture(USER_TOURNAMENT_PICTURE,
				new Picture(USER_CLUB_PICTURE,
				new Picture(USER_PICTURE))));
			$user_pic->
				set($player_id, $event_player_nickname, $event_player_flags, 'e' . $this->game->data->eventId)->
				set($player_id, $full_player_name, $tournament_player_flags, 't' . (isset($this->game->data->tournamentId) ? $this->game->data->tournamentId : ''))->
				set($player_id, $full_player_name, $club_player_flags, 'c' . $this->game->data->clubId)->
				set($player_id, $full_player_name, $player_flags);
			echo '<a href="user_info.php?bck=1&id=' . $player_id . '">';
			$user_pic->show(TNAILS_DIR, false);
			echo '</a>';
		}
		else
		{
			echo '<img src="images/tnails/user.png">';
		}
		echo '</td><td align="center"><h3><p>' . get_label('Number [0]', $this->player_num) . '</p><p>' . $full_player_name . '</p><p>';
		if (!isset($player->role) || $player->role == 'civ')
		{
			echo get_label('Civilian') . '</p><p><img src="images/civ.png" title="' . get_label('sheriff') . '" style="opacity: 0.5;">';
		}
		else if ($player->role == 'sheriff')
		{
			echo get_label('Sheriff') . '</p><p><img src="images/sheriff.png" title="' . get_label('don') . '" style="opacity: 0.5;"> ';
		}
		else if ($player->role == 'maf')
		{
			echo get_label('Mafia') . '</p><p><img src="images/maf.png" title="' . get_label('mafia') . '" style="opacity: 0.5;"> ';
		}
		else if ($player->role == 'don')
		{
			echo get_label('Don') . '</p><p><img src="images/don.png" title="' . get_label('don') . '" style="opacity: 0.5;"> ';
		}
		echo '</p></h3></td></tr>';

		if ($show_bonus)
		{
			if (isset($player->bonus))
			{
				$comment = isset($player->comment) ? str_replace('"', '&quot;', $player->comment) : '';
				echo '<tr><td align="center">';
				echo '<table class="transp"><tr>';
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
				echo '</tr></table>';
				echo '</td><td>';
				if (isset($player->comment))
				{
					echo '<i>' . $player->comment . '</i></td></tr>';
				}	
				echo '</td></tr>';
			}
			else if (isset($player->comment))
			{
				echo '<tr><td colspan="2"><i>' . $player->comment . '</i></td></tr>';
			}
		}

		$alive = array(true, true, true, true, true, true, true, true, true, true);
		$maf_alive = 3;
		$civ_alive = 7;
		$warnings = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
		$techFouls = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
		$round = -1;
		$is_night = true;
		$actions = $this->game->get_actions();
		$players = $this->game->data->players;
		
		// Game
		foreach ($actions as $action)
		{
			$action_text = NULL;
			switch ($action->action)
			{
				case GAME_ACTION_ARRANGEMENT:
					if (isset($player->role) && $player->role == 'don')
					{
						$arrangement = '';
						for ($i = 0; $i < count($action->players); ++$i)
						{
							if ($i > 0)
							{
								$arrangement .= get_label(', then ');
							}
							$arrangement .= get_player_number_html($this->game, $action->players[$i]);
						}
						$action_text = get_label('[0] statically arranges [1].', $player_name, $arrangement);
					}
					else
					{
						for ($i = 0; $i < count($action->players); ++$i)
						{
							if ($action->players[$i] == $this->player_num)
							{
								break;
							}
						}
						if ($i < count($action->players))
						{
							$action_text = get_label('[0] is statically arranged for night [1].', $player_name, $i + 1);
						}
					}
					break;
				case GAME_ACTION_LEAVING:
					$alive[$action->player-1] = false;
					$p = $players[$action->player-1];
					$is_maf = false;
					if (isset($p->role) && ($p->role == 'maf' || $p->role == 'don'))
					{
						$is_maf = true;
						--$maf_alive;
					}
					else
					{
						--$civ_alive;
					}
					if ($action->player == $this->player_num)
					{
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
						if (isset($player->death) && isset($player->death->type))
						{
							switch ($player->death->type)
							{
								case DEATH_TYPE_GIVE_UP:
									$action_text = get_label('[0] gives up and leaves the game [2]. [1]', $player_name, $info, $this->game->get_gametime_text($action));
									break;
								case DEATH_TYPE_WARNINGS:
									$action_text = get_label('[0] gets fourth warning and leaves the game. [1]', $player_name, $info);
									break;
								case DEATH_TYPE_TECH_FOULS:
									$action_text = get_label('[0] gets the second technical foul and leaves the game. [1]', $player_name, $info);
									break;
								case DEATH_TYPE_KICK_OUT:
									$action_text = get_label('[0] is kicked out from the game [2]. [1]', $player_name, $info, $this->game->get_gametime_text($action));
									break;
								case DEATH_TYPE_TEAM_KICK_OUT:
									$action_text = get_label('[0] is kicked out from the game with team defeat [2]. [1]', $player_name, $is_maf ? get_label('Town wins.') : get_label('Mafia wins.'), $this->game->get_gametime_text($action));
									break;
								case DEATH_TYPE_NIGHT:
									$action_text = get_label('[0] is shot and leaves the game. [1]', $player_name, $info);
									break;
								case DEATH_TYPE_DAY:
									$action_text = get_label('[0] is voted out and leaves the game. [1]', $player_name, $info);
									break;
								default:
									$action_text = get_label('[0] leaves the game. [1]', $player_name, $info);
									break;
							}
						}
						else
						{
							$action_text = get_label('[0] leaves the game. [1]', $player_name, $info);
						}
					}
					break;
				case GAME_ACTION_KILL_ALL:
					$is_voter = false;
					foreach ($action->votes as $v)
					{
						if ($v == $this->player_num)
						{
							$is_voter = true;
							break;
						}
					}
					
					$noms = '';
					foreach ($action->nominees as $n)
					{
						if (!empty($noms))
						{
							$noms .= ', ';
						}
						$noms .= get_player_number_html($this->game, $n);
					}
					
					if ($is_voter)
					{
						$action_text = get_label('[0] votes to kill all [1]', $player_name, $noms);
					}
					else
					{
						$action_text = get_label('[0] does not vote to kill all [1]', $player_name, $noms);
					}
					break;
				case GAME_ACTION_ON_RECORD:
					if ($action->speaker == $this->player_num)
					{
						$r = '';
						foreach ($action->record as $rec)
						{
							if (!empty($r))
							{
								$r .= ', ';
							}
							
							if ($rec < 0)
							{
								$r .= get_label('[0] black', get_player_number_html($this->game, -$rec));
							}
							else
							{
								$r .= get_label('[0] red', get_player_number_html($this->game, $rec));
							}
						}
						$action_text = get_label('[0] leaves on record: [1]', $player_name, $r);
					}
					else foreach ($action->record as $rec)
					{
						if ($this->player_num == abs($rec))
						{
							if ($rec > 0)
							{
								$action_text = get_label('[0] leaves [1] red', get_player_number_html($this->game, $action->speaker), $player_name);
							}
							else
							{
								$action_text = get_label('[0] leaves [1] black', get_player_number_html($this->game, $action->speaker), $player_name);
							}
							break;
						}
					}
					break;
				case GAME_ACTION_WARNING:
					if ($action->player == $this->player_num)
					{
						switch (++$warnings[$action->player-1])
						{
							case 2:
								$action_text = get_label('[0] gets second warning [1].', $player_name, $this->game->get_gametime_text($action));
								break;
							case 3:
								$action_text = get_label('[0] gets third warning [1].', $player_name, $this->game->get_gametime_text($action));
								break;
							case 4:
								$action_text = get_label('[0] gets fourth warning [1].', $player_name, $this->game->get_gametime_text($action));
								break;
							default:
								$action_text = get_label('[0] gets a warning [1].', $player_name, $this->game->get_gametime_text($action));
								break;
						}
					}
					break;
				case GAME_ACTION_TECH_FOUL:
					if ($action->player == $this->player_num)
					{
						switch (++$techFouls[$action->player-1])
						{
							case 2:
								$action_text = get_label('[0] gets the second technical foul [1].', $player_name, $this->game->get_gametime_text($action));
								break;
							default:
								$action_text = get_label('[0] gets a technical foul [1].', $player_name, $this->game->get_gametime_text($action));
								break;
						}
					}
					break;
				case GAME_ACTION_DON:
					if (isset($player->role) && $player->role == 'don')
					{
						$action_text = get_label('[0] checks [1].', $player_name, get_player_number_html($this->game, $action->player));
					}
					else if ($action->player == $this->player_num)
					{
						$action_text = get_label('[0] is checked by don.', $player_name, get_player_number_html($this->game, $action->player), isset($players[$action->player-1]->role) && $players[$action->player-1]->role == 'sheriff' ? get_label('Finds the sheriff') : get_label('Not the sheriff'));
					}
					break;
				case GAME_ACTION_SHERIFF:
					if (isset($player->role) && $player->role == 'sheriff')
					{
						$action_text = get_label('[0] checks [1].', $player_name, get_player_number_html($this->game, $action->player));
					}
					else if ($action->player == $this->player_num)
					{
						$action_text = get_label('[0] is checked by sheriff.', $player_name, get_player_number_html($this->game, $action->player), isset($players[$action->player-1]->role) && $players[$action->player-1]->role == 'sheriff' ? get_label('Finds the sheriff') : get_label('Not the sheriff'));
					}
					break;
					break;
				case GAME_ACTION_LEGACY:
					if ($action->player == $this->player_num)
					{
						if (is_array($action->legacy))
						{
							$legacy = '';
							foreach ($action->legacy as $leg)
							{
								if (!empty($legacy))
								{
									$legacy .= ', ';
								}
								$legacy .= get_player_number_html($this->game, $leg);
							}
							$action_text = get_label('[0] leaves the legacy [1].', $player_name, $legacy);
						}
						else
						{
							$action_text = get_label('[0] leaves [1] mafs in the legacy.', $player_name, (int)$action->legacy);
						}
					}
					break;
				case GAME_ACTION_NOMINATING:
					if ($action->speaker == $this->player_num)
					{
						$action_text = get_label('[0] nominates [1].', $player_name, get_player_number_html($this->game, $action->nominee));
					}
					else if ($action->nominee == $this->player_num)
					{
						$action_text = get_label('[0] nominates [1].', get_player_number_html($this->game, $action->speaker), $player_name);
					}
					break;
				case GAME_ACTION_VOTING:
					if ($action->nominee == $this->player_num)
					{
						switch (count($action->votes))
						{
							case 0:
								$action_text = get_label('No one votes for [0].', get_player_number_html($this->game, $action->nominee));
								break;
							case 1:
								$action_text = get_label('Only [0] votes for [1].', get_player_number_html($this->game, $action->votes[0]), $player_name);
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
								$action_text = get_label('[0] vote for [1].', $voters, $player_name);
						}
					}
					else
					{
						$output = false;
						$voters = '';
						foreach ($action->votes as $vote)
						{
							if ($vote == $this->player_num)
							{
								$output = true;
							}
							else
							{
								if (!empty($voters))
								{
									$voters .= ', ';
								}
								$voters .= get_player_number_html($this->game, $vote);
							}
						}
						if ($output)
						{
							if (empty($voters))
							{
								$action_text = get_label('[0] votes for [1] alone.', $player_name, get_player_number_html($this->game, $action->nominee));
							}
							else
							{
								$action_text = get_label('[0] with [1] vote for [2].', $player_name, $voters, get_player_number_html($this->game, $action->nominee));
							}
						}
					}
					break;
				case GAME_ACTION_SHOOTING:
					if (!is_array($action->shooting))
					{
						if ($action->shooting == $this->player_num)
						{
							$action_text = get_label('Mafia shoots [0].', $player_name);
						}
					}
					else if (count($action->shooting) == 1)
					{
						$shooting = key($action->shooting);
						if (!empty($shooting))
						{
							if ($shooting == $this->player_num)
							{
								$action_text = get_label('Mafia shoots [0].', $player_name);
							}
							else foreach ($action->shooting[$shooting] as $shooter)
							{
								if ($shooter == $this->player_num)
								{
									$shooters_count = count($action->shooting[$shooting]);
									switch ($shooters_count)
									{
										case 1:
											$action_text = get_label('[0] kills [1].', $player_name, get_player_number_html($this->game, $shooting));
											break;
										case 2:
											$action_text = get_label('[0] with [2] other maf kills [1].', $player_name, get_player_number_html($this->game, $shooting), $shooters_count - 1);
											break;
										default:
											$action_text = get_label('[0] with [2] other mafs kills [1].', $player_name, get_player_number_html($this->game, $shooting), $shooters_count - 1);
											break;
									}
									break;
								}
							}
						}
					}
					else
					{
						$miss_details = '';
						foreach ($action->shooting as $victim => $shot)
						{
							if ($victim == $this->player_num)
							{
								if (count($shot) == 1)
								{
									$action_text = get_label('[0] shoots [1] but misses.', get_player_number_html($this->game, $shot[0]), $player_name);
								}
								else
								{
									$action_text = get_label('[0] and [1] shoot [2] but miss.', get_player_number_html($this->game, $shot[0]), get_player_number_html($this->game, $shot[1]), $player_name);
								}
							}
							else
							{
								foreach ($shot as $shooter)
								{
									if ($shooter == $this->player_num)
									{
										$action_text = get_label('[0] shoots [1] but misses.', $player_name, get_player_number_html($this->game, $victim));
										break;
									}
								}
							}
						}
					}
					break;
			}
			
			if (is_null($action_text))
			{
				continue;
			}
			
			$night = Game::is_night($action);
			if ($action->round != $round || $is_night != $night)
			{
				if ($round >= 0)
				{
					echo '</ul>';
				}
				if ($night)
				{
					echo '</td></tr><tr class="dark"><td colspan="2"><a href="javascript:viewNight(' . $action->round . ')"><b>' . get_label('Night [0]', $action->round) . '</b></a><ul>';
				}
				else
				{
					echo '</td></tr><tr class="light"><td colspan="2" valign="top"><a href="javascript:viewDay(' . $action->round . ')"><b>' . get_label('Day [0]', $action->round) . '</b></a><ul>';
				}
				$round = $action->round;
				$is_night = $night;
			}
			
			echo '<li>' . $action_text . '</li>';
		}
		if ($round >= 0)
		{
			echo '</ul>';
		}
		echo '</td></tr></table>';
	}
	
	function show_player_pic($i)
	{
		$player = $this->game->data->players[$i];
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
		}
		else
		{
			echo '<img src="images/icons/user_null.png" width="48" height="48">';
			$player_name = '';
			if (isset($player->name))
			{
				$player_name = $player->name;
			}
		}
		echo '<br><b>' . ($i + 1) . '</b>';
	}

	function show_mr_points()
	{
		$p = $this->game->get_mafiaratings_points();
		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="64"></td>';
		for ($i = 0; $i < 10; ++$i)
		{
			echo '<td width="64" align="center"';
			if ($this->game->is_maf($i+1))
			{
				echo ' class="darkest"';
			}
			echo '>';
			$this->show_player_pic($i);
			echo '</td>';
		}
		echo '<td align="center"><b>'.get_label('Sum').'</b></td></tr>';
		
		for ($i = 0; $i < 10; ++$i)
		{
			$normal = '';
			$highlight = ' class="darker"';
			if ($this->game->is_maf($i+1))
			{
				$highlight = ' class="darkest"';
				$normal = ' class="dark"';
			}
			
			echo '<tr'.$normal.'><td align="center"'.$highlight.'>';
			$this->show_player_pic($i);
			echo '</td>';
			$sum = 0;
			for ($j = 0; $j < 10; ++$j)
			{
				$class = '';
				if ($i == $j)
				{
					$class = $highlight;
				}
				else if ($this->game->is_maf($j+1))
				{
					$class = ' class="dark"';
				}
				echo '<td align="center"'.$class.'>';
				$pts = $p->pos_points[$i][$j] + $p->neg_points[$i][$j];
				if ($pts != 0)
				{
					echo number_format($pts, 2);
				}
				echo '</td>';
				$sum += $pts;
			}
			echo '<td align="center"><b><a href="javascript:goTo({view:'.VIEW_MR_POINTS_LOG.',player_num:'.($i+1).'})">' . number_format($sum, 2) . '</a></b></td></tr>';
		}
		echo '</table></p>';
	}
	
	private function is_in_action($player_num, $action)
	{
		if ($player_num == 0)
		{
			return true;
		}
		
		switch ($action->time)
		{
		case GAMETIME_SHOOTING:
		case GAMETIME_SHERIFF:
			return $player_num == $action->player;
			
		case GAMETIME_VOTING_KILL_ALL: // voted out
			foreach ($action->voting as $dst => $votes)
			{
				if ($player_num == $dst)
				{
					return true;
				}
				foreach ($votes as $v)
				{
					if ($player_num == $v)
					{
						return true;
					}
				}
			}
			break;
			
		case GAMETIME_NIGHT_KILL_SPEAKING: // legacy and or on record
		case GAMETIME_SPEAKING:
		case GAMETIME_VOTING: // splitting speach
		case GAMETIME_DAY_KILL_SPEAKING:
			if ($player_num == $action->speaker)
			{
				return true;
			}
			foreach ($action->record as $r)
			{
				if ($player_num == abs($r))
				{
					return true;
				}
			}
			break;
		}
		return false;
	}

	function show_mr_points_log()
	{
		$p = $this->game->get_mafiaratings_points();
		
		echo '<p><table class="transp" width="100%"><tr><td align="center"><select id="player" onchange="viewPlayerMrLog()">';
		show_option(0, $this->player_num, '');
		for ($i = 1; $i <= 10; ++$i)
		{
			$player = $this->game->data->players[$i-1];
			if (isset($player->name))
			{
				show_option($i, $this->player_num, $i . ': ' . $player->name);
			}
			else
			{
				show_option($i, $this->player_num, $i);
			}
		}
		echo '</select></td></tr></table></p>';
		
		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="darker"><th>'.get_label('Action').'</th><th width="' . (REDNESS_WIDTH + 40) . '">'.get_label('Redness').'</th><th width="450">'.get_label('Points').'</th></tr>';
		foreach ($p->actions as $action)
		{
			if (!$this->is_in_action($this->player_num, $action))
			{
				continue;
			}
			
			switch ($action->time)
			{
			case GAMETIME_SHOOTING:
				echo '<tr><td><a href="javascript:viewNight(' . $action->round . ')"><b>'.get_label('Night [0]', $action->round).'</b></a><p>';
				echo get_label('Mafia shoots [0].', $action->player).'</p>';
				break;
			case GAMETIME_SHERIFF:
				echo '<tr><td><a href="javascript:viewNight(' . $action->round . ')"><b>'.get_label('Night [0]', $action->round).'</b></a><p>';
				echo get_label('Sheriff checks [0].', abs($action->player)).'</p>';
				break;
			case GAMETIME_VOTING_KILL_ALL: // voted out
				echo '<tr><td><a href="javascript:viewDay(' . $action->round . ')"><b>'.get_label('Day [0] voting', $action->round).'</b></a><p>';
				$delim = '';
				foreach ($action->voting as $dst => $votes)
				{
					if ($this->player_num == 0 || $dst == $this->player_num)
					{
						echo $delim . get_list_string($votes).' '.get_label('vote for [0].', $dst);
					}
					else foreach ($votes as $v)
					{
						if ($v == $this->player_num)
						{
							echo $delim . $v .' '.get_label('votes for [0].', $dst);
						}
					}
					$delim = '<br>';
				}
				if (isset($action->kill_all))
				{
					if ($this->player_num == 0)
					{
						echo '</p>'.get_list_string($action->kill_all).' '.get_label('vote to kill all.').'<p>';
					}
					else foreach ($action->kill_all as $v)
					{
						if ($v == $this->player_num)
						{
							echo '</p>'.$v.' '.get_label('votes to kill all.').'<p>';
							break;
						}
					}
				}
				echo '</p>';
				break;
			case GAMETIME_NIGHT_KILL_SPEAKING: // legacy and or on record
			case GAMETIME_SPEAKING:
			case GAMETIME_VOTING: // splitting speach
			case GAMETIME_DAY_KILL_SPEAKING:
				echo '<tr><td><a href="javascript:viewDay(' . $action->round . ')"><b>'.get_label('Day [0]', $action->round).'</b></a><p>';
				if ($this->player_num == 0 || $this->player_num == $action->speaker)
				{
					echo get_label('[0] leaves on record: [1]', $action->speaker, get_on_rec_string($action->record)).'</p>';
				}
				else foreach ($action->record as $r)
				{
					if ($r == $this->player_num)
					{
						echo get_label('[0] leaves on record: [1]', $action->speaker, get_label('[0] red', $r)).'</p>';
						break;
					}
				}
				break;
			default:
				echo '<tr><td>' . $action->time;
				break;
			}
			echo '</td><td align="center">';
			echo '<table class="transp">';
			for ($i = 1; $i <= 10; ++$i)
			{
				$redness = $action->redness[$i-1];
				$player = $this->game->data->players[$i-1];
				$red_width = round(REDNESS_WIDTH * $redness);
				$text = get_label('Redness of player [0] is [1]%.', $i, number_format($redness * 100, 1));
				echo '<tr><td width="20">'.$i.'</td><td><img src="images/red_dot.png" width="' . $red_width . '" height="12" title="' . $text . '">';
				echo '<img src="images/black_dot.png" width="' . (REDNESS_WIDTH - $red_width) . '" height="12" title="' . $text . '">';
				if (isset($player->role))
				{
					switch ($player->role)
					{
					case 'sheriff':
						echo ' <img src="images/sheriff.png" width="12" title="' . get_label('sheriff') . '" style="opacity: 0.5;">';
						break;
					case 'don':
						echo ' <img src="images/don.png" width="12" title="' . get_label('don') . '" style="opacity: 0.5;">';
						break;
					case 'maf':
						echo ' <img src="images/maf.png" width="12" title="' . get_label('mafia') . '" style="opacity: 0.5;">';
						break;
					}
				}
				echo '</td></tr>';
			}
			echo '</table></td>';
			echo '<td>';
			if (isset($action->points))
			{
				$delim = '';
				foreach ($action->points as $points)
				{
					$src = $points[0];
					$dst = $points[1];
					$pts = $points[2];
					if ($this->player_num == 0 || $this->player_num == $src || $this->player_num == $dst)
					{
						$active_points = $pts < 0 ? $p->neg_points[$src-1][$dst-1] : $p->pos_points[$src-1][$dst-1];
						echo $delim;
						if ($pts == $active_points)
						{
							echo '<b>' . get_label('[0] receives [1] points for giving color to [2].', $src, number_format($pts, 2), $dst) . '</b>';
						}
						else
						{
							echo get_label('[0] could receive [1] points for giving color to [2] but they already have [3] points for it.', $src, number_format($pts, 2), $dst, number_format($active_points, 2));
						}
						$delim = '<br>';
					}
				}
			}
			echo '</td></tr>';
		}
		echo '</table></p>';
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
?>
		function viewPlayer(num)
		{
			goTo({view:<?php echo VIEW_PLAYER; ?>,player_num:num});
		}
		
		function viewDay(round)
		{
			goTo({view:<?php echo VIEW_ROUND; ?>,round:round,day:1});
		}
		
		function viewNight(round)
		{
			goTo({view:<?php echo VIEW_ROUND; ?>,round:round,day:undefined});
		}
		
		function deleteGame(id)
		{
			mr.deleteGame(id, '<?php echo get_label('Are you sure you want to delete the game [0]?', $this->id); ?>', function()
			{
				<?php echo $this->on_delete; ?>
			});
		}
		
		function viewPlayerMrLog()
		{
			let p = $('#player').val();
			if (p < 1 || p > 10)
				goTo({player_num:undefined});
			else
				goTo({player_num:p});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Game'));

?>