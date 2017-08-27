<?php

require_once 'include/session.php';
require_once 'include/user_location.php';

define('CURRENT_VERSION', 0);

class WSCountry
{
	public $id;
	public $country;
	public $code;
	
	function __construct($row)
	{
		list ($this->id, $this->country, $this->code) = $row;
		$this->id = (int)$this->id;
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
	<h1>ws_countries Parameters:</h1>
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
				<dd>Search pattern. For example: <a href="ws_countries.php?contains=us">ws_countries.php?contains=us</a> returns countries containing "us" in their name.</dd>
			<dt>starts</dt>
				<dd>Search pattern. For example: <a href="ws_countries.php?starts=us">ws_cities.php?starts=us</a> returns countries with names starting with "us".</dd>
			<dt>country</dt>
				<dd>Country id or country name. For example: <a href="ws_countries.php?country=1">ws_countries.php?country=1</a> returns information about Canada; <a href="ws_countries.php?country=russia">ws_countries.php?country=russia</a> returns information about Russia.</dd>
			<dt>count</dt>
				<dd>Returns countries count instead of the countries themselves. For example: <a href="ws_countries.php?contains=an&count">ws_countries.php?contains=an&count</a> returns how many countries contain 'an' in their name.</dd>
			<dt>page</dt>
				<dd>Page number. For example: <a href="ws_countries.php?page=1">ws_countries.php?page=1</a> returns the second page of countries by alphabet.</dd>
			<dt>page_size</dt>
				<dd>Page size. Default page_size is 16. For example: <a href="ws_countries.php?page_size=32">ws_countries.php?page_size=32</a> returns first 32 countries; <a href="ws_countries.php?page_size=0">ws_countries.php?page_size=0</a> returns countries in one page; <a href="ws_countries.php">ws_countries.php</a> returns first 16 countries by alphabet.</dd>
		</dl>	
	<h1>ws_countries Results:</h1>
		<dl>
			<dt>version</dt>
			  <dd>Data version.</dd>
			<dt>countries</dt>
			  <dd>The array of countries. Countries are always sorted in alphabetical order. There is no way to change sorting order in the current version of the API.</dd>
			<dt>count</dt>
			  <dd>The total number of countries satisfying the request parameters.</dd>
			<dt>error</dt>
			  <dd>Error message when an error occurs.</dd>
		</dl>
	<h2>Country:</h2>
		<dl>
			<dt>id</dt>
			  <dd>Country id.</dd>
			<dt>country</dt>
			  <dd>Country name.</dd>
			<dt>code</dt>
			  <dd>Country code.</dd>
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
			$condition->add($delim . '(name_en LIKE(?) OR name_ru LIKE(?))', $contains, $contains);
			$delim = ' AND ';
		}
		
		if ($starts != '')
		{
			$starts1 = '% ' . $starts . '%';
			$starts2 = $starts . '%';
			$condition->add($delim . '(name_en LIKE(?) OR name_ru LIKE(?) OR name_en LIKE(?) OR name_ru LIKE(?))', $starts1, $starts1, $starts2, $starts2);
			$delim = ' AND ';
		}
		
		if ($country > 0)
		{
			$condition->add($delim . 'id = ?', $country);
		}
		
		list($count) = Db::record('country', 'SELECT count(*) FROM countries', $condition);
		$result->count = (int)$count;
		if (!$count_only)
		{
			$query = new DbQuery('SELECT id, name_' . $_lang_code . ', code FROM countries', $condition);
			$query->add(' ORDER BY name_' . $_lang_code);
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$result->countries = array();
			while ($row = $query->next())
			{
				$result->countries[] = new WSCountry($row);
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