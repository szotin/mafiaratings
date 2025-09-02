<?php

require_once '../include/session.php';
require_once '../include/game.php';
require_once '../include/picture.php';

initiate_session();


function get_player_number_html($game, $num)
{
	$player = $game->data->players[$num-1];
	if (isset($player->role))
	{
		switch ($player->role)
		{
			case 'maf':
				return $num . ' (' . get_label('maf') . ')';
			case 'don':
				return $num . ' (' . get_label('don') . ')';
			case 'sheriff':
				return $num . ' (' . get_label('sheriff') . ')';
		}
	}
	return $num;
}

function show_bonus($bonus)
{
	if (is_numeric($bonus))
	{
		if ($bonus > 0)
		{
			echo '<big><b>+' . $bonus . '</b></big>';
		}
		else if ($bonus < 0)
		{
			echo '<big><b>' . $bonus . '</b></big>';
		}
	}
	else if ($bonus == 'bestPlayer')
	{
		echo '<img src="images/best_player.png" title="' . get_label('Best player') . '">';
	}
	else if ($bonus == 'bestMove')
	{
		echo '<img src="images/best_move.png" title="' . get_label('Best move') . '">';
	}
	else if ($bonus == 'worstMove')
	{
		echo '<img src="images/worst_move.png" title="' . get_label('No auto-bonus') . '">';
	}
}


