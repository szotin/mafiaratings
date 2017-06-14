<?php

require_once 'include/session.php';
require_once 'include/rating_system.php';
require_once 'include/languages.php';
require_once 'include/game_state.php';

define('CURRENT_VERSION', 0);

class WSResult
{
	public $version;
	
	function __construct()
	{
		$this->version = CURRENT_VERSION;
	}
}

class WSPlayer
{
	public $user_id;
	public $nick_name;
	public $role;
	public $death_round;
	public $death_type;
	public $warnings;
	public $arranged_for_round;
	public $checked_by_don;
	public $checked_by_srf;
	public $best_player;
	public $best_move;
	public $guessed_all_maf;
	
	function normalize()
	{
		$this->user_id = (int)$this->user_id;
		$this->role = (int)$this->role;
		$this->death_round = (int)$this->death_round;
		$this->warnings = (int)$this->warnings;
		$this->arranged_for_round = (int)$this->arranged_for_round;
		$this->checked_by_don = (int)$this->checked_by_don;
		$this->checked_by_srf = (int)$this->checked_by_srf;

		switch ($this->role)
		{
			case PLAYER_ROLE_CIVILIAN:
				$this->role = 'civ';
				break;
			case PLAYER_ROLE_SHERIFF:
				$this->role = 'srf';
				break;
			case PLAYER_ROLE_MAFIA:
				$this->role = 'maf';
				break;
			case PLAYER_ROLE_DON:
				$this->role = 'don';
				break;
		}
		
		if ($this->death_round < 0)
		{
			unset($this->death_round);
		}
		
		if ($this->user_id <= 0)
		{
			unset($this->user_id);
		}
		
		if ($this->warnings <= 0)
		{
			unset($this->warnings);
		}
		
		if ($this->arranged_for_round < 0)
		{
			unset($this->arranged_for_round);
		}
		
		if ($this->checked_by_srf < 0)
		{
			unset($this->checked_by_srf);
		}
		
		if ($this->checked_by_don < 0)
		{
			unset($this->checked_by_don);
		}
		
		if (!$this->best_player)
		{
			unset($this->best_player);
		}
		
		if (!$this->best_move)
		{
			unset($this->best_move);
		}
		
		if (!$this->guessed_all_maf)
		{
			unset($this->guessed_all_maf);
		}
	}
	
	function __construct($gs, $index)
	{
		$player = $gs->players[$index];
		$this->user_id = $player->id;
		$this->nick_name = $player->nick;
		$this->role = $player->role;
		$this->death_round = $player->kill_round;
        switch ($player->kill_reason)
        {
            case KILL_REASON_NORMAL:
                if ($player->state == PLAYER_STATE_KILLED_NIGHT)
                {
                    $this->death_type = 'night';
                }
                else if ($player->state == PLAYER_STATE_KILLED_DAY)
                {
                    $this->death_type = 'day';
                }
                break;
            case KILL_REASON_SUICIDE:
                $this->death_type = 'suicide';
                break;
            case KILL_REASON_WARNINGS:
                $this->death_type = 'warning';
                break;
            case KILL_REASON_KICK_OUT:
                $this->death_type = 'kick-out';
                break;
            default:
				unset($this->death_type);
                break;
        }
		
		$this->warnings = $player->warnings;
		$this->arranged_for_round = $player->arranged;
		$this->checked_by_don = $player->don_check;
		$this->checked_by_srf = $player->sheriff_check;
		$this->best_player = ($gs->best_player == $index);
		$this->best_move = ($gs->best_move == $index);
		$this->guessed_all_maf = $gs->is_good_guesser($index);
		
		$this->normalize();
	}
}

class WSGame
{
	public $id;
	public $club_id;
	public $event_id;
	public $start_time;
	public $end_time;
	public $language;
	public $moderator_id;
	public $winner;
	public $players;
	
	function __construct($gs)
	{
		$this->id = $gs->id;
		$this->club_id = $gs->club_id;
		if (is_null($gs->event_id))
		{
			unset($this->event_id);
		}
		else
		{
			$this->event_id = $gs->event_id;
		}
		$this->start_time = $gs->start_time;
		$this->end_time = $gs->end_time;
		
		$this->language = get_lang_code($gs->lang);
		switch ($gs->gamestate)
		{
			case GAME_MAFIA_WON:
				$this->winner = 'maf';
				break;
			case GAME_CIVIL_WON:
				$this->winner = 'civ';
				break;
			default:
				unset($this->winner);
				break;
		}
		$this->moderator_id = $gs->moder_id;
		
		$this->players = array();
		for ($i = 0; $i < 10; ++$i)
		{
			$this->players[$i] = new WSPlayer($gs, $i);
		}
		
		// $this->voting = $gs->votings;
		foreach ($gs->votings as $voting)
		{
			$round_key = 'round_' . $voting->round;
			if ($voting->voting_round == 0)
			{
				foreach ($voting->nominants as $nominant)
				{
					if ($nominant->nominated_by >= 0 && $nominant->nominated_by < 10)
					{
						$this->players[$nominant->nominated_by]->nominating[$round_key] = $nominant->player_num;
					}
				}
			}
			
			for ($i = 0; $i < 10; ++$i)
			{
				$vote = $voting->votes[$i];
				if ($vote >= 0)
				{
					$player = $this->players[$i];
					if (!isset($player->voting) || !isset($player->voting[$round_key]))
					{
						$player->voting[$round_key] = $voting->nominants[$vote]->player_num;
					}
					else if (is_array($player->voting[$round_key]))
					{
						$player->voting[$round_key][] = $voting->nominants[$vote]->player_num;
					}
					else
					{
						$player->voting[$round_key] = array($player->voting[$round_key], $voting->nominants[$vote]->player_num);
					}
				}
			}
		}
		
		$round = 0;
		foreach ($gs->shooting as $shots)
		{
			foreach ($shots as $shoter => $shooted)
			{
				if ($shooted >= 0 && $shooted < 10)
				{
					$this->players[$shoter]->shooting['round_' . $round] = $shooted;
				}
			}
			++$round;
		}
	}
}

	$result = new WSResult();
	try
	{
		if (isset($_REQUEST['version']))
		{
			$result->version = (int)$_REQUEST['version'];
			if ($result->version > CURRENT_VERSION)
			{
				throw new Exc(get_label('Version [0] is not supported. The latest supported version is [1].', $result->version, CURRENT_VERSION));
			}
		}

		if (isset($_REQUEST['help']))
		{
?>
		<h1>Parameters:</h1>
			<dl>
			  <dt>help</dt>
				<dd>Shows this screen.</dd>
			  <dt>version</dt>
				<dd>Requiered data version. It is recommended to set it. It guarantees that the format of a data you receive is never changed. If not set, the latest version is returned. Note that <i>version</i> is the only parameter that can be used together with <i>help</i>.
<?php				
					echo 'Current version is ' . CURRENT_VERSION . '.';
					if (CURRENT_VERSION != $result->version)
					{
						echo ' This help shows the data format for version ' . $result->version . '.';
					}
?>
				</dd>
			  <dt>from</dt>
				<dd>Unix timestamp for the earliest game to return. For example: <a href="ws_games.php?from=1483228800">ws_games.php?from=1483228800</a> returns all games played starting from January 1, 2017</dd>
			  <dt>to</dt>
				<dd>Unix timestamp for the latest game to return. For example: <a href="ws_games.php?to=1483228800">ws_games.php?to=1483228800</a> returns all games played before 2017; <a href="ws_games.php?from=1483228800&to=1485907200">ws_games.php?from=1483228800&to=1485907200</a> returns all games played in January 2017</dd>
			  <dt>club</dt>
				<dd>Club id. For example: <a href="ws_games.php?club=1">ws_games.php?club=1</a> returns all games for Vancouver Mafia Club. If missing, all games for all clubs are returned.</dd>
			  <dt>event</dt>
				<dd>Event id. For example: <a href="ws_games.php?event=7927">ws_games.php?event=7927</a> returns all games for VaWaCa tournament. If missing, all games for all events are returned.</dd>
			  <dt>user</dt>
				<dd>User id. For example: <a href="ws_games.php?user=25">ws_games.php?user=25</a> returns all games where Fantomas played. If missing, all games for all users are returned.</dd>
			  <dt>game</dt>
				<dd>Game id. For example: <a href="ws_games.php?game=1299">ws_games.php?game=1299</a> returns only one game played in VaWaCa tournament.</dd>
			  <dt>count</dt>
				<dd>Returns game count instead of games. For example: <a href="ws_games.php?user=25&count">ws_games.php?user=25&count</a> returns how many games Fantomas have played; <a href="ws_games.php?event=7927&count">ws_games.php?event=7927&count</a> returns how many games were played in VaWaCa tournament.</dd>
			  <dt>page</dt>
				<dd>Page number. For example: <a href="ws_games.php?club=1&page=1">ws_games.php?club=1&page=1</a> returns the second page for Vancouver Mafia Club.</dd>
			  <dt>page_size</dt>
				<dd>Page size. Default page_size is 16. For example: <a href="ws_games.php?club=1&page_size=32">ws_games.php?club=1&page_size=32</a> returns last 32 games for Vancouver Mafia Club; <a href="ws_games.php?club=6&page_size=0">ws_games.php?club=6&page_size=0</a> returns all games for Empire of Mafia club in one page; <a href="ws_games.php?club=1">ws_games.php?club=1</a> returns last 16 games for Vancouver Mafia Club;</dd>
			</dl>	
		<h1>Results:</h1>
			<dt>version</dt>
			  <dd>Data version.</dd>
			<dt>games</dt>
			  <dd>The array of games. Games are always sorted from latest to oldest. There is no way to change sorting order in the current version of the API.</dd>
			<dt>count</dt>
			  <dd>The total number of games sutisfying the request parameters. It is set only when the parameter <i>count</i> is set.</dd>
			<dt>error</dt>
			  <dd>Error message when an error occurs.</dd>
			<h2>Game parameters:</h2>
				<dt>id</dt>
				  <dd>Game id. Unique game identifier.</dd>
				<dt>club_id</dt>
				  <dd>Club id. Unique club identifier.</dd>
				<dt>event_id</dt>
				  <dd>Event id. Unique event identifier.</dd>
				<dt>start_time</dt>
				  <dd>Unix timestamp for the game start.</dd>
				<dt>end_time</dt>
				  <dd>Unix timestamp for the game end.</dd>
				<dt>language</dt>
				  <dd>Language of the game. Possible values are: "ru" or "en". Other languages are not supported in the current version.</dd>
				<dt>moderator_id</dt>
				  <dd>User id of the user who moderated the game.</dd>
				<dt>winner</dt>
				  <dd>Who won the game. Possible values: "civ" or "maf". Tie is not supported in the current version.</dd>
				<dt>players</dt>
				  <dd>The array of players who played. Array size is always 10. Players index in the array matches their number at the table.</dd>
			<h2>Player parameters:</h2>
				<dt>user_id</td>
					<dd>User id. <i>Optional:</i> missing when someone not registered in mafiaratings played.</dd>
				<dt>nick_name</td>
					<dd>Nick name used in this game.</dd>
				<dt>role</td>
					<dd>One of: "civ", "maf", "srf", or "don".</dd>
				<dt>death_round</td>
					<dd>The round number (starting from 0) when this player was killed. <i>Optional:</i> missing if the player survived.</dd>
				<dt>death_type</td>
					<dd>How this player was killed. Possible values: "day" - killed by day votings; "night" - killed by night shooting; "warning" - killed by 4th warning; "suicide" - left the game by theirself; "kick-out' - kicked out by the moderator. <i>Optional:</i> missing if the player survived.</dd>
				<dt>warnings</td>
					<dd>Number of warnings. <i>Optional:</i> missing when 0.</dd>
				<dt>arranged_for_round</td>
					<dd>Was arranged by mafia to be shooted down in the round (starting from 0). <i>Optional:</i> missing when the player was not arranged.</dd>
				<dt>checked_by_don</td>
					<dd>The round (starting from 0) when the don checked this player. <i>Optional:</i> missing when the player was not checked by the don.</dd>
				<dt>checked_by_srf</td>
					<dd>The round (starting from 0) when the sheriff checked this player. <i>Optional:</i> missing when the player was not checked by the sheriff.</dd>
				<dt>best_player</td>
					<dd>True if this is the best player. <i>Optional:</i> missing when false.</dd>
				<dt>best_move</td>
					<dd>True if the player did the best move of the game. <i>Optional:</i> missing when false.</dd>
				<dt>guessed_all_maf</td>
					<dd>True if the player guessed all 3 mafs right. <i>Optional:</i> missing when player was not killed in night 0, or when they guessed wrong.</dd>
				<dt>voting</td>
					<dd>How the player was voting. An assotiated array in the form <i>round_N: M</i>. Where N is day number (starting from 0); M is the number of player for whom he/she voted (0 to 9).</dd>
				<dt>nominating</td>
					<dd>How the player was nominating. An assotiated array in the form <i>round_N: M</i>. Where N is day number (starting from 0); M is the number of player who was nominated (0 to 9).</dd>
				<dt>shooting</td>
					<dd>For mafia only. An assotiated array in the form <i>round_N: M</i>. . Where N is day number (starting from 0); M is the number of player who was nominated (0 to 9). For example: { round_0: 0, round_1: 8, round_2: 9 } means that this player was shooting player 1(index 0) the first night; player 9 the second night; and player 10 the third night.</dd>
<?php		
		}
		else
		{
			initiate_session();
			
			$raw = isset($_REQUEST['raw']);
			
			$from = 0;
			if (isset($_REQUEST['from']))
			{
				$from = (int)$_REQUEST['from'];
			}
			
			$to = 0;
			if (isset($_REQUEST['to']))
			{
				$to = (int)$_REQUEST['to'];
			}
			
			$club = 0;
			if (isset($_REQUEST['club']))
			{
				$club = (int)$_REQUEST['club'];
			}
			
			$event = 0;
			if (isset($_REQUEST['event']))
			{
				$event = (int)$_REQUEST['event'];
			}
			
			$user = 0;
			if (isset($_REQUEST['user']))
			{
				$user = (int)$_REQUEST['user'];
			}
			
			$game = 0;
			if (isset($_REQUEST['game']))
			{
				$game = (int)$_REQUEST['game'];
			}
			
			$page_size = 16;
			if (isset($_REQUEST['page_size']))
			{
				$page_size = (int)$_REQUEST['page_size'];
			}
			
			$page = 0;
			if (isset($_REQUEST['page']))
			{
				$page = (int)$_REQUEST['page'];
			}
			
			$count_only = isset($_REQUEST['count']);
			
			if ($user > 0)
			{
				if ($count_only)
				{
					$query = new DbQuery('SELECT count(*) FROM players p JOIN games g ON p.game_id = g.id WHERE g.result IN(1,2) AND p.user_id = ?', $user);
				}
				else
				{
					$query = new DbQuery('SELECT g.id, g.log FROM players p JOIN games g ON  p.game_id = g.id WHERE g.result IN(1,2) AND p.user_id = ?', $user);
				}
			}
			else if ($count_only)
			{
				$query = new DbQuery('SELECT count(*) FROM games g WHERE g.result IN(1,2)');
			}
			else
			{
				$query = new DbQuery('SELECT g.id, g.log FROM games g WHERE g.result IN(1,2)');
			}
			if ($from > 0)
			{
				$query->add(' AND g.end_time > ?', $from);
			}

			if ($to > 0)
			{
				$query->add(' AND g.start_time < ?', $to);
			}

			if ($club > 0)
			{
				$query->add(' AND g.club_id = ?', $club);
			}

			if ($event > 0)
			{
				$query->add(' AND g.event_id = ?', $event);
			}
			
			if ($game > 0)
			{
				$query->add(' AND g.id = ?', $game);
			}
			
			$query->add(' ORDER BY g.start_time DESC');
			
			if ($count_only)
			{
				list ($result->count) = $query->next();
			}
			else
			{
				if ($page_size > 0)
				{
					$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
				}
				
				while ($row = $query->next())
				{
					list ($id, $log) = $row;
					$gs = new GameState();
					$gs->init_existing($id, $log);
					if ($raw)
					{
						$game = $gs;
					}
					else
					{
						$game = new WSGame($gs);
					}
					$result->games[] = $game;
				}
			}
		}
	}
	catch (Exception $e)
	{
		Exc::log($e, true);
		$result->error = $e->getMessage();
	}
	
	if (isset($_REQUEST['sql']))
	{
		$result->sql = $query->get_parsed_sql();
	}
	echo json_encode($result);

?>