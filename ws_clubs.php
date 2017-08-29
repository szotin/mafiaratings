<?php

require_once 'include/session.php';
require_once 'include/user_location.php';

define('CURRENT_VERSION', 0);

class WSClub
{
	public $id;
	public $name;
	public $langs;
	public $city_id;
	public $city;
	public $country;
	public $rules_id;
	public $scoring_id;
	
	function __construct($row)
	{
		list ($this->id, $this->name, $this->langs, $web, $email, $phone, $this->city_id, $this->city, $this->country, $this->rules_id, $this->scoring_id) = $row;
		$this->id = (int)$this->id;
		if ($web != NULL)
		{
			$this->web_site = $web;
		}
		if ($email != NULL)
		{
			$this->email = $email;
		}
		if ($phone != NULL)
		{
			$this->phone = $web;
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
	<h1>ws_clubs Parameters:</h1>
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
				<dd>Search pattern. For example: <a href="ws_clubs.php?contains=co">ws_clubs.php?contains=co</a> returns clubs containing "co" in their name.</dd>
			<dt>starts</dt>
				<dd>Search pattern. For example: <a href="ws_clubs.php?starts=co">ws_cities.php?starts=co</a> returns clubs with names starting with "co".</dd>
			<dt>club</dt>
				<dd>Club id or club name. For example: <a href="ws_clubs.php?club=1">ws_clubs.php?club=1</a> returns information Vancouver Mafia Club.</dd>
			<dt>city</dt>
				<dd>City id. For example: <a href="ws_clubs.php?city=2">ws_clubs.php?city=2</a> returns all clubs from Moscow. List of the cities and their ids can be obtained using <a href="ws_cities.php?help">ws_cities.php</a>.</dd>
			<dt>area</dt>
				<dd>City id. The difference with city is that when area is set, the games from all nearby cities are also returned. For example: <a href="ws_clubs.php?area=2">ws_clubs.php?area=2</a> returns all clubs from Moscow and nearby cities like Podolsk, Himki, etc. Though <a href="ws_clubs.php?city=2">ws_clubs.php?city=2</a> returns only the clubs from Moscow itself.</dd>
			<dt>country</dt>
				<dd>Country id. For example: <a href="ws_clubs.php?country=2">ws_clubs.php?country=2</a> returns all clubs from Russia. List of the countries and their ids can be obtained using <a href="ws_countries.php?help">ws_countries.php</a>.</dd>
			<dt>user</dt>
				<dd>User id. For example: <a href="ws_clubs.php?user=25">ws_clubs.php?user=25</a> returns all clubs where Fantomas is a member.</dd>
			<dt>langs</dt>
				<dd>Languages filter. 1 for English; 2 for Russian. Bit combination - 3 - means both (this is a default value). For example: <a href="ws_clubs.php?langs=1">ws_clubs.php?langs=1</a> returns all clubs that support English as their language.</dd>
			<dt>count</dt>
				<dd>Returns clubs count instead of the clubs themselves. For example: <a href="ws_clubs.php?contains=an&count">ws_clubs.php?contains=an&count</a> returns how many clubs contain 'an' in their name.</dd>
			<dt>page</dt>
				<dd>Page number. For example: <a href="ws_clubs.php?page=1">ws_clubs.php?page=1</a> returns the second page of clubs by alphabet.</dd>
			<dt>page_size</dt>
				<dd>Page size. Default page_size is 16. For example: <a href="ws_clubs.php?page_size=32">ws_clubs.php?page_size=32</a> returns first 32 clubs; <a href="ws_clubs.php?page_size=0">ws_clubs.php?page_size=0</a> returns clubs in one page; <a href="ws_clubs.php">ws_clubs.php</a> returns first 16 clubs by alphabet.</dd>
		</dl>	
	<h1>ws_clubs Results:</h1>
		<dl>
			<dt>version</dt>
			  <dd>Data version.</dd>
			<dt>clubs</dt>
			  <dd>The array of clubs. Clubs are always sorted in alphabetical order. There is no way to change sorting order in the current version of the API.</dd>
			<dt>count</dt>
			  <dd>The total number of clubs satisfying the request parameters.</dd>
			<dt>error</dt>
			  <dd>Error message when an error occurs.</dd>
		</dl>
	<h2>Club:</h2>
		<dl>
			<dt>id</dt>
			  <dd>Club id.</dd>
			<dt>name</dt>
			  <dd>Club name.</dd>
			<dt>langs</dt>
			  <dd>Languages used in the club. A bit combination of: 1 - English; 2 - Russian.</dd>
			<dt>web_site</dt>
			  <dd>Subj. Not set if unknown.</dd>
			<dt>email</dt>
			  <dd>Subj. Not set if unknown.</dd>
			<dt>phone</dt>
			  <dd>Subj. Not set if unknown.</dd>
			<dt>city_id</dt>
			  <dd>City id</dd>
			<dt>city</dt>
			  <dd>City name using default language for the profile.</dd>
			<dt>country</dt>
			  <dd>Country name using default language for the profile.</dd>
			<dt>rules_id</dt>
			  <dd>Default rules used in the club.</dd>
			<dt>scoring_id</dt>
			  <dd>Default scoring used in the club.</dd>
		</dl>
	<br><br>
<?php		
	}
	else
	{
		initiate_session();
	
		$contains = '';
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
		
		$city = 0;
		if (isset($_REQUEST['city']))
		{
			$city = (int)$_REQUEST['city'];
		}
		
		$area = 0;
		if (isset($_REQUEST['area']))
		{
			$area = (int)$_REQUEST['area'];
		}
		
		$country = 0;
		if (isset($_REQUEST['country']))
		{
			$country = (int)$_REQUEST['country'];
		}
		
		$user = 0;
		if (isset($_REQUEST['user']))
		{
			$user = (int)$_REQUEST['user'];
		}
		
		$langs = 0;
		if (isset($_REQUEST['langs']))
		{
			$langs = (int)$_REQUEST['langs'];
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
		
		$condition = new SQL(' WHERE (c.flags & ?) = 0', CLUB_FLAG_RETIRED);
		if ($contains != '')
		{
			$contains = '%' . $contains . '%';
			$condition->add(' AND name LIKE(?)', $contains);
		}
		
		if ($starts != '')
		{
			$starts1 = '% ' . $starts . '%';
			$starts2 = $starts . '%';
			$condition->add(' AND (name LIKE(?) OR name LIKE(?))', $starts1, $starts2);
		}
		
		if ($club > 0)
		{
			$condition->add(' AND c.id = ?', $club);
		}
		else if ($city > 0)
		{
			$condition->add(' AND c.city_id = ?', $city);
		}
		else if ($area > 0)
		{
			$condition->add(' AND i.area_id = (SELECT area_id FROM cities WHERE id = ?)', $area);
		}
		else if ($country > 0)
		{
			$condition->add(' AND i.country_id = ?', $country);
		}
		
		if ($user > 0)
		{
			$condition->add(' AND c.id IN (SELECT club_id FROM user_clubs WHERE user_id = ?)', $user);
		}
		
		if ($langs > 0)
		{
			$condition->add(' AND (c.langs & ?) <> 0', $langs);
		}
		
		list($count) = Db::record('club', 'SELECT count(*) FROM clubs c JOIN cities i ON i.id = c.city_id', $condition);
		$result->count = (int)$count;
		if (!$count_only)
		{
			$query = new DbQuery(
				'SELECT c.id, c.name, c.langs, c.web_site, c.email, c.phone, c.city_id, i.name_' . $_lang_code . ', o.name_' . $_lang_code . ', rules_id, scoring_id FROM clubs c' . 
				' JOIN cities i ON i.id = c.city_id' .
				' JOIN countries o ON o.id = i.country_id', $condition);
			$query->add(' ORDER BY name');
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$result->clubs = array();
			while ($row = $query->next())
			{
				$result->clubs[] = new WSClub($row);
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