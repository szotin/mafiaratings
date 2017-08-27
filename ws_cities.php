<?php

require_once 'include/session.php';
require_once 'include/user_location.php';

define('CURRENT_VERSION', 0);

class WSCity
{
	public $id;
	public $city;
	public $country_id;
	public $country;
	public $area_id;
	
	function __construct($row)
	{
		list ($this->id, $this->city, $this->country_id, $this->country, $area) = $row;
		$this->id = (int)$this->id;
		$this->country_id = (int)$this->country_id;
		if ($area != NULL)
		{
			$this->area_id = (int)$area;
		}
		else
		{
			$this->area_id = $this->id;
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
	<h1>ws_cities Parameters:</h1>
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
				<dd>Search pattern. For example: <a href="ws_cities.php?contains=va">ws_cities.php?contains=va</a> returns cities containing "va" in their name.</dd>
			<dt>starts</dt>
				<dd>Search pattern. For example: <a href="ws_cities.php?starts=va">ws_cities.php?starts=va</a> returns cities with names starting with "va".</dd>
			<dt>city</dt>
				<dd>City id or city name. For example: <a href="ws_cities.php?city=1">ws_cities.php?city=1</a> returns information about Vancouver; <a href="ws_cities.php?city=moscow">ws_cities.php?city=moscow</a> returns information about Moscow.</dd>
			<dt>area</dt>
				<dd>City id or city name. For example: <a href="ws_cities.php?area=1">ws_cities.php?area=1</a> returns cities near Vancouver; <a href="ws_cities.php?area=moscow">ws_cities.php?area=moscow</a> returns cities near Moscow.</dd>
			<dt>country</dt>
				<dd>Country id or country name. For example: <a href="ws_cities.php?country=2">ws_cities.php?country=2</a> returns Russian cities; <a href="ws_cities.php?country=canada">ws_cities.php?country=canada</a> returns Canadian cities.</dd>
			<dt>lang</dt>
				<dd>Language for city and country names. 1 is English; 2 is Russian. For example: <a href="ws_cities.php?lang=2">ws_cities.php?lang=2</a> returns cities names in Russian. If not specified, default language for the logged in account is used. If not logged in the system tries to guess the language by ip address.</dd>
			<dt>count</dt>
				<dd>Returns cities count instead of the cities themselves. For example: <a href="ws_cities.php?contains=mo&count">ws_cities.php?contains=mo&count</a> returns how many cities contain 'mo' in their name.</dd>
			<dt>page</dt>
				<dd>Page number. For example: <a href="ws_cities.php?page=1">ws_cities.php?page=1</a> returns the second page of cities by alphabet.</dd>
			<dt>page_size</dt>
				<dd>Page size. Default page_size is 16. For example: <a href="ws_cities.php?page_size=32">ws_cities.php?page_size=32</a> returns first 32 cities; <a href="ws_cities.php?page_size=0">ws_cities.php?page_size=0</a> returns cities in one page; <a href="ws_cities.php">ws_cities.php</a> returns first 16 cities by alphabet.</dd>
		</dl>	
	<h1>ws_cities Results:</h1>
		<dl>
			<dt>version</dt>
			  <dd>Data version.</dd>
			<dt>cities</dt>
			  <dd>The array of cities. Cities are always sorted in alphabetical order. There is no way to change sorting order in the current version of the API.</dd>
			<dt>count</dt>
			  <dd>The total number of cities satisfying the request parameters.</dd>
			<dt>error</dt>
			  <dd>Error message when an error occurs.</dd>
		</dl>
	<h2>City:</h2>
		<dl>
			<dt>id</dt>
			  <dd>City id.</dd>
			<dt>city</dt>
			  <dd>City name.</dd>
			<dt>country_id</dt>
			  <dd>Country id that this city belongs to.</dd>
			<dt>country</dt>
			  <dd>Country name.</dd>
			<dt>area_id</dt>
			  <dd>City id. This is the id of the center city grouping other cities around it. For example Burnaby, Richmond, and Vancouver have Vancouver id as their area id.</dd>
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
		
		$country = 0;
		if (isset($_REQUEST['country']))
		{
			$country = (int)$_REQUEST['country'];
			if ($country <= 0)
			{
				$query = new DbQuery('SELECT id FROM countries WHERE name_en = ? OR name_ru = ? ORDER BY id LIMIT 1', $_REQUEST['country'], $_REQUEST['country']);
				if ($row = $query->next())
				{
					list($country) = $row;
				}
			}
		}
		
		$city = 0;
		if (isset($_REQUEST['city']))
		{
			$city = (int)$_REQUEST['city'];
			if ($city <= 0)
			{
				$query = new DbQuery('SELECT id FROM cities WHERE name_en = ? OR name_ru = ? ORDER BY id LIMIT 1', $_REQUEST['city'], $_REQUEST['city']);
				if ($row = $query->next())
				{
					list($city) = $row;
				}
			}
		}
		
		$area = 0;
		if (isset($_REQUEST['area']))
		{
			$area = (int)$_REQUEST['area'];
			if ($area <= 0)
			{
				$query = new DbQuery('SELECT id FROM cities WHERE name_en = ? OR name_ru = ? ORDER BY id LIMIT 1', $_REQUEST['area'], $_REQUEST['area']);
				if ($row = $query->next())
				{
					list($area) = $row;
				}
			}
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
		
		$condition = new SQL();
		$delim = ' WHERE ';
		if ($contains != '')
		{
			$contains = '%' . $contains . '%';
			$condition->add($delim . '(i.name_en LIKE(?) OR i.name_ru LIKE(?))', $contains, $contains);
			$delim = ' AND ';
		}
		
		if ($starts != '')
		{
			$starts1 = '% ' . $starts . '%';
			$starts2 = $starts . '%';
			$condition->add($delim . '(i.name_en LIKE(?) OR i.name_ru LIKE(?) OR i.name_en LIKE(?) OR i.name_ru LIKE(?))', $starts1, $starts1, $starts2, $starts2);
			$delim = ' AND ';
		}
		
		if ($city > 0)
		{
			$condition->add($delim . 'i.id = ?', $city);
		}
		else if ($area)
		{
			$query1 = new DbQuery('SELECT near_id FROM cities WHERE id = ?', $area);
			list($parent_city) = $query1->record('city');
			if ($parent_city == NULL)
			{
				$parent_city = $area;
			}
			$condition->add($delim . ' i.id = ? OR i.near_id = ?', $area, $area);
		}
		else if ($country > 0)
		{
			$condition->add($delim . 'i.country_id = ?', $country);
		}
		
		list($count) = Db::record('city', 'SELECT count(*) FROM cities i JOIN countries o ON o.id = i.country_id', $condition);
		$result->count = (int)$count;
		if (!$count_only)
		{
			$query = new DbQuery('SELECT i.id, i.name_' . $_lang_code . ', o.id, o.name_' . $_lang_code . ', i.near_id FROM cities i JOIN countries o ON o.id = i.country_id', $condition);
			$query->add(' ORDER BY i.name_' . $_lang_code);
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$result->cities = array();
			while ($row = $query->next())
			{
				$result->cities[] = new WSCity($row);
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