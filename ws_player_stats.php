<?php

require_once 'include/session.php';
require_once 'include/languages.php';
require_once 'include/game_state.php';
require_once 'include/scoring.php';

define('CURRENT_VERSION', 0);

class WSResult
{
	public $version;
	
	function __construct()
	{
		$this->version = CURRENT_VERSION;
	}
	
	function init($row)
	{
		list ($games, $won, $rating, $best_player, $best_move, $guess_all_maf, $warinigs, $voted_civ, $voted_maf, $voted_sheriff, $voted_by_civ, $voted_by_maf, $voted_by_sheriff, $nominated_civ, $nominated_maf, $nominated_sheriff, $nominated_by_civ, $nominated_by_maf, $nominated_by_sheriff, $arranged, $arranged_1_night, $checked_by_don, $checked_by_sheriff) = $row;
		$this->games = (int)$games;
		$this->won = (int)$won;
		$this->rating = (float)$rating;
		$this->best_player = (int)$best_player;
		$this->best_move = (int)$best_move;
		$this->guess_all_maf = (int)$guess_all_maf;
		$this->warinigs = (int)$warinigs;
		$this->voted_civ = (int)$voted_civ;
		$this->voted_maf = (int)$voted_maf;
		$this->voted_sheriff = (int)$voted_sheriff;
		$this->voted_by_civ = (int)$voted_by_civ;
		$this->voted_by_maf = (int)$voted_by_maf;
		$this->voted_by_sheriff = (int)$voted_by_sheriff;
		$this->nominated_civ = (int)$nominated_civ;
		$this->nominated_maf = (int)$nominated_maf;
		$this->nominated_sheriff = (int)$nominated_sheriff;
		$this->nominated_by_civ = (int)$nominated_by_civ;
		$this->nominated_by_maf = (int)$nominated_by_maf;
		$this->nominated_by_sheriff = (int)$nominated_by_sheriff;
		$this->arranged = (int)$arranged;
		$this->arranged_1_night = (int)$arranged_1_night;
		$this->checked_by_don = (int)$checked_by_don;
		$this->checked_by_sheriff = (int)$checked_by_sheriff;
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
		<dt>user</dt>
			<dd>User id. This is a mandatory parameter. For example: <a href="ws_player_stats.php?user=25">ws_player_stats.php?user=25</a> returns stats for Fantomas.</dd>
		<dt>before</dt>
			<dd>Unix timestamp for the end of a period. For example: <a href="ws_player_stats.php?user=25&before=1483228800">ws_player_stats.php?user=25&before=1483228800</a> returns Fantomas stats in the games played before 2017.</dd>
		<dt>after</dt>
			<dd>Unix timestamp for the beginning of a period. For example: <a href="ws_player_stats.php?user=25&after=1483228800">ws_player_stats.php?user=25&after=1483228800</a> returns Fantomas stats in the games starting from January 1 2017; <a href="ws_player_stats.php?user=25&after=1483228800&before=1485907200">ws_player_stats.php?user=25&after=1483228800&before=1485907200</a> returns Fantomas stats in the games played in January 2017. (If the game ended in February but started in January it is still a January game).</dd>
		<dt>club</dt>
			<dd>Club id. For example: <a href="ws_player_stats.php?user=25&club=1">ws_player_stats.php?user=25&club=1</a> returns Fantomas stats in the games played in Vancouver Mafia Club.</dd>
		<dt>game</dt>
			<dd>Game id. For example: <a href="ws_player_stats.php?user=25&game=1299">ws_player_stats.php?user=25&game=1299</a> returns Fantomas statis in the game 1299 played in VaWaCa tournament.</dd>
		<dt>event</dt>
			<dd>Event id. For example: <a href="ws_player_stats.php?user=25&event=7927">ws_player_stats.php?user=25&event=7927</a> returns Fantomas stats in the VaWaCa tournament.</dd>
		<dt>address</dt>
			<dd>Address id. For example: <a href="ws_player_stats.php?user=25&address=10">ws_player_stats.php?user=25&address=10</a> returns Fantomas stats in the games played in Tafs Cafe by Vancouver Mafia Club.</dd>
		<dt>city</dt>
			<dd>City id. For example: <a href="ws_player_stats.php?user=25&city=49">ws_player_stats.php?user=25&city=49</a> returns Fantomas stats in the games played in Seattle. List of the cities and their ids can be obtained using <a href="ws_cities.php?help">ws_cities.php</a>.</dd>
		<dt>area</dt>
			<dd>City id. The difference with city is that when area is set, the games from all nearby cities are also returned. For example: <a href="ws_player_stats.php?user=25&area=1">ws_player_stats.php?user=25&area=1</a> returns Fantomas stats in the games played in Vancouver and nearby cities. Though <a href="ws_player_stats.php?user=25&city=1">ws_player_stats.php?user=25&city=1</a> returns Fantomas stats in the games played only in Vancouver itself.</dd>
		<dt>country</dt>
			<dd>Country id. For example: <a href="ws_player_stats.php?user=25&country=2">ws_player_stats.php?user=25&country=2</a> returns Fantomas stats in the games played in Russia. List of the countries and their ids can be obtained using <a href="ws_countries.php?help">ws_countries.php</a>.</dd>
		<dt>with_user</dt>
			<dd>User id. For example: <a href="ws_player_stats.php?user=25&with_user=4">ws_player_stats.php?user=25&with_user=4</a> returns Fantomas stats in the games that he played with lilya.</dd>
		<dt>langs</dt>
			<dd>Languages filter. 1 for English; 2 for Russian. Bit combination - 3 - means both (this is a default value). For example: <a href="ws_player_stats.php?user=25&langs=1">ws_player_stats.php?user=25&langs=1</a> returns Fantomas stats in the games played in English.</dd>
		<dt>role</dt>
			<dd>Player stats in the specified role only. Possible values are:
				<ul>
					<li>a - all roles (default)</li>
					<li>r - red roles = civilian + sheriff: <a href="ws_player_stats.php?user=25&role=r">ws_player_stats.php?user=25&role=r</a></li>
					<li>b - black roles = mafia + don: <a href="ws_player_stats.php?user=25&role=b">ws_player_stats.php?user=25&role=b</a></li>
					<li>c - civilian but not the sheriff: <a href="ws_player_stats.php?user=25&role=c">ws_player_stats.php?user=25&role=c</a></li>
					<li>s - sheriff: <a href="ws_player_stats.php?user=25&role=s">ws_player_stats.php?user=25&role=s</a></li>
					<li>m - mafia but not the don: <a href="ws_player_stats.php?user=25&role=m">ws_player_stats.php?user=25&role=m</a></li>
					<li>d - don: <a href="ws_player_stats.php?user=25&role=d">ws_player_stats.php?user=25&role=d</a></li>
				</ul>
			</dd>
		<dt>number</dt>
			<dd>Number in the game (1-10). For example: <a href="ws_player_stats.php?user=25&number=2">ws_player_stats.php?user=25&number=2</a> returns Fantomas stats earned in the VaWaCa tournement when he was number 2.</dd>
		</dl>	
	<h1>Results:</h1>
		<dt>version</dt>
		  <dd>Data version.</dd>
		<br><br>
<?php		
	}
	else
	{
		initiate_session();
		
		$raw = isset($_REQUEST['raw']);
		
		$user = 0;
		if (isset($_REQUEST['user']))
		{
			$user = (int)$_REQUEST['user'];
		}
		else
		{
			throw new Exc('Please specify the user id. For example ws_player_stats.php?user=25.');
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
		
		$club = 0;
		if (isset($_REQUEST['club']))
		{
			$club = (int)$_REQUEST['club'];
		}
		
		$address = 0;
		if (isset($_REQUEST['address']))
		{
			$address = (int)$_REQUEST['address'];
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
		
		$with_user = 0;
		if (isset($_REQUEST['with_user']))
		{
			$with_user = (int)$_REQUEST['with_user'];
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
		
		$query = new DbQuery(
			'SELECT COUNT(*), SUM(p.won), SUM(p.rating_earned), SUM(IF((p.flags & ' . SCORING_BEST_PLAYER . ') <> 0, 1, 0)),' .
			' SUM(IF((p.flags & ' . SCORING_BEST_MOVE . ') <> 0, 1, 0)), SUM(IF((p.flags & ' . SCORING_GUESS_ALL_MAF . ') <> 0, 1, 0)), SUM(p.warns),' .
			' SUM(p.voted_civil), SUM(p.voted_mafia), SUM(p.voted_sheriff), SUM(p.voted_by_civil), SUM(p.voted_by_mafia), SUM(p.voted_by_sheriff),' .
			' SUM(p.nominated_civil), SUM(p.nominated_mafia), SUM(p.nominated_sheriff), SUM(p.nominated_by_civil), SUM(p.nominated_by_mafia), SUM(p.nominated_by_sheriff),' .
			' SUM(IF(p.was_arranged < 0, 0, 1)), SUM(IF(p.was_arranged <> 0, 0, 1)), SUM(IF(p.checked_by_don < 0, 0, 1)), SUM(IF(p.checked_by_sheriff < 0, 0, 1))' .
			' FROM players p JOIN games g ON  p.game_id = g.id WHERE g.result IN(1,2) AND p.user_id = ?', $user);
		$query->add(get_roles_condition($role));
		
		if ($before > 0)
		{
			$query->add(' AND g.start_time < ?', $before);
		}

		if ($after > 0)
		{
			$query->add(' AND g.start_time >= ?', $after);
		}

		if ($club > 0)
		{
			$query->add(' AND g.club_id = ?', $club);
		}

		if ($game > 0)
		{
			$query->add(' AND g.id = ?', $game);
		}
		else if ($event > 0)
		{
			$query->add(' AND g.event_id = ?', $event);
		}
		else if ($address > 0)
		{
			$query->add(' AND g.event_id IN (SELECT id FROM events WHERE address_id = ?)', $address);
		}
		else if ($city > 0)
		{
			$query->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id WHERE a1.city_id = ?)', $city);
		}
		else if ($area > 0)
		{
			$query->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.area_id = (SELECT area_id FROM cities WHERE id = ?))', $area);
		}
		else if ($country > 0)
		{
			$query->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.country_id = ?)', $country);
		}
		
		if ($langs != LANG_ALL)
		{
			$query->add(' AND (g.language & ?) <> 0', $langs);
		}
		
		if ($with_user > 0)
		{
			$query->add(' AND g.id IN (SELECT game_id FROM players WHERE user_id = ?)', $with_user);
		}
		
		if ($number > 0)
		{
			$query->add(' AND p.number = ?', $number);
		}

		if ($row = $query->next())
		{
			$result->init($row);
		}
		else
		{
			throw new Exc('User ' . $user . ' not found.');
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