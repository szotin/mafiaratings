<?php

require_once '../include/session.php';
require_once '../include/game.php';
require_once '../include/picture.php';

function show_bonus($bonus, $comment)
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
		echo '<td align="right" width="24" title="' . $comment . '"><img src="images/worst_move.png"></td>';
	}
}

function url_prev($game_id, $round, $is_day)
{
	$url = 'form/game_round_view.php?game_id=' .  $game_id . '&round=';
	if ($is_day)
	{
		$url .= $round . '&night';
	}
	else
	{
		$url .= $round - 1;
	}
	return $url;
}

function url_next($game_id, $round, $is_day)
{
	$url = 'form/game_round_view.php?game_id=' .  $game_id . '&round=';
	if ($is_day)
	{
		$url .= ($round + 1) . '&night';
	}
	else
	{
		$url .= $round;
	}
	return $url;
}

function show_player_html($game, $players, $user_pic, $num)
{
	$player = $game->data->players[$num-1];
	echo '<table class="transp" width="100%"><tr><td width="54"><a href="javascript:viewPlayer(' . $num . ')">';
	if (isset($player->id) && isset($players[$player->id]))
	{
		list($player_id, $player_name, $player_flags, $event_player_nickname, $event_player_flags, $tournament_player_flags, $club_player_flags) = $players[$player->id];
		if ($player_name != $player->name)
		{
			$player_name = $player->name . ' (' . $player_name . ')';
		}
		
		$user_pic->
			set($player_id, $event_player_nickname, $event_player_flags, 'e' . $game->data->eventId)->
			set($player_id, $player_name, $tournament_player_flags, 't' . (isset($game->data->tournamentId) ? $game->data->tournamentId : ''))->
			set($player_id, $player_name, $club_player_flags, 'c' . $game->data->clubId)->
			set($player_id, $player_name, $player_flags);
		$user_pic->show(ICONS_DIR, false, 48);
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
				show_bonus($bonus, $comment);
			}
		}
		else
		{
			show_bonus($player->bonus, $comment);
		}
	}
	echo '</td></tr></table>';
}


initiate_session();

try
{
	if (!isset($_REQUEST['game_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('game')));
	}
	$game_id = $_REQUEST['game_id'];
	
	if (!isset($_REQUEST['round']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('round')));
	}
	$round = $_REQUEST['round'];
	$is_day = !isset($_REQUEST['night']);
	
	$span = !isset($_REQUEST['recursive']);
	if ($span)
	{
		echo '<span id="round">';
	}
	
	list($json) = Db::record(get_label('game'), 'SELECT json FROM games WHERE id = ?', $game_id);
	$game = new Game($json);
	
	$players = array();
	$query = new DbQuery(
		'SELECT u.id, nu.name, u.flags, eu.nickname, eu.flags, tu.flags, cu.flags' . 
			' FROM players p' . 
			' JOIN games g ON g.id = p.game_id' . 
			' JOIN users u ON u.id = p.user_id' . 
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN event_users eu ON eu.user_id = u.id AND eu.event_id = g.event_id' . 
			' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = g.tournament_id' . 
			' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = g.club_id' . 
			' WHERE p.game_id = ?', $game_id);
	while ($row = $query->next())
	{
		$players[$row[0]] = $row;
	}
	
	$user_pic =
		new Picture(USER_EVENT_PICTURE, 
		new Picture(USER_TOURNAMENT_PICTURE,
		new Picture(USER_CLUB_PICTURE,
		new Picture(USER_PICTURE))));
	
	if ($is_day)
	{
		$start = new stdClass();
		$start->round = $round;
		$start->time = GAMETIME_DAY_START;
		
		$end = new stdClass();
		$end->round = $round + 1;
		$end->time = GAMETIME_SHOOTING;
	}
	else
	{
		$start = new stdClass();
		$start->round = $round;
		$start->time = GAMETIME_SHOOTING;
		
		$end = new stdClass();
		$end->round = $round;
		$end->time = GAMETIME_DAY_START;
	}
	
	echo '<table class="transp" width="100%"><tr>';
	if ($is_day || $round > 0)
	{
		echo '<td><button class="icon" onclick="go_prev()"><img src="images/prev.png"></button></td>';
	}
	if ($game->compare_gametimes($game->get_last_gametime(true), $end) >= 0)
	{
		echo '<td align="right"><button class="icon" onclick="go_next()"><img src="images/next.png"></button></td>';
	}
	echo '</tr></table>';
	
	if ($is_day)
	{
		echo '<p><h3>' . get_label('Day [0].', $round) . '</h3></p>';
	}
	else
	{
		echo '<p><h3>' . get_label('Night [0].', $round) . '</h3></p>';
	}
	
	echo '<table class="bordered light" width="100%">';
	echo '<tr class="header" align="center"><td colspan="2">';
	if ($is_day)
	{
		echo '<td width="80"><b>' . get_label('Nominated') . '</b></td><td width="80"><b>' . get_label('Voted') . '</b></td>';
	}
	else if ($round > 0)
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
		$player = $game->data->players[$i-1];
		$death_time = $game->get_player_death_time($i);
		$dead_already = $death_time != NULL && $game->compare_gametimes($death_time, $start) < 0;
		if ($dead_already)
		{
			echo '<tr class="darker"><td width="20" class="darkest" align="center">' . $i . '</td>';
		}
		else
		{
			echo '<tr><td width="20" class="darker" align="center">' . $i . '</td>';
		}
		
		echo '<td>';
		show_player_html($game, $players, $user_pic, $i);
		echo '</td>';
		
		if ($is_day)
		{
			echo '<td align="center">';
			if (isset($player->nominating[$round]))
			{
				echo $player->nominating[$round];
			}
			echo '</td>';
			
			echo '<td align="center">';
			if (isset($player->voting[$round]))
			{
				if (is_array($player->voting[$round]))
				{
					$delim = '';
					foreach ($player->voting[$round] as $vote)
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
					echo $player->voting[$round];
				}
			}
			echo '</td>';
		}
		else if ($round > 0)
		{
			echo '<td align="center">';
			if (isset($player->shooting[$round - 1]))
			{
				echo $player->shooting[$round - 1];
			}
			echo '</td>';
			
			echo '<td align="center">';
			if (isset($player->role))
			{
				if ($player->role == 'don')
				{
					for ($j = 0; $j < 10; ++$j)
					{
						$p = $game->data->players[$j];
						if (isset($p->don) && $p->don == $round)
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
						$p = $game->data->players[$j];
						if (isset($p->sheriff) && $p->sheriff == $round)
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
				if ($game->compare_gametimes($warning, $start) < 0)
				{
					++$prev_rounds;
				}
				else if ($game->compare_gametimes($warning, $end) < 0)
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
		echo '</td>';
		
		echo '<td>';
		if ($dead_already || $game->compare_gametimes($death_time, $end) < 0)
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
	
?>	
<script>
	function go_prev()
	{
		html.get("<?php echo url_prev($game_id, $round, $is_day); ?>", function(html)
		{
			$("#round").html(html);
		});
	}
	
	function go_next()
	{
		html.get("<?php echo url_next($game_id, $round, $is_day); ?>", function(html)
		{
			$("#round").html(html);
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