try
{
	$game_id = -1;
	if (isset($_REQUEST['game_id']))
	{
		$game_id = (int)$_REQUEST['game_id'];
	}
	
	$event_id = -1;
	if (isset($_REQUEST['event_id']))
	{
		$event_id = (int)$_REQUEST['event_id'];
	}
	
	$table_num = 0;
	if (isset($_REQUEST['table_num']))
	{
		$table_num = (int)$_REQUEST['table_num'];
	}
	
	$game_num = 0;
	if (isset($_REQUEST['game_num']))
	{
		$game_num = (int)$_REQUEST['game_num'];
	}
	
	if (!isset($_REQUEST['player_num']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('player')));
	}
	$player_num = (int)$_REQUEST['player_num'];
	
	if ($player_num < 1 || $player_num > 10)
	{
		throw new Exc(get_label('Player num must be a number from 1 to 10.'));
	}
	
	$span = !isset($_REQUEST['recursive']);
	if ($span)
	{
		echo '<span id="user">';
	}
	
	echo '<table class="transp" width="100%"><tr>';
	if ($player_num > 1)
	{
		echo '<td><button class="icon" onclick="go_prev()" title="' . get_label('Player #[0]', $player_num - 1) . '"><img src="images/prev.png"></button></td>';
	}
	if ($player_num < 10)
	{
		echo '<td align="right"><button class="icon" onclick="go_next()" title="' . get_label('Player #[0]', $player_num + 1) . '"><img src="images/next.png"></button></td>';
	}
	echo '</tr></table>';
	
	if ($game_id > 0)
	{
		list($json, $club_id, $event_id, $tournament_id, $tournament_flags, $round_num, $feature_flags) = Db::record(get_label('game'), 
			'SELECT g.json, g.event_id, g.club_id, t.id, t.flags, e.round, g.feature_flags'.
			' FROM games g'.
			' JOIN events e ON e.id = g.event_id'.
			' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id'.
			' WHERE g.id = ?', $game_id);
		$url_params = '?game_id=' . $game_id;
	}
	else
	{
		list($json, $club_id, $tournament_id, $tournament_flags, $round_num) = Db::record(get_label('game'), 
			'SELECT g.game, e.club_id, t.id, t.flags, e.round'.
			' FROM current_games g'.
			' JOIN events e ON e.id = g.event_id'.
			' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id'.
			' WHERE g.event_id = ? AND g.table_num = ? AND g.game_num = ?', $event_id, $table_num, $game_num);
		$feature_flags = GAME_FEATURE_MASK_ALL;
		$url_params = '?event_id=' . $event_id . '&table_num=' . $table_num . '&game_num=' . $game_num;
	}
	$game = new Game($json, $feature_flags);
	$player = $game->data->players[$player_num-1];
	$player_id = 0;
	$full_player_name = $player->name;
	$player_name = '<b>' . $player->name . '</b>';
	$player_flags = 0; 
	if (isset($player->id) && $player->id > 0)
	{
		list($player_id, $pname, $player_flags, $event_player_nickname, $event_player_flags, $tournament_player_flags, $club_player_flags) = Db::record(get_label('user'), 
			'SELECT u.id, nu.name, u.flags, eu.nickname, eu.flags, tu.flags, cu.flags' . 
				' FROM users u' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN events e ON e.id = ?' . 
				' LEFT OUTER JOIN event_users eu ON eu.user_id = u.id AND eu.event_id = e.id' . 
				' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = e.tournament_id' . 
				' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = e.club_id' . 
				' WHERE u.id = ?', $event_id, $player->id);
		if (empty($player_name))
		{
			$full_player_name = $player_name = $pname;
		}
		else if ($pname != $player->name)
		{
			$full_player_name = $player->name . ' (' . $pname . ')';
		}
	}
	if (empty($player_name))
	{
		$full_player_name = $player_name = $player_num;
	}
	
	if (isset($_REQUEST['show_all']) && 
		is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $tournament_id))
	{
		$tournament_flags &= ~(TOURNAMENT_HIDE_TABLE_MASK | TOURNAMENT_HIDE_BONUS_MASK);
	}
	
	$show_bonus = true;
	if (($tournament_flags & TOURNAMENT_FLAG_FINISHED) == 0 && ($_profile == NULL || $_profile->user_id != $player_id))
	{
		switch (($tournament_flags & TOURNAMENT_HIDE_TABLE_MASK) >> TOURNAMENT_HIDE_TABLE_MASK_OFFSET)
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
		switch (($tournament_flags & TOURNAMENT_HIDE_BONUS_MASK) >> TOURNAMENT_HIDE_BONUS_MASK_OFFSET)
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
			set($player_id, $event_player_nickname, $event_player_flags, 'e' . $game->data->eventId)->
			set($player_id, $full_player_name, $tournament_player_flags, 't' . (isset($game->data->tournamentId) ? $game->data->tournamentId : ''))->
			set($player_id, $full_player_name, $club_player_flags, 'c' . $game->data->clubId)->
			set($player_id, $full_player_name, $player_flags);
		echo '<a href="user_info.php?bck=1&id=' . $player_id . '">';
		$user_pic->show(TNAILS_DIR, false);
		echo '</a>';
	}
	else
	{
		echo '<img src="images/tnails/user.png">';
	}
	echo '</td><td align="center"><h3><p>' . get_label('Number [0]', $player_num) . '</p><p>' . $full_player_name . '</p><p>';
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
			echo '<tr><td align="center">';
			if (is_array($player->bonus))
			{
				echo '<table class="transp"><tr>';
				foreach ($player->bonus as $bonus)
				{
					echo '<td width="24">';
					show_bonus($bonus);
					echo '</td>';
				}
				echo '</tr></table>';
			}
			else
			{
				show_bonus($player->bonus);
			}
			
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
	$round = -1;
	$is_night = true;
	$actions = $game->get_actions();
	$players = $game->data->players;
	
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
						$arrangement .= get_player_number_html($game, $action->players[$i]);
					}
					$action_text = get_label('[0] statically arranges [1].', $player_name, $arrangement);
				}
				else
				{
					for ($i = 0; $i < count($action->players); ++$i)
					{
						if ($action->players[$i] == $player_num)
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
				if ($action->player == $player_num)
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
							$info .= get_player_number_html($game, $i + 1);
						}
						$info = get_label('[0] are still playing.', $info);
					}
					if (isset($player->death) && isset($player->death->type))
					{
						switch ($player->death->type)
						{
							case DEATH_TYPE_GIVE_UP:
								$action_text = get_label('[0] gives up and leaves the game [2]. [1]', $player_name, $info, $game->get_gametime_text($action));
								break;
							case DEATH_TYPE_WARNINGS:
								$action_text = get_label('[0] gets fourth warning and leaves the game. [1]', $player_name, $info);
								break;
							case DEATH_TYPE_KICK_OUT:
								$action_text = get_label('[0] is kicked out from the game [2]. [1]', $player_name, $info, $game->get_gametime_text($action));
								break;
							case DEATH_TYPE_TEAM_KICK_OUT:
								$action_text = get_label('[0] is kicked out from the game with team defeat [2]. [1]', $player_name, $is_maf ? get_label('Town wins.') : get_label('Mafia wins.'), $game->get_gametime_text($action));
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
					if ($v == $player_num)
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
					$noms .= get_player_number_html($game, $n);
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
				if ($action->speaker == $player_num)
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
							$r .= get_label('[0] black', get_player_number_html($game, -$rec));
						}
						else
						{
							$r .= get_label('[0] red', get_player_number_html($game, $rec));
						}
					}
					$action_text = get_label('[0] leaves on record: [1]', $player_name, $r);
				}
				else foreach ($action->record as $rec)
				{
					if ($player_num == abs($rec))
					{
						if ($rec > 0)
						{
							$action_text = get_label('[0] leaves [1] red', get_player_number_html($game, $action->speaker), $player_name);
						}
						else
						{
							$action_text = get_label('[0] leaves [1] black', get_player_number_html($game, $action->speaker), $player_name);
						}
						break;
					}
				}
				break;
			case GAME_ACTION_WARNING:
				if ($action->player == $player_num)
				{
					switch (++$warnings[$action->player-1])
					{
						case 2:
							$action_text = get_label('[0] gets second warning [1].', $player_name, $game->get_gametime_text($action));
							break;
						case 3:
							$action_text = get_label('[0] gets third warning [1].', $player_name, $game->get_gametime_text($action));
							break;
						case 4:
							$action_text = get_label('[0] gets fourth warning [1].', $player_name, $game->get_gametime_text($action));
							break;
						default:
							$action_text = get_label('[0] gets a warning [1].', $player_name, $game->get_gametime_text($action));
							break;
					}
				}
				break;
			case GAME_ACTION_DON:
				if (isset($player->role) && $player->role == 'don')
				{
					$action_text = get_label('[0] checks [1].', $player_name, get_player_number_html($game, $action->player));
				}
				else if ($action->player == $player_num)
				{
					$action_text = get_label('[0] is checked by don.', $player_name, get_player_number_html($game, $action->player), isset($players[$action->player-1]->role) && $players[$action->player-1]->role == 'sheriff' ? get_label('Finds the sheriff') : get_label('Not the sheriff'));
				}
				break;
			case GAME_ACTION_SHERIFF:
				if (isset($player->role) && $player->role == 'sheriff')
				{
					$action_text = get_label('[0] checks [1].', $player_name, get_player_number_html($game, $action->player));
				}
				else if ($action->player == $player_num)
				{
					$action_text = get_label('[0] is checked by sheriff.', $player_name, get_player_number_html($game, $action->player), isset($players[$action->player-1]->role) && $players[$action->player-1]->role == 'sheriff' ? get_label('Finds the sheriff') : get_label('Not the sheriff'));
				}
				break;
				break;
			case GAME_ACTION_LEGACY:
				if ($action->player == $player_num)
				{
					$legacy = '';
					foreach ($action->legacy as $leg)
					{
						if (!empty($legacy))
						{
							$legacy .= ', ';
						}
						$legacy .= get_player_number_html($game, $leg);
					}
					$action_text = get_label('[0] leaves the legacy [1].', $player_name, $legacy);
				}
				break;
			case GAME_ACTION_NOMINATING:
				if ($action->speaker == $player_num)
				{
					$action_text = get_label('[0] nominates [1].', $player_name, get_player_number_html($game, $action->nominee));
				}
				else if ($action->nominee == $player_num)
				{
					$action_text = get_label('[0] nominates [1].', get_player_number_html($game, $action->speaker), $player_name);
				}
				break;
			case GAME_ACTION_VOTING:
				if ($action->nominee == $player_num)
				{
					switch (count($action->votes))
					{
						case 0:
							$action_text = get_label('No one votes for [0].', get_player_number_html($game, $action->nominee));
							break;
						case 1:
							$action_text = get_label('Only [0] votes for [1].', get_player_number_html($game, $action->votes[0]), $player_name);
							break;
						default:
							$voters = '';
							foreach ($action->votes as $vote)
							{
								if (!empty($voters))
								{
									$voters .= ', ';
								}
								$voters .= get_player_number_html($game, $vote);
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
						if ($vote == $player_num)
						{
							$output = true;
						}
						else
						{
							if (!empty($voters))
							{
								$voters .= ', ';
							}
							$voters .= get_player_number_html($game, $vote);
						}
					}
					if ($output)
					{
						if (empty($voters))
						{
							$action_text = get_label('[0] votes for [1] alone.', $player_name, get_player_number_html($game, $action->nominee));
						}
						else
						{
							$action_text = get_label('[0] with [1] vote for [2].', $player_name, $voters, get_player_number_html($game, $action->nominee));
						}
					}
				}
				break;
			case GAME_ACTION_SHOOTING:
				if (!is_array($action->shooting))
				{
					if ($action->shooting == $player_num)
					{
						$action_text = get_label('Mafia shoots [0].', $player_name);
					}
				}
				else if (count($action->shooting) == 1)
				{
					$shooting = key($action->shooting);
					if (!empty($shooting))
					{
						if ($shooting == $player_num)
						{
							$action_text = get_label('Mafia shoots [0].', $player_name);
						}
						else foreach ($action->shooting[$shooting] as $shooter)
						{
							if ($shooter == $player_num)
							{
								$shooters_count = count($action->shooting[$shooting]);
								switch ($shooters_count)
								{
									case 1:
										$action_text = get_label('[0] kills [1].', $player_name, get_player_number_html($game, $shooting));
										break;
									case 2:
										$action_text = get_label('[0] with [2] other maf kills [1].', $player_name, get_player_number_html($game, $shooting), $shooters_count - 1);
										break;
									default:
										$action_text = get_label('[0] with [2] other mafs kills [1].', $player_name, get_player_number_html($game, $shooting), $shooters_count - 1);
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
						if ($victim == $player_num)
						{
							if (count($shot) == 1)
							{
								$action_text = get_label('[0] shoots [1] but misses.', get_player_number_html($game, $shot[0]), $player_name);
							}
							else
							{
								$action_text = get_label('[0] and [1] shoot [2] but miss.', get_player_number_html($game, $shot[0]), get_player_number_html($game, $shot[1]), $player_name);
							}
						}
						else
						{
							foreach ($shot as $shooter)
							{
								if ($shooter == $player_num)
								{
									$action_text = get_label('[0] shoots [1] but misses.', $player_name, get_player_number_html($game, $victim));
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
				echo '</td></tr><tr class="dark"><td colspan="2"><b>' . get_label('Night [0]', $action->round) . '</b><ul>';
			}
			else
			{
				echo '</td></tr><tr class="light"><td colspan="2" valign="top"><b>' . get_label('Day [0]', $action->round) . '</b><ul>';
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
	
?>	
<script>
	function go_prev()
	{
		html.get("form/game_player_view.php<?php echo $url_params; ?>&recursive&player_num=" + <?php echo $player_num - 1; ?>, function(html)
		{
			$("#user").html(html);
		});
	}
	
	function go_next(onSuccess)
	{
		html.get("form/game_player_view.php<?php echo $url_params; ?>&recursive&player_num=" + <?php echo $player_num + 1; ?>, function(html)
		{
			$("#user").html(html);
		});
	}
</script>
<?php
	
	if ($span)
	{
		echo '</span>';
	}
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>