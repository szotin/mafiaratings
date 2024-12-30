<?php

require_once '../../include/api.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		$club = 0;
		if (isset($_REQUEST['club']))
		{
			$club = (int)$_REQUEST['club'];
		}
		
		$page_size = API_DEFAULT_PAGE_SIZE;
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
		
		$from = 0;
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$from = (int)$_REQUEST['from'];
		}
		
		$to = 0;
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$to = (int)$_REQUEST['to'];
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
		
		$count_only = isset($_REQUEST['count']);
		
		$condition = new SQL(' FROM news n JOIN clubs c ON c.id = n.club_id JOIN cities ct ON ct.id = c.city_id WHERE (n.lang & ?) <> 0', $langs);
		if ($from > 0)
		{
			$condition->add(' AND n.timestamp > ?', $from);
		}

		if ($to > 0)
		{
			$condition->add(' AND n.timestamp < ?', $to);
		}
		
		if ($club > 0)
		{
			$condition->add(' AND c.id = ?', $club);
		}
		else if ($city > 0)
		{
			$condition->add(' AND ct.id = ?', $city);
		}
		else if ($area > 0)
		{
			$condition->add(' AND ct.area_id = (SELECT area_id FROM cities WHERE id = ?)', $area);
		}
		else if ($country > 0)
		{
			$condition->add(' AND ct.country_id = ?', $country);
		}
		
		list ($count) = Db::record('advert', 'SELECT count(*)', $condition);
		$this->response['count'] = (int)$count;

		if (!$count_only)
		{
			$query = new DbQuery('SELECT n.id, n.timestamp, ct.timezone, n.message, n.lang', $condition);
			$query->add(' ORDER BY n.timestamp DESC LIMIT ' . ($page * $page_size) . ',' . $page_size);

			$messages = array();
			while ($row = $query->next())
			{
				$message = new stdClass();
				list($message->id, $message->timestamp, $message->timezone, $message->message, $message->language) = $row;
				$message->id = (int)$message->id;
				$message->timestamp = (int)$message->timestamp;
				$message->language = (int)$message->language;
				$messages[] = $message;
			}
			$this->response['messages'] = $messages;
		}
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('club', 'Club id.</i> For example: <a href="adverts.php?club=1">/api/get/adverts.php?club=1</a> returns all advertizements of Vancouver Mafia Club. If missing, all players for all clubs are returned.', '-');
		$help->request_param('city', 'City id. For example: <a href="adverts.php?city=2">/api/get/adverts.php?city=2</a> returns all adverts from Moscow clubs. List of the cities and their ids can be obtained using <a href="cities.php?help">/api/get/cities.php</a>.', '-');
		$help->request_param('area', 'City id. The difference with city is that when area is set, the adverts from all nearby cities are also returned. For example: <a href="adverts.php?area=1">/api/get/adverts.php?area=1</a> returns all adverts published in Vancouver and nearby cities. Though <a href="adverts.php?city=1">/api/get/adverts.php?city=1</a> returns only the adverts published in Vancouver itself.', '-');
		$help->request_param('country', 'Country id. For example: <a href="adverts.php?country=2">/api/get/adverts.php?country=2</a> returns all adverts published in Russia. List of the countries and their ids can be obtained using <a href="countries.php?help">/api/get/countries.php</a>.', '-');
		$help->request_param('langs', 'Message languages filter. Bit combination of language ids. ' . LANG_ALL . ' - means all languages (this is a default value). For example: <a href="adverts.php?club=1&langs=1">/api/get/adverts.php?club=1&langs=1</a> returns all English advertizements of Vancouver Mafia Club; <a href="adverts.php?club=1&langs=3">/api/get/adverts.php?club=1&langs=3</a> returns all advertizements of Vancouver Mafia Club.' . valid_langs_help(), '-');
		$help->request_param('from', 'Unix timestamp for the earliest message to return. For example: <a href="adverts.php?club=1&from=1483228800">/api/get/adverts.php?club=1&from=1483228800</a> returns all messages starting from January 1, 2017', '-');
		$help->request_param('to', 'Unix timestamp for the latest message to return. For example: <a href="adverts.php?club=1&to=1483228800">/api/get/adverts.php?club=1&to=1483228800</a> returns all messages before 2017; <a href="adverts.php?club=1&from=1483228800&to=1485907200">/api/get/adverts.php?club=1&from=1483228800&to=1485907200</a> returns all messages in January 2017', '-');
		$help->request_param('count', 'Returns game count instead of advertizements list. For example: <a href="adverts.php?club=1&count">/api/get/adverts.php?club=1&count</a> returns how many advertizements are there in Vancouver Mafia Club', '-');
		$help->request_param('page', 'Page number. For example: <a href="adverts.php?club=1&page=1">/api/get/adverts.php?club=1&page=1</a> returns the second page of advertizements for Vancouver Mafia Club players.', '-');
		$help->request_param('page_size', 'Page size. Default page_size is ' . API_DEFAULT_PAGE_SIZE . '. For example: <a href="adverts.php?club=1&page_size=32">/api/get/adverts.php?club=1&page_size=32</a> returns last 32 advertizements for Vancouver Mafia Club; <a href="adverts.php?club=6&page_size=0">/api/get/adverts.php?club=6&page_size=0</a> returns all advertizements for Empire of Mafia club in one page; <a href="adverts.php?club=1">/api/get/adverts.php?club=1</a> returns last ' . API_DEFAULT_PAGE_SIZE . ' advertizements for Vancouver Mafia Club;', '-');

		$param = $help->response_param('mesages', 'The array of advertizements. They are always sorted from latest to oldest. There is no way to change sorting order in the current version of API.');
			$param->sub_param('id', 'Advertizement id.');
			$param->sub_param('timestamp', 'Unix timestamp of the advertizemant.');
			$param->sub_param('timezone', 'Timezone of the message. It is always the same as club timezone.');
			$param->sub_param('message', 'The message.');
			$param->sub_param('language', 'The language of the message.' . valid_langs_help());
		$help->response_param('count', 'The total number of advertizements satisfying the request parameters.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Club Advertisements', CURRENT_VERSION);

?>