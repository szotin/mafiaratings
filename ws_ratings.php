<?php

require_once 'include/session.php';
require_once 'include/ws.php';
require_once 'include/scoring.php';

define('CURRENT_VERSION', 0);

class WSRating
{
	public $num;
	public $pos;
	public $id;
	public $name;
	public $languages;
	public $rating;
	public $num_games;
	public $games_won;
	public $club_id;
	public $club_name;
	
	function __construct($row, $num)
	{
		list($this->id, $this->name, $user_flags, $this->languages, $this->pos, $this->rating, $this->num_games, $this->games_won, $this->club_id, $this->club_name) = $row;
		$this->num = (int)$num;
		$this->pos = (int)$this->pos;
		$this->id = (int)$this->id;
		$this->languages = (int)$this->languages;
		$this->rating = (float)$this->rating;
		$this->num_games = (int)$this->num_games;
		$this->games_won = (int)$this->games_won;
		$this->club_id = (int)$this->club_id;
		if (($user_flags & U_FLAG_MALE) != 0)
		{
			$this->male = true;
		}
		if (($user_flags & U_ICON_MASK) != 0)
		{
			$this->image = USER_PICS_DIR . TNAILS_DIR . $this->id . '.png?' . (($user_flags & U_ICON_MASK) >> U_ICON_MASK_OFFSET);
			$this->icon = USER_PICS_DIR . ICONS_DIR . $this->id . '.png?' . (($user_flags & U_ICON_MASK) >> U_ICON_MASK_OFFSET);
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
			<dt>club</dt>
				<dd>Club id. For example: <a href="ws_ratings.php?club=1">ws_ratings.php?club=1</a> returns ratings for all players of Vancouver Mafia Club. If missing, all players for all clubs are returned.</dd>
			<dt>club_members</dt>
				<dd>Club id. For example: <a href="ws_ratings.php?club_members=42">ws_ratings.php?club_members=42</a> returns ratings for all members of Seattle Mafia Club. The difference with the "club" parameter is that "club" returns only the players who consider Seattle Mafia Club as their main club. Though "club_members" returns all members even if their main club is different. Note that Fantomas and Tigra are returned in the sample request, though their main club is Vancouver.</dd>
			<dt>game</dt>
				<dd>Game id. For example: <a href="ws_ratings.php?game=1299">ws_ratings.php?game=1299</a> returns ratings for all players participated in the game 1299, played in VaWaCa tournament.</dd>
			<dt>event</dt>
				<dd>Event id. For example: <a href="ws_ratings.php?event=7927">ws_ratings.php?event=7927</a> returns ratings for all players participated in VaWaCa tournament. If missing, all players for all events are returned.</dd>
			<dt>address</dt>
				<dd>Address id. For example: <a href="ws_ratings.php?address=10">ws_ratings.php?address=10</a> returns ratings for all players who played in Tafs Cafe in Vancouver Mafia Club.</dd>
			<dt>city</dt>
				<dd>City id. For example: <a href="ws_ratings.php?city=49">ws_ratings.php?city=49</a> returns ratings for all players from Seattle. List of the cities and their ids can be obtained using <a href="ws_cities.php?help">ws_cities.php</a>.</dd>
			<dt>area</dt>
				<dd>City id. The difference with city is that when area is set, the games from all nearby cities are also returned. For example: <a href="ws_ratings.php?area=1">ws_ratings.php?area=1</a> returns all players from Vancouver and nearby cities like Delta, Burnaby, Surrey, etc. Though <a href="ws_ratings.php?city=1">ws_ratings.php?city=1</a> returns only the players from Vancouver itself.</dd>
			<dt>country</dt>
				<dd>Country id. For example: <a href="ws_ratings.php?country=2">ws_ratings.php?country=2</a> returns all players from Russia. List of the countries and their ids can be obtained using <a href="ws_countries.php?help">ws_countries.php</a>.</dd>
			<dt>user</dt>
				<dd>User id. For example: <a href="ws_ratings.php?user=4">ws_ratings.php?user=4</a> returns all players who played with lilya at least once.</dd>
			<dt>langs</dt>
				<dd>Languages filter. 1 for English; 2 for Russian. Bit combination - 3 - means both (this is a default value). For example: <a href="ws_ratings.php?langs=1">ws_ratings.php?langs=1</a> returns ratings for players who speak English; <a href="ws_ratings.php?club=1&langs=3">ws_ratings.php?club=1&langs=3</a> returns ratings for players who can speak English and Russian.</dd>
			<dt>in_role</dt>
				<dd>The rating for the specified role only. Possible values are:
					<ul>
						<li>a - all roles (default)</li>
						<li>r - red roles = civilian + sheriff: <a href="ws_ratings.php?in_role=r">ws_ratings.php?in_role=r</a></li>
						<li>b - black roles = mafia + don: <a href="ws_ratings.php?in_role=b">ws_ratings.php?in_role=b</a></li>
						<li>c - civilian but not the sheriff: <a href="ws_ratings.php?in_role=c">ws_ratings.php?in_role=c</a></li>
						<li>s - sheriff: <a href="ws_ratings.php?in_role=s">ws_ratings.php?in_role=s</a></li>
						<li>m - mafia but not the don: <a href="ws_ratings.php?in_role=m">ws_ratings.php?in_role=m</a></li>
						<li>d - don: <a href="ws_ratings.php?in_role=d">ws_ratings.php?in_role=d</a></li>
					</ul>
					If any of the parameters staring with "in_" is set, the rating calculation is changed. For example in_role shows only rating earned in a specific roles; in_country - only the rating earned in a specific country; etc.
				</dd>
			<dt>in_club</dt>
				<dd>Club id. For example: <a href="ws_ratings.php?in_club=1">ws_ratings.php?in_club=1</a> returns only rating points earned in Vancouver Mafia Club. <a href="ws_ratings.php?in_club=1&club=42">ws_ratings.php?in_club=1&club=42</a> returns rating points of Seattle club players earned in Vancouver.</dd>
			<dt>in_game</dt>
				<dd>Game id. For example: <a href="ws_ratings.php?in_game=1299">ws_ratings.php?in_game=1299</a> returns rating points earned in the game 1299, played in VaWaCa tournament.</dd>
			<dt>in_event</dt>
				<dd>Event id. For example: <a href="ws_ratings.php?in_event=7927">ws_ratings.php?in_event=7927</a> returns ratings points earned in VaWaCa tournament.</dd>
			<dt>in_address</dt>
				<dd>Address id. For example: <a href="ws_ratings.php?in_address=10">ws_ratings.php?in_address=10</a> returns rating points earned in Tafs Cafe in Vancouver Mafia Club.</dd>
			<dt>in_city</dt>
				<dd>City id. For example: <a href="ws_ratings.php?in_city=49">ws_ratings.php?in_city=49</a> returns rating points earned in Seattle. List of the cities and their ids can be obtained using <a href="ws_cities.php?help">ws_cities.php</a>.</dd>
			<dt>in_area</dt>
				<dd>City id. The difference with city is that when area is set, the games from all nearby cities are also returned. For example: <a href="ws_ratings.php?in_area=1">ws_ratings.php?in_area=1</a> returns all rating points earned in Vancouver and nearby cities like Delta, Burnaby, Surrey, etc. Though <a href="ws_ratings.php?in_city=1">ws_ratings.php?in_city=1</a> returns only rating points earned in Vancouver itself.</dd>
			<dt>in_country</dt>
				<dd>Country id. For example: <a href="ws_ratings.php?in_country=2">ws_ratings.php?in_country=2</a> returns rating points earned in Russia. List of the countries and their ids can be obtained using <a href="ws_countries.php?help">ws_countries.php</a>.</dd>
			<dt>in_user</dt>
				<dd>User id. For example: <a href="ws_ratings.php?in_user=4">ws_ratings.php?in_user=4</a> returns rating points in the games where lilya played.</dd>
			<dt>in_langs</dt>
				<dd>Languages filter. 1 for English; 2 for Russian. Bit combination - 3 - means both (this is a default value). For example: <a href="ws_ratings.php?in_langs=1">ws_ratings.php?in_langs=1</a> returns rating points earned in games played in English; <a href="ws_ratings.php?club=1&in_langs=3">ws_ratings.php?club=1&in_langs=3</a> returns rating points earned in English and Russian games by the players of Vancouver club.</dd>
			<dt>in_before</dt>
				<dd>Unix timestamp. For example: <a href="ws_ratings.php?in_before=1483228800">ws_ratings.php?in_before=1483228800</a> returns ratings earned before 2017. In other words it returns ratings as they were at the end of 2016.</dd>
			<dt>in_after</dt>
				<dd>Unix timestamp. For example: <a href="ws_ratings.php?in_after=1483228800">ws_ratings.php?in_after=1483228800</a> returns ratings earned after January 1, 2017; <a href="ws_ratings.php?in_after=1483228800&in_before=1485907200">ws_ratings.php?in_after=1483228800&in_before=1485907200</a> returns ratings earned in January 2017</dd>
			<dt>count</dt>
				<dd>Returns game count instead of players list. For example: <a href="ws_ratings.php?club=1&count">ws_ratings.php?club=1&count</a> returns how many players with ratings are there in Vancouver Mafia Club; <a href="ws_ratings.php?event=7927&count">ws_ratings.php?event=7927&count</a> returns how many players with ratings participated in VaWaCa tournament.</dd>
			<dt>page</dt>
				<dd>Page number. For example: <a href="ws_ratings.php?club=1&page=1">v.php?club=1&page=1</a> returns the second page of ratings for Vancouver Mafia Club players.</dd>
			<dt>page_size</dt>
				<dd>Page size. Default page_size is 16. For example: <a href="ws_ratings.php?club=1&page_size=32">ws_ratings.php?club=1&page_size=32</a> returns top 32 players for Vancouver Mafia Club; <a href="ws_ratings.php?club=6&page_size=0">ws_ratings.php?club=6&page_size=0</a> returns all players for Empire of Mafia club in one page; <a href="ws_ratings.php?club=1">ws_ratings.php?club=1</a> returns top 16 players for Vancouver Mafia Club;</dd>
		</dl>	
	<h1>Results:</h1>
		<dl>
			<dt>version</dt>
			  <dd>Data version.</dd>
			<dt>ratings</dt>
			  <dd>The array of ratings. Ratings are always sorted in "from bigger to smaller" order. There is no way to change sorting order in the current version of the API.</dd>
			<dt>count</dt>
			  <dd>The total number of players satisfying the request parameters.</dd>
			<dt>error</dt>
			  <dd>Error message when an error occurs.</dd>
		</dl>
	<h2>Rating parameters:</h2>
		<dl>
			<dt>num</dt>
			  <dd>Number in the current list.</dd>
			<dt>pos</dt>
			  <dd>Position in the global rating.</dd>
			<dt>id</dt>
			  <dd>User id. Unique user identifier.</dd>
			<dt>name</dt>
			  <dd>User name.</dd>
			<dt>image</dt>
			  <dd>A link to the user image at mafiaratings.com. Not set when the image is not uploaded by the user.</dd>
			<dt>icon</dt>
			  <dd>A link to the user icon at mafiaratings.com. Not set when the icon is not uploaded by the user.</dd>
			<dt>rating</dt>
			  <dd>The Elo rating.</dd>
			<dt>num_games</dt>
			  <dd>Number of games played by the player.</dd>
			<dt>games_won</dt>
			  <dd>Number of games won by the player.</dd>
			<dt>male</dt>
			  <dd>True for males; not set for females.</dd>
			<dt>club_id</dt>
			  <dd>This player's main club id.</dd>
			<dt>club_name</dt>
			  <dd>This player's main club name.</dd>
		</dl>
	<br><br>
<?php		
	}
	else
	{
		initiate_session();
		
		$club = 0;
		if (isset($_REQUEST['club']))
		{
			$club = (int)$_REQUEST['club'];
		}
		
		$club_members = 0;
		if (isset($_REQUEST['club_members']))
		{
			$club_members = (int)$_REQUEST['club_members'];
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

		$in_set = false;
		$in_role = POINTS_ALL;
		if (isset($_REQUEST['in_role']))
		{
			$in_role = $_REQUEST['in_role'];
			$in_set = true;
			switch($in_role)
			{
				case 'r';
					$in_role = POINTS_RED;
					break;
				case 'b';
					$in_role = POINTS_DARK;
					break;
				case 'c';
					$in_role = POINTS_CIVIL;
					break;
				case 's';
					$in_role = POINTS_SHERIFF;
					break;
				case 'm';
					$in_role = POINTS_MAFIA;
					break;
				case 'd';
					$in_role = POINTS_DON;
					break;
				default:
					$in_set = false;
					break;
			}
		}
		
		$in_club = 0;
		if (isset($_REQUEST['in_club']))
		{
			$in_set = true;
			$in_club = (int)$_REQUEST['in_club'];
		}
		
		$in_game = 0;
		if (isset($_REQUEST['in_game']))
		{
			$in_set = true;
			$in_game = (int)$_REQUEST['in_game'];
		}
		
		$in_event = 0;
		if (isset($_REQUEST['in_event']))
		{
			$in_set = true;
			$in_event = (int)$_REQUEST['in_event'];
		}
		
		$in_address = 0;
		if (isset($_REQUEST['in_address']))
		{
			$in_set = true;
			$in_address = (int)$_REQUEST['in_address'];
		}
		
		$in_city = 0;
		if (isset($_REQUEST['in_city']))
		{
			$in_set = true;
			$in_city = (int)$_REQUEST['in_city'];
		}
		
		$in_area = 0;
		if (isset($_REQUEST['in_area']))
		{
			$in_set = true;
			$in_area = (int)$_REQUEST['in_area'];
		}
		
		$in_country = 0;
		if (isset($_REQUEST['in_country']))
		{
			$in_set = true;
			$in_country = (int)$_REQUEST['in_country'];
		}
		
		$in_user = 0;
		if (isset($_REQUEST['in_user']))
		{
			$in_set = true;
			$in_user = (int)$_REQUEST['in_user'];
		}
		
		$in_langs = 0;
		if (isset($_REQUEST['in_langs']))
		{
			$in_set = true;
			$in_langs = (int)$_REQUEST['in_langs'];
		}
		
		$in_before = 0;
		if (isset($_REQUEST['in_before']))
		{
			$in_set = true;
			$in_before = (int)$_REQUEST['in_before'];
		}
		
		$in_after = 0;
		if (isset($_REQUEST['in_after']))
		{
			$in_set = true;
			$in_after = (int)$_REQUEST['in_after'];
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
		
		$condition = new SQL(' WHERE (u.flags & ' . U_FLAG_BANNED . ') = 0 AND u.games > 0');
		
		if ($club > 0)
		{
			$condition->add(' AND u.club_id = ?', $club);
		}
		else if ($club_members > 0)
		{
			$condition->add(' AND u.id IN (SELECT user_id FROM user_clubs WHERE club_id = ?)', $club_members);
		}
		
		if ($game > 0)
		{
			$condition->add(' AND u.id IN (SELECT user_id FROM players WHERE game_id = ?)', $game);
		}
		else if ($event > 0)
		{
			$condition->add(' AND u.id IN (SELECT user_id FROM registrations WHERE event_id = ?)', $event);
		}
		else if ($address > 0)
		{
			$condition->add(' AND u.id IN (SELECT DISTINCT p1.user_id FROM players p1 JOIN games g1 ON p1.game_id = g1.id JOIN events e1 ON g1.event_id = e1.id WHERE e1.address_id = ?)', $address);
		}
		else if ($city > 0)
		{
			$condition->add(' AND u.city_id = ?', $city);
		}
		else if ($area > 0)
		{
			$query1 = new DbQuery('SELECT near_id FROM cities WHERE id = ?', $area);
			list($parent_city) = $query1->record('city');
			if ($parent_city == NULL)
			{
				$parent_city = $area;
			}
			$condition->add(' AND (u.city_id = ? OR u.city_id IN (SELECT id FROM cities WHERE near_id = ?))', $parent_city, $parent_city);
		}
		else if ($country > 0)
		{
			$condition->add(' AND u.city_id IN (SELECT id FROM cities WHERE country_id = ?)', $country);
		}
		
		if ($user > 0)
		{
			$condition->add(' AND u.id IN (SELECT DISTINCT p1.user_id FROM players p1 JOIN players p2 ON p1.game_id = p2.game_id WHERE p2.user_id = ?)', $user);
		}
		
		if ($langs != LANG_ALL)
		{
			$condition->add(' AND (u.languages & ?) <> 0', $langs);
		}
			
		if ($in_set)
		{
			$condition->add(get_roles_condition($in_role));
			if ($in_langs > 0)
			{
				$condition->add(' AND (g.language & ?) <> 0', $in_langs);
			}
			
			if ($in_before > 0)
			{
				$condition->add(' AND g.start_time < ?', $in_before);
			}
			
			if ($in_after > 0)
			{
				$condition->add(' AND g.start_time >= ?', $in_after);
			}
			
			if ($in_user > 0)
			{
				$condition->add(' AND g.id IN (SELECT game_id FROM players WHERE user_id = ?)', $in_user);
			}
			
			if ($in_game > 0)
			{
				$condition->add(' AND g.id = ?', $in_game);
			}
			else if ($in_event > 0)
			{
				$condition->add(' AND g.event_id = ?', $in_event);
			}
			else if ($in_address > 0)
			{
				$condition->add(' AND g.event_id IN (SELECT id FROM events WHERE address_id = ?)', $in_address);
			}
			else if ($in_club > 0)
			{
				$condition->add(' AND g.club_id = ?', $in_club);
			}
			else if ($in_city > 0)
			{
				$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id WHERE a1.city_id = ?)', $in_city);
			}
			else if ($in_area > 0)
			{
				if ($in_area != $area)
				{
					$query1 = new DbQuery('SELECT near_id FROM cities WHERE id = ?', $in_area);
					list($parent_city) = $query1->record('city');
					if ($parent_city == NULL)
					{
						$parent_city = $in_area;
					}
				}
				$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.id = ? OR c1.near_id = ?)', $parent_city, $parent_city);
			}
			else if ($in_country > 0)
			{
				$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.country_id = ?)', $in_country, $in_country);
			}
			
			$query = new DbQuery(
				'SELECT u.id, u.name, u.flags, u.languages, (SELECT count(*) FROM users u1 WHERE u1.rating >= u.rating) as pos, ' . USER_INITIAL_RATING . ' + SUM(p.rating_earned) as rating, count(*) as games, SUM(p.won) as won, c.id, c.name FROM users u' . 
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
				' JOIN players p ON p.user_id = u.id' .
				' JOIN games g ON p.game_id = g.id', $condition); 
			$query->add(' GROUP BY u.id ');
			$count_query = new DbQuery('SELECT count(DISTINCT u.id) FROM users u JOIN players p ON p.user_id = u.id JOIN games g ON p.game_id = g.id', $condition);
		}
		else
		{
			$query = new DbQuery(
				'SELECT u.id, u.name, u.flags, u.languages, (SELECT count(*) FROM users u1 WHERE u1.rating >= u.rating) as pos, u.rating as rating, u.games as games, u.games_won as won, c.id, c.name FROM users u' . 
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id', $condition);
			$count_query = new DbQuery('SELECT count(*) FROM users u', $condition);	
		}
		
		$num = 0;
		if ($page_size > 0)
		{
			$num = $page * $page_size;
			$query->add(' ORDER BY rating DESC, won DESC, games DESC  LIMIT ' . $num . ',' . $page_size);
		}
		
		list($count) = Db::record('rating', $count_query);
		$result->count = (int)$count;
		if (!$count_only)
		{
			$result->ratings = array();
			while ($row = $query->next())
			{
				$rating = new WSRating($row, ++$num);
				$result->ratings[] = $rating;
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