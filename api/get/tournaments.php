<?php

require_once '../../include/api.php';
require_once '../../include/user_location.php';
require_once '../../include/rules.php';
require_once '../../include/datetime.php';
require_once '../../include/picture.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		global $_profile;
		
		$name_contains = get_optional_param('name_contains');
		$name_starts = get_optional_param('name_starts');
		$started_before = get_optional_param('started_before');
		$ended_before = get_optional_param('ended_before');
		$started_after = get_optional_param('started_after');
		$ended_after = get_optional_param('ended_after');
		$tournament_id = (int)get_optional_param('tournament_id', -1);
		$club_id = (int)get_optional_param('club_id', -1);
		$league_id = (int)get_optional_param('league_id', -1);
		$series_id = (int)get_optional_param('series_id', -1);
		$address_id = (int)get_optional_param('address_id', -1);
		$city_id = (int)get_optional_param('city_id', -1);
		$area_id = (int)get_optional_param('area_id', -1);
		$country_id = (int)get_optional_param('country_id', -1);
		$scoring_id = (int)get_optional_param('scoring_id', -1);
		$scoring_version = (int)get_optional_param('scoring_version', -1);
		$rules_code = get_optional_param('rules_code');
		$user_id = (int)get_optional_param('user_id', -1);
		$langs = (int)get_optional_param('langs', 0);
		$canceled = (int)get_optional_param('canceled', 0);
		$lod = (int)get_optional_param('lod', 0);
		$count_only = isset($_REQUEST['count']);
		$page = (int)get_optional_param('page', 0);
		$page_size = (int)get_optional_param('page_size', API_DEFAULT_PAGE_SIZE);
		
		$condition = new SQL(' WHERE TRUE');
		if (!empty($name_contains))
		{
			$name_contains = '%' . $name_contains . '%';
			$condition->add(' AND t.name LIKE(?)', $name_contains);
		}
		
		if (!empty($name_starts))
		{
			$name_starts1 = '% ' . $name_starts . '%';
			$name_starts2 = $name_starts . '%';
			$condition->add(' AND (t.name LIKE(?) OR t.name LIKE(?))', $name_starts1, $name_starts2);
		}

		if (!empty($started_before))
		{
			if (strpos($started_before, '+') === 0)
			{
				$condition->add(' AND t.start_time <= ?', get_datetime(trim(substr($started_before, 1)))->getTimestamp());
			}
			else
			{
				$condition->add(' AND t.start_time < ?', get_datetime($started_before)->getTimestamp());
			}
		}

		if (!empty($ended_before))
		{
			if (strpos($ended_before, '+') === 0)
			{
				$condition->add(' AND t.start_time + t.duration <= ?', get_datetime(trim(substr($ended_before, 1)))->getTimestamp());
			}
			else
			{
				$condition->add(' AND t.start_time + t.duration < ?', get_datetime($ended_before)->getTimestamp());
			}
		}

		if (!empty($started_after))
		{
			if (strpos($started_after, '+') === 0)
			{
				$condition->add(' AND t.start_time >= ?', get_datetime(trim(substr($started_after, 1)))->getTimestamp());
			}
			else
			{
				$condition->add(' AND t.start_time > ?', get_datetime($started_after)->getTimestamp());
			}
		}

		if (!empty($ended_after))
		{
			if (strpos($ended_after, '+') === 0)
			{
				$condition->add(' AND t.start_time + t.duration >= ?', get_datetime(trim(substr($ended_after, 1)))->getTimestamp());
			}
			else
			{
				$condition->add(' AND t.start_time + t.duration > ?', get_datetime($ended_after)->getTimestamp());
			}
		}

		if ($tournament_id > 0)
		{
			$condition->add(' AND t.id = ?', $tournament_id);
		}
		
		if ($club_id > 0)
		{
			$condition->add(' AND t.club_id = ?', $club_id);
		}
		
		if ($address_id > 0)
		{
			$condition->add(' AND t.address_id = ?', $address_id);
		}
		
		if ($city_id > 0)
		{
			$condition->add(' AND a.city_id = ?', $city_id);
		}
		
		if ($area_id > 0)
		{
			$condition->add(' AND a.city_id IN (SELECT id FROM cities WHERE area_id = ?)', $area_id);
		}
		
		if ($country_id > 0)
		{
			$condition->add(' AND a.city_id IN (SELECT id FROM cities WHERE country_id = ?)', $country_id);
		}
		
		if ($league_id > 0)
		{
			$condition->add(' AND t.id IN (SELECT _st.tournament_id FROM series_tournaments _st JOIN series _s ON _s.id = _st.series_id WHERE _s.league_id = ?)', $league_id);
		}
		
		if ($series_id == 0)
		{
			$condition->add(' AND t.id NOT IN (SELECT tournament_id FROM series_tournaments)');
		}
		else if ($series_id > 0)
		{
			$condition->add(' AND t.id IN (SELECT tournament_id FROM series_tournaments WHERE series_id = ?)', $series_id);
		}
		
		if ($scoring_id > 0)
		{
			$condition->add(' AND t.scoring_id = ?', $scoring_id);
		}
		
		if ($scoring_version > 0)
		{
			$condition->add(' AND t.scoring_version = ?', $scoring_version);
		}
		
		if (!empty($rules_code))
		{
			$condition->add(' AND t.rules = ?', $rules_code);
		}
		
		if ($user_id > 0)
		{
			$condition->add(' AND t.id IN (SELECT e.tournament_id FROM players p JOIN games g ON g.id = p.game_id JOIN events e ON e.id = g.event_id WHERE p.user_id = ? AND g.is_canceled = FALSE AND g.result > 0)', $user_id);
		}
		
		if ($langs > 0)
		{
			$condition->add(' AND (t.langs & ?) <> 0', $langs);
		}
		
		switch ($canceled)
		{
			case 1: // all including canceled
				break;
			case 2: // canceled only
				$condition->add(' AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') <> 0');
				break;
			default: // except canceled
				$condition->add(' AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0');
				break;
		}
		
		list($count) = Db::record('tournament', 'SELECT count(*) FROM tournaments t JOIN addresses a ON a.id = t.address_id', $condition);
		$this->response['count'] = (int)$count;
		if ($count_only)
		{
			return;
		}
		
		$tournaments = array();
		if ($lod >= 1)
		{
			$query = new DbQuery(
				'SELECT t.id, t.name, t.flags, t.langs, a.id, a.name, a.flags, c.id, c.name, c.flags, t.start_time, t.duration, t.notes, t.fee, t.currency_id, t.scoring_id, t.scoring_version, t.rules, ct.timezone FROM tournaments t' . 
				' JOIN addresses a ON a.id = t.address_id' .
				' JOIN clubs c ON c.id = t.club_id' .
				' JOIN cities ct ON ct.id = a.city_id', $condition);
			$query->add(' ORDER BY t.start_time DESC, t.id DESC');
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$this->show_query($query);
			while ($row = $query->next())
			{
				$tournament = new stdClass();
				list ($tournament->id, $tournament->name, $tournament->flags, $tournament->langs, $tournament->address_id, $tournament->address_name, $address_flags, $tournament->club_id, $tournament->club_name, $club_flags, $tournament->timestamp, $tournament->duration, $tournament->notes, $fee, $currency_id, $tournament_scoring_id, $tournament_scoring_version, $rules_code, $tournament_timezone) = $row;
				$tournament->id = (int)$tournament->id;
				$tournament->langs = (int)$tournament->langs;
				$tournament->address_id = (int)$tournament->address_id;
				$tournament->club_id = (int)$tournament->club_id;
				$tournament->timestamp = (int)$tournament->timestamp;
				$tournament->duration = (int)$tournament->duration;
				if (!is_null($tournament_scoring_id))
				{
					$tournament->scoring_id = (int)$tournament_scoring_id;
					$tournament->scoring_version = (int)$tournament_scoring_version;
				}
				$tournament->rules = rules_code_to_object($rules_code);
				
				$tournament->start = timestamp_to_string($tournament->timestamp, $tournament_timezone);
				$tournament->end = timestamp_to_string($tournament->timestamp + $tournament->duration, $tournament_timezone);
				
				$server_url = get_server_url() . '/';
				
				$address_pic = new Picture(ADDRESS_PICTURE);
				$address_pic->set($tournament->address_id, $tournament->address_name, $address_flags);
				$tournament->address_icon = $server_url . $address_pic->url(ICONS_DIR);
				$tournament->address_picture = $server_url . $address_pic->url(TNAILS_DIR);
				
				$club_pic = new Picture(CLUB_PICTURE);
				$club_pic->set($tournament->club_id, $tournament->club_name, $club_flags);
				$tournament->club_icon = $server_url . $club_pic->url(ICONS_DIR);
				$tournament->club_picture = $server_url . $club_pic->url(TNAILS_DIR);
				
				$tournament_pic = new Picture(TOURNAMENT_PICTURE, $club_pic);
				
				$tournament_pic->set($tournament->id, $tournament->name, $tournament->flags);
				$tournament->icon = $server_url . $tournament_pic->url(ICONS_DIR);
				$tournament->picture = $server_url . $tournament_pic->url(TNAILS_DIR);
				
				$tournament->flags = (int)$tournament->flags | TOURNAMENT_EDITABLE_MASK;
				if (!is_null($fee) && !is_null($currency_id))
				{
					$tournament->fee = (int)$fee;
					$tournament->currency_id = (int)$currency_id;
				}
				$tournaments[] = $tournament;
			}
		}
		else
		{
			$query = new DbQuery(
				'SELECT t.id, t.name, t.flags, t.langs, t.address_id, t.club_id, t.start_time, t.duration, t.notes, t.fee, t.currency_id, t.scoring_id, t.scoring_version, t.rules FROM tournaments t' . 
				' JOIN addresses a ON a.id = t.address_id', $condition);
			$query->add(' ORDER BY t.start_time DESC, t.id DESC');
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$this->show_query($query);
			while ($row = $query->next())
			{
				$tournament = new stdClass();
				list ($tournament->id, $tournament->name, $tournament->flags, $tournament->langs, $tournament->address_id, $tournament->club_id, $tournament->timestamp, $tournament->duration, $tournament->notes, $fee, $currency_id, $tournament_scoring_id, $tournament_scoring_version, $rules_code) = $row;
				$tournament->id = (int)$tournament->id;
				$tournament->langs = (int)$tournament->langs;
				$tournament->address_id = (int)$tournament->address_id;
				$tournament->club_id = (int)$tournament->club_id;
				$tournament->timestamp = (int)$tournament->timestamp;
				$tournament->duration = (int)$tournament->duration;
				if (!is_null($tournament_scoring_id))
				{
					$tournament->scoring_id = (int)$tournament_scoring_id;
					$tournament->scoring_version = (int)$tournament_scoring_version;
				}
				$tournament->rules = rules_code_to_object($rules_code);
				$tournament->flags = (int)$tournament->flags | TOURNAMENT_EDITABLE_MASK;
				if (!is_null($fee) && !is_null($currency_id))
				{
					$tournament->fee = (int)$fee;
					$tournament->currency_id = (int)$currency_id;
				}
				$tournaments[] = $tournament;
			}
		}
		$this->response['tournaments'] = $tournaments;
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('name_contains', 'Search pattern. For example: <a href="tournaments.php?name_contains=co">' . PRODUCT_URL . '/api/get/tournaments.php?name_contains=co</a> returns tournaments containing "co" in their name.', '-');
		$help->request_param('name_starts', 'Search pattern. For example: <a href="tournaments.php?name_starts=co">' . PRODUCT_URL . '/api/get/tournaments.php?name_starts=co</a> returns tournaments with names starting with "co".', '-');
		$help->request_param('started_before', 'Unix timestamp, or datetime, or <q>now</q>. Returns tournaments that are started before a certain time. For example: <a href="tournaments.php?started_before=2017-01-01%2000:00">' . PRODUCT_URL . '/api/get/tournaments.php?started_before=2017-01-01%2000:00</a> returns all tournaments started before January 1, 2017. Logged user timezone is used for converting dates. If it starts from "+" (for example "+2017-01-01%2000:00"), the search is inclusive.', '-');
		$help->request_param('ended_before', 'Unix timestamp, or datetime, or <q>now</q>. Returns tournaments that are ended before a certain time. For example: <a href="tournaments.php?ended_before=1483228800">' . PRODUCT_URL . '/api/get/tournaments.php?ended_before=1483228800</a> returns all tournaments ended before January 1, 2017; <a href="tournaments.php?ended_before=now">' . PRODUCT_URL . '/api/get/tournaments.php?ended_before=now</a> returns all tournaments that are already ended. Logged user timezone is used for converting dates. If it starts from "+" (for example "+2017-01-01%2000:00"), the search is inclusive.', '-');
		$help->request_param('started_after', 'Unix timestamp, or datetime, or <q>now</q>. Returns tournaments that are started after a certain time. For example: <a href="tournaments.php?started_after=2017-01-01%2000:00">' . PRODUCT_URL . '/api/get/tournaments.php?started_after=2017-01-01%2000:00</a> returns all tournaments started after January 1, 2017. Logged user timezone is used for converting dates. If it starts from "+" (for example "+2017-01-01%2000:00"), the search is inclusive.', '-');
		$help->request_param('ended_after', 'Unix timestamp, or datetime, or <q>now</q>. Returns tournaments that are ended after a certain time. For example: <a href="tournaments.php?ended_after=1483228800">' . PRODUCT_URL . '/api/get/tournaments.php?ended_after=1483228800</a> returns all tournaments ended after January 1, 2017; <a href="tournaments.php?started_before=now&ended_after=now">' . PRODUCT_URL . '/api/get/tournaments.php?started_before=now&ended_after=now</a> returns all tournaments that happening now. Logged user timezone is used for converting dates. If it starts from "+" (for example "+2017-01-01%2000:00"), the search is inclusive.', '-');
		$help->request_param('tournament_id', 'Tournament id. For example: <a href="tournaments.php?tournament_id=1">' . PRODUCT_URL . '/api/get/tournaments.php?tournament_id=1</a> returns the tournament with id 1.', '-');
		$help->request_param('club_id', 'Club id. For example: <a href="tournaments.php?club_id=1">' . PRODUCT_URL . '/api/get/tournaments.php?club_id=1</a> returns all tournaments in Vancouver Mafia Club. List of the cities and their ids can be obtained using <a href="clubs.php?help">' . PRODUCT_URL . '/api/get/clubs.php</a>.', '-');
		$help->request_param('league_id', 'League id. For example: <a href="tournaments.php?league_id=2">' . PRODUCT_URL . '/api/get/tournaments.php?league_id=2</a> returns all tournaments of the American Mafia League.', '-');
		$help->request_param('series_id', 'Series id. For example: <a href="tournaments.php?series_id=1">' . PRODUCT_URL . '/api/get/tournaments.php?series_id=1</a> returns all tournaments of the American Mafia League Season 2022. <a href="tournaments.php?series_id=0">' . PRODUCT_URL . '/api/get/tournaments.php?series_id=0</a> returns all tournaments that do not belong to any series.', '-');
		$help->request_param('address_id', 'Address id. For example: <a href="tournaments.php?address_id=10">' . PRODUCT_URL . '/api/get/tournaments.php?address_id=10</a> returns all tournaments played in Tafs Cafe by Vancouver Mafia Club.', '-');
		$help->request_param('city_id', 'City id. For example: <a href="tournaments.php?city_id=2">' . PRODUCT_URL . '/api/get/tournaments.php?city_id=2</a> returns all tournaments in Moscow. List of the cities and their ids can be obtained using <a href="cities.php?help">' . PRODUCT_URL . '/api/get/cities.php</a>.', '-');
		$help->request_param('area_id', 'City id. The difference with city is that when area is set, the tournaments from all nearby cities are also returned. For example: <a href="tournaments.php?area_id=2">' . PRODUCT_URL . '/api/get/tournaments.php?area_id=2</a> returns all tournaments in Moscow and nearby cities like Podolsk, Himki, etc. Though <a href="tournaments.php?city_id=2">' . PRODUCT_URL . '/api/get/tournaments.php?city_id=2</a> returns only the tournaments in Moscow itself.', '-');
		$help->request_param('country_id', 'Country id. For example: <a href="tournaments.php?country_id=2">' . PRODUCT_URL . '/api/get/tournaments.php?country_id=2</a> returns all tournaments in Russia. List of the countries and their ids can be obtained using <a href="countries.php?help">' . PRODUCT_URL . '/api/get/countries.php</a>.', '-');
		$help->request_param('scoring_id', 'Scoring id. For example: <a href="tournaments.php?scoring_id=21">' . PRODUCT_URL . '/api/get/tournaments.php?scoring_id=21</a> returns all tournaments where VaWaCa scoring was used.', '-');
		$help->request_param('rules_code', 'Rules code. For example: <a href="tournaments.php?rules_code=00000000100101010200000000000">' . PRODUCT_URL . '/api/get/tournaments.php?rules_code=00000000100101010200000000000</a> returns all tournaments where the rules with the code 00000000100101010200000000000 was used. Please check <a href="rules.php?help">' . PRODUCT_URL . '/api/get/rules.php?help</a> for the meaning of rules codes and getting rules list.', '-');
		$help->request_param('user_id', 'User id. For example: <a href="tournaments.php?user_id=25">' . PRODUCT_URL . '/api/get/tournaments.php?user_id=25</a> returns all tournaments where Fantomas was playing.', '-');
		$help->request_param('langs', 'Languages filter. A bit combination of language ids. For example: <a href="tournaments.php?langs=1">' . PRODUCT_URL . '/api/get/tournaments.php?langs=1</a> returns all tournaments that support English as their language.' . valid_langs_help(), '-');
		$help->request_param('canceled', '0 - exclude canceled tournaments (default); 1 - incude canceled tournaments; 2 - canceled tournaments only. For example: <a href="tournaments.php?canceled=2">' . PRODUCT_URL . '/api/get/tournaments.php?canceled=2</a> returns all canceled tournaments.', '-');
		$help->request_param('page', 'Page number. For example: <a href="tournaments.php?page=1">' . PRODUCT_URL . '/api/get/tournaments.php?page=1</a> returns the second page of tournaments by time from newest to oldest.', '-');
		$help->request_param('page_size', 'Page size. Default page_size is ' . API_DEFAULT_PAGE_SIZE . '. For example: <a href="tournaments.php?page_size=32">' . PRODUCT_URL . '/api/get/tournaments.php?page_size=32</a> returns first 32 tournaments; <a href="tournaments.php?page_size=0">' . PRODUCT_URL . '/api/get/tournaments.php?page_size=0</a> returns tournaments in one page; <a href="tournaments.php">' . PRODUCT_URL . '/api/get/tournaments.php</a> returns first ' . API_DEFAULT_PAGE_SIZE . ' tournaments by alphabet.', '-');

		$param = $help->response_param('tournaments', 'The array of tournaments. Tournaments are always sorted in time order from newest to oldest. There is no way to change sorting order in the current version of the API.');
			$param->sub_param('id', 'Tournament id.');
			$param->sub_param('name', 'Tournament name.');
			$param->sub_param('icon', 'Tournament icon URL.', 1);
			$param->sub_param('picture', 'Tournament picture URL.', 1);
			$param->sub_param('langs', 'A bit combination of languages used in the tournament.' . valid_langs_help());
			$param->sub_param('address_id', 'Tournament address id.');
			$param->sub_param('address_name', 'Tournament address name.', 1);
			$param->sub_param('address_icon', 'Tournament address icon URL.', 1);
			$param->sub_param('address_picture', 'Tournament address picture URL.', 1);
			$param->sub_param('club_id', 'Club id of the tournament.');
			$param->sub_param('club_name', 'Tournament club name.', 1);
			$param->sub_param('club_icon', 'Tournament club icon.', 1);
			$param->sub_param('club_picture', 'Club picture URL of the tournament.', 1);
			$param->sub_param('timestamp', 'Unix timestamp for the start of the tournament.');
			$param->sub_param('duration', 'Duration of the tournament in seconds.');
			$param->sub_param('start', 'Formatted date "yyyy-mm-dd HH:MM" for the start of the tournament. Tournament timezone is used.', 1);
			$param->sub_param('end', 'Formatted date "yyyy-mm-dd HH:MM" for the end of the tournament. Tournament timezone is used.', 1);
			$param->sub_param('notes', 'Tournament notes.');
			$param->sub_param('fee', 'Tournament admission rate.', 'admission rate is unknown.');
			$param->sub_param('currency_id', 'Currency id for the admission rate.', 'admission rate is zero or unknown.');
			$param->sub_param('canceled', 'True for canceled tournaments, false for others.');
			$param->sub_param('scoring_id', 'Id of the scoring system used in the tournament.', 'every round of the tournament is using its own scoring system.');
			$param->sub_param('league_id', 'League id when the tournament belongs to a league.', 'the tournament is internal club tournament.');
			$param->sub_param('league_name', 'Tournament name.', 'the tournament is internal club tournament.', 1);
			$param->sub_param('league_icon', 'Tournament icon URL.', 'the tournament is internal club tournament.', 1);
			$param->sub_param('league_picture', 'Tournament picture URL.', 'the tournament is internal club tournament.', 1);
			$param->sub_param('flags', 'A bit cobination of:<ol>' .
										'<li value="16">This is a long term tournament when set. Long term tournament is something like a season championship. Short-term tournament is a one day to one week competition.</li>' .
										'<li value="32">When a moderator starts a new game, they can assign it to the tournament even if the game is in a non-tournament or in any other tournament event.</li>' .
										'<li value="64">When a custom event is created, it can be assigned to this tournament as a round.</li>' .
										'<li value="128">Tournament rounds must use this tournament game rules.</li>' .
										'<li value="256">Tournament rounds must use this tournament scoring system.</li>' .
										'</ol>');
			api_rules_help($param->sub_param('rules', 'Game rules used in the tournament.'), true);
		$help->response_param('count', 'Total number of tournaments satisfying the request parameters.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Tournaments', CURRENT_VERSION);

?>