<?php

require_once 'include/session.php';
require_once 'include/rating_system.php';
require_once 'include/player_stats.php';
require_once 'include/game_player.php';
require_once 'include/languages.php';

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
	public $voted_civ;
	public $voted_maf;
	public $voted_srf;
	public $voted_by_civ;
	public $voted_by_maf;
	public $voted_by_srf;
	public $nominated_civ;
	public $nominated_maf;
	public $nominated_srf;
	public $nominated_by_civ;
	public $nominated_by_maf;
	public $nominated_by_srf;
	public $death_round;
	public $death_type;
	public $warnings;
	public $arranged_for_round;
	public $checked_by_don;
	public $checked_by_srf;
	public $best_player;
	public $best_move;
	public $guessed_all_maf;
	
	function __construct($row)
	{
		list (
			$number, $this->user_id, $this->nick_name, $this->role, $this->voted_civ, $this->voted_maf, 
			$this->voted_srf, $this->voted_by_civ, $this->voted_by_maf, $this->voted_by_srf, $this->nominated_civ, 
			$this->nominated_maf, $this->nominated_srf, $this->nominated_by_civ, $this->nominated_by_maf, $this->nominated_by_srf, 
			$this->death_round, $this->death_type, $this->warnings, $this->arranged_for_round, $this->checked_by_don, 
			$this->checked_by_srf, $flags) = $row;
			
		$this->user_id = (int)$this->user_id;
		$this->role = (int)$this->role;
		$this->voted_civ = (int)$this->voted_civ;
		$this->voted_maf = (int)$this->voted_maf;
		$this->voted_srf = (int)$this->voted_srf;
		$this->voted_by_civ = (int)$this->voted_by_civ;
		$this->voted_by_maf = (int)$this->voted_by_maf;
		$this->voted_by_srf = (int)$this->voted_by_srf;
		$this->nominated_civ = (int)$this->nominated_civ;
		$this->nominated_maf = (int)$this->nominated_maf;
		$this->nominated_srf = (int)$this->nominated_srf;
		$this->nominated_by_civ = (int)$this->nominated_by_civ;
		$this->nominated_by_maf = (int)$this->nominated_by_maf;
		$this->nominated_by_srf = (int)$this->nominated_by_srf;
		$this->death_round = (int)$this->death_round;
		$this->death_type = (int)$this->death_type;
		$this->warnings = (int)$this->warnings;
		$this->arranged_for_round = (int)$this->arranged_for_round;
		$this->checked_by_don = (int)$this->checked_by_don;
		$this->checked_by_srf = (int)$this->checked_by_srf;
		$flags = (int)$flags;
			
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
		
		switch ($this->death_type)
		{
			case DAY_KILL:
				$this->death_type = 'day';
				break;
			case NIGHT_KILL:
				$this->death_type = 'night';
				break;
			case WARNINGS_KILL:
				$this->death_type = 'warning';
				break;
			case SUICIDE_KILL:
				$this->death_type = 'suicide';
				break;
			case KICK_OUT_KILL:
				$this->death_type = 'kick-out';
				break;
			case SURVIVED:
			default:
				unset($this->death_type);
				break;
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
		
		if ($this->voted_civ <= 0)
		{
			unset($this->voted_civ);
		}
		
		if ($this->voted_maf <= 0)
		{
			unset($this->voted_maf);
		}
		
		if ($this->voted_srf <= 0)
		{
			unset($this->voted_srf);
		}
		
		if ($this->voted_by_civ <= 0)
		{
			unset($this->voted_by_civ);
		}
		
		if ($this->voted_by_maf <= 0)
		{
			unset($this->voted_by_maf);
		}
		
		if ($this->voted_by_srf <= 0)
		{
			unset($this->voted_by_srf);
		}
		
		if ($this->nominated_civ <= 0)
		{
			unset($this->nominated_civ);
		}
		
		if ($this->nominated_maf <= 0)
		{
			unset($this->nominated_maf);
		}
		
		if ($this->nominated_srf <= 0)
		{
			unset($this->nominated_srf);
		}
		
		if ($this->nominated_by_civ <= 0)
		{
			unset($this->nominated_by_civ);
		}
		
		if ($this->nominated_by_maf <= 0)
		{
			unset($this->nominated_by_maf);
		}
		
		if ($this->nominated_by_srf <= 0)
		{
			unset($this->nominated_by_srf);
		}
		
		if ($flags & RATING_BEST_PLAYER)
		{
			$this->best_player = true;
		}
		else
		{
			unset($this->best_player);
		}
		
		if ($flags & RATING_BEST_MOVE)
		{
			$this->best_move = true;
		}
		else
		{
			unset($this->best_move);
		}
		
		if ($flags & RATING_GUESS_ALL_MAF)
		{
			$this->guessed_all_maf = true;
		}
		else
		{
			unset($this->guessed_all_maf);
		}
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
	
	function __construct($row)
	{
		list ($this->id, $this->club_id, $this->event_id, $this->start_time, $this->end_time, $this->language, $this->moderator_id, $this->winner) = $row;
		$this->players = array();
		
		$this->id = (int)$this->id;
		$this->club_id = (int)$this->club_id;
		if (is_null($this->event_id))
		{
			unset($this->event_id);
		}
		else
		{
			$this->event_id = (int)$this->event_id;
		}
		$this->start_time = (int)$this->start_time;
		$this->end_time = (int)$this->end_time;
		
		$this->language = get_lang_code($this->language);
		switch ($this->winner)
		{
			case 1:
				$this->winner = 'civ';
				break;
			case 2:
				$this->winner = 'maf';
				break;
			default:
				unset($this->winner);
				break;
		}
		$this->moderator_id = (int)$this->moderator_id;
		
		$this->players = array( NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL );
		$query = new DbQuery(
			'SELECT number, user_id, nick_name, role, voted_civil, voted_mafia, voted_sheriff, voted_by_civil, voted_by_mafia, ' .
			'voted_by_sheriff, nominated_civil, nominated_mafia, nominated_sheriff, nominated_by_civil, nominated_by_mafia, ' . 
			'nominated_by_sheriff, kill_round, kill_type, warns, was_arranged, checked_by_don, checked_by_sheriff, flags ' .
			'FROM players WHERE game_id = ?',
			$this->id);
		while ($row = $query->next())
		{
			$this->players[(int)$row[0] - 1] = new WSPlayer($row);
		}
		
		$query = new DbQuery(
			'SELECT user_id, shots1_ok + shots2_ok + shots3_ok, shots1_miss + shots2_miss + shots3_miss, shots1_miss + shots2_blank + shots3_blank, shots3_fail, shots3_rearrange FROM mafiosos WHERE game_id = ?',
			$this->id);
		while ($row = $query->next())
		{
			list ($user_id, $successful_shots, $missed_shots, $skipped_shots, $failed_shots, $rearranged_shots) = $row;
			foreach ($this->players as $player)
			{
				if (!is_null($player) && $player->user_id == $user_id)
				{
					if ($successful_shots > 0)
					{
						$player->successful_shots = (int)$successful_shots;
					}
					if ($missed_shots > 0)
					{
						$player->missed_shots = (int)$missed_shots;
					}
					if ($skipped_shots > 0)
					{
						$player->skipped_shots = (int)$skipped_shots;
					}
					if ($failed_shots > 0)
					{
						$player->failed_shots = (int)$failed_shots;
					}
					if ($rearranged_shots > 0)
					{
						$player->rearranged_shots = (int)$rearranged_shots;
					}
					break;
				}
			}
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
				  <dd>The array of players who played. Array size is always 10. Players index in the array matches their number at the table. For some players null is returned. This means that a player does not have mafiaratings account.</dd>
			<h2>Player parameters:</h2>
				<dt>user_id</td>
					<dd>User id.</dd>
				<dt>nick_name</td>
					<dd>Nick name used in this game.</dd>
				<dt>role</td>
					<dd>One of: "civ", "maf", "srf", or "don"</dd>
				<dt>voted_civ</td>
					<dd>How many times this player voted against civilians. <i>Optional:</i> missing when 0.</dd>
				<dt>voted_maf</td>
					<dd>How many times this player voted against mafia. <i>Optional:</i> missing when 0.</dd>
				<dt>voted_srf</td>
					<dd>How many times this player voted against sheriff. <i>Optional:</i> missing when 0.</dd>
				<dt>voted_by_civ</td>
					<dd>How many times civilians voted against this player. <i>Optional:</i> missing when 0.</dd>
				<dt>voted_by_maf</td>
					<dd>How many times mafia voted against this player. <i>Optional:</i> missing when 0.</dd>
				<dt>voted_by_srf</td>
					<dd>How many times sheriff voted against this player. <i>Optional:</i> missing when 0.</dd>
				<dt>nominated_civ</td>
					<dd>How many times this player nominated civilians. <i>Optional:</i> missing when 0.</dd>
				<dt>nominated_maf</td>
					<dd>How many times this player nominated mafia. <i>Optional:</i> missing when 0.</dd>
				<dt>nominated_srf</td>
					<dd>How many times this player nominated sheriff. <i>Optional:</i> missing when 0.</dd>
				<dt>nominated_by_civ</td>
					<dd>How many times civilians nominated this player. <i>Optional:</i> missing when 0.</dd>
				<dt>nominated_by_maf</td>
					<dd>How many times mafia nominated this player. <i>Optional:</i> missing when 0.</dd>
				<dt>nominated_by_srf</td>
					<dd>How many times sheriff nominated this player. <i>Optional:</i> missing when 0.</dd>
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
				<dt>successful_shots</dt>
					<dd>Number of times the player partisipated in successful night shooting. <i>Optional:</i> missing when the player was red or was not participating in successful shooting.</dd>
				<dt>missed_shots</dt>
					<dd>Number of times the player partisipated in unsuccessful night shooting. <i>Optional:</i> missing when the player was red or was not participating in missed shooting.</dd>
				<dt>skipped_shots</dt>
					<dd>Number of times the player did not do the night shot. <i>Optional:</i> missing when the player was red or skipped_shots is 0.</dd>
				<dt>failed_shots</dt>
					<dd>Number of times the player was guilty in a mafia miss. This is when 2 partners shoot one player but this player shoots someone else.<i>Optional:</i> missing when the player was red or failed_shots is 0.</dd>
				<dt>rearranged_shots</dt>
					<dd>Number of times the player participated in successful shot that was not statically arranged. <i>Optional:</i> missing when the player was red or rearranged_shots is 0.</dd>
<?php		
		}
		else
		{
			initiate_session();
			
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
					$query = new DbQuery('SELECT g.id, g.club_id, g.event_id, g.start_time, g.end_time, g.language, g.moderator_id, g.result FROM players p JOIN games g ON  p.game_id = g.id WHERE g.result IN(1,2) AND p.user_id = ?', $user);
				}
			}
			else if ($count_only)
			{
				$query = new DbQuery('SELECT count(*) FROM games g WHERE g.result IN(1,2)');
			}
			else
			{
				$query = new DbQuery('SELECT g.id, g.club_id, g.event_id, g.start_time, g.end_time, g.language, g.moderator_id, g.result FROM games g WHERE g.result IN(1,2)');
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
					$result->games[] = new WSGame($row);
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