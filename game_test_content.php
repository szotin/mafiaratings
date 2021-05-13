<?php

require_once 'include/session.php';
require_once 'include/game.php';

define('COLUMN_COUNT', 5);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));
define('LEGACY_SUPPORTED_SINCE', 1366393836);

define('SHOW_ISSUES', 'i');
define('SHOW_GAME', 'g');
define('SHOW_VOTING', 'v');
define('SHOW_ORIGINAL', 'o');
define('SHOW_FIXED', 'f');
define('SHOW_ACTIONS', 'a');
define('DEFAULT_SHOW', 'ig');

initiate_session();

function show_flags($flags)
{
	$yes = get_label('yes');
	$no = get_label('no');
	echo '<tr><td width="34%" valign="top"><table class="transp">';
	echo '<tr><td>' . get_label('Arrangement') . ':</td><td style="padding-left: 10px;">' . (($flags & GAME_FEATURE_FLAG_ARRANGEMENT) ? $yes : $no) . '</td></tr>';
	echo '<tr><td>' . get_label('Don checks') . ':</td><td style="padding-left: 10px;">' . (($flags & GAME_FEATURE_FLAG_DON_CHECKS) ? $yes : $no) . '</td></tr>';
	echo '<tr><td>' . get_label('Sheriff checks') . ':</td><td style="padding-left: 10px;">' . (($flags & GAME_FEATURE_FLAG_SHERIFF_CHECKS) ? $yes : $no) . '</td></tr>';
	echo '<tr><td>' . get_label('If players died') . ':</td><td style="padding-left: 10px;">' . (($flags & GAME_FEATURE_FLAG_DEATH) ? $yes : $no) . '</td></tr>';
	echo '<tr><td>' . get_label('Which round players died') . ':</td><td style="padding-left: 10px;">' . (($flags & GAME_FEATURE_FLAG_DEATH_ROUND) ? $yes : $no) . '</td></tr>';
	echo '</table></td><td width="33%" valign="top"><table class="transp">';
	echo '<tr><td>' . get_label('How players died') . ':</td><td style="padding-left: 10px;">' . (($flags & GAME_FEATURE_FLAG_DEATH_TYPE) ? $yes : $no) . '</td></tr>';
	echo '<tr><td>' . get_label('When players died') . ':</td><td style="padding-left: 10px;">' . (($flags & GAME_FEATURE_FLAG_DEATH_TIME) ? $yes : $no) . '</td></tr>';
	echo '<tr><td>' . get_label('Legacy of the first shot player') . ':</td><td style="padding-left: 10px;">' . (($flags & GAME_FEATURE_FLAG_LEGACY) ? $yes : $no) . '</td></tr>';
	echo '<tr><td>' . get_label('Mafia shots') . ':</td><td style="padding-left: 10px;">' . (($flags & GAME_FEATURE_FLAG_SHOOTING) ? $yes : $no) . '</td></tr>';
	echo '<tr><td>' . get_label('Voting') . ':</td><td style="padding-left: 10px;">' . (($flags & GAME_FEATURE_FLAG_VOTING) ? $yes : $no) . '</td></tr>';
	echo '</table></td><td width="33%" valign="top"><table class="transp">';
	echo '<tr><td>' . get_label('Voting for killing all') . ':</td><td style="padding-left: 10px;">' . (($flags & GAME_FEATURE_FLAG_VOTING_KILL_ALL) ? $yes : $no) . '</td></tr>';
	echo '<tr><td>' . get_label('Nominating') . ':</td><td style="padding-left: 10px;">' . (($flags & GAME_FEATURE_FLAG_NOMINATING) ? $yes : $no) . '</td></tr>';
	echo '<tr><td>' . get_label('Number of warnings') . ':</td><td style="padding-left: 10px;">' . (($flags & GAME_FEATURE_FLAG_WARNINGS) ? $yes : $no) . '</td></tr>';
	echo '<tr><td>' . get_label('When warnings were received') . ':</td><td style="padding-left: 10px;">' . (($flags & GAME_FEATURE_FLAG_WARNINGS_DETAILS) ? $yes : $no) . '</td></tr>';
	echo '</table></td></tr>';
}

function show_game($game, $title)
{
	echo '<tr class="th darker"><td colspan="3">' . $title . '</td></tr>';
	if (isset($game->issues))
	{
		foreach ($game->issues as $issue)
		{
			echo '<tr class="lighter"><td colspan="3">' . $issue . '</td></tr>';
		}
	}
	show_flags($game->flags);
	echo '<tr><td colspan="3">';
	print_json($game->data);
	echo '</td></tr>';
}

try
{
	if (isset($_REQUEST['game']))
	{
		$game = new Game($_REQUEST['game']);
	}
	else if (isset($_REQUEST['game_id']))
	{
		$game_id = (int)$_REQUEST['game_id'];
		list ($game_log, $is_canceled) = Db::record(get_label('game'), 'SELECT log, canceled FROM games WHERE id = ?', $game_id);
		$gs = new GameState();
		$gs->init_existing($game_id, $game_log, $is_canceled);
		$feature_flags = GAME_FEATURE_MASK_MAFIARATINGS;
		if ($gs->flags & GAME_FLAG_SIMPLIFIED_CLIENT)
		{
			$feature_flags &= ~GAME_FEATURE_FLAG_VOTING;
		}
		$game = new Game($gs, $feature_flags);
	}
	$game->check();
	
	if (isset($_REQUEST['show']))
	{
		$show = $_REQUEST['show'];
	}		
	else
	{
		$show = DEFAULT_SHOW;
	}
	
	if (isset($game))
	{
		echo '<table class="bordered light" width="100%">';
		for ($i = 0; $i < strlen($show); $i++)
		{
			switch ($show[$i])
			{
				case SHOW_ISSUES:
					break;
					
				case SHOW_GAME:
					if (isset($game_id))
					{
						show_game($game, get_label('Game #[0]', $game_id));
					}
					else
					{
						show_game($game, get_label('The game'));
					}
					break;
					
				case SHOW_VOTING:
					echo '<tr class="th darker"><td colspan="3">' . get_label('Votings json') . '</td></tr>';
					echo '<tr><td colspan="3">';
					if (isset($game->votings))
					{
						print_json($game->votings);
					}
					else
					{
						echo 'Not set';
					}
					echo '</td></tr>';
					break;
					
				case SHOW_ORIGINAL:
					if (isset($game_id))
					{
						list ($game_log, $is_canceled) = Db::record(get_label('game'), 'SELECT log, canceled FROM games WHERE id = ?', $game_id);
						$gs = new GameState();
						$gs->init_existing($game_id, $game_log, $is_canceled);
						echo '<tr class="th darker"><td colspan="3">' . get_label('Original game #[0]', $game_id) . '</td></tr><tr><td colspan="3">';
						print_json($gs);
						echo '</td></tr>';
					}
					break;
					
				case SHOW_FIXED:
					$fixed = new Game($game, $feature_flags);
					$fixed->fix();
					if (isset($game_id))
					{
						show_game($fixed, get_label('Fixed version of the game #[0]', $game_id));
					}
					else
					{
						show_game($fixed, get_label('Fixed version of the game'));
					}
					break;
					
				case SHOW_ACTIONS:
					echo '<tr class="th darker"><td colspan="3">' . get_label('Game actions') . '</td></tr>';
					$actions = $game->get_actions();
					echo '<tr><td colspan="3">';
					print_json($actions);
					echo '</td></tr>';
					break;
			}
		}
		echo '</table>';
	}
}
catch (Exception $e)
{
	echo '<b>Error:</b> ' . $e->getMessage();
}

?>