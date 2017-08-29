<?php

require_once 'include/session.php';
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

class WSRole
{
}

$result = new WSResult();
try
{
	if (isset($_REQUEST['version']))
	{
		$result->version = (int)$_REQUEST['version'];
		if ($result->version > CURRENT_VERSION)
		{
			throw new Exc('Version ' . $result->version . ' is not supported. The latest supported version is ' . CURRENT_VERSION . '.');
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
		  <dt>before</dt>
			<dd>Unix timestamp for the latest game to return. For example: <a href="ws_games.php?before=1483228800">ws_games.php?before=1483228800</a> returns all games started before 2017</dd>
		  <dt>after</dt>
			<dd>Unix timestamp for the earliest game to return. For example: <a href="ws_games.php?after=1483228800">ws_games.php?after=1483228800</a> returns all games started after January 1, 2017 inclusive; <a href="ws_games.php?after=1483228800&before=1485907200">ws_games.php?after=1483228800&before=1485907200</a> returns all games played in January 2017. (Using start time - if the game ended in February but started in January it is still a January game).</dd>
		  <dt>club</dt>
			<dd>Club id. For example: <a href="ws_games.php?club=1">ws_games.php?club=1</a> returns all games for Vancouver Mafia Club. If missing, all games for all clubs are returned.</dd>
		  <dt>game</dt>
			<dd>Game id. For example: <a href="ws_games.php?game=1299">ws_games.php?game=1299</a> returns only one game played in VaWaCa tournament.</dd>
		  <dt>event</dt>
			<dd>Event id. For example: <a href="ws_games.php?event=7927">ws_games.php?event=7927</a> returns all games for VaWaCa tournament. If missing, all games for all events are returned.</dd>
		  <dt>address</dt>
			<dd>Address id. For example: <a href="ws_games.php?address=10">ws_games.php?address=10</a> returns all games played in Tafs Cafe by Vancouver Mafia Club.</dd>
		  <dt>city</dt>
			<dd>City id. For example: <a href="ws_games.php?city=49">ws_games.php?city=49</a> returns all games played in Seattle. List of the cities and their ids can be obtained using <a href="ws_cities.php?help">ws_cities.php</a>.</dd>
		  <dt>area</dt>
			<dd>City id. The difference with city is that when area is set, the games from all nearby cities are also returned. For example: <a href="ws_games.php?area=1">ws_games.php?area=1</a> returns all games played in Vancouver and nearby cities. Though <a href="ws_games.php?city=1">ws_games.php?city=1</a> returns only the games played in Vancouver itself.</dd>
		  <dt>country</dt>
			<dd>Country id. For example: <a href="ws_games.php?country=2">ws_games.php?country=2</a> returns all games played in Russia. List of the countries and their ids can be obtained using <a href="ws_countries.php?help">ws_countries.php</a>.</dd>
		  <dt>user</dt>
			<dd>User id. For example: <a href="ws_games.php?user=25">ws_games.php?user=25</a> returns all games where Fantomas played. If missing, all games for all users are returned.</dd>
		  <dt>langs</dt>
			<dd>Languages filter. 1 for English; 2 for Russian. Bit combination - 3 - means both (this is a default value). For example: <a href="ws_games.php?langs=1">ws_games.php?langs=1</a> returns all games played in English; <a href="ws_games.php?club=1&langs=3">ws_games.php?club=1&langs=3</a> returns all English and Russian games of Vancouver Mafia Club</dd>
		  <dt>count</dt>
			<dd>Returns game count but does not return the games. For example: <a href="ws_games.php?user=25&count">ws_games.php?user=25&count</a> returns how many games Fantomas have played; <a href="ws_games.php?event=7927&count">ws_games.php?event=7927&count</a> returns how many games were played in VaWaCa tournament.</dd>
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
			  <dd>Language of the game. Possible values are: 1 for English; 2 for Russian. Other languages are not supported in the current version.</dd>
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
		<br><br>
<?php		
	}
	else
	{
		initiate_session();
		
		$raw = isset($_REQUEST['raw']);
		
		$after = 0;
		if (isset($_REQUEST['after']))
		{
			$after = (int)$_REQUEST['after'];
		}
		
		$before = 0;
		if (isset($_REQUEST['before']))
		{
			$before = (int)$_REQUEST['before'];
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
		
		$langs = LANG_ALL;
		if (isset($_REQUEST['langs']))
		{
			$langs = (int)$_REQUEST['langs'];
		}
		
		$game = 0;
		if (isset($_REQUEST['game']))
		{
			$game = (int)$_REQUEST['game'];
		}
		
		$address = 0;
		if (isset($_REQUEST['address']))
		{
			$address = (int)$_REQUEST['address'];
		}
		
		$country = 0;
		if (isset($_REQUEST['country']))
		{
			$country = (int)$_REQUEST['country'];
		}
		
		$area = 0;
		if (isset($_REQUEST['area']))
		{
			$area = (int)$_REQUEST['area'];
		}
		
		$city = 0;
		if (isset($_REQUEST['city']))
		{
			$city = (int)$_REQUEST['city'];
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
		
		$condition = new SQL('');
		if ($before > 0)
		{
			$condition->add(' AND g.start_time < ?', $before);
		}

		if ($after > 0)
		{
			$condition->add(' AND g.start_time >= ?', $after);
		}

		if ($club > 0)
		{
			$condition->add(' AND g.club_id = ?', $club);
		}

		if ($game > 0)
		{
			$condition->add(' AND g.id = ?', $game);
		}
		else if ($event > 0)
		{
			$condition->add(' AND g.event_id = ?', $event);
		}
		else if ($address > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT id FROM events WHERE address_id = ?)', $address);
		}
		else if ($city > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id WHERE a1.city_id = ?)', $city);
		}
		else if ($area > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.area_id = (SELECT area_id FROM cities WHERE id = ?))', $area);
		}
		else if ($country > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.country_id = ?)', $country);
		}
		
		if ($langs != LANG_ALL)
		{
			$condition->add(' AND (g.language & ?) <> 0', $langs);
		}
		
		$condition->add(' ORDER BY g.start_time DESC');
		
		if ($user > 0)
		{
			$count_query = new DbQuery('SELECT count(*) FROM players p JOIN games g ON p.game_id = g.id WHERE g.result IN(1,2) AND p.user_id = ?', $user, $condition);
			$query = new DbQuery('SELECT g.id, g.log FROM players p JOIN games g ON  p.game_id = g.id WHERE g.result IN(1,2) AND p.user_id = ?', $user, $condition);
		}
		else
		{
			$count_query = new DbQuery('SELECT count(*) FROM games g WHERE g.result IN(1,2)', $condition);
			$query = new DbQuery('SELECT g.id, g.log FROM games g WHERE g.result IN(1,2)', $condition);
		}
		
		list ($count) = $count_query->record('game');
		$result->count = (int)$count;
		if (!$count_only)
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