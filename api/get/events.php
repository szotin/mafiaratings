<?php

require_once '../../include/api.php';
require_once '../../include/user_location.php';
require_once '../../include/rules.php';
require_once '../../include/datetime.php';
require_once '../../include/picture.php';
require_once '../../include/scoring.php';

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
		$event_id = (int)get_optional_param('event_id', -1);
		$tournament_id = (int)get_optional_param('tournament_id', -1);
		$club_id = (int)get_optional_param('club_id', -1);
		$league_id = (int)get_optional_param('league_id', -1);
		$address_id = (int)get_optional_param('address_id', -1);
		$city_id = (int)get_optional_param('city_id', -1);
		$area_id = (int)get_optional_param('area_id', -1);
		$country_id = (int)get_optional_param('country_id', -1);
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
			$condition->add(' AND e.name LIKE(?)', $name_contains);
		}
		
		if (!empty($name_starts))
		{
			$name_starts1 = '% ' . $name_starts . '%';
			$name_starts2 = $name_starts . '%';
			$condition->add(' AND (e.name LIKE(?) OR e.name LIKE(?))', $name_starts1, $name_starts2);
		}

		if (!empty($started_before))
		{
			$condition->add(' AND e.start_time < ?', get_datetime($started_before)->getTimestamp());
		}

		if (!empty($ended_before))
		{
			$condition->add(' AND e.start_time + e.duration < ?', get_datetime($ended_before)->getTimestamp());
		}

		if (!empty($started_after))
		{
			$condition->add(' AND e.start_time >= ?', get_datetime($started_after)->getTimestamp());
		}

		if (!empty($ended_after))
		{
			$condition->add(' AND e.start_time + e.duration >= ?', get_datetime($ended_after)->getTimestamp());
		}

		if ($event_id > 0)
		{
			$condition->add(' AND e.id = ?', $event_id);
		}
		
		if ($club_id > 0)
		{
			$condition->add(' AND e.club_id = ?', $club_id);
		}
		
		if ($league_id == 0)
		{
			$condition->add(' AND (SELECT league_id FROM tournaments WHERE id = e.tournament_id) IS NULL');
		}
		else if ($league_id > 0)
		{
			$condition->add(' AND (SELECT league_id FROM tournaments WHERE id = e.tournament_id) = ?', $league_id);
		}
		
		if ($address_id > 0)
		{
			$condition->add(' AND e.address_id = ?', $address_id);
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
		
		if ($tournament_id == 0)
		{
			$condition->add(' AND e.tournament_id IS NULL');
		}
		else if ($tournament_id > 0)
		{
			$condition->add(' AND e.tournament_id = ?', $tournament_id);
		}
		
		if (!empty($rules_code))
		{
			$condition->add(' AND e.rules = ?', $rules_code);
		}
		
		if ($user_id > 0)
		{
			$condition->add(' AND e.id IN (SELECT g.event_id FROM players p JOIN games g ON g.id = p.game_id WHERE p.user_id = ? AND g.is_canceled = FALSE AND g.result > 0)', $user_id);
		}
		
		if ($langs > 0)
		{
			$condition->add(' AND (e.languages & ?) <> 0', $langs);
		}
		
		switch ($canceled)
		{
			case 1: // all including canceled
				break;
			case 2: // canceled only
				$condition->add(' AND (e.flags & ' . EVENT_FLAG_CANCELED . ') <> 0');
				break;
			default: // except canceled
				$condition->add(' AND (e.flags & ' . EVENT_FLAG_CANCELED . ') = 0');
				break;
		}
		
		list($count) = Db::record('event', 'SELECT count(*) FROM events e JOIN addresses a ON a.id = e.address_id', $condition);
		$this->response['count'] = (int)$count;
		if ($count_only)
		{
			return;
		}
		
		$events = array();
		if ($lod >= 1)
		{
			$query = new DbQuery(
				'SELECT e.id, e.name, e.flags, e.languages, a.id, a.name, a.flags, c.id, c.name, c.flags, e.start_time, e.duration, e.notes, e.fee, e.currency_id, e.rules, e.scoring_id, e.scoring_version, e.scoring_options, t.id, t.name, t.flags, ct.timezone FROM events e' . 
				' JOIN addresses a ON a.id = e.address_id' .
				' JOIN clubs c ON c.id = e.club_id' .
				' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' .
				' JOIN cities ct ON ct.id = a.city_id', $condition);
			$query->add(' ORDER BY e.start_time DESC, e.id DESC');
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$this->show_query($query);
			while ($row = $query->next())
			{
				$event = new stdClass();
				list ($event->id, $event->name, $event_flags, $event->langs, $event->address_id, $event->address_name, $address_flags, $event->club_id, $event->club_name, $club_flags, $event->timestamp, $event->duration, $event->notes, $fee, $currency_id, $rules_code, $event->scoring_id, $event->scoring_version, $event->scoring_options, $tournament_id, $tournament_name, $tournament_flags, $event_timezone) = $row;
				$event->id = (int)$event->id;
				$event->langs = (int)$event->langs;
				$event->address_id = (int)$event->address_id;
				$event->club_id = (int)$event->club_id;
				$event->timestamp = (int)$event->timestamp;
				$event->duration = (int)$event->duration;
				$event->rules = rules_code_to_object($rules_code);
				$event->scoring_id = (int)$event->scoring_id;
				$event->scoring_version = (int)$event->scoring_version;
				$event->scoring_options = json_decode($event->scoring_options);
				if (!is_null($fee) && !is_null($currency_id))
				{
					$event->fee = (int)$fee;
					$event->currency_id = (int)$currency_id;
				}
				
				$event->start = timestamp_to_string($event->timestamp, $event_timezone);
				$event->end = timestamp_to_string($event->timestamp + $event->duration, $event_timezone);
				
				$server_url = get_server_url() . '/';
				
				$address_pic = new Picture(ADDRESS_PICTURE);
				$address_pic->set($event->address_id, $event->address_name, $address_flags);
				$event->address_icon = $server_url . $address_pic->url(ICONS_DIR);
				$event->address_picture = $server_url . $address_pic->url(TNAILS_DIR);
				
				$club_pic = new Picture(CLUB_PICTURE);
				$club_pic->set($event->club_id, $event->club_name, $club_flags);
				$event->club_icon = $server_url . $club_pic->url(ICONS_DIR);
				$event->club_picture = $server_url . $club_pic->url(TNAILS_DIR);
				
				if (!is_null($tournament_id))
				{
					$event->tournament_id = (int)$tournament_id;
					$event->tournament_name = $tournament_name;
					
					$tournament_pic = new Picture(TOURNAMENT_PICTURE, $club_pic);
					$tournament_pic->set($tournament_id, $tournament_name, $tournament_flags);
					$event->tournament_icon = $server_url . $club_pic->url(ICONS_DIR);
					$event->tournament_picture = $server_url . $club_pic->url(TNAILS_DIR);
					
					$event_pic = new Picture(EVENT_PICTURE, $tournament_pic);
				}
				else
				{
					$event_pic = new Picture(EVENT_PICTURE, $club_pic);
				}
				
				$event_pic->set($event->id, $event->name, $event_flags);
				$event->icon = $server_url . $club_pic->url(ICONS_DIR);
				$event->picture = $server_url . $club_pic->url(TNAILS_DIR);
				
				$events[] = $event;
			}
		}
		else
		{
			$query = new DbQuery(
				'SELECT e.id, e.name, e.flags, e.languages, e.address_id, e.club_id, e.start_time, e.duration, e.notes, e.fee, e.currency_id, e.rules, e.scoring_id, e.scoring_version, e.scoring_options, e.tournament_id FROM events e' . 
				' JOIN addresses a ON a.id = e.address_id', $condition);
			$query->add(' ORDER BY e.start_time DESC, e.id DESC');
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$this->show_query($query);
			while ($row = $query->next())
			{
				$event = new stdClass();
				list ($event->id, $event->name, $flags, $event->langs, $event->address_id, $event->club_id, $event->timestamp, $event->duration, $event->notes, $fee, $currency_id, $rules_code, $event->scoring_id, $event->scoring_version, $event->scoring_options, $tournament_id) = $row;
				$event->id = (int)$event->id;
				$event->langs = (int)$event->langs;
				$event->address_id = (int)$event->address_id;
				$event->club_id = (int)$event->club_id;
				$event->timestamp = (int)$event->timestamp;
				$event->duration = (int)$event->duration;
				$event->rules = rules_code_to_object($rules_code);
				$event->scoring_id = (int)$event->scoring_id;
				$event->scoring_version = (int)$event->scoring_version;
				$event->scoring_options = json_decode($event->scoring_options);
				if (!is_null($tournament_id))
				{
					$event->tournament_id = (int)$tournament_id;
				}
				if (!is_null($fee) && !is_null($currency_id))
				{
					$event->fee = (int)$fee;
					$event->currency_id = (int)$currency_id;
				}
				$events[] = $event;
			}
		}
		$this->response['events'] = $events;
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('name_contains', 'Search pattern. For example: <a href="events.php?name_contains=co">' . PRODUCT_URL . '/api/get/events.php?name_contains=co</a> returns events containing "co" in their name.', '-');
		$help->request_param('name_starts', 'Search pattern. For example: <a href="events.php?name_starts=co">' . PRODUCT_URL . '/api/get/events.php?name_starts=co</a> returns events with names starting with "co".', '-');
		$help->request_param('started_before', 'Unix timestamp, or datetime, or <q>now</q>. Returns events that are started before a certain time. For example: <a href="events.php?started_before=2017-01-01%2000:00">' . PRODUCT_URL . '/api/get/events.php?started_before=2017-01-01%2000:00</a> returns all events started before January 1, 2017. Logged user timezone is used for converting dates.', '-');
		$help->request_param('ended_before', 'Unix timestamp, or datetime, or <q>now</q>. Returns events that are ended before a certain time. For example: <a href="events.php?ended_before=1483228800">' . PRODUCT_URL . '/api/get/events.php?ended_before=1483228800</a> returns all events ended before January 1, 2017; <a href="events.php?ended_before=now">' . PRODUCT_URL . '/api/get/events.php?ended_before=now</a> returns all events that are already ended. Logged user timezone is used for converting dates.', '-');
		$help->request_param('started_after', 'Unix timestamp, or datetime, or <q>now</q>. Returns events that are started after a certain time. For example: <a href="events.php?started_after=2017-01-01%2000:00">' . PRODUCT_URL . '/api/get/events.php?started_after=2017-01-01%2000:00</a> returns all events started after January 1, 2017. Logged user timezone is used for converting dates.', '-');
		$help->request_param('ended_after', 'Unix timestamp, or datetime, or <q>now</q>. Returns events that are ended after a certain time. For example: <a href="events.php?ended_after=1483228800">' . PRODUCT_URL . '/api/get/events.php?ended_after=1483228800</a> returns all events ended after January 1, 2017; <a href="events.php?started_before=now&ended_after=now">' . PRODUCT_URL . '/api/get/events.php?started_before=now&ended_after=now</a> returns all events that happening now. Logged user timezone is used for converting dates.', '-');
		$help->request_param('event_id', 'Event id. For example: <a href="events.php?event_id=1">' . PRODUCT_URL . '/api/get/events.php?event_id=1</a> returns information Vancouver Mafia Club.', '-');
		$help->request_param('tournament_id', 'Tournament id. For example: <a href="events.php?tournament_id=1">' . PRODUCT_URL . '/api/get/events.php?tournament_id=1</a> returns all rounds of VaWaCa-2017. <a href="events.php?tournament_id=0">' . PRODUCT_URL . '/api/get/events.php?tournament_id=0</a> returns all stand alone events that are not tournament rounds.', '-');
		$help->request_param('club_id', 'Club id. For example: <a href="events.php?club_id=1">' . PRODUCT_URL . '/api/get/events.php?club_id=1</a> returns all events in Vancouver Mafia Club. List of the cities and their ids can be obtained using <a href="clubs.php?help">' . PRODUCT_URL . '/api/get/clubs.php</a>.', '-');
		$help->request_param('league_id', 'League id. For example: <a href="events.php?league_id=2">/api/get/events.php?league_id=2</a> returns all American Mafia League tournament rounds. <a href="events.php?league_id=0">/api/get/events.php?league_id=0</a> returns all events that were played outside of any league.', '-');
		$help->request_param('address_id', 'Address id. For example: <a href="events.php?address_id=10">' . PRODUCT_URL . '/api/get/events.php?address_id=10</a> returns all events played in Tafs Cafe by Vancouver Mafia Club.', '-');
		$help->request_param('city_id', 'City id. For example: <a href="events.php?city_id=2">' . PRODUCT_URL . '/api/get/events.php?city_id=2</a> returns all events in Moscow. List of the cities and their ids can be obtained using <a href="cities.php?help">' . PRODUCT_URL . '/api/get/cities.php</a>.', '-');
		$help->request_param('area_id', 'City id. The difference with city is that when area is set, the events from all nearby cities are also returned. For example: <a href="events.php?area_id=2">' . PRODUCT_URL . '/api/get/events.php?area_id=2</a> returns all events in Moscow and nearby cities like Podolsk, Himki, etc. Though <a href="events.php?city_id=2">' . PRODUCT_URL . '/api/get/events.php?city_id=2</a> returns only the events in Moscow itself.', '-');
		$help->request_param('country_id', 'Country id. For example: <a href="events.php?country_id=2">' . PRODUCT_URL . '/api/get/events.php?country_id=2</a> returns all events in Russia. List of the countries and their ids can be obtained using <a href="countries.php?help">' . PRODUCT_URL . '/api/get/countries.php</a>.', '-');
		$help->request_param('rules_code', 'Rules code. For example: <a href="events.php?rules_code=00000000100101010200000000000">' . PRODUCT_URL . '/api/get/events.php?rules_code=00000000100101010200000000000</a> returns all events where the rules with the code 00000000100101010200000000000 was used. Please check <a href="rules.php?help">' . PRODUCT_URL . '/api/get/rules.php?help</a> for the meaning of rules codes and getting rules list.', '-');
		$help->request_param('user_id', 'User id. For example: <a href="events.php?user_id=25">' . PRODUCT_URL . '/api/get/events.php?user_id=25</a> returns all events where Fantomas was playing.', '-');
		$help->request_param('langs', 'Languages filter. A bit combination of language ids. For example: <a href="events.php?langs=1">' . PRODUCT_URL . '/api/get/events.php?langs=1</a> returns all events that support English as their language.' . valid_langs_help(), '-');
		$help->request_param('canceled', '0 - exclude canceled events (default); 1 - incude canceled events; 2 - canceled events only. For example: <a href="events.php?canceled=2">' . PRODUCT_URL . '/api/get/events.php?canceled=2</a> returns all canceled events.', '-');
		$help->request_param('lod', 'Level of details. 0 - basic (default); 1 - extended. Include club name/icon, city/country name, etc. For example: <a href="events.php?club=1&lod=1">' . PRODUCT_URL . '/api/get/events.php?club=1&lod=1</a> returns events with all the fields that have lod >= 1.', '-');
		$help->request_param('count', 'Returns events count instead of the events themselves. For example: <a href="events.php?name_contains=an&count">' . PRODUCT_URL . '/api/get/events.php?name_contains=an&count</a> returns how many events contain "an" in their name.', '-');
		$help->request_param('page', 'Page number. For example: <a href="events.php?page=1">' . PRODUCT_URL . '/api/get/events.php?page=1</a> returns the second page of events by time from newest to oldest.', '-');
		$help->request_param('page_size', 'Page size. Default page_size is ' . API_DEFAULT_PAGE_SIZE . '. For example: <a href="events.php?page_size=32">' . PRODUCT_URL . '/api/get/events.php?page_size=32</a> returns first 32 events; <a href="events.php?page_size=0">' . PRODUCT_URL . '/api/get/events.php?page_size=0</a> returns events in one page; <a href="events.php">' . PRODUCT_URL . '/api/get/events.php</a> returns first ' . API_DEFAULT_PAGE_SIZE . ' events by alphabet.', '-');

		$param = $help->response_param('events', 'The array of events. Events are always sorted in time order from newest to oldest. There is no way to change sorting order in the current version of the API.');
			$param->sub_param('id', 'Event id.');
			$param->sub_param('name', 'Event name.');
			$param->sub_param('icon', 'Event icon URL.', 1);
			$param->sub_param('picture', 'Event picture URL.', 1);
			$param->sub_param('langs', 'A bit combination of languages used in the event.' . valid_langs_help());
			$param->sub_param('address_id', 'Event address id.');
			$param->sub_param('address_name', 'Event address name.', 1);
			$param->sub_param('address_icon', 'Event address icon URL.', 1);
			$param->sub_param('address_picture', 'Event address picture URL.', 1);
			$param->sub_param('club_id', 'Club id of the event.');
			$param->sub_param('club_name', 'Event club name.', 1);
			$param->sub_param('club_icon', 'Event club icon.', 1);
			$param->sub_param('club_picture', 'Event club picture URL of the event.', 1);
			$param->sub_param('timestamp', 'Unix timestamp for the start of the event.');
			$param->sub_param('duration', 'Duration of the event in seconds.');
			$param->sub_param('start', 'Formatted date "yyyy-mm-dd HH:MM" for the start of the event. Event timezone is used.', 1);
			$param->sub_param('end', 'Formatted date "yyyy-mm-dd HH:MM" for the end of the event. Event timezone is used.', 1);
			$param->sub_param('notes', 'Event notes.');
			$param->sub_param('fee', 'Event admission rate.','fee is unknown');
			$param->sub_param('currency_id', 'Currency id for the admission rate.','fee is unknown');
			$param->sub_param('canceled', 'Trus for canceled events, false for others.');
			$param->sub_param('scoring_id', 'Scoring system id for this event.');
			$param->sub_param('scoring_version', 'The version of scoring system id for this event.');
			api_scoring_help($param->sub_param('scoring_options', 'Scoring options for this event.'));
			$param->sub_param('tournament_id', 'Tournament id when the event belongs to a tournament.', 'the event is not a tournament round.');
			$param->sub_param('tournament_name', 'Tournament name.', 'the event is not a tournament round.', 1);
			$param->sub_param('tournament_icon', 'Tournament icon URL.', 'the event is not a tournament round.', 1);
			$param->sub_param('tournament_picture', 'Tournament picture URL.', 'the event is not a tournament round.', 1);
			api_rules_help($param->sub_param('rules', 'Game rules used in the event.'), true);
	$help->response_param('count', 'Total number of events satisfying the request parameters.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Events', CURRENT_VERSION);

?>