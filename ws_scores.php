<?php

require_once 'include/session.php';
require_once 'include/ws.php';
require_once 'include/scoring.php';

define('CURRENT_VERSION', 0);

class WSScore
{
	public $num;
	public $id;
	public $name;
	public $languages;
	public $points;
	public $num_games;
	public $games_won;
	public $club_id;
	
	function __construct($score, $num)
	{
		$this->id = $score->id;
		$this->name = $score->name;
		$this->languages = $score->langs;
		$this->points = $score->points;
		$this->num_games = $score->get_count(SCORING_MATTER_PLAY);
		$this->games_won = $score->get_count(SCORING_MATTER_WIN);
		$this->club_id = $score->club_id;
		$this->num = (int)$num;
		
		if (($score->flags & U_FLAG_MALE) != 0)
		{
			$this->male = true;
		}
		if (($score->flags & U_ICON_MASK) != 0)
		{
			$this->image = USER_PICS_DIR . TNAILS_DIR . $this->id . '.png?' . (($score->flags & U_ICON_MASK) >> U_ICON_MASK_OFFSET);
			$this->icon = USER_PICS_DIR . ICONS_DIR . $this->id . '.png?' . (($score->flags & U_ICON_MASK) >> U_ICON_MASK_OFFSET);
		}
	}
}

class WSResult
{
	public $version;
	
	function __construct()
	{
		$this->version = CURRENT_VERSION;
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
			throw new Exc('Version ' . $result->version . ' is not supported. The latest supported version is ' . CURRENT_VERSION . '.');
		}
	}

	if (isset($_REQUEST['help']))
	{
?>
	<h1>ws_scores Parameters:</h1>
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
			<dt>contains</dt>
				<dd>Search pattern. For example: <a href="ws_scores.php?contains=al">ws_scores.php?contains=al</a> returns players containing "al" in their name.</dd>
			<dt>starts</dt>
				<dd>Search pattern. For example: <a href="ws_scores.php?starts=bo">ws_scores.php?starts=bo</a> returns players with names starting with "bo". Note that "Bad Boy" is also returned.</dd>
			<dt>scoring</dt>
				<dd>Scoring system id. For example: <a href="ws_scores.php?club=1&scoring=13">ws_scores.php?club=1&scoring=13</a> returns scores for all players of Vancouver Mafia Club using 3-4-4-5 scoring system. If missing, the club scoring system (if club is specified) or event scoring system (if event is specified) or default scoring system is used.</dd>
			<dt>club</dt>
				<dd>Club id. For example: <a href="ws_scores.php?club=1">ws_scores.php?club=1</a> returns the scores in Vancouver Mafia Club. Club scoring system is used by default.</dd>
			<dt>game</dt>
				<dd>Game id. For example: <a href="ws_scores.php?game=1299">ws_scores.php?game=1299</a> returns the scores of the game 1299, played in VaWaCa tournament. VaWaCa event scoring system is used by default because this game belongs to this event.</dd>
			<dt>event</dt>
				<dd>Event id. For example: <a href="ws_scores.php?event=7927">ws_scores.php?event=7927</a> returns the scores of VaWaCa tournament. VaWaCa event scoring system is used by default.</dd>
			<dt>address</dt>
				<dd>Address id. For example: <a href="ws_scores.php?address=10">ws_scores.php?address=10</a> returns the scores of the games played in Tafs Cafe in Vancouver Mafia Club. Vancouver Mafia Club scoring system is used by default.</dd>
			<dt>city</dt>
				<dd>City id. For example: <a href="ws_scores.php?city=49">ws_scores.php?city=49</a> returns the scores of the games played in Seattle. Default scoring system is used by default (currently it is ФИИМ).</dd>
			<dt>area</dt>
				<dd>City id. The difference with city is that when area is set, the games from all nearby cities are also returned. For example: <a href="ws_scores.php?area=1">ws_scores.php?area=1</a> returns the scores of the games played in Vancouver and nearby cities like Delta, Burnaby, Surrey, etc. Though <a href="ws_scores.php?city=1">ws_scores.php?city=1</a> returns only the scores in Vancouver itself. Default scoring system is used by default (currently it is ФИИМ).</dd>
			<dt>country</dt>
				<dd>Country id. For example: <a href="ws_scores.php?country=2">ws_scores.php?country=2</a> returns the sores of the games played in Russia. Default scoring system is used by default (currently it is ФИИМ).</dd>
			<dt>with_user</dt>
				<dd>User id. For example: <a href="ws_scores.php?with_user=4">ws_scores.php?with_user=4</a> returns the scores for the games where lilya participated. Default scoring system is used by default (currently it is ФИИМ).</dd>
			<dt>langs</dt>
				<dd>Languages filter. 1 for English; 2 for Russian. Bit combination - 3 - means both (this is a default value). For example: <a href="ws_scores.php?langs=1">ws_scores.php?langs=1</a> returns the scores for English games Default scoring system is used by default (currently it is ФИИМ).</dd>
			<dt>role</dt>
				<dd>The score for the specified role only. Possible values are:
					<ul>
						<li>a - all roles (default)</li>
						<li>r - red roles = civilian + sheriff: <a href="ws_scores.php?role=r">ws_scores.php?role=r</a></li>
						<li>b - black roles = mafia + don: <a href="ws_scores.php?role=b">ws_scores.php?role=b</a></li>
						<li>c - civilian but not the sheriff: <a href="ws_scores.php?role=c">ws_scores.php?role=c</a></li>
						<li>s - sheriff: <a href="ws_scores.php?role=s">ws_scores.php?role=s</a></li>
						<li>m - mafia but not the don: <a href="ws_scores.php?role=m">ws_scores.php?role=m</a></li>
						<li>d - don: <a href="ws_scores.php?role=d">ws_scores.php?role=d</a></li>
					</ul>
				</dd>
			<dt>number</dt>
				<dd>Number in the game (1-10). For example: <a href="ws_scores.php?number=2&event=7927">ws_scores.php?number=2&event=7927</a> returns scores earned by players playing on number 2 in VaWaCa tournement. In the other words: who is the best number 2 in the tournament.</dd>
			<dt>before</dt>
				<dd>Unix timestamp. For example: <a href="ws_scores.php?before=1483228800">ws_scores.php?before=1483228800</a> returns scores earned before 2017. In other words it returns scores as they were at the end of 2016.</dd>
			<dt>after</dt>
				<dd>Unix timestamp. For example: <a href="ws_scores.php?after=1483228800">ws_scores.php?after=1483228800</a> returns scores earned after January 1, 2017; <a href="ws_scores.php?after=1483228800&before=1485907200">ws_scores.php?after=1483228800&before=1485907200</a> returns scores earned in January 2017</dd>
			<dt>count</dt>
				<dd>Returns game count instead of players list. For example: <a href="ws_scores.php?club=1&count">ws_scores.php?club=1&count</a> returns how many players with scores are there in Vancouver Mafia Club; <a href="ws_scores.php?event=7927&count">ws_scores.php?event=7927&count</a> returns how many players with scores participated in VaWaCa tournament.</dd>
			<dt>page</dt>
				<dd>Page number. For example: <a href="ws_scores.php?club=1&page=1">v.php?club=1&page=1</a> returns the second page of scores for Vancouver Mafia Club players.</dd>
			<dt>page_size</dt>
				<dd>Page size. Default page_size is 16. For example: <a href="ws_scores.php?club=1&page_size=32">ws_scores.php?club=1&page_size=32</a> returns top 32 players for Vancouver Mafia Club; <a href="ws_scores.php?club=6&page_size=0">ws_scores.php?club=6&page_size=0</a> returns all players for Empire of Mafia club in one page; <a href="ws_scores.php?club=1">ws_scores.php?club=1</a> returns top 16 players for Vancouver Mafia Club;</dd>
		</dl>	
	<h1>ws_scores Results:</h1>
		<dl>
			<dt>version</dt>
			  <dd>Data version.</dd>
			<dt>scores</dt>
			  <dd>The array of scores. scores are always sorted in "from bigger to smaller" order. There is no way to change sorting order in the current version of the API.</dd>
			<dt>count</dt>
			  <dd>The total number of players satisfying the request parameters.</dd>
			<dt>error</dt>
			  <dd>Error message when an error occurs.</dd>
		</dl>
	<h2>Score:</h2>
		<dl>
			<dt>num</dt>
			  <dd>Number in the current list.</dd>
			<dt>id</dt>
			  <dd>User id. Unique user identifier.</dd>
			<dt>name</dt>
			  <dd>User name.</dd>
			<dt>image</dt>
			  <dd>A link to the user image at mafiaratings.com. Not set when the image is not uploaded by the user.</dd>
			<dt>icon</dt>
			  <dd>A link to the user icon at mafiaratings.com. Not set when the icon is not uploaded by the user.</dd>
			<dt>points</dt>
			  <dd>The score.</dd>
			<dt>num_games</dt>
			  <dd>Number of games played by the player.</dd>
			<dt>games_won</dt>
			  <dd>Number of games won by the player.</dd>
			<dt>male</dt>
			  <dd>True for males; not set for females.</dd>
			<dt>club_id</dt>
			  <dd>This player's main club id.</dd>
		</dl>
	<br><br>
<?php		
	}
	else
	{
		initiate_session();
		
		$scoring = 0;
		if (isset($_REQUEST['scoring']))
		{
			$scoring = (int)$_REQUEST['scoring'];
		}
		
		if (isset($_REQUEST['contains']))
		{
			$contains = $_REQUEST['contains'];
		}
		
		$starts = '';
		if (isset($_REQUEST['starts']))
		{
			$starts = $_REQUEST['starts'];
		}
		
		$club = 0;
		if (isset($_REQUEST['club']))
		{
			$club = (int)$_REQUEST['club'];
		}
		
		$game = 0;
		if (isset($_REQUEST['game']))
		{
			$game = (int)$_REQUEST['game'];
		}
		
		$event = 0;
		if (isset($_REQUEST['event']))
		{
			$event = (int)$_REQUEST['event'];
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
		
		$with_user = 0;
		if (isset($_REQUEST['with_user']))
		{
			$with_user = (int)$_REQUEST['with_user'];
		}
		
		$langs = LANG_ALL;
		if (isset($_REQUEST['langs']))
		{
			$langs = (int)$_REQUEST['langs'];
		}

		$role = POINTS_ALL;
		if (isset($_REQUEST['role']))
		{
			$role = $_REQUEST['role'];
			switch($role)
			{
				case 'r';
					$role = POINTS_RED;
					break;
				case 'b';
					$role = POINTS_DARK;
					break;
				case 'c';
					$role = POINTS_CIVIL;
					break;
				case 's';
					$role = POINTS_SHERIFF;
					break;
				case 'm';
					$role = POINTS_MAFIA;
					break;
				case 'd';
					$role = POINTS_DON;
					break;
			}
		}
		
		$number = 0;
		if (isset($_REQUEST['number']))
		{
			$number = (int)$_REQUEST['number'];
		}
		
		$before = 0;
		if (isset($_REQUEST['before']))
		{
			$before = (int)$_REQUEST['before'];
		}
		
		$after = 0;
		if (isset($_REQUEST['after']))
		{
			$after = (int)$_REQUEST['after'];
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
		
		if ($scoring <= 0)
		{
			if ($game > 0)
			{
				list($scoring) = Db::record('scoring', 'SELECT e.scoring_id FROM games g JOIN events e ON g.event_id = e.id WHERE g.id = ?', $game);
			}
			else if ($event > 0)
			{
				list($scoring) = Db::record('scoring', 'SELECT scoring_id FROM events WHERE id = ?', $event);
			}
			else if ($address > 0)
			{
				list($scoring) = Db::record('scoring', 'SELECT c.scoring_id FROM addresses a JOIN clubs c ON a.club_id = c.id WHERE a.id = ?', $address);
			}
			else if ($club > 0)
			{
				list($scoring) = Db::record('scoring', 'SELECT scoring_id FROM clubs WHERE id = ?', $club);
			}
			else
			{
				$scoring = SCORING_DEFAULT_ID;
			}
		}
		$result->scoring_id = (int)$scoring;
		
		$condition = new SQL(' AND (u.flags & ' . U_FLAG_BANNED . ') = 0 AND u.games > 0');
		$result->role = $role;
		
		if (isset($contains))
		{
			$condition->add(' AND u.name LIKE(?)', '%' . $contains . '%');
		}
		
		if (isset($starts))
		{
			$condition->add(' AND (u.name LIKE(?) OR u.name LIKE(?))', $starts . '%', '% ' . $starts . '%');
		}
		
		if ($number > 0)
		{
			$condition->add(' AND p.number = ?', $number);
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
			$condition->add(' AND g.id IN (SELECT g1.id FROM games g1 JOIN events e1 ON g1.event_id = e1.id WHERE e1.address_id = ?)', $address);
		}
		else if ($city > 0)
		{
			$condition->add(' AND g.id IN (SELECT g1.id FROM games g1 JOIN events e1 ON g1.event_id = e1.id JOIN addresses a1 ON e1.address_id = a1.id WHERE a1.city_id = ?)', $city);
		}
		else if ($area > 0)
		{
			$condition->add(' AND g.id IN (SELECT g1.id FROM games g1 JOIN events e1 ON g1.event_id = e1.id JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.area_id = (SELECT area_id FROM cities WHERE id = ?))', $area);
		}
		else if ($country > 0)
		{
			$condition->add(' AND g.id IN (SELECT g1.id FROM games g1 JOIN events e1 ON g1.event_id = e1.id JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.country_id = ?)', $country);
		}
		
		if ($with_user > 0)
		{
			$condition->add(' AND g.id IN (SELECT game_id FROM players WHERE user_id = ?)', $with_user);
		}
		
		if ($before > 0)
		{
			$condition->add(' AND g.start_time < ?', $before);
		}
		
		if ($after > 0)
		{
			$condition->add(' AND g.start_time >= ?', $after);
		}
			
		if ($langs != LANG_ALL)
		{
			$condition->add(' AND (g.language & ?) <> 0', $langs);
		}
			
		$scoring_system = new ScoringSystem($scoring);
		$scores = new Scores($scoring_system, $condition, get_roles_condition($role));
		$players_count = count($scores->players);
		
		$result->count = $players_count;
		if (!$count_only)
		{
			$result->scores = array();
			if ($page_size > 0)
			{
				$page_start = $page * $page_size;
				if ($players_count > $page_start + $page_size)
				{
					$players_count = $page_start + $page_size;
				}
			}
			else
			{
				$page_start = 0;
			}
			for ($number = $page_start; $number < $players_count; ++$number)
			{
				$result->scores[] = new WSScore($scores->players[$number], $number + 1);
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