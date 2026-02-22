<?php

require_once '../../include/api.php';
require_once '../../include/tournament.php';
require_once '../../include/email.php';
require_once '../../include/message.php';
require_once '../../include/datetime.php';
require_once '../../include/scoring.php';
require_once '../../include/image.php';
require_once '../../include/game.php';
require_once '../../include/mwt.php';

define('CURRENT_VERSION', 0);

function send_series_notification($filename, $tournament_id, $tournament_name, $club_id, $club_name, $series)
{
	global $_profile;
	
	// send emails to league managers notifying about the tournament participating in the series
	$query = new DbQuery(
		'SELECT u.id, nu.name, u.email, u.def_lang, s.name, l.id, l.name'.
		' FROM series s' .
		' JOIN leagues l ON l.id = s.league_id' .
		' JOIN league_managers lm ON lm.league_id = s.league_id' .
		' JOIN users u ON u.id = lm.user_id' .
		' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0 AND (u.flags & '.USER_FLAG_ADMIN_NOTIFY.') <> 0'.
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
		send_email($user_email, $body, $text_body, $subj, admin_unsubscribe_url($user_id), $user_lang);
	}
}

function create_rounds($type, $langs, $scoring_options, $address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, $tournament_id, $rules_code, $tournament_flags)
{
	global $_lang;
	if (is_valid_lang($langs))
	{
		$lang_code = get_lang_code($langs);
	}
	else
	{
		$lang_code = get_lang_code($_lang);
	}
	switch ($type)
	{
		case TOURNAMENT_TYPE_FIIM_ONE_ROUND:
			$ops = new stdClass();
			$ops->group = 'main';
			if (isset($scoring_options->flags))
			{
				$ops->flags = $scoring_options->flags;
			}
			create_tournament_round($address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code, 0, $tournament_flags);
			break;
		case TOURNAMENT_TYPE_FIIM_TWO_ROUNDS_FINALS3:
			$ops = new stdClass();
			$ops->group = 'main';
			$ops->flags = SCORING_OPTION_NO_GAME_DIFFICULTY;
			if (isset($scoring_options->flags))
			{
				$ops->flags |= $scoring_options->flags;
			}
			create_tournament_round($address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code, 0, $tournament_flags);
			$ops->group = 'final';
			$ops->flags |= SCORING_OPTION_NO_NIGHT_KILLS;
			create_tournament_round($address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code, 1, $tournament_flags);
			break;
		case TOURNAMENT_TYPE_FIIM_TWO_ROUNDS_FINALS4:
			$ops = new stdClass();
			$ops->group = 'main';
			$ops->flags = SCORING_OPTION_NO_GAME_DIFFICULTY;
			if (isset($scoring_options->flags))
			{
				$ops->flags |= $scoring_options->flags;
			}
			create_tournament_round($address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code, 0, $tournament_flags);
			$ops->group = 'final';
			create_tournament_round($address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code, 1, $tournament_flags);
			break;
		case TOURNAMENT_TYPE_FIIM_THREE_ROUNDS_FINALS3:
			$ops = new stdClass();
			$ops->group = 'main';
			$ops->flags = SCORING_OPTION_NO_GAME_DIFFICULTY;
			if (isset($scoring_options->flags))
			{
				$ops->flags |= $scoring_options->flags;
			}
			create_tournament_round($address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code, 0, $tournament_flags);
			create_tournament_round($address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code, 2, $tournament_flags);
			$ops->group = 'final';
			$ops->flags |= SCORING_OPTION_NO_NIGHT_KILLS;
			create_tournament_round($address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code, 1, $tournament_flags);
			break;
		case TOURNAMENT_TYPE_FIIM_THREE_ROUNDS_FINALS4:
			$ops = new stdClass();
			$ops->group = 'main';
			$ops->flags = SCORING_OPTION_NO_GAME_DIFFICULTY;
			if (isset($scoring_options->flags))
			{
				$ops->flags |= $scoring_options->flags;
			}
			create_tournament_round($address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code, 0, $tournament_flags);
			create_tournament_round($address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code, 2, $tournament_flags);
			$ops->group = 'final';
			create_tournament_round($address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, json_encode($ops), $tournament_id, $rules_code, 1, $tournament_flags);
			break;
		default:
			break;
	}
}

function tournament_type_help_str()
{
	$str = 'Tournament type. Rounds for the tournament are created depending on this value. Possible values are:<ol>';
	$str .= '<li value="' . TOURNAMENT_TYPE_CUSTOM . '">Custom tournament. No rounds created. They will be created manually in the future.</li>';
	$str .= '<li value="' . TOURNAMENT_TYPE_FIIM_ONE_ROUND . '">Mini-tournament. FIIM tournament with only one round.</li>';
	$str .= '<li value="' . TOURNAMENT_TYPE_FIIM_TWO_ROUNDS_FINALS3 . '">Two rounds short. FIIM tournament with main round and final round. Final round has less than 4 games.</li>';
	$str .= '<li value="' . TOURNAMENT_TYPE_FIIM_TWO_ROUNDS_FINALS4 . '">Two rounds long. FIIM tournament with main round and final round. Final round has 4 games or more.</li>';
	$str .= '<li value="' . TOURNAMENT_TYPE_FIIM_THREE_ROUNDS_FINALS3 . '">Two rounds short. FIIM tournament with main round, semi-final round, and final round. Final round has less than 4 games.</li>';
	$str .= '<li value="' . TOURNAMENT_TYPE_FIIM_THREE_ROUNDS_FINALS4 . '">Two rounds long. FIIM tournament with main round, semi-final round, and final round. Final round has 4 games or more.</li>';
	$str .= '<li value="' . TOURNAMENT_TYPE_CHAMPIONSHIP . '">Seasonal championship. No rounds created. They will be created manually in the future.</li>';
	return $str;
}

function parse_id_from_url($url, $site_name)
{
	if (empty($url))
	{
		return NULL;
	}
	if (!is_null($url) && !is_numeric($url))
	{
		$url = parse_number_from_url($url, '/tournaments/');
		if ($url <= 0)
		{
			throw new Exc(get_label('Invalid [0] ID. Id has to be an integer, or a URL of the tournament in the [0] site.', $site_name));
		}
	}
	return $url;
}

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile, $_lang;
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		$club = $_profile->clubs[$club_id];
		
		if (is_sanctioned($club->city_id))
		{
			throw new Exc(get_label('Due to international sanctions against Russia, the creation of [0] in this country is currently unavailable.', get_label('tournaments')));
		}
		
		$name = get_required_param('name');
		if (empty($name))
		{
			throw new Exc(get_label('Please enter [0].', get_label('tournament name')));
		}
		
		$type = (int)get_optional_param('type', TOURNAMENT_TYPE_CUSTOM);
		$fee = (int)get_optional_param('fee', -1);
		if ($fee < 0)
		{
			$fee = NULL;
		}
		$currency_id = (int)get_optional_param('currency_id', $club->currency_id);
		if (!is_null($currency_id) && $currency_id <= 0)
		{
			$currency_id = NULL;
		}
		$players = (int)get_optional_param('players', 0);
		if ($players < 10)
		{
			$players = 0;
		}
		$scoring_id = (int)get_optional_param('scoring_id', -1);
		$scoring_version = (int)get_optional_param('scoring_version', -1);
		$normalizer_id = (int)get_optional_param('normalizer_id', -1);
		$normalizer_version = (int)get_optional_param('normalizer_version', -1);
		$scoring_options = json_decode(get_optional_param('scoring_options', NULL));
		$scoring_options_str = json_encode($scoring_options);
		$parent_series = json_decode(get_optional_param('parent_series', '[]'));
		
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
		$flags = (int)get_optional_param('flags', 0) & TOURNAMENT_EDITABLE_MASK;
		if (!is_permitted(PERMISSION_ADMIN))
		{
			$flags &= ~TOURNAMENT_ADMIN_EDITABLE_MASK;
		}
		
		$langs = get_optional_param('langs', $club->langs);
		$rules_code = get_optional_param('rules_code', NULL);
		
		Db::begin();
		
		// Check if elite flag should be set
		foreach ($parent_series as $s)
		{
			if ($s->stars > 1)
			{
				list($sflags) = Db::record(get_label('series'), 'SELECT flags FROM series WHERE id = ?', $s->id);
				if ($sflags & SERIES_FLAG_ELITE)
				{
					$flags |= TOURNAMENT_FLAG_ELITE;
					break;
				}
			}
		}
		
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
	
			$warning = load_map_info($address_id, '../../' . ADDRESS_PICS_DIR);
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
		$rules_code = check_rules_code($rules_code);

		Db::exec(
			get_label('tournament'), 
			'INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, fee, currency_id, num_players, scoring_id, scoring_version, normalizer_id, normalizer_version, scoring_options, rules, flags, type) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$name, $club_id, $address_id, $start, $end - $start, $langs, $notes, $fee, $currency_id, $players, $scoring_id, $scoring_version, $normalizer_id, $normalizer_version, $scoring_options_str, $rules_code, $flags, $type);
		list ($tournament_id) = Db::record(get_label('tournament'), 'SELECT LAST_INSERT_ID()');
		
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->club_id = $club_id; 
		$log_details->address_id = $address_id; 
		$log_details->start = $start;
		$log_details->duration = $end - $start;
		$log_details->langs = $langs;
		$log_details->notes = $notes;
		if (!is_null($fee) && !is_null($currency_id))
		{
			$log_details->fee = $fee;
			$log_details->currency_id = $currency_id;
		}
		if ($players > 0)
		{
			$log_details->players = $players;
		}
		$log_details->scoring_id = $scoring_id;
		$log_details->scoring_version = $scoring_version;
		$log_details->normalizer_id = $normalizer_id;
		$log_details->normalizer_version = $normalizer_version;
		$log_details->scoring_options = $scoring_options_str;
		$log_details->rules_code = $rules_code;
		$log_details->flags = $flags;
		$log_details->type = $type;
		$log_details->parent_series = json_encode($parent_series);
		db_log(LOG_OBJECT_TOURNAMENT, 'created', $log_details, $tournament_id, $club_id);
		
		create_rounds($type, $langs, $scoring_options, $address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, $tournament_id, $rules_code, $flags);
		
		// create parent series records
		foreach ($parent_series as $s)
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
		$series_help = $help->request_param('parent_series', 'Json array of series that this tournament belongs to. For example "[{id:2,stars:3},{id:4,stars:1,finals:true}]".', 'tournament does not belong to any series - same as "[]".');
			$series_help->sub_param('id', 'Series id');
			$series_help->sub_param('stars', 'Number of stars for this series.');
			$series_help->sub_param('finals', 'true/false - if this tournament is a series finals.', 'false');
		$help->request_param('type', tournament_type_help_str(), '0');
		$help->request_param('start', 'Tournament start date. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.');
		$help->request_param('end', 'Tournament end date. Exclusive. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.');
		$help->request_param('fee', 'Admission rate. When <0 - admission rate is unknown.', '0.');
		$help->request_param('currency_id', 'Currency id for the admission rate. When <=0 - admission rate is unknown.', '0.');
		$help->request_param('players', 'Expected number of players. Zero for unknown.', '0.');
		$help->request_param('rules_code', 'Rules for this tournament.', 'default club rules are used.');
		$help->request_param('scoring_id', 'Scoring id for this tournament.', 'default club scoring system is used.');
		$help->request_param('scoring_version', 'Scoring version for this tournament.', 'the latest version of the system identified by scoring_id is used.');
		$help->request_param('normalizer_id', 'Normalizer id for this tournament.', 'default club scoring normalizer is used.');
		$help->request_param('normalizer_version', 'Normalizer version for this tournament.', 'the latest version of the system identified by normalizer_id is used.');
		api_scoring_help($help->request_param('scoring_options', 'Scoring options for this tournament.', 'null is used. All values are assumed to be default.'));
		$help->request_param('notes', 'Tournament notes. Just a text.', 'empty.');
		$help->request_param('langs', 'Languages on this tournament. A bit combination language ids.' . valid_langs_help(), 'all club languages are used.');
		$help->request_param('flags', 'Tournament flags. A bit cobination of:<ol>' .
									'<li value="16">This is a long term tournament when set. Long term tournament is something like a season championship. Short-term tournament is a one day to one week competition.</li>' .
									'<li value="32">When a moderator starts a new game, they can assign it to the tournament even if the game is in a non-tournament or in any other tournament event.</li>' .
									'<li value="64">Tournament is pinned to the front page of the site (only site admins can do it)</li>' .
									'<li value="256">Teams tournament.</li>' .
									'<li value="512">No games information about this tournament - scores are entered manually.</li>' .
									'<li value="1024">This tournament has MVP as an award.</li>' .
									'<li value="2096">This tournament has best red as an award.</li>' .
									'<li value="4096">This tournament has best sheriff as an award.</li>' .
									'<li value="8192">This tournament has best black as an award.</li>' .
									'<li value="16384">This tournament has best don as an award.</li>' .
									'<li value="16777216">Games of the tournament are video streamed.</li>' .
									'</ol>', '0 is used.');
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
		
		list ($club_id, $old_name, $old_start, $old_duration, $old_timezone, $old_address_id, $old_scoring_id, $old_scoring_version, $old_normalizer_id, $old_normalizer_version, $old_scoring_options, $old_fee, $old_currency_id, $old_num_players, $old_langs, $old_notes, $old_flags, $old_type, $old_rules_code, $old_mwt_id, $old_imafia_id, $old_emo_id) = 
			Db::record(get_label('tournament'), 'SELECT t.club_id, t.name, t.start_time, t.duration, ct.timezone, t.address_id, t.scoring_id, t.scoring_version, t.normalizer_id, t.normalizer_version, t.scoring_options, t.fee, t.currency_id, t.num_players, t.langs, t.notes, t.flags, t.type, t.rules, t.mwt_id, t.imafia_id, t.emo_id FROM tournaments t' . 
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
		$fee = (int)get_optional_param('fee', $old_fee);
		if (!is_null($fee) && $fee < 0)
		{
			$fee = NULL;
		}
		$currency_id = (int)get_optional_param('currency_id', $old_currency_id);
		if (!is_null($currency_id) && $currency_id <= 0)
		{
			$currency_id = NULL;
		}
		$num_players = (int)get_optional_param('players', $old_num_players);
		if ($num_players < 10)
		{
			$num_players = 0;
		}
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
		$type = (int)get_optional_param('type', $old_type);
		$rules_code = check_rules_code(get_optional_param('rules_code', $old_rules_code));

		$update_flags = (int)get_optional_param('update_flags', 0);
		
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
		if (!is_permitted(PERMISSION_ADMIN))
		{
			$flags = ($flags & ~TOURNAMENT_ADMIN_EDITABLE_MASK) + ($old_flags & TOURNAMENT_ADMIN_EDITABLE_MASK);
		}
		
		$address_id = get_optional_param('address_id', $old_address_id);
		if ($address_id != $old_address_id)
		{
			list ($timezone) = Db::record(get_label('address'), 'SELECT c.timezone FROM addresses a JOIN cities c ON a.city_id = c.id WHERE a.id = ?', $address_id);
			update_tournament_stats($tournament_id);
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
		
		$mwt_id = parse_id_from_url(get_optional_param('mwt_id', $old_mwt_id), 'MWT');
		if ($mwt_id != $old_mwt_id)
		{
			$query = new DbQuery(
				'SELECT t.id, t.flags, t.name'.
				' FROM tournaments t'.
				' WHERE t.id <> ? AND t.mwt_id = ?', $tournament_id, $mwt_id);
			if ($row = $query->next())
			{
				list($mwt_tournament_id, $mwt_tournament_flags, $mwt_tournament_name) = $row;
				throw new Exc(get_label('[3] ID [0] is already used by <a href="tournament_info.php?id=[1]">[2]</a>', $mwt_id, $mwt_tournament_id, $mwt_tournament_name, 'MWT'));
			}
		}
		
		$imafia_id = parse_id_from_url(get_optional_param('imafia_id', $old_imafia_id), 'iMafia');
		if ($imafia_id != $old_imafia_id)
		{
			$query = new DbQuery(
				'SELECT t.id, t.flags, t.name'.
				' FROM tournaments t'.
				' WHERE t.id <> ? AND t.imafia_id = ?', $tournament_id, $imafia_id);
			if ($row = $query->next())
			{
				list($imafia_tournament_id, $imafia_tournament_flags, $imafia_tournament_name) = $row;
				throw new Exc(get_label('[3] ID [0] is already used by <a href="tournament_info.php?id=[1]">[2]</a>', $imafia_id, $imafia_tournament_id, $imafia_tournament_name, 'Emotion Games'));
			}
		}
		
		$emo_id = parse_id_from_url(get_optional_param('emo_id', $old_emo_id), 'Emotion.games');
		if ($emo_id != $old_emo_id)
		{
			$query = new DbQuery(
				'SELECT t.id, t.flags, t.name'.
				' FROM tournaments t'.
				' WHERE t.id <> ? AND t.emo_id = ?', $tournament_id, $emo_id);
			if ($row = $query->next())
			{
				list($emo_tournament_id, $emo_tournament_flags, $emo_tournament_name) = $row;
				throw new Exc(get_label('[3] ID [0] is already used by <a href="tournament_info.php?id=[1]">[2]</a>', $emo_id, $emo_tournament_id, $emo_tournament_name, 'Emotion.games'));
			}
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

		// Delete caches
		Db::exec(get_label('score'), 'DELETE FROM event_scores_cache WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)', $tournament_id);
		Db::exec(get_label('score'), 'DELETE FROM tournament_scores_cache WHERE tournament_id = ?', $tournament_id);
		
		// update parent series records
		$parent_series = json_decode(get_optional_param('parent_series', NULL));
		$parent_series_changed = false;
		if (!is_null($parent_series))
		{
			$old_parent_series = array();
			$query = new DbQuery('SELECT s.id, st.stars, s.finals_id FROM series_tournaments st JOIN series s ON s.id = st.series_id WHERE st.tournament_id = ?', $tournament_id);
			while ($row = $query->next())
			{
				$s = new stdClass();
				list($s->id, $s->stars, $finals_id) = $row;
				$s->finals = ($finals_id == $tournament_id);
				$old_parent_series[$s->id] = $s;
			}
			
			$flags &= ~TOURNAMENT_FLAG_ELITE;
			foreach ($parent_series as $s)
			{
				$changed = false;
				if (isset($old_parent_series[$s->id]))
				{
					$os = $old_parent_series[$s->id];
					$finals = isset($s->finals) ? $s->finals : false;
					if ($os->stars != $s->stars)
					{
						Db::exec(
							get_label('sеriеs'), 
							'UPDATE series_tournaments SET stars = ? WHERE series_id = ? AND tournament_id = ?', $s->stars, $s->id, $tournament_id);
						if ($flags & TOURNAMENT_FLAG_FINISHED)
						{
							Db::exec(get_label('series'), 'UPDATE series SET flags = flags | ' . SERIES_FLAG_DIRTY . ' WHERE id = ?', $s->id);
						}
						send_series_notification('tournament_series_change', $tournament_id, $name, $club_id, $club->name, $s);
						$changed = true;
					}
					if ($os->finals != $finals)
					{
						$finals_id = $finals ? $tournament_id : NULL;
						Db::exec(
							get_label('sеriеs'), 
							'UPDATE series SET finals_id = ? WHERE id = ?', $finals_id, $s->id);
						if ($flags & TOURNAMENT_FLAG_FINISHED)
						{
							Db::exec(get_label('series'), 'UPDATE series SET flags = flags | ' . SERIES_FLAG_DIRTY . ' WHERE id = ?', $s->id);
						}
						send_series_notification('tournament_series_change', $tournament_id, $name, $club_id, $club->name, $s);
						$changed = true;
					}
					unset($old_parent_series[$s->id]);
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
					$changed = true;
				}
				
				if ($changed)
				{
					if ($flags & TOURNAMENT_FLAG_FINISHED)
					{
						Db::exec(get_label('series'), 'UPDATE series SET flags = flags | ' . SERIES_FLAG_DIRTY . ' WHERE id = ?', $s->id);
					}
					$parent_series_changed = true;
				}
				
				if ($s->stars > 1)
				{
					list($sflags) = Db::record(get_label('series'), 'SELECT flags FROM series WHERE id = ?', $s->id);
					if ($sflags & SERIES_FLAG_ELITE)
					{
						$flags |= TOURNAMENT_FLAG_ELITE;
					}
				}
			}
			
			foreach ($old_parent_series as $parent_series_id => $s)
			{
				Db::exec(
					get_label('sеriеs'), 
					'DELETE FROM series_tournaments WHERE tournament_id = ? AND series_id = ?', $tournament_id, $parent_series_id);
				send_series_notification('tournament_series_remove', $tournament_id, $name, $club_id, $club->name, $s);
			}
		}
			
		// reset TOURNAMENT_FLAG_FINISHED flag if needed
		if (
			(($old_flags & TOURNAMENT_FLAG_MANUAL_SCORE) != 0 && (($flags & TOURNAMENT_FLAG_MANUAL_SCORE) == 0)) ||
			($old_flags & TOURNAMENT_FLAG_FORCE_NUM_PLAYERS) != ($flags & TOURNAMENT_FLAG_FORCE_NUM_PLAYERS) ||
			$old_num_players != $num_players ||
			$old_scoring_id != $scoring_id ||
			$old_scoring_options != $scoring_options ||
			$old_scoring_version != $scoring_version ||
			$old_normalizer_id != $normalizer_id ||
			$old_normalizer_version != $normalizer_version)
		{
			$flags &= ~TOURNAMENT_FLAG_FINISHED;
		}
		
		if ($type != $old_type)
		{
			list($games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games WHERE tournament_id = ?', $tournament_id);
			if ($games_count > 0)
			{
				throw new Exc(get_label('Unable to change tournament type because the tournament has already started.'));
			}
			
			Db::exec(get_label('round'), 'DELETE FROM event_regs WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)', $tournament_id);
			Db::exec(get_label('round'), 'DELETE FROM event_incomers WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)', $tournament_id);
			Db::exec(get_label('round'), 'DELETE FROM events WHERE tournament_id = ?', $tournament_id);
			
			create_rounds($type, $langs, $scoring_options, $address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, $tournament_id, $rules_code, $flags);
		}
		else 
		{
			if (
				$rules_code != $old_rules_code ||
				$scoring_id != $old_scoring_id || 
				$scoring_version != $old_scoring_version || 
				($old_flags & TOURNAMENT_FLAG_STREAMING) != ($flags & TOURNAMENT_FLAG_STREAMING))
			{
				if ($flags & TOURNAMENT_FLAG_STREAMING)
				{
					Db::exec(get_label('round'), 'UPDATE events SET rules = ?, scoring_id = ?, scoring_version = ?, flags = flags | ' . EVENT_FLAG_STREAMING . ' WHERE tournament_id = ?', $rules_code, $scoring_id, $scoring_version, $tournament_id);
				}
				else
				{
					Db::exec(get_label('round'), 'UPDATE events SET rules = ?, scoring_id = ?, scoring_version = ?, flags = flags & ~' . EVENT_FLAG_STREAMING . ' WHERE tournament_id = ?', $rules_code, $scoring_id, $scoring_version, $tournament_id);
				}
			}
			
			if ($scoring_options != $old_scoring_options)
			{
				$ops = json_decode($scoring_options);
				$old_ops = json_decode($old_scoring_options);
				if (isset($ops->flags) && (!isset($old_ops->flags) || $ops->flags != $old_ops->flags))
				{
					$query = new DbQuery('SELECT id, scoring_options FROM events WHERE tournament_id = ?', $tournament_id);
					while ($row = $query->next())
					{
						list ($round_id, $round_ops) = $row;
						$round_ops = json_decode($round_ops);
						$round_ops->flags = $ops->flags;
						Db::exec(get_label('round'), 'UPDATE events SET scoring_options = ? WHERE id = ?', json_encode($round_ops), $round_id);
					}
				}
			}
		}
	
		if ($rules_code != $old_rules_code && ($update_flags & UPDATE_FLAG_CLUB) != 0)
		{
			Db::exec(get_label('club'), 'UPDATE clubs SET rules = ? WHERE id = ?', $rules_code, $club_id);
			Db::exec(get_label('tournament'), 'UPDATE tournaments SET rules = ? WHERE club_id = ? AND start_time > UNIX_TIMESTAMP()', $rules_code, $club_id);
			Db::exec(get_label('events'), 'UPDATE events SET rules = ? WHERE club_id = ? AND start_time > UNIX_TIMESTAMP()', $rules_code, $club_id);
			if (isset($_profile->clubs[$club_id]))
			{
				$_profile->clubs[$club_id]->rules_code = $rules_code;
			}
		}
	
		// update tournament
		Db::exec(
			get_label('tournament'), 
			'UPDATE tournaments SET name = ?, address_id = ?, start_time = ?, duration = ?, langs = ?, notes = ?, fee = ?, currency_id = ?, num_players = ?, scoring_id = ?, scoring_version = ?, normalizer_id = ?, normalizer_version = ?, scoring_options = ?, flags = ?, type = ?, mwt_id = ?, imafia_id = ?, emo_id = ?, rules = ? WHERE id = ?',
			$name, $address_id, $start, $duration, $langs, $notes, $fee, $currency_id, $num_players, $scoring_id, $scoring_version, $normalizer_id, $normalizer_version, $scoring_options, $flags, $type, $mwt_id, $imafia_id, $emo_id, $rules_code, $tournament_id);
		if (Db::affected_rows() > 0 || $parent_series_changed)
		{
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
			if ($fee != $old_fee)
			{
				$log_details->fee = $fee;
			}
			if ($currency_id != $old_currency_id)
			{
				$log_details->currency_id = $currency_id;
			}
			if ($num_players != $old_num_players)
			{
				$log_details->num_players = $num_players;
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
			if ($old_mwt_id != $mwt_id)
			{
				$log_details->mwt_id = $mwt_id;
			}
			if ($old_imafia_id != $imafia_id)
			{
				$log_details->imafia_id = $imafia_id;
			}
			if ($old_emo_id != $emo_id)
			{
				$log_details->emo_id = $emo_id;
			}
			if ($logo_uploaded)
			{
				$log_details->logo_uploaded = true;
			}
			if ($parent_series_changed)
			{
				$log_details->parent_series = json_encode($parent_series);
			}
			if ($type != $old_type)
			{
				$log_details->type = $type;
			}
			if ($rules_code != $old_rules_code)
			{
				$log_details->rules_code = $rules_code;
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
		$series_help = $help->request_param('parent_series', 'Json array of series that this tournament belongs to. For example "[{id:2,stars:3},{id:4,stars:1,finals:true}]".', 'remains the same.');
			$series_help->sub_param('id', 'Series id');
			$series_help->sub_param('stars', 'Number of stars for this series.');
			$series_help->sub_param('finals', 'true/false - if this tournament is a series finals.', 'false');
		$help->request_param('type', tournament_type_help_str(), 'remains the same.');
		$help->request_param('start', 'Tournament start date. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.', 'remains the same.');
		$help->request_param('end', 'Tournament end date. Exclusive. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.', 'remains the same.');
		$help->request_param('fee', 'Admission rate. Send -1 if unknown. Zero means free.', 'remains the same.');
		$help->request_param('currency_id', 'Currency id for the admission rate. Send 0 if unknown.', 'remains the same.');
		$help->request_param('players', 'Expected number of players. Zero for unknown.', 'remains the same.');
		$help->request_param('rules_code', 'Rules for this tournament.', 'remains the same.');
		$help->request_param('scoring_id', 'Scoring id for this tournament.', 'remains the same.');
		$help->request_param('scoring_version', 'Scoring version for this tournament.', 'remain the same, or set to the latest for current scoring if scoring_id is changed.');
		$help->request_param('normalizer_id', 'Normalizer id for this tournament.', 'remains the same.');
		$help->request_param('normalizer_version', 'Normalizer version for this tournament.', 'remain the same, or set to the latest for current normalizer if normalizer_id is changed.');
		api_scoring_help($help->request_param('scoring_options', 'Scoring options for this tournament.', 'remain the same.'));
		$help->request_param('notes', 'Tournament notes. Just a text.', 'remains the same.');
		$help->request_param('langs', 'Languages on this tournament. A bit combination of language ids.' . valid_langs_help(), 'remains the same.');
		$help->request_param('flags', 'Tournament flags. A bit cobination of:<ol>' .
									'<li value="16">This is a long term tournament when set. Long term tournament is something like a season championship. Short-term tournament is a one day to one week competition.</li>' .
									'<li value="32">When a moderator starts a new game, they can assign it to the tournament even if the game is in a non-tournament or in any other tournament event.</li>' .
									'<li value="64">Tournament is pinned to the front page of the site (only site admins can do it)</li>' .
									'<li value="256">Teams tournament.</li>' .
									'<li value="512">No games information about this tournament - scores are entered manually.</li>' .
									'<li value="1024">This tournament has MVP as an award.</li>' .
									'<li value="2096">This tournament has best red as an award.</li>' .
									'<li value="4096">This tournament has best sheriff as an award.</li>' .
									'<li value="8192">This tournament has best black as an award.</li>' .
									'<li value="16384">This tournament has best don as an award.</li>' .
									'<li value="16777216">Games of the tournament are video streamed.</li>' .
									'</ol>', 'remain the same.');
		$help->request_param('address_id', 'Address id of the tournament.', 'remains the same.');
		$help->request_param('mwt_id', 'Id of this tournament on the MWT site. It can be either integer or MWT site URL for the tournament (for example: <a href="https://mafiaworldtour.com/tournaments/2898">https://mafiaworldtour.com/tournaments/2898</a>).', "remains the same");
		$help->request_param('imafia_id', 'Id of this tournament on the iMafia site. It can be either integer or iMafia site URL for the tournament (for example: <a href="https://mafiaworldtour.com/tournaments/2898">https://mafiaworldtour.com/tournaments/2898</a>).', "remains the same");
		$help->request_param('emo_id', 'Id of this tournament on the emotion.games site.', "remains the same");
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
		
		Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = ((flags | ' . TOURNAMENT_FLAG_CANCELED . ') & ~' . TOURNAMENT_FLAG_FINISHED .') WHERE id = ?', $tournament_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_TOURNAMENT, 'canceled', NULL, $tournament_id, $club_id);
		}
		Db::exec(get_label('score'), 'DELETE FROM event_scores_cache WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)', $tournament_id);
		Db::exec(get_label('score'), 'DELETE FROM tournament_scores_cache WHERE tournament_id = ?', $tournament_id);
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
		
		Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = (flags & ~' . (TOURNAMENT_FLAG_CANCELED | TOURNAMENT_FLAG_FINISHED) . ') WHERE id = ?', $tournament_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_TOURNAMENT, 'restored', NULL, $tournament_id, $club_id);
		}
		Db::exec(get_label('score'), 'DELETE FROM event_scores_cache WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)', $tournament_id);
		Db::exec(get_label('score'), 'DELETE FROM tournament_scores_cache WHERE tournament_id = ?', $tournament_id);
		Db::commit();
	}
	
	function restore_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Restore canceled tournament.');
		$help->request_param('tournament_id', 'Tournament id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$tournament_id = (int)get_required_param('tournament_id');
		$log_details = new stdClass();
		$prev_game_id = NULL;
		
		Db::begin();
		list($club_id) = Db::record(get_label('tournament'), 'SELECT club_id FROM tournaments WHERE id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		list($games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games WHERE tournament_id = ?', $tournament_id);
		if ($games_count > 0)
		{
			if (!is_permitted(PERMISSION_ADMIN))
			{
				throw new Exc(get_label('[0] games were played in this tournament. The operation is dangerous. Only site administrator can delete it. Please contact him at admin@mafiaratings.com.', $games_count));
			}
			
			$query = new DbQuery('SELECT id, end_time FROM games WHERE tournament_id = ? AND (flags & '.GAME_FLAG_RATING.') <> 0 ORDER BY end_time, id LIMIT 1', $tournament_id);
			if ($row = $query->next())
			{
				list($game_id, $end_time) = $row;
				$prev_game_id = NULL;
				$query = new DbQuery('SELECT id FROM games WHERE end_time < ? OR (end_time = ? AND id < ?) ORDER BY end_time DESC, id DESC', $end_time, $end_time, $game_id);
				if ($row = $query->next())
				{
					list($prev_game_id) = $row;
				}
				Game::rebuild_ratings($prev_game_id, $end_time);
				$log_details->rebuild_ratings = $prev_game_id;
			}
		}
		
		Db::exec(get_label('user'), 'DELETE FROM tournament_regs WHERE tournament_id = ?', $tournament_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->users = Db::affected_rows();
		}
		Db::exec(get_label('user'), 'DELETE FROM tournament_invitations WHERE tournament_id = ?', $tournament_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->invitations = Db::affected_rows();
		}
		Db::exec(get_label('team'), 'DELETE FROM tournament_teams WHERE tournament_id = ?', $tournament_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->team = Db::affected_rows();
		}
		Db::exec(get_label('comment'), 'DELETE FROM tournament_comments WHERE tournament_id = ?', $tournament_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->comments = Db::affected_rows();
		}
		Db::exec(get_label('user'), 'DELETE FROM tournament_places WHERE tournament_id = ?', $tournament_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->places = Db::affected_rows();
		}
		Db::exec(get_label('album'), 'DELETE FROM photo_albums WHERE tournament_id = ?', $tournament_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->photo_albums = Db::affected_rows();
		}
		Db::exec(get_label('video'), 'DELETE FROM videos WHERE tournament_id = ?', $tournament_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->videos = Db::affected_rows();
		}
		Db::exec(get_label('game'), 'DELETE FROM current_games WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)', $tournament_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->current_games = Db::affected_rows();
		}
		
		// delete games
		Db::exec(get_label('game'), 'UPDATE rebuild_ratings SET game_id = ? WHERE game_id IN (SELECT id FROM games WHERE tournament_id = ?)', $prev_game_id, $tournament_id); // it also triggers rebuilding mr_bonus_stats
		Db::exec(get_label('player'), 'DELETE FROM dons WHERE game_id IN (SELECT id FROM games WHERE tournament_id = ?)', $tournament_id);
		Db::exec(get_label('player'), 'DELETE FROM mafiosos WHERE game_id IN (SELECT id FROM games WHERE tournament_id = ?)', $tournament_id);
		Db::exec(get_label('player'), 'DELETE FROM sheriffs WHERE game_id IN (SELECT id FROM games WHERE tournament_id = ?)', $tournament_id);
		Db::exec(get_label('player'), 'DELETE FROM players WHERE game_id IN (SELECT id FROM games WHERE tournament_id = ?)', $tournament_id);
		Db::exec(get_label('game'), 'DELETE FROM objections WHERE game_id IN (SELECT id FROM games WHERE tournament_id = ?)', $tournament_id);
		Db::exec(get_label('game'), 'DELETE FROM game_issues WHERE game_id IN (SELECT id FROM games WHERE tournament_id = ?)', $tournament_id);
		Db::exec(get_label('game'), 'DELETE FROM mr_bonus_stats WHERE game_id IN (SELECT id FROM games WHERE tournament_id = ?)', $tournament_id);
		Db::exec(get_label('game'), 'DELETE FROM games WHERE tournament_id = ?', $tournament_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->games = Db::affected_rows();
		}
		
		// delete rounds
		$events_with_games = '';
		$sep = '';
		$query = new DbQuery('SELECT DISTINCT e.id FROM games g JOIN events e ON e.id = g.event_id WHERE e.tournament_id = ?', $tournament_id);
		while ($row = $query->next())
		{
			list($eid) = $row;
			$events_with_games .= $sep . $eid;
			$sep = ', ';
		}
		if (empty($events_with_games))
		{
			$del_condition1 = '';
			$del_condition2 = '';
		}
		else
		{
			$del_condition1 = ' AND event_id NOT IN('.$events_with_games.')';
			$del_condition2 = ' AND id NOT IN('.$events_with_games.')';
			Db::exec(get_label('round'), 'UPDATE events SET tournament_id = NULL, flags = flags & ~'.EVENT_MASK_HIDDEN.' WHERE id IN ('.$events_with_games.')');
			if (Db::affected_rows() > 0)
			{
				$log_details->rounds_updated = Db::affected_rows();
			}
		}
		
		Db::exec(get_label('score'), 'DELETE FROM event_scores_cache WHERE WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)'.$del_condition1, $tournament_id);
		Db::exec(get_label('score'), 'DELETE FROM  tournament_scores_cache WHERE tournament_id = ?', $tournament_id);
			
		Db::exec(get_label('user'), 'DELETE FROM event_regs WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)'.$del_condition1, $tournament_id);
		Db::exec(get_label('user'), 'DELETE FROM event_incomers WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)'.$del_condition1, $tournament_id);
		Db::exec(get_label('comment'), 'DELETE FROM event_comments WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)'.$del_condition1, $tournament_id);
		Db::exec(get_label('points'), 'DELETE FROM event_extra_points WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)'.$del_condition1, $tournament_id);
		Db::exec(get_label('mailing'), 'DELETE FROM event_mailings WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)'.$del_condition1, $tournament_id);
		Db::exec(get_label('place'), 'DELETE FROM event_places WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)'.$del_condition1, $tournament_id);
		Db::exec(get_label('album'), 'DELETE FROM photo_albums WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)'.$del_condition1, $tournament_id);
		Db::exec(get_label('video'), 'DELETE FROM videos WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)'.$del_condition1, $tournament_id);
		Db::exec(get_label('round'), 'DELETE FROM events WHERE tournament_id = ?'.$del_condition2, $tournament_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->rounds_deleted = Db::affected_rows();
		}
		
		// delete tournament
		Db::exec(get_label('series'), 'DELETE FROM series_tournaments WHERE tournament_id = ?', $tournament_id);
		Db::exec(get_label('tournament'), 'DELETE FROM tournaments WHERE id = ?', $tournament_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_TOURNAMENT, 'deleted', $log_details, $tournament_id, $club_id);
		}
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Cancel tournament.');
		$help->request_param('tournament_id', 'Tournament id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// finish
	//-------------------------------------------------------------------------------------------------------
	function finish_op()
	{
		$tournament_id = (int)get_required_param('tournament_id');
		$now = time();
		
		Db::begin();
		list($club_id, $start_time, $duration, $flags) = Db::record(get_label('tournament'), 'SELECT club_id, start_time, duration, flags FROM tournaments WHERE id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		if (($flags & TOURNAMENT_FLAG_FINISHED) == 0)
		{
			if ($now < $start_time)
			{
				$start_time = $now;
			}
			if ($start_time + $duration > $now)
			{
				$duration = $now - $start_time;
			}
			Db::exec(get_label('tournament'), 'UPDATE tournaments SET start_time = ?, duration = ? WHERE id = ?', $start_time, $duration, $tournament_id);
			$query = new DbQuery('SELECT id, start_time, duration FROM events WHERE tournament_id = ?', $tournament_id);
			while ($row = $query->next())
			{
				list($round_id, $round_start, $round_duration) = $row;
				if ($now < $round_start)
				{
					$round_start = $now;
				}
				if ($round_start + $round_duration > $now)
				{
					$round_duration = $now - $round_start;
				}
				Db::exec(get_label('round'), 'UPDATE events SET start_time = ?, duration = ? WHERE id = ?', $round_start, $round_duration, $round_id);
			}
			db_log(LOG_OBJECT_TOURNAMENT, 'finished', NULL, $tournament_id, $club_id);
		}
		Db::commit();
	}
	
	function finish_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Finish the tournament. After finishing the tournament within one hour players will get all series points for this tournament. Finish tournament functionality lets not to wait until the time expires and get the results quicker.');
		$help->request_param('tournament_id', 'Tournament id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// change_player
	//-------------------------------------------------------------------------------------------------------
	function change_player_op()
	{
		global $_lang;
		
		$tournament_id = (int)get_required_param('tournament_id');
		$user_id = (int)get_required_param('user_id');
		$new_user_id = (int)get_optional_param('new_user_id', 0);
		$nickname = get_optional_param('nick', NULL);
		$changed = false;
		
		list($club_id, $lat, $lon, $tournament_flags) = Db::record(get_label('tournament'), 'SELECT t.club_id, a.lat, a.lon, t.flags FROM tournaments t JOIN addresses a ON a.id = t.address_id WHERE t.id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		
		if ($user_id <= 0 || $new_user_id <= 0)
		{
			throw new Exc(get_label('Unknown [0]', get_label('player')));
		}
		
		Db::begin();
		if ($user_id != $new_user_id)
		{
			list($user_name, $user_city_id, $user_rating) = Db::record(get_label('user'), 'SELECT nu.name, u.city_id, u.rating FROM users u JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0 WHERE u.id = ?', $new_user_id);
			if ($nickname == NULL)
			{
				$nickname = $user_name; 
			}
			list($count) = Db::record(get_label('registration'), 'SELECT count(*) FROM event_regs eu JOIN events e ON eu.event_id = e.id WHERE eu.user_id = ? AND e.tournament_id = ?', $new_user_id, $tournament_id);
			if ($count > 0)
			{
				Db::exec(get_label('registration'), 'DELETE FROM event_regs WHERE user_id = ? AND event_id IN (SELECT id FROM events WHERE tournament_id = ?)', $user_id, $tournament_id);
			}
			else
			{
				Db::exec(get_label('registration'), 'UPDATE event_regs eu JOIN events e ON eu.event_id = e.id SET eu.user_id = ?, eu.nickname = ? WHERE eu.user_id = ? AND e.tournament_id = ?', $new_user_id, $nickname, $user_id, $tournament_id);
			}
			$changed = $changed || Db::affected_rows() > 0;
			
			list($count) = Db::record(get_label('registration'), 'SELECT count(*) FROM tournament_regs WHERE user_id = ? AND tournament_id = ?', $new_user_id, $tournament_id);
			if ($count > 0)
			{
				Db::exec(get_label('registration'), 'DELETE FROM tournament_regs WHERE user_id = ? AND tournament_id = ?', $user_id, $tournament_id);
			}
			else
			{
				Db::exec(get_label('registration'), 'UPDATE tournament_regs SET user_id = ?, city_id = ?, rating = ? WHERE user_id = ? AND tournament_id = ?', $new_user_id, $user_city_id, $user_rating, $user_id, $tournament_id);
			}
			update_tournament_stats($tournament_id, $lat, $lon, $tournament_flags);
			$changed = $changed || Db::affected_rows() > 0;
		}
		else if ($nickname != NULL)
		{
			Db::exec(get_label('registration'), 'UPDATE event_regs eu JOIN events e ON eu.event_id = e.id SET eu.nickname = ? WHERE eu.user_id = ? AND e.tournament_id = ?', $nickname, $user_id, $tournament_id);
			$changed = $changed || Db::affected_rows() > 0;
		}
		Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = flags & ~' . TOURNAMENT_FLAG_FINISHED . ' WHERE id = ?', $tournament_id);
		Db::exec(get_label('score'), 'DELETE FROM event_scores_cache WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)', $tournament_id);
		Db::exec(get_label('score'), 'DELETE FROM tournament_scores_cache WHERE tournament_id = ?', $tournament_id);
		
		$query = new DbQuery('SELECT id, json, feature_flags FROM games WHERE tournament_id = ?', $tournament_id);
		while ($row = $query->next())
		{
			list ($game_id, $json, $feature_flags) = $row;
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
			list($club_id) = Db::record(get_label('tournament'), 'SELECT club_id FROM tournaments WHERE id = ?', $tournament_id);
			check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
			Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = flags & ~' . TOURNAMENT_FLAG_FINISHED . ' WHERE id = ?', $tournament_id);
			Db::exec(get_label('score'), 'DELETE FROM event_scores_cache WHERE event_id IN (SELECT id FROM events WHERE tournament_id = ?)', $tournament_id);
			Db::exec(get_label('score'), 'DELETE FROM tournament_scores_cache WHERE tournament_id = ?', $tournament_id);
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
			Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = flags & ~' . TOURNAMENT_FLAG_FINISHED);
			Db::exec(get_label('score'), 'DELETE FROM event_scores_cache WHERE event_id IN (SELECT id FROM events WHERE tournament_id IS NOT NULL)');
			Db::exec(get_label('score'), 'DELETE FROM tournament_scores_cache WHERE tournament_id = ?', $tournament_id);
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
	// set_score
	//-------------------------------------------------------------------------------------------------------
	function set_score_op()
	{
		$tournament_id = (int)get_required_param('tournament_id');
		$user_id = (int)get_required_param('user_id');
		$points = (double)get_optional_param('points', 0);
		$bonus_points = get_optional_param('bonus_points', NULL);
		$shot_points = get_optional_param('shot_points', NULL);
		$games_count = get_optional_param('games_count', NULL);
		if (!is_null($games_count) && $games_count <= 0)
		{
			$games_count = NULL;
		}
		
		$bp = is_null($bonus_points) ? 0 : $bonus_points;
		$sp = is_null($shot_points) ? 0 : $shot_points;
		
		$main_points = $points - $bp - $sp;
		
		list($club_id, $flags) = Db::record(get_label('tournament'), 'SELECT club_id, flags FROM tournaments WHERE id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		
		if (($flags & TOURNAMENT_FLAG_MANUAL_SCORE) == 0)
		{
			throw new Exc(get_label('Scoring for tournament [0] is calculated automatically. Manual editing of the scores is not allowed. Set manual-editing flag first.'));
		}
		
		Db::begin();
		list ($records_count) = Db::record(get_label('score'), 'SELECT COUNT(*) FROM tournament_places WHERE tournament_id = ? AND user_id = ?', $tournament_id, $user_id);
		if ($records_count > 0)
		{
			list($place) = Db::record(get_label('score'), 'SELECT place FROM tournament_places WHERE tournament_id = ? AND user_id = ?', $tournament_id, $user_id);
			Db::exec(get_label('score'), 'DELETE FROM tournament_places WHERE tournament_id = ? AND user_id = ?', $tournament_id, $user_id);
			Db::exec(get_label('score'), 'UPDATE tournament_places SET place = place - 1 WHERE tournament_id = ? AND place > ?', $tournament_id, $place);
		}
		
		Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = flags & ~' . TOURNAMENT_FLAG_FINISHED . ' WHERE id = ?', $tournament_id);
		Db::exec(get_label('score'), 'DELETE FROM event_scores_cache WHERE event_id IN (SELECT id FROM events WHERE tournament_id IS NOT NULL)');
		Db::exec(get_label('score'), 'DELETE FROM tournament_scores_cache WHERE tournament_id = ?', $tournament_id);
		list($place) = Db::record(get_label('score'), 'SELECT count(*) FROM tournament_places WHERE tournament_id = ? AND (main_points + IFNULL(bonus_points,0) + IFNULL(shot_points,0) - ? > 0.0001 OR (main_points + IFNULL(bonus_points,0) + IFNULL(shot_points,0) - ? > -0.001 AND (IFNULL(bonus_points,0) - ? > 0.0001 OR (IFNULL(bonus_points,0) - ? > -0.001 AND user_id < ?))))', $tournament_id, $points, $points, $bp, $bp, $user_id);
		++$place;
		Db::exec(get_label('score'), 'UPDATE tournament_places SET place = place + 1 WHERE tournament_id = ? AND place >= ?', $tournament_id, $place);
		Db::exec(get_label('score'), 'INSERT INTO tournament_places (tournament_id, user_id, place, main_points, bonus_points, shot_points, games_count) VALUES (?, ?, ?, ?, ?, ?, ?)', $tournament_id, $user_id, $place, $main_points, $bonus_points, $shot_points, $games_count);

		$log_details = new stdClass();
		$log_details->tournament_id = $tournament_id;
		$log_details->user_id = $user_id;
		$log_details->main_points = $main_points;
		$log_details->bonus_points = $bonus_points;
		$log_details->shot_points = $shot_points;
		$log_details->games_count = $games_count;
		db_log(LOG_OBJECT_USER, 'set score', $log_details, $user_id);
		Db::commit();
	}
	
	function set_score_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Manually add player score for a tournament. Tournament must have no games. This is only for the tournaments where game inforation is missing.');
		
		$help->request_param('tournament_id', 'Tournament id.');
		$help->request_param('user_id', 'User id of a player who played on this tournament.');
		$help->request_param('points', 'Total points of the player. Bonus and shot points are included', '0.');
		$help->request_param('bonus_points', 'Bonus (extra) poinst for the player.', 'null, which is unknown.');
		$help->request_param('shot_points', 'Poinst for being shot first night for the player.', 'null, which is unknown');
		$help->request_param('games_count', 'Number of games played.', 'null, which is unknown');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// remove_score
	//-------------------------------------------------------------------------------------------------------
	function remove_score_op()
	{
		$tournament_id = (int)get_required_param('tournament_id');
		$user_id = (int)get_required_param('user_id');
		
		list($club_id, $flags) = Db::record(get_label('tournament'), 'SELECT club_id, flags FROM tournaments WHERE id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		
		if (($flags & TOURNAMENT_FLAG_MANUAL_SCORE) == 0)
		{
			throw new Exc(get_label('Scoring for tournament [0] is calculated automatically. Manual editing of the scores is not allowed. Set manual-editing flag first.'));
		}
		
		Db::begin();
		Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = flags & ~' . TOURNAMENT_FLAG_FINISHED . ' WHERE id = ?', $tournament_id);
		list($place) = Db::record(get_label('score'), 'SELECT place FROM tournament_places WHERE tournament_id = ? AND user_id = ?', $tournament_id, $user_id);
		Db::exec(get_label('score'), 'DELETE FROM tournament_places WHERE tournament_id = ? AND user_id = ?', $tournament_id, $user_id);
		Db::exec(get_label('score'), 'UPDATE tournament_places SET place = place - 1 WHERE tournament_id = ? AND place > ?', $tournament_id, $place);
		Db::exec(get_label('score'), 'DELETE FROM event_scores_cache WHERE event_id IN (SELECT id FROM events WHERE tournament_id IS NOT NULL)');
		Db::exec(get_label('score'), 'DELETE FROM tournament_scores_cache WHERE tournament_id = ?', $tournament_id);
		
		$log_details = new stdClass();
		$log_details->tournament_id = $tournament_id;
		$log_details->user_id = $user_id;
		db_log(LOG_OBJECT_USER, 'removed score', $log_details, $user_id);
		Db::commit();
	}
	
	function remove_score_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Manually remove player score for a tournament. Tournament must have no games. This is only for the tournaments where game inforation is missing.');
		
		$help->request_param('tournament_id', 'Tournament id.');
		$help->request_param('user_id', 'User id of a player who played on this tournament.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// comment
	//-------------------------------------------------------------------------------------------------------
	function comment_op()
	{
		global $_profile, $_lang;
		
		check_permissions(PERMISSION_USER);
		$tournament_id = (int)get_required_param('id');
		$comment = prepare_message(get_required_param('comment'));
		$lang = detect_lang($comment);
		if ($lang == LANG_NO)
		{
			$lang = $_lang;
		}
		
		Db::exec(get_label('comment'), 'INSERT INTO tournament_comments (time, user_id, comment, tournament_id, lang) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?)', $_profile->user_id, $comment, $tournament_id, $lang);
		
		list($tournament_id, $tournament_name, $tournament_start_time, $tournament_duration, $tournament_timezone, $tournament_addr) = 
			Db::record(get_label('tournament'), 
				'SELECT e.id, e.name, e.start_time, e.duration, c.timezone, a.address FROM tournaments e' .
				' JOIN addresses a ON a.id = e.address_id' . 
				' JOIN cities c ON c.id = a.city_id' . 
				' WHERE e.id = ?', $tournament_id);
		
		$query = new DbQuery(
			'(SELECT u.id, nu.name, u.email, u.flags, u.def_lang'.
			' FROM users u' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
			' JOIN tournament_invitations ti ON u.id = ti.user_id' .
			' WHERE ti.status <> ' . TOURNAMENT_INVITATION_STATUS_DECLINED . ')' .
			' UNION DISTINCT ' .
			' (SELECT DISTINCT u.id, nu.name, u.email, u.flags, u.def_lang'.
			' FROM users u' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
			' JOIN tournament_comments c ON c.user_id = u.id' .
			' WHERE c.tournament_id = ?)', $tournament_id, $tournament_id);
		//echo $query->get_parsed_sql();
		while ($row = $query->next())
		{
			list($user_id, $user_name, $user_email, $user_flags, $user_lang) = $row;
			if ($user_id == $_profile->user_id || ($user_flags & USER_FLAG_NOTIFY) == 0 || empty($user_email))
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
				'tournament_date' => new Tag(format_date_period($tournament_start_time, $tournament_timezone, false, $user_lang)),
				'addr' => new Tag($tournament_addr),
				'code' => new Tag($code),
				'sender' => new Tag($_profile->user_name),
				'message' => new Tag($comment),
				'url' => new Tag($request_base),
				'unsub' => new Tag('<a href="' . $request_base . '&unsub=1" target="_blank">', '</a>'));
			
			list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email/comment_tournament.php';
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			send_notification($user_email, $body, $text_body, $subj, $user_id, $user_lang, EMAIL_OBJ_TOURNAMENT, $tournament_id, $code);
		}
	}
	
	function comment_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Leave a comment on the tournament.');
		$help->request_param('id', 'Tournament id.');
		$help->request_param('comment', 'Comment text.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// payment
	//-------------------------------------------------------------------------------------------------------
	function payment_op()
	{
		$tournament_id = (int)get_required_param('tournament_id');
		$series_id = (int)get_required_param('series_id');
		$payment = get_optional_param('payment', NULL);
		$not_payed = get_optional_param('not_payed', NULL);
		
		Db::begin();
		list($club_id, $league_id, $series_fee, $old_series_flags, $old_payment, $num_players) = Db::record(get_label('tournament'), 
			'SELECT t.club_id, s.league_id, s.fee, st.flags, st.fee, t.num_players'.
			' FROM series_tournaments st'.
			' JOIN series s ON s.id = st.series_id'.
			' JOIN tournaments t ON t.id = st.tournament_id'.
			' WHERE st.series_id = ? AND st.tournament_id = ?', $series_id, $tournament_id);
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		if (is_null($payment))
		{
			$payment = (int)$old_payment;
		}
		else if ($payment < 0)
		{
			$payment = NULL;
		}
		else
		{
			$payment = (int)$payment;
		}
		if (!is_null($payment) && $payment == $num_players * $series_fee)
		{
			$payment = NULL;
		}
		
		$series_flags = $old_series_flags;
		if (!is_null($not_payed))
		{
			if ($not_payed)
			{
				$series_flags |= SERIES_TOURNAMENT_FLAG_NOT_PAYED;
			}
			else
			{
				$series_flags &= ~SERIES_TOURNAMENT_FLAG_NOT_PAYED;
			}
		}
		Db::exec(get_label('tournament'), 'UPDATE series_tournaments SET flags = ?, fee = ? WHERE tournament_id = ? AND series_id = ?', $series_flags, $payment, $tournament_id, $series_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->tournament_id = $tournament_id;
			$log_details->series_id = $series_id;
			if ($payment != $old_payment)
			{
				$log_details->payment = $payment;
			}
			if ($series_flags != $old_series_flags)
			{
				$log_details->flags = $series_flags;
			}
			db_log(LOG_OBJECT_TOURNAMENT, 'payment changed', $log_details, $tournament_id, $club_id);
		}
		Db::commit();
	}
	
	function payment_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Finish the tournament. After finishing the tournament within one hour players will get all series points for this tournament. Finish tournament functionality lets not to wait until the time expires and get the results quicker.');
		$help->request_param('tournament_id', 'Tournament id.');
		$help->request_param('series_id', 'Series id.');
		$help->request_param('payment', 'How much the organizers payed to the series league. Currency of the series is used. Send negative value for the default payment = series_fee * number_of_players', 'payment remains the same');
		$help->request_param('not_payed', '0 if everything is ok with the tournament; 1 if there is no payment. In case of 1 the results won\'t count in the series.', 'remains the same');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// add_registration
	//-------------------------------------------------------------------------------------------------------
	function add_registration_op()
	{
		global $_profile;
		
		$owner_id = 0;
		if ($_profile != NULL)
		{
			$owner_id = $_profile->user_id;
		}
		
		$user_id = (int)get_optional_param('user_id', $owner_id);
		$tournament_id = (int)get_required_param('tournament_id');
		$team = get_optional_param('team', NULL);
		$city_id = (int)get_optional_param('city_id', 0);
		$flags = (int)get_optional_param('access_flags', USER_PERM_PLAYER) & USER_PERM_MASK;
		if ($flags == 0)
		{
			throw new Exc(get_label('Please choose at least one role for the user.'));
		}
		$flags += USER_TOURNAMENT_NEW_PLAYER_FLAGS;
		
		Db::begin();
		list ($user_city_id, $user_rating, $user_club_id) = Db::record(get_label('user'), 'SELECT city_id, rating, club_id FROM users WHERE id = ?', $user_id);
		if ($city_id == NULL)
		{
			$city_id = $user_city_id;
		}
		else if ($city_id != $user_city_id && is_permitted(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, $user_id, $user_club_id))
		{
			Db::exec(get_label('city'), 'UPDATE users SET city_id = ? WHERE id = ?', $city_id, $user_id);
		}
		
		list($club_id, $lat, $lon, $tournament_flags) = Db::record(get_label('tournament'), 'SELECT t.club_id, a.lat, a.lon, t.flags FROM tournaments t JOIN addresses a ON a.id = t.address_id WHERE t.id = ?', $tournament_id);
		
		if (!is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id))
		{
			if ($user_id == $owner_id)
			{
				$flags |= USER_TOURNAMENT_FLAG_NOT_ACCEPTED; 
			}
			else
			{
				no_permission();
			}
		}
		
		$query = new DbQuery('SELECT t.id, t.name, u.flags, u.city_id, u.rating FROM tournament_regs u LEFT OUTER JOIN tournament_teams t ON u.team_id = t.id WHERE u.user_id = ? AND u.tournament_id = ?', $user_id, $tournament_id);
		if ($row = $query->next())
		{
			list($old_team_id, $old_team, $old_flags, $old_city_id, $old_rating) = $row;
			if (($tournament_flags & TOURNAMENT_FLAG_TEAM) != 0 && $team != $old_team)
			{
				if ($team == NULL || empty($team))
				{
					Db::exec(get_label('registration'), 'UPDATE tournament_regs SET team_id = NULL, flags = ?, city_id = ?, rating = ? WHERE user_id = ? AND tournament_id = ?', $flags, $city_id, $user_rating, $user_id, $tournament_id);
				}
				else
				{
					$query = new DbQuery('SELECT id FROM tournament_teams WHERE name = ?', $team);
					if ($row = $query->next())
					{
						list($team_id) = $row;
					}
					else
					{
						Db::exec(get_label('team'), 'INSERT INTO tournament_teams (tournament_id, name) VALUES (?, ?)', $tournament_id, $team);
						list ($team_id) = Db::record(get_label('team'), 'SELECT LAST_INSERT_ID()');
					}
					Db::exec(get_label('registration'), 'UPDATE tournament_regs SET team_id = ?, flags = ?, city_id = ?, rating = ? WHERE user_id = ? AND tournament_id = ?', $team_id, $flags, $city_id, $user_rating, $user_id, $tournament_id);
				}
				
				list($count) = Db::record(get_label('registration'), 'SELECT count(*) FROM tournament_regs WHERE team_id = ? AND tournament_id = ?', $old_team_id, $tournament_id);
				if ($count <= 0)
				{
					Db::exec(get_label('team'), 'DELETE FROM tournament_teams WHERE id = ?', $old_team_id);
				}
			}
			else if ($flags != $old_flags || $city_id != $old_city_id || $old_rating != $user_rating)
			{
				Db::exec(get_label('registration'), 'UPDATE tournament_regs SET flags = ?, city_id = ?, rating = ? WHERE user_id = ? AND tournament_id = ?', $flags, $city_id, $user_rating, $user_id, $tournament_id);
			}
		}
		else if ($tournament_flags & TOURNAMENT_FLAG_TEAM)
		{
			$team_id = NULL;
			if ($team != NULL && !empty($team))
			{
				$query = new DbQuery('SELECT id FROM tournament_teams WHERE name = ?', $team);
				if ($row = $query->next())
				{
					list($team_id) = $row;
				}
				else
				{
					Db::exec(get_label('team'), 'INSERT INTO tournament_teams (tournament_id, name) VALUES (?, ?)', $tournament_id, $team);
					list ($team_id) = Db::record(get_label('team'), 'SELECT LAST_INSERT_ID()');
				}
			}
			Db::exec(get_label('registration'), 'INSERT INTO tournament_regs (user_id, tournament_id, flags, team_id, city_id, rating) values (?, ?, ?, ?, ?, ?)', $user_id, $tournament_id, $flags, $team_id, $city_id, $user_rating);
			$log_details = new stdClass();
			$log_details->tournament_id = $tournament_id;
			if ($team_id != NULL)
			{
				$log_details->team = $team;
			}
			db_log(LOG_OBJECT_USER, 'joined tournament', $log_details, $user_id, $club_id);
		}
		else
		{
			Db::exec(get_label('registration'), 'INSERT INTO tournament_regs (user_id, tournament_id, flags, city_id, rating) values (?, ?, ?, ?, ?)', $user_id, $tournament_id, $flags, $city_id, $user_rating);
			$log_details = new stdClass();
			$log_details->tournament_id = $tournament_id;
			db_log(LOG_OBJECT_USER, 'joined tournament', $log_details, $user_id, $club_id);
		}
		
		update_tournament_stats($tournament_id, $lat, $lon, $tournament_flags);
		Db::commit();
		
		$this->response['tournament_id'] = $tournament_id;
		$this->response['user_id'] = $user_id;
		
		if ($flags & USER_TOURNAMENT_FLAG_NOT_ACCEPTED)
		{
			echo get_label('You application is submitted. The tournament organizers will review your application and contact you if necessary.');
		}
	}
	
	function add_registration_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Register user to the tournament.');
		$help->request_param('user_id', 'User id. If the user is a member already success is returned anyway.', 'the one who is making request is used.');
		$help->request_param('tournament_id', 'Tournament id.');
		$help->response_param('city_id', 'City where this user lives. If the caller is authorized to change user profile, this city becomes the user home city.', 'The city from user profile is used.');
		$help->response_param('team', 'Team name for this user. Works for team tournaments only.', 'user is registered without a team.');
		$help->response_param('access_flags', 'A bit-set of user permissions for this toournament. 1 - player; 2 - referee; 4 - manager.', '1 - player.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// edit_registration
	//-------------------------------------------------------------------------------------------------------
	function edit_registration_op()
	{
		global $_profile, $_lang;
		
		$owner_id = 0;
		if ($_profile != NULL)
		{
			$owner_id = $_profile->user_id;
		}
		
		$tournament_id = (int)get_required_param('tournament_id');
		$user_id = (int)get_optional_param('user_id', $owner_id);
		
		Db::begin();
		list($club_id, $lat, $lon, $tournament_flags) = Db::record(get_label('tournament'), 'SELECT t.club_id, a.lat, a.lon, t.flags FROM tournaments t JOIN addresses a ON a.id = t.address_id WHERE t.id = ?', $tournament_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $user_id, $club_id, $tournament_id);
		
		list ($old_team_id, $old_team, $old_city_id, $old_city_name, $user_club_id, $old_flags) = Db::record(get_label('user'), 
			'SELECT tu.team_id, t.name, tu.city_id, n.name, u.club_id, tu.flags'.
			' FROM tournament_regs tu'.
			' JOIN users u ON u.id = tu.user_id'.
			' LEFT OUTER JOIN tournament_teams t ON t.id = tu.team_id AND t.tournament_id = tu.tournament_id'.
			' JOIN cities c ON c.id = tu.city_id'.
			' JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0'.
			' WHERE tu.tournament_id = ? AND tu.user_id = ?', $tournament_id, $user_id);
		$team = get_optional_param('team', $old_team);
		$city_id = (int)get_optional_param('city_id', $old_city_id);
		$flags = (int)get_optional_param('access_flags', ($old_flags & USER_PERM_MASK)) & USER_PERM_MASK;
		if ($flags == 0)
		{
			throw new Exc(get_label('Please choose at least one role for the user.'));
		}
		$flags += ($old_flags & ~USER_PERM_MASK);
			
		if ($city_id != $old_city_id || $flags != $old_flags)
		{
			if (is_permitted(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, $user_id, $user_club_id))
			{
				Db::exec(get_label('city'), 'UPDATE users SET city_id = ? WHERE id = ?', $city_id, $user_id);
			}
			Db::exec(get_label('registration'), 'UPDATE tournament_regs SET city_id = ?, flags = ? WHERE user_id = ? AND tournament_id = ?', $city_id, $flags, $user_id, $tournament_id);
			update_tournament_stats($tournament_id, $lat, $lon, $tournament_flags);
		}
		
		if (($tournament_flags & TOURNAMENT_FLAG_TEAM) != 0 && $team != $old_team)
		{
			if ($team == NULL || empty($team))
			{
				Db::exec(get_label('registration'), 'UPDATE tournament_regs SET team_id = NULL WHERE user_id = ? AND tournament_id = ?', $user_id, $tournament_id);
			}
			else
			{
				$query = new DbQuery('SELECT id FROM tournament_teams WHERE name = ? AND tournament_id = ?', $team, $tournament_id);
				if ($row = $query->next())
				{
					list($team_id) = $row;
				}
				else
				{
					Db::exec(get_label('team'), 'INSERT INTO tournament_teams (tournament_id, name) VALUES (?, ?)', $tournament_id, $team);
					list ($team_id) = Db::record(get_label('team'), 'SELECT LAST_INSERT_ID()');
				}
				Db::exec(get_label('registration'), 'UPDATE tournament_regs SET team_id = ? WHERE user_id = ? AND tournament_id = ?', $team_id, $user_id, $tournament_id);
			}
			
			list($count) = Db::record(get_label('registration'), 'SELECT count(*) FROM tournament_regs WHERE team_id = ? AND tournament_id = ?', $old_team_id, $tournament_id);
			if ($count <= 0)
			{
				Db::exec(get_label('team'), 'DELETE FROM tournament_teams WHERE id = ?', $old_team_id);
			}
		}
		Db::commit();
	}
	
	function edit_registration_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Edit user registration.');
		$help->request_param('user_id', 'User id. If the user is a member already success is returned anyway.', 'the one who is making request is used.');
		$help->request_param('tournament_id', 'Tournament id.');
		$help->response_param('city_id', 'City where this user lives. If the caller is authorized to change user profile, this city becomes the user home city.', 'the city is not changed.');
		$help->response_param('team', 'Team name for this user. Works for team tournaments only.', 'user is registered without a team.');
		$help->response_param('access_flags', 'A bit-set of user permissions for this toournament. 1 - player; 2 - referee; 4 - manager.', 'remain the same.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// remove_registration
	//-------------------------------------------------------------------------------------------------------
	function remove_registration_op()
	{
		global $_profile;
		
		$owner_id = 0;
		if ($_profile != NULL)
		{
			$owner_id = $_profile->user_id;
		}
		
		$user_id = (int)get_optional_param('user_id', $owner_id);
		$tournament_id = (int)get_required_param('tournament_id');
		
		Db::begin();
		list($club_id, $lat, $lon, $tournament_flags) = Db::record(get_label('tournament'), 'SELECT t.club_id, a.lat, a.lon, t.flags FROM tournaments t JOIN addresses a ON a.id = t.address_id WHERE t.id = ?', $tournament_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $user_id, $club_id, $tournament_id);
		
		list($team_id) = Db::record(get_label('registration'), 'SELECT team_id FROM tournament_regs WHERE user_id = ? AND tournament_id = ?', $user_id, $tournament_id);
		Db::exec(get_label('registration'), 'DELETE FROM tournament_regs WHERE user_id = ? AND tournament_id = ?', $user_id, $tournament_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->tournament_id = $tournament_id;
			db_log(LOG_OBJECT_USER, 'left tournament', $log_details, $user_id, $club_id);
			
			update_tournament_stats($tournament_id, $lat, $lon, $tournament_flags);
		}
		if (!is_null($team_id))
		{
			list($count) = Db::record(get_label('registration'), 'SELECT count(*) FROM tournament_regs WHERE team_id = ? AND tournament_id = ?', $team_id, $tournament_id);
			if ($count <= 0)
			{
				Db::exec(get_label('team'), 'DELETE FROM tournament_teams WHERE id = ?', $team_id);
			}
		}
		Db::commit();
		
		$this->response['tournament_id'] = $tournament_id;
		$this->response['user_id'] = $user_id;
	}
	
	function remove_registration_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Remove user from the registrations to the tournament.');
		$help->request_param('user_id', 'User id. If the user is not a member already success is returned anyway.', 'the one who is making request is used.');
		$help->request_param('tournament_id', 'Tournament id.');
		$help->response_param('user_id', 'User id.');
		$help->response_param('tournament_id', 'Tournament id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// accept_registration
	//-------------------------------------------------------------------------------------------------------
	function accept_registration_op()
	{
		global $_profile, $_lang;
		
		$user_id = (int)get_required_param('user_id');
		$tournament_id = (int)get_required_param('tournament_id');
		
		Db::begin();
		list($club_id, $tournament_name, $lat, $lon, $tournament_flags) = Db::record(get_label('tournament'), 'SELECT t.club_id, t.name, a.lat, a.lon, t.flags FROM tournaments t JOIN addresses a ON a.id = t.address_id WHERE t.id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		
		list ($user_id, $user_name, $user_email, $user_lang, $user_flags) = db::record(get_label('user'), 
			'SELECT u.id, nu.name, u.email, u.def_lang, u.flags'.
			' FROM users u'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
			' WHERE u.id = ?', $user_id);
		
		Db::exec(get_label('registration'), 'UPDATE tournament_regs SET flags = flags & '.~USER_TOURNAMENT_FLAG_NOT_ACCEPTED.' WHERE user_id = ? AND tournament_id = ?', $user_id, $tournament_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->tournament_id = $tournament_id;
			db_log(LOG_OBJECT_USER, 'accepted for tournament', $log_details, $user_id, $club_id);
			
			update_tournament_stats($tournament_id, $lat, $lon, $tournament_flags);
		}
		else
		{
			throw new Exc(get_label('User [0] did not apply for the tournament.', $user_name));
		}
		Db::commit();
		
		if ($user_flags & USER_FLAG_NOTIFY)
		{
			$lang = get_lang_code($user_lang);
			$tags = array(
				'root' => new Tag(get_server_url()),
				'user_id' => new Tag($user_id),
				'user_name' => new Tag($user_name),
				'tournament_id' => new Tag($tournament_id),
				'tournament_name' => new Tag($tournament_name));
			list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email/tournament_reg_accept.php';
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			send_email($user_email, $body, $text_body, $subj, user_unsubscribe_url($user_id), $user_lang);
		}
		
		$this->response['tournament_id'] = $tournament_id;
		$this->response['user_id'] = $user_id;
	}
	
	function accept_registration_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Accept user application for the tournament participation.');
		$help->request_param('user_id', 'User id. If the user is accepted already success is returned anyway.', 'the one who is making request is used.');
		$help->request_param('tournament_id', 'Tournament id.');
		$help->response_param('user_id', 'User id.');
		$help->response_param('tournament_id', 'Tournament id.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Tournament Operations', CURRENT_VERSION);

?>