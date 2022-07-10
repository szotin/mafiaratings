<?php

require_once '../../include/api.php';
require_once '../../include/tournament.php';
require_once '../../include/email.php';
require_once '../../include/message.php';
require_once '../../include/datetime.php';
require_once '../../include/scoring.php';
require_once '../../include/image.php';
require_once '../../include/game.php';

define('CURRENT_VERSION', 0);

function send_series_notification($filename, $tournament_id, $tournament_name, $club_id, $club_name, $series)
{
	global $_profile;
	
	// send emails to league managers notifying about the tournament participating in the series
	$query = new DbQuery(
		'SELECT u.id, u.name, u.email, u.def_lang, s.name, l.id, l.name FROM series s' .
		' JOIN leagues l ON l.id = s.league_id' .
		' JOIN league_managers lm ON lm.league_id = s.league_id' .
		' JOIN users u ON u.id = lm.user_id' .
		' WHERE s.id = ?', $series->id);
	while ($row = $query->next())
	{
		list($user_id, $user_name, $user_email, $user_lang, $series_name, $league_id, $league_name) = $row;
		if (!is_valid_lang($user_lang))
		{
			$user_lang = get_lang($league_langs);
			if (!is_valid_lang($user_lang))
			{
				$user_lang = LANG_RUSSIAN;
			}
		}
		list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email/' . $filename . '.php';
		$tags = array(
			'root' => new Tag(get_server_url()),
			'user_id' => new Tag($user_id),
			'user_name' => new Tag($user_name),
			'league_id' => new Tag($league_id),
			'league_name' => new Tag($league_name),
			'series_id' => new Tag($series->id),
			'series_name' => new Tag($series_name),
			'tournament_id' => new Tag($tournament_id),
			'tournament_name' => new Tag($tournament_name),
			'stars' => new Tag($series->stars),
			'stars_str' => new Tag(tournament_stars_str($series->stars)),
			'club_id' => new Tag($club_id),
			'club_name' => new Tag($club_name),
			'sender' => new Tag($_profile->user_name));
		$body = parse_tags($body, $tags);
		$text_body = parse_tags($text_body, $tags);
		send_email($user_email, $body, $text_body, $subj);
	}
}

function create_event($event_name, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, $scoring_options, $tournament_id, $rules_code)
{
	Db::exec(
		get_label('round'), 
		'INSERT INTO events (name, address_id, club_id, start_time, duration, notes, flags, languages, price, scoring_id, scoring_version, scoring_options, tournament_id, rules) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
		$event_name, $address_id, $club_id, $start, $end - $start, $notes, EVENT_MASK_HIDDEN | EVENT_FLAG_ALL_CAN_REFEREE, $langs, $price, $scoring_id, $scoring_version, $scoring_options, $tournament_id, $rules_code);
		
	$log_details = new stdClass();
	$log_details->name = $event_name;
	$log_details->tournament_id = $tournament_id;
	$log_details->club_id = $club_id; 
	$log_details->address_id = $address_id; 
	$log_details->start = $start;
	$log_details->duration = $end - $start;
	$log_details->langs = $langs;
	$log_details->notes = $notes;
	$log_details->price = $price;
	$log_details->scoring_id = $scoring_id;
	$log_details->scoring_version = $scoring_version;
	$log_details->scoring_options = $scoring_options;
	$log_details->rules_code = $rules_code;
	$log_details->flags = EVENT_MASK_HIDDEN | EVENT_FLAG_ALL_CAN_REFEREE;
	db_log(LOG_OBJECT_EVENT, 'round created', $log_details, $tournament_id, $club_id);
}

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile, $_lang_code;
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		$club = $_profile->clubs[$club_id];
		
		$name = get_required_param('name');
		if (empty($name))
		{
			throw new Exc(get_label('Please enter [0].', get_label('tournament name')));
		}
		
		$type = (int)get_optional_param('type', TOURNAMENT_TYPE_CUSTOM);
		$price = get_optional_param('price', '');
		$scoring_id = (int)get_optional_param('scoring_id', -1);
		$scoring_version = (int)get_optional_param('scoring_version', -1);
		$normalizer_id = (int)get_optional_param('normalizer_id', -1);
		$normalizer_version = (int)get_optional_param('normalizer_version', -1);
		$scoring_options = json_decode(get_optional_param('scoring_options', NULL));
		$scoring_options_str = json_encode($scoring_options);
		$series = json_decode(get_optional_param('series', '[]'));
		
		if ($normalizer_id <= 0)
		{
			if ($scoring_id <= 0)
			{
				list($scoring_id, $normalizer_id) = Db::record(get_label('club'), 'SELECT scoring_id, normalizer_id FROM clubs WHERE id = ?', $club_id);
			}
			else
			{
				list($normalizer_id) = Db::record(get_label('club'), 'SELECT normalizer_id FROM clubs WHERE id = ?', $club_id);
			}
		}
		else if ($scoring_id <= 0)
		{
			list($scoring_id) = Db::record(get_label('club'), 'SELECT scoring_id FROM clubs WHERE id = ?', $club_id);
		}
		
		if ($scoring_version < 0)
		{
			list($scoring_version) = Db::record(get_label('scoring'), 'SELECT MAX(version) FROM scoring_versions WHERE scoring_id = ?', $scoring_id);
		}
		if (!is_null($normalizer_id) && $normalizer_version < 0)
		{
			list($normalizer_version) = Db::record(get_label('scoring normalizer'), 'SELECT MAX(version) FROM normalizer_versions WHERE normalizer_id = ?', $normalizer_id);
		}
		
		$notes = get_optional_param('notes', '');
		$flags = (int)get_optional_param('flags', TOURNAMENT_FLAG_USE_ROUNDS_SCORING) & TOURNAMENT_EDITABLE_MASK;
		$langs = get_optional_param('langs', $club->langs);
		$rules_code = get_optional_param('rules_code', NULL);
		
		Db::begin();
		
		$address_id = (int)get_required_param('address_id');
		if ($address_id <= 0)
		{
			$address = htmlspecialchars(get_required_param('address'), ENT_QUOTES);
			check_address_name($address, $club_id);
			
			$city_id = (int)get_optional_param('city_id', 0);
			if ($city_id <= 0)
			{
				$city = get_required_param('city');
				$country_id = (int)get_optional_param('country_id', 0);
				if ($country_id <= 0)
				{
					$country_id = retrieve_country_id(get_required_param('country'));
				}
				$city_id = retrieve_city_id($city, $country_id, $club->timezone);
			}
			
			Db::exec(
				get_label('address'), 
				'INSERT INTO addresses (name, club_id, address, map_url, city_id, flags) values (?, ?, ?, \'\', ?, 0)',
				$address, $club_id, $address, $city_id);
			list ($address_id) = Db::record(get_label('address'), 'SELECT LAST_INSERT_ID()');
			
			$log_details = new stdClass();
			$log_details->name = $address;
			$log_details->address = $address;
			$log_details->city_id = $city_id;
			db_log(LOG_OBJECT_ADDRESS, 'created', $log_details, $address_id, $club_id);
	
			$warning = load_map_info($address_id);
			if ($warning != NULL)
			{
				echo '<p>' . $warning . '</p>';
			}
		}
		
		list($timezone) = Db::record(get_label('address'), 'SELECT c.timezone FROM addresses a JOIN cities c ON c.id = a.city_id WHERE a.id = ?', $address_id);
		$start_datetime = get_datetime(get_required_param('start'), $timezone);
		$end_datetime = get_datetime(get_required_param('end'), $timezone);
		$start = $start_datetime->getTimestamp();
		$end = $end_datetime->getTimestamp();
		if ($end <= $start)
		{
			throw new Exc(get_label('Tournament ends before or right after the start.'));
		}
		
		if ($rules_code == NULL)
		{
			$rules_code = $club->rules_code;
		}

		Db::exec(
			get_label('tournament'), 
			'INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, scoring_version, normalizer_id, normalizer_version, scoring_options, rules, flags) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$name, $club_id, $address_id, $start, $end - $start, $langs, $notes, $price, $scoring_id, $scoring_version, $normalizer_id, $normalizer_version, $scoring_options_str, $rules_code, $flags);
		list ($tournament_id) = Db::record(get_label('tournament'), 'SELECT LAST_INSERT_ID()');
		
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->club_id = $club_id; 
		$log_details->address_id = $address_id; 
		$log_details->start = $start;
		$log_details->duration = $end - $start;
		$log_details->langs = $langs;
		$log_details->notes = $notes;
		$log_details->price = $price;
		$log_details->scoring_id = $scoring_id;
		$log_details->scoring_version = $scoring_version;
		$log_details->normalizer_id = $normalizer_id;
		$log_details->normalizer_version = $normalizer_version;
		$log_details->scoring_options = $scoring_options_str;
		$log_details->rules_code = $rules_code;
		$log_details->flags = $flags;
		$log_details->series = json_encode($series);
		db_log(LOG_OBJECT_TOURNAMENT, 'created', $log_details, $tournament_id, $club_id);
		
		if (is_valid_lang($langs))
		{
			$lang_code = get_lang_code($langs);
		}
		else
		{
			$lang_code = $_lang_code;
		}
		$round_names = include '../../include/languages/' . $lang_code . '/rounds.php';
		switch ($type)
		{
			case TOURNAMENT_TYPE_FIIM_ONE_ROUND:
				$ops = new stdClass();
				if (isset($scoring_options->flags))
				{
					$ops->flags = $scoring_options->flags;
				}
				create_event($round_names->main, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code);
				break;
			case TOURNAMENT_TYPE_FIIM_TWO_ROUNDS_FINALS3:
				$ops = new stdClass();
				$ops->group = 'main';
				$ops->flags = SCORING_OPTION_NO_GAME_DIFFICULTY;
				if (isset($scoring_options->flags))
				{
					$ops->flags |= $scoring_options->flags;
				}
				create_event($round_names->main, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code);
				$ops->group = 'final';
				$ops->flags |= SCORING_OPTION_NO_NIGHT_KILLS;
				create_event($round_names->final, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code);
				break;
			case TOURNAMENT_TYPE_FIIM_TWO_ROUNDS_FINALS4:
				$ops = new stdClass();
				$ops->group = 'main';
				$ops->flags = SCORING_OPTION_NO_GAME_DIFFICULTY;
				if (isset($scoring_options->flags))
				{
					$ops->flags |= $scoring_options->flags;
				}
				create_event($round_names->main, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code);
				$ops->group = 'final';
				create_event($round_names->final, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code);
				break;
			case TOURNAMENT_TYPE_FIIM_THREE_ROUNDS_FINALS3:
				$ops = new stdClass();
				$ops->group = 'main';
				$ops->flags = SCORING_OPTION_NO_GAME_DIFFICULTY;
				if (isset($scoring_options->flags))
				{
					$ops->flags |= $scoring_options->flags;
				}
				create_event($round_names->main, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code);
				create_event($round_names->semi, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code);
				$ops->group = 'final';
				$ops->flags |= SCORING_OPTION_NO_NIGHT_KILLS;
				create_event($round_names->final, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code);
				break;
			case TOURNAMENT_TYPE_FIIM_THREE_ROUNDS_FINALS4:
				$ops = new stdClass();
				$ops->group = 'main';
				$ops->flags = SCORING_OPTION_NO_GAME_DIFFICULTY;
				if (isset($scoring_options->flags))
				{
					$ops->flags |= $scoring_options->flags;
				}
				create_event($round_names->main, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code);
				create_event($round_names->semi, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code);
				$ops->group = 'final';
				create_event($round_names->final, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code);
				break;
			case TOURNAMENT_TYPE_AML_ONE_ROUND:
				$ops = new stdClass();
				if (isset($scoring_options->flags))
				{
					$ops->flags = $scoring_options->flags;
				}
				create_event($round_names->main, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code);
				break;
			case TOURNAMENT_TYPE_AML_TWO_ROUNDS:
				$ops = new stdClass();
				if (isset($scoring_options->flags))
				{
					$ops->flags = $scoring_options->flags;
				}
				create_event($round_names->main, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code);
				$ops->weight = 1.5;
				create_event($round_names->final, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code);
				break;
			case TOURNAMENT_TYPE_AML_THREE_ROUNDS:
				$ops = new stdClass();
				if (isset($scoring_options->flags))
				{
					$ops->flags = $scoring_options->flags;
				}
				create_event($round_names->main, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code);
				$ops->weight = 1.2;
				create_event($round_names->semi, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code);
				$ops->weight = 1.5;
				create_event($round_names->final, $address_id, $club_id, $start, $end, $notes, $langs, $price, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code);
				break;
			default:
				break;
		}
		
		// create series records
		foreach ($series as $s)
		{
			Db::exec(
				get_label('sеriеs'), 
				'INSERT INTO series_tournaments (tournament_id, series_id, stars) values (?, ?, ?)',
				$tournament_id, $s->id, $s->stars);
			if (isset($s->finals) && $s->finals)
			{
				Db::exec(get_label('sеriеs'), 'UPDATE series SET finals_id = ? WHERE id = ?', $tournament_id, $s->id);
			}
			send_series_notification('tournament_series_add', $tournament_id, $name, $club_id, $club->name, $s);
		}
		
		Db::commit();
		$this->response['tournament_id'] = $tournament_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Create tournament.');
		$help->request_param('name', 'Tournament name.');
		$help->request_param('club_id', 'Club id.');
		$series_help = $help->request_param('series', 'Json array of series that this tournament belongs to. For example "[{id:2,stars:3},{id:4,stars:1,finals:true}]".', 'tournament does not belong to any series - same as "[]".');
			$series_help->sub_param('id', 'Series id');
			$series_help->sub_param('stars', 'Number of stars for this series.');
			$series_help->sub_param('finals', 'true/false - if this tournament is a series finals.', 'false');
		$help->request_param('start', 'Tournament start date. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.');
		$help->request_param('end', 'Tournament end date. Exclusive. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.');
		$help->request_param('price', 'Admission rate. Just a string explaing it.', 'empty.');
		$help->request_param('rules_code', 'Rules for this tournament.', 'default club rules are used.');
		$help->request_param('scoring_id', 'Scoring id for this tournament.', 'default club scoring system is used.');
		$help->request_param('scoring_version', 'Scoring version for this tournament.', 'the latest version of the system identified by scoring_id is used.');
		$help->request_param('normalizer_id', 'Normalizer id for this tournament.', 'default club scoring normalizer is used.');
		$help->request_param('normalizer_version', 'Normalizer version for this tournament.', 'the latest version of the system identified by normalizer_id is used.');
		api_scoring_help($help->request_param('scoring_options', 'Scoring options for this tournament.', 'null is used. All values are assumed to be default.'));
		$help->request_param('notes', 'Tournament notes. Just a text.', 'empty.');
		$help->request_param('langs', 'Languages on this tournament. A bit combination of 1 (English) and 2 (Russian). Other languages are not supported yet.', 'all club languages are used.');
		$help->request_param('flags', 'Tournament flags. A bit cobination of:<ol>' .
									'<li value="16">This is a long term tournament when set. Long term tournament is something like a season championship. Short-term tournament is a one day to one week competition.</li>' .
									'<li value="32">When a moderator starts a new game, they can assign it to the tournament even if the game is in a non-tournament or in any other tournament event.</li>' .
									'<li value="64">When a custom event is created, it can be assigned to this tournament as a round.</li>' .
									'<li value="128">Tournament rounds must use this tournament game rules.</li>' .
									'<li value="256">Tournament rounds must use this tournament scoring system.</li>' .
									'</ol>', '384 (=128+256) is used, which is a short term tournament enforcing rules and scoring system.');
		$help->request_param('address_id', 'Address id of the tournament.', '<q>address</q>, <q>city</q>, and <q>country</q> are used to create new address.');
		$help->request_param('address', 'When address_id is not set, <?php echo PRODUCT_NAME; ?> creates new address. This is the address line to create.', '<q>address_id</q> must be set');
		$help->request_param('country', 'When address_id is not set, <?php echo PRODUCT_NAME; ?> creates new address. This is the country name for the new address. If <?php echo PRODUCT_NAME; ?> can not find a country with this name, new country is created.', '<q>address_id</q> must be set');
		$help->request_param('city', 'When address_id is not set, <?php echo PRODUCT_NAME; ?> creates new address. This is the city name for the new address. If <?php echo PRODUCT_NAME; ?> can not find a city with this name, new city is created.', '<q>address_id</q> must be set');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile;
		$tournament_id = (int)get_required_param('tournament_id');
		
		Db::begin();
		
		list ($club_id, $old_name, $old_start, $old_duration, $old_timezone, $old_address_id, $old_scoring_id, $old_scoring_version, $old_normalizer_id, $old_normalizer_version, $old_scoring_options, $old_price, $old_langs, $old_notes, $old_flags) = 
			Db::record(get_label('tournament'), 'SELECT t.club_id, t.name, t.start_time, t.duration, ct.timezone, t.address_id, t.scoring_id, t.scoring_version, t.normalizer_id, t.normalizer_version, t.scoring_options, t.price, t.langs, t.notes, t.flags FROM tournaments t' . 
			' JOIN addresses a ON a.id = t.address_id' .
			' JOIN cities ct ON ct.id = a.city_id' .
			' WHERE t.id = ?', $tournament_id);
		
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		if (isset($_profile->clubs[$club_id]))
		{
			$club = $_profile->clubs[$club_id];
		}
		else
		{
			$club = new stdClass();
			list($club->name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $club_id);
		}
		
		$name = get_optional_param('name', $old_name);
		$price = get_optional_param('price', $old_price);
		$scoring_id = get_optional_param('scoring_id', $old_scoring_id);
		$scoring_version = get_optional_param('scoring_version', -1);
		$normalizer_id = get_optional_param('normalizer_id', $old_normalizer_id);
		$normalizer_version = get_optional_param('normalizer_version', -1);
		if ($normalizer_id <= 0)
		{
			$normalizer_id = NULL;
			$normalizer_version = NULL;
		}
		$scoring_options = get_optional_param('scoring_options', $old_scoring_options);
		
		if ($scoring_version < 0)
		{
			if ($scoring_id == $old_scoring_id)
			{
				$scoring_version = $old_scoring_version;
			}
			else
			{
				list($scoring_version) = Db::record(get_label('scoring'), 'SELECT MAX(version) FROM scoring_versions WHERE scoring_id = ?', $scoring_id);
			}
		}
		
		if (!is_null($normalizer_id) && $normalizer_version < 0)
		{
			if ($normalizer_id == $old_normalizer_id)
			{
				$normalizer_version = $old_normalizer_version;
			}
			else
			{
				list($normalizer_version) = Db::record(get_label('scoring normalizer'), 'SELECT MAX(version) FROM normalizer_versions WHERE normalizer_id = ?', $normalizer_id);
			}
		}
		
		$notes = get_optional_param('notes', $old_notes);
		$langs = get_optional_param('langs', $old_langs);
		$flags = (int)get_optional_param('flags', $old_flags);
		$flags = ($flags & TOURNAMENT_EDITABLE_MASK) + ($old_flags & ~TOURNAMENT_EDITABLE_MASK);
		
		$address_id = get_optional_param('address_id', $old_address_id);
		if ($address_id != $old_address_id)
		{
			list ($timzone) = Db::record(get_label('address'), 'SELECT c.timezone FROM addresses a JOIN cities c ON a.city_id = c.id WHERE a.id = ?', $address_id);
		}
		else
		{
			$timezone = $old_timezone;
		}
		
		$old_start_datetime = get_datetime($old_start, $old_timezone);
		$old_end_datetime = get_datetime($old_start + $old_duration, $old_timezone);
		$start_datetime = get_datetime(get_optional_param('start', datetime_to_string($old_start_datetime)), $timezone);
		$end_datetime = get_datetime(get_optional_param('end', datetime_to_string($old_end_datetime)), $timezone);
		$start = $start_datetime->getTimestamp();
		$end = $end_datetime->getTimestamp();
		$duration = $end - $start;
		if ($duration <= 0)
		{
			throw new Exc(get_label('Tournament ends before or right after the start.'));
		}
		
		$logo_uploaded = false;
		if (isset($_FILES['logo']))
		{
			upload_logo('logo', '../../' . TOURNAMENT_PICS_DIR, $tournament_id);
			
			$icon_version = (($flags & TOURNAMENT_ICON_MASK) >> TOURNAMENT_ICON_MASK_OFFSET) + 1;
			if ($icon_version > TOURNAMENT_ICON_MAX_VERSION)
			{
				$icon_version = 1;
			}
			$flags = ($flags & ~TOURNAMENT_ICON_MASK) + ($icon_version << TOURNAMENT_ICON_MASK_OFFSET);
			$logo_uploaded = true;
		}
		
		// update series records
		$series = json_decode(get_optional_param('series', NULL));
		$series_changed = false;
		$old_series = array();
		$query = new DbQuery('SELECT s.id, st.stars, s.finals_id FROM series_tournaments st JOIN series s ON s.id = st.series_id WHERE st.tournament_id = ?', $tournament_id);
		while ($row = $query->next())
		{
			$s = new stdClass();
			list($s->id, $s->stars, $finals_id) = $row;
			$s->finals = ($finals_id == $tournament_id);
			$old_series[$s->id] = $s;
		}
			
		if ($series != NULL)
		{
			foreach ($series as $s)
			{
				if (isset($old_series[$s->id]))
				{
					$os = $old_series[$s->id];
					$finals = isset($s->finals) ? $s->finals : false;
					if ($os->stars != $s->stars)
					{
						Db::exec(
							get_label('sеriеs'), 
							'UPDATE series_tournaments SET stars = ? WHERE series_id = ? AND tournament_id = ?', $s->stars, $s->id, $tournament_id);
						send_series_notification('tournament_series_change', $tournament_id, $name, $club_id, $club->name, $s);
						$series_changed = true;
					}
					if ($os->finals != $finals)
					{
						$finals_id = $finals ? $tournament_id : NULL;
						Db::exec(
							get_label('sеriеs'), 
							'UPDATE series SET finals_id = ? WHERE id = ?', $finals_id, $s->id);
						send_series_notification('tournament_series_change', $tournament_id, $name, $club_id, $club->name, $s);
						$series_changed = true;
					}
					unset($old_series[$s->id]);
				}
				else
				{
					Db::exec(
						get_label('sеriеs'), 
						'INSERT INTO series_tournaments (tournament_id, series_id, stars) values (?, ?, ?)',
						$tournament_id, $s->id, $s->stars);
					if (isset($s->finals) && $s->finals)
					{
						Db::exec(get_label('sеriеs'), 'UPDATE series SET finals_id = ? WHERE id = ?', $tournament_id, $s->id);
					}
					send_series_notification('tournament_series_add', $tournament_id, $name, $club_id, $club->name, $s);
					$series_changed = true;
				}
			}
		}
			
		foreach ($old_series as $series_id => $s)
		{
			Db::exec(
				get_label('sеriеs'), 
				'DELETE FROM series_tournaments WHERE tournament_id = ? AND series_id = ?', $tournament_id, $series_id);
			send_series_notification('tournament_series_remove', $tournament_id, $name, $club_id, $club->name, $s);
		}
		
		// update tournament
		Db::exec(
			get_label('tournament'), 
			'UPDATE tournaments SET name = ?, address_id = ?, start_time = ?, duration = ?, langs = ?, notes = ?, price = ?, scoring_id = ?, scoring_version = ?, normalizer_id = ?, normalizer_version = ?, scoring_options = ?, flags = ? WHERE id = ?',
			$name, $address_id, $start, $duration, $langs, $notes, $price, $scoring_id, $scoring_version, $normalizer_id, $normalizer_version, $scoring_options, $flags, $tournament_id);
		if (Db::affected_rows() > 0 || $series_changed)
		{
			if ($scoring_id != $old_scoring_id || $scoring_version != $old_scoring_version)
			{
				Db::exec(
					get_label('round'),
					'UPDATE events SET scoring_id = ?, scoring_version = ? WHERE tournament_id = ?', $scoring_id, $scoring_version, $tournament_id);
			}
			
			$log_details = new stdClass();
			if ($name != $old_name)
			{
				$log_details->name = $name;
			}
			if ($address_id != $old_address_id)
			{
				$log_details->address_id = $address_id;
			}
			if ($start != $old_start)
			{
				$log_details->start = $start;
			}
			if ($duration != $old_duration)
			{
				$log_details->duration = $duration;
			}
			if ($langs != $old_langs)
			{
				$log_details->langs = $langs;
			}
			if ($notes != $old_notes)
			{
				$log_details->notes = $notes;
			}
			if ($price != $old_price)
			{
				$log_details->price = $price;
			}
			if ($scoring_id != $old_scoring_id || $scoring_version != $old_scoring_version)
			{
				$log_details->scoring_id = $scoring_id;
				$log_details->scoring_version = $scoring_version;
			}
			if ($normalizer_id != $old_normalizer_id || $normalizer_version != $old_normalizer_version)
			{
				$log_details->normalizer_id = $normalizer_id;
				$log_details->normalizer_version = $normalizer_version;
			}
			if ($scoring_options != $old_scoring_options)
			{
				$log_details->scoring_options = $scoring_options;
			}
			if ($flags != $old_flags)
			{
				$log_details->flags = $flags;
			}
			if ($logo_uploaded)
			{
				$log_details->logo_uploaded = true;
			}
			if ($series_changed)
			{
				$log_details->series = json_encode($series);
			}
			db_log(LOG_OBJECT_TOURNAMENT, 'changed', $log_details, $tournament_id, $club_id);
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Change tournament.');
		$help->request_param('tournament_id', 'Tournament id.');
		$help->request_param('name', 'Tournament name.', 'remains the same.');
		$series_help = $help->request_param('series', 'Json array of series that this tournament belongs to. For example "[{id:2,stars:3},{id:4,stars:1,finals:true}]".', 'remains the same.');
			$series_help->sub_param('id', 'Series id');
			$series_help->sub_param('stars', 'Number of stars for this series.');
			$series_help->sub_param('finals', 'true/false - if this tournament is a series finals.', 'false');
		$help->request_param('start', 'Tournament start date. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.', 'remains the same.');
		$help->request_param('end', 'Tournament end date. Exclusive. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.', 'remains the same.');
		$help->request_param('price', 'Admission rate. Just a string explaing it.', 'remains the same.');
		$help->request_param('scoring_id', 'Scoring id for this tournament.', 'remains the same.');
		$help->request_param('scoring_version', 'Scoring version for this tournament.', 'remain the same, or set to the latest for current scoring if scoring_id is changed.');
		$help->request_param('normalizer_id', 'Normalizer id for this tournament.', 'remains the same.');
		$help->request_param('normalizer_version', 'Normalizer version for this tournament.', 'remain the same, or set to the latest for current normalizer if normalizer_id is changed.');
		api_scoring_help($help->request_param('scoring_options', 'Scoring options for this tournament.', 'remain the same.'));
		$help->request_param('notes', 'Tournament notes. Just a text.', 'remains the same.');
		$help->request_param('langs', 'Languages on this tournament. A bit combination of 1 (English) and 2 (Russian). Other languages are not supported yet.', 'remains the same.');
		$help->request_param('flags', 'Tournament flags. A bit cobination of:<ol>' .
									'<li value="16">This is a long term tournament when set. Long term tournament is something like a season championship. Short-term tournament is a one day to one week competition.</li>' .
									'<li value="32">When a moderator starts a new game, they can assign it to the tournament even if the game is in a non-tournament or in any other tournament event.</li>' .
									'<li value="64">When a custom event is created, it can be assigned to this tournament as a round.</li>' .
									'</ol>', 'remain the same.');
		$help->request_param('address_id', 'Address id of the tournament.', 'remains the same.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// cancel
	//-------------------------------------------------------------------------------------------------------
	function cancel_op()
	{
		$tournament_id = (int)get_required_param('tournament_id');
		
		Db::begin();
		list($club_id) = Db::record(get_label('tournament'), 'SELECT club_id FROM tournaments WHERE id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		
		Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = (flags | ' . TOURNAMENT_FLAG_CANCELED . ') WHERE id = ?', $tournament_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_TOURNAMENT, 'canceled', NULL, $tournament_id, $club_id);
		}
		Db::commit();
	}
	
	function cancel_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Cancel tournament.');
		$help->request_param('tournament_id', 'Tournament id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// restore
	//-------------------------------------------------------------------------------------------------------
	function restore_op()
	{
		$tournament_id = (int)get_required_param('tournament_id');
		
		Db::begin();
		list($club_id) = Db::record(get_label('tournament'), 'SELECT club_id FROM tournaments WHERE id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id);
		
		Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = (flags & ~' . TOURNAMENT_FLAG_CANCELED . ') WHERE id = ?', $tournament_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_TOURNAMENT, 'restored', NULL, $tournament_id, $club_id);
		}
		Db::commit();
	}
	
	function restore_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Restore canceled tournament.');
		$help->request_param('tournament_id', 'Tournament id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// change_player
	//-------------------------------------------------------------------------------------------------------
	function change_player_op()
	{
		$tournament_id = (int)get_required_param('tournament_id');
		$user_id = (int)get_required_param('user_id');
		$new_user_id = (int)get_optional_param('new_user_id', 0);
		$nickname = get_optional_param('nick', NULL);
		$changed = false;
		
		list($club_id) = Db::record(get_label('tournament'), 'SELECT club_id FROM tournaments WHERE id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		
		if ($user_id <= 0 || $new_user_id <= 0)
		{
			throw new Exc(get_label('Unknown [0]', get_label('player')));
		}
		
		Db::begin();
		if ($user_id != $new_user_id)
		{
			if ($nickname == NULL)
			{
				list($nickname) = Db::record(get_label('user'), 'SELECT name FROM users WHERE id = ?', $new_user_id);
			}
			Db::exec(get_label('registration'), 'UPDATE event_users eu JOIN events e ON eu.event_id = e.id SET eu.user_id = ?, eu.nickname = ? WHERE eu.user_id = ? AND e.tournament_id = ?', $new_user_id, $nickname, $user_id, $tournament_id);
			$changed = $changed || Db::affected_rows() > 0;
			
			Db::exec(get_label('registration'), 'UPDATE tournament_users SET user_id = ? WHERE user_id = ? AND tournament_id = ?', $new_user_id, $user_id, $tournament_id);
			$changed = $changed || Db::affected_rows() > 0;
		}
		else if ($nickname != NULL)
		{
			Db::exec(get_label('registration'), 'UPDATE event_users eu JOIN events e ON eu.event_id = e.id SET eu.nickname = ? WHERE eu.user_id = ? AND e.tournament_id = ?', $nickname, $user_id, $tournament_id);
			$changed = $changed || Db::affected_rows() > 0;
		}
		
		$query = new DbQuery('SELECT id, json, feature_flags, is_canceled FROM games WHERE tournament_id = ? AND result > 0', $tournament_id);
		while ($row = $query->next())
		{
			list ($game_id, $json, $feature_flags, $is_canceled) = $row;
			$game = new Game($json, $feature_flags);
			if ($game->change_user($user_id, $new_user_id, $nickname))
			{
				$game->update();
				$changed = true;
			}
		}
		
		if ($changed)
		{
			$log_details = new stdClass();
			$log_details->tournament_id = $tournament_id;
			$log_details->old_user_id = $user_id;
			$log_details->nickname = $nickname;
			db_log(LOG_OBJECT_USER, 'replaced', $log_details, $new_user_id);
		}
		Db::commit();
		
		$this->response['user_id'] = $new_user_id;
		$this->response['nickname'] = $nickname;
	}
	
	function change_player_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Change player on the tournament.');
		$help->request_param('tournament_id', 'Tournament id.');
		$help->request_param('user_id', 'User id of a player who played on this tournament.');
		$help->request_param('new_user_id', 'If it is different from user_id, player is replaced in this tournament with the player new_user_id.', 'user_id is used.');
		$help->request_param('nick', 'Nickname for this tournament. If it is empty, user name is used.', 'user name is used.');
		
		$help->response_param('user_id', 'New user id.');
		$help->response_param('nickname', 'New nickname.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// rebuild_places
	//-------------------------------------------------------------------------------------------------------
	function rebuild_places_op()
	{
		$tournament_id = (int)get_optional_param('tournament_id', 0);
		
		Db::begin();
		
		if ($tournament_id > 0)
		{
			Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = flags & ' . (~TOURNAMENT_FLAG_FINISHED) . ' WHERE id = ?', $tournament_id);
		}
		else
		{
			Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = flags & ' . (~TOURNAMENT_FLAG_FINISHED));
		}
		db_log(LOG_OBJECT_TOURNAMENT, 'rebuild_places', NULL, $tournament_id);
		Db::commit();
	}
	
	function rebuild_places_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Schedules tournament places for rebuild. It is needed when in user tournaments view the place taken is wrong.');
		$help->request_param('tournament_id', 'Tournament id to rebuild places.', 'places are rebuilt for all tournaments');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// comment
	//-------------------------------------------------------------------------------------------------------
	function comment_op()
	{
		global $_profile;
		
		check_permissions(PERMISSION_USER);
		$tournament_id = (int)get_required_param('id');
		$comment = prepare_message(get_required_param('comment'));
		$lang = detect_lang($comment);
		if ($lang == LANG_NO)
		{
			$lang = $_profile->user_def_lang;
		}
		
		Db::exec(get_label('comment'), 'INSERT INTO tournament_comments (time, user_id, comment, tournament_id, lang) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?)', $_profile->user_id, $comment, $tournament_id, $lang);
		
		list($tournament_id, $tournament_name, $tournament_start_time, $tournament_timezone, $tournament_addr) = 
			Db::record(get_label('tournament'), 
				'SELECT e.id, e.name, e.start_time, c.timezone, a.address FROM tournaments e' .
				' JOIN addresses a ON a.id = e.address_id' . 
				' JOIN cities c ON c.id = a.city_id' . 
				' WHERE e.id = ?', $tournament_id);
		
		$query = new DbQuery(
			'(SELECT u.id, u.name, u.email, u.flags, u.def_lang FROM users u' .
			' JOIN tournament_invitations ti ON u.id = ti.user_id' .
			' WHERE ti.status <> ' . TOURNAMENT_INVITATION_STATUS_DECLINED . ')' .
			' UNION DISTINCT ' .
			' (SELECT DISTINCT u.id, u.name, u.email, u.flags, u.def_lang FROM users u' .
			' JOIN tournament_comments c ON c.user_id = u.id' .
			' WHERE c.tournament_id = ?)', $tournament_id, $tournament_id);
		//echo $query->get_parsed_sql();
		while ($row = $query->next())
		{
			list($user_id, $user_name, $user_email, $user_flags, $user_lang) = $row;
			if ($user_id == $_profile->user_id || ($user_flags & USER_FLAG_MESSAGE_NOTIFY) == 0 || empty($user_email))
			{
				continue;
			}
		
			$code = generate_email_code();
			$request_base = get_server_url() . '/email_request.php?code=' . $code . '&user_id=' . $user_id;
			$tags = array(
				'root' => new Tag(get_server_url()),
				'user_id' => new Tag($user_id),
				'user_name' => new Tag($user_name),
				'tournament_id' => new Tag($tournament_id),
				'tournament_name' => new Tag($tournament_name),
				'tournament_date' => new Tag(format_date('l, F d, Y', $tournament_start_time, $tournament_timezone, $user_lang)),
				'tournament_time' => new Tag(format_date('H:i', $tournament_start_time, $tournament_timezone, $user_lang)),
				'addr' => new Tag($tournament_addr),
				'code' => new Tag($code),
				'sender' => new Tag($_profile->user_name),
				'message' => new Tag($comment),
				'url' => new Tag($request_base),
				'unsub' => new Tag('<a href="' . $request_base . '&unsub=1" target="_blank">', '</a>'));
			
			list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email/comment_tournament.php';
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			send_notification($user_email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_TOURNAMENT, $tournament_id, $code);
		}
	}
	
	function comment_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Leave a comment on the tournament.');
		$help->request_param('id', 'Tournament id.');
		$help->request_param('comment', 'Comment text.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Tournament Operations', CURRENT_VERSION);

?>