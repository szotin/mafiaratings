<?php

require_once 'include/session.php';
require_once 'include/ws.php';
require_once 'include/scoring.php';

define('CURRENT_VERSION', 0);

class WSRating
{
	public $num;
	public $pos;
	public $user_id;
	public $user_name;
	public $rating;
	public $num_games;
	public $games_won;
	public $club_id;
	public $club_name;
	
	function __construct($row, $num)
	{
		list($this->user_id, $this->user_name, $user_flags, $this->pos, $this->rating, $this->num_games, $this->games_won, $this->club_id, $this->club_name) = $row;
		$this->num = (int)$num;
		$this->pos = (int)$this->pos;
		$this->user_id = (int)$this->user_id;
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
			$this->user_image = USER_PICS_DIR . TNAILS_DIR . $this->user_id . '.png?' . (($user_flags & U_ICON_MASK) >> U_ICON_MASK_OFFSET);
			$this->user_icon = USER_PICS_DIR . ICONS_DIR . $this->user_id . '.png?' . (($user_flags & U_ICON_MASK) >> U_ICON_MASK_OFFSET);
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
		  <dt>role</dt>
			<dd>The rating for the specified role only. Possible values are:<ul>
				<li>a - all roles (default)</li>
				<li>r - red roles = civilian + sheriff: <a href="ws_ratings.php?role=r">ws_ratings.php?role=r</a></li>
				<li>b - black roles = mafia + don: <a href="ws_ratings.php?role=b">ws_ratings.php?role=b</a></li>
				<li>c - civilian but not the sheriff: <a href="ws_ratings.php?role=c">ws_ratings.php?role=c</a></li>
				<li>s - sheriff: <a href="ws_ratings.php?role=s">ws_ratings.php?role=s</a></li>
				<li>m - mafia but not the don: <a href="ws_ratings.php?role=m">ws_ratings.php?role=m</a></li>
				<li>d - don: <a href="ws_ratings.php?role=d">ws_ratings.php?role=d</a></li>
				</ul>
			</dd>
		  <dt>club</dt>
			<dd>Club id. For example: <a href="ws_ratings.php?club=1">ws_ratings.php?club=1</a> returns ratings for all players of Vancouver Mafia Club. If missing, all players for all clubs are returned.</dd>
		  <dt>event</dt>
			<dd>Event id. For example: <a href="ws_ratings.php?event=7927">ws_ratings.php?event=7927</a> returns ratings for all players participated in VaWaCa tournament. If missing, all players for all events are returned.</dd>
		  <dt>game</dt>
			<dd>Game id. For example: <a href="ws_ratings.php?game=1299">ws_ratings.php?game=1299</a> returns ratings for all players participated in the game 1299, played in VaWaCa tournament.</dd>
		  <dt>count</dt>
			<dd>Returns game count instead of players list. For example: <a href="ws_ratings.php?club=1&count">ws_ratings.php?club=1&count</a> returns how many players with ratings are there in Vancouver Mafia Club; <a href="ws_ratings.php?event=7927&count">ws_ratings.php?event=7927&count</a> returns how many players with ratings participated in VaWaCa tournament.</dd>
		  <dt>page</dt>
			<dd>Page number. For example: <a href="ws_ratings.php?club=1&page=1">v.php?club=1&page=1</a> returns the second page of ratings for Vancouver Mafia Club players.</dd>
		  <dt>page_size</dt>
			<dd>Page size. Default page_size is 16. For example: <a href="ws_ratings.php?club=1&page_size=32">ws_ratings.php?club=1&page_size=32</a> returns top 32 players for Vancouver Mafia Club; <a href="ws_ratings.php?club=6&page_size=0">ws_ratings.php?club=6&page_size=0</a> returns all players for Empire of Mafia club in one page; <a href="ws_ratings.php?club=1">ws_ratings.php?club=1</a> returns top 16 players for Vancouver Mafia Club;</dd>
		</dl>	
	<h1>Results:</h1>
		<dt>version</dt>
		  <dd>Data version.</dd>
		<dt>ratings</dt>
		  <dd>The array of ratings. Ratings are always sorted in "from bigger to smaller" order. There is no way to change sorting order in the current version of the API.</dd>
		<dt>count</dt>
		  <dd>The total number of players satisfying the request parameters.</dd>
		<dt>error</dt>
		  <dd>Error message when an error occurs.</dd>
	<h2>Rating parameters:</h2>
		<dt>num</dt>
		  <dd>Number in the current list.</dd>
		<dt>pos</dt>
		  <dd>Position in the global rating.</dd>
		<dt>user_id</dt>
		  <dd>User id. Unique user identifier.</dd>
		<dt>user_name</dt>
		  <dd>Any questions?</dd>
		<dt>user_image</dt>
		  <dd>A link to the user image at mafiaratings.com. Not set when the image is not uploaded by the user.</dd>
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
	<br><br>
<?php		
	}
	else
	{
		initiate_session();
		
		$role = POINTS_ALL;
		if (isset($_REQUEST['role']))
		{
			$role = $_REQUEST['role'];
			switch($role)
			{
				case 'a';
					$role = POINTS_ALL;
					break;
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
		
		$condition = new SQL(' WHERE (u.flags & ' . U_FLAG_BANNED . ') = 0 AND u.games > 0');
		
		if ($club > 0)
		{
			$condition->add(' AND u.club_id = ?', $club);
		}
		
		if ($event > 0)
		{
			$condition->add(' AND u.id IN (SELECT user_id FROM registrations WHERE event_id = ?)', $event);
		}
		
		if ($game > 0)
		{
			$condition->add(' AND u.id IN (SELECT user_id FROM players WHERE game_id = ?)', $game);
		}
		
		if ($role == POINTS_ALL)
		{
			$query = new DbQuery(
				'SELECT u.id, u.name, u.flags, (SELECT count(*) FROM users u1 WHERE u1.rating >= u.rating) as pos, u.rating as rating, u.games as games, u.games_won as won, c.id, c.name FROM users u' . 
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id', $condition);
			$count_query = new DbQuery('SELECT count(*) FROM users u', $condition);	
		}
		else
		{
			$condition->add(get_roles_condition($role));
			$query = new DbQuery(
				'SELECT u.id, u.name, u.flags, (SELECT count(*) FROM users u1 WHERE u1.rating >= u.rating) as pos, ' . USER_INITIAL_RATING . ' + SUM(p.rating_earned) as rating, count(*) as games, SUM(p.won) as won, c.id, c.name FROM users u' . 
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
				' JOIN players p ON p.user_id = u.id', $condition);
			$query->add(' GROUP BY u.id ');
			$count_query = new DbQuery('SELECT count(DISTINCT u.id) FROM users u JOIN players p ON p.user_id = u.id', $condition);
		}
		
		$num = 0;
		if ($page_size > 0)
		{
			$num = $page * $page_size;
			$query->add(' ORDER BY rating DESC, games, won DESC  LIMIT ' . $num . ',' . $page_size);
		}
		
		list($count) = Db::record('rating', $count_query);
		$result->count = $count;
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