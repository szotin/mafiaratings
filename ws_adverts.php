<?php

require_once 'include/session.php';
require_once 'include/ws.php';

define('CURRENT_VERSION', 0);

class WSMessage
{
	public $id;
	public $timestamp;
	public $timezone;
	public $message;
	public $language;
	
	function __construct($row)
	{
		list($this->id, $this->timestamp, $this->timezone, $this->message, $this->language) = $row;
		$this->id = (int)$this->id;
		$this->timestamp = (int)$this->timestamp;
		$this->language = (int)$this->language;
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
			<dd>Club id. For example: <a href="ws_adverts.php?club=1">ws_adverts.php?club=1</a> returns all advertizements of Vancouver Mafia Club. If missing, all players for all clubs are returned.</dd>
		  <dt>langs</dt>
			<dd>Message languages filter. 1 for English; 2 for Russian. Bit combination - 3 - means both (this is a default value). For example: <a href="ws_adverts.php?club=1&langs=1">ws_adverts.php?club=1&langs=1</a> returns all English advertizements of Vancouver Mafia Club; <a href="ws_adverts.php?club=1&langs=3">ws_adverts.php?club=1&langs=3</a> returns all English and Russian advertizements of Vancouver Mafia Club</dd>
		  <dt>count</dt>
			<dd>Returns game count instead of advertizements list. For example: <a href="ws_adverts.php?club=1&count">ws_adverts.php?club=1&count</a> returns how many advertizements are there in Vancouver Mafia Club</dd>
		  <dt>page</dt>
			<dd>Page number. For example: <a href="ws_adverts.php?club=1&page=1">v.php?club=1&page=1</a> returns the second page of advertizements for Vancouver Mafia Club players.</dd>
		  <dt>page_size</dt>
			<dd>Page size. Default page_size is 16. For example: <a href="ws_adverts.php?club=1&page_size=32">ws_adverts.php?club=1&page_size=32</a> returns last 32 advertizements for Vancouver Mafia Club; <a href="ws_adverts.php?club=6&page_size=0">ws_adverts.php?club=6&page_size=0</a> returns all advertizements for Empire of Mafia club in one page; <a href="ws_adverts.php?club=1">ws_adverts.php?club=1</a> returns last 16 advertizements for Vancouver Mafia Club;</dd>
		</dl>	
	<h1>Results:</h1>
		<dt>version</dt>
		  <dd>Data version.</dd>
		<dt>mesages</dt>
		  <dd>The array of advertizements. They are always sorted from latest to oldest. There is no way to change sorting order in the current version of the API.</dd>
		<dt>count</dt>
		  <dd>The total number of advertizements satisfying the request parameters.</dd>
		<dt>error</dt>
		  <dd>Error message when an error occurs.</dd>
	<h2>Messages parameters:</h2>
		<dt>id</dt>
		  <dd>Advertizement id.</dd>
		<dt>timestamp</dt>
		  <dd>Unix timestamp of the advertizemant.</dd>
		<dt>timezone</dt>
		  <dd>Timezone of the message. It is always the same as club timezone.</dd>
		<dt>message</dt>
		  <dd>The message.</dd>
		<dt>language</dt>
		  <dd>The language of the message. The supported values are: 1 - English; 2 - Russian.</dd>
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
		
		$langs = LANG_ALL;
		if (isset($_REQUEST['langs']))
		{
			$langs = (int)$_REQUEST['langs'];
		}
		
		$count_only = isset($_REQUEST['count']);
		
		$condition = new SQL(' FROM news n JOIN clubs c ON c.id = n.club_id JOIN cities ct ON ct.id = c.city_id WHERE (n.lang & ?) <> 0 AND c.id = ?', $langs, $club);
		list ($count) = Db::record('advert', 'SELECT count(*)', $condition);
		$result->count = (int)$count;

		if (!$count_only)
		{
			$query = new DbQuery('SELECT n.id, n.timestamp, ct.timezone, n.message, n.lang', $condition);
			$query->add(' ORDER BY n.timestamp DESC LIMIT ' . ($page * $page_size) . ',' . $page_size);

			$result->messages = array();
			while ($row = $query->next())
			{
				$result->messages[] = new WSMessage($row);
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