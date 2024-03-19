<?php

require_once '../../include/api.php';
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
		$series_id = (int)get_optional_param('series_id', -1);
		$league_id = (int)get_optional_param('league_id', -1);
		$club_id = (int)get_optional_param('club_id', -1);
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
			$condition->add(' AND s.name LIKE(?)', $name_contains);
		}
		
		if (!empty($name_starts))
		{
			$name_starts1 = '% ' . $name_starts . '%';
			$name_starts2 = $name_starts . '%';
			$condition->add(' AND (s.name LIKE(?) OR s.name LIKE(?))', $name_starts1, $name_starts2);
		}

		if (!empty($started_before))
		{
			if (strpos($started_before, '+') === 0)
			{
				$condition->add(' AND s.start_time <= ?', get_datetime(trim(substr($started_before, 1)))->getTimestamp());
			}
			else
			{
				$condition->add(' AND s.start_time < ?', get_datetime($started_before)->getTimestamp());
			}
		}

		if (!empty($ended_before))
		{
			if (strpos($ended_before, '+') === 0)
			{
				$condition->add(' AND s.start_time + s.duration <= ?', get_datetime(trim(substr($ended_before, 1)))->getTimestamp());
			}
			else
			{
				$condition->add(' AND s.start_time + s.duration < ?', get_datetime($ended_before)->getTimestamp());
			}
		}

		if (!empty($started_after))
		{
			if (strpos($started_after, '+') === 0)
			{
				$condition->add(' AND s.start_time >= ?', get_datetime(trim(substr($started_after, 1)))->getTimestamp());
			}
			else
			{
				$condition->add(' AND s.start_time > ?', get_datetime($started_after)->getTimestamp());
			}
		}

		if (!empty($ended_after))
		{
			if (strpos($ended_after, '+') === 0)
			{
				$condition->add(' AND s.start_time + s.duration >= ?', get_datetime(trim(substr($ended_after, 1)))->getTimestamp());
			}
			else
			{
				$condition->add(' AND s.start_time + s.duration > ?', get_datetime($ended_after)->getTimestamp());
			}
		}

		if ($tournament_id > 0)
		{
			$condition->add(' AND s.id IN (SELECT series_id FROM series_tournaments WHERE tournament_id = ?)', $tournament_id);
		}
		
		if ($series_id > 0)
		{
			$condition->add(' AND s.id = ?', $series_id);
		}
		
		if ($league_id > 0)
		{
			$condition->add(' AND s.league_id = ?', $league_id);
		}
		
		if ($club_id > 0)
		{
			$condition->add(' AND s.league_id IN (SELECT league_id FROM league_clubs WHERE club_id = ?)', $club_id);
		}
		
		if ($langs > 0)
		{
			$condition->add(' AND (s.langs & ?) <> 0', $langs);
		}
		
		switch ($canceled)
		{
			case 1: // all including canceled
				break;
			case 2: // canceled only
				$condition->add(' AND (s.flags & ' . SERIES_FLAG_CANCELED . ') <> 0');
				break;
			default: // except canceled
				$condition->add(' AND (s.flags & ' . SERIES_FLAG_CANCELED . ') = 0');
				break;
		}
		
		list($count) = Db::record('sеriеs', 'SELECT count(*) FROM series s', $condition);
		$this->response['count'] = (int)$count;
		if ($count_only)
		{
			return;
		}
		
		$server_url = get_server_url() . '/';
		$league_pic = new Picture(LEAGUE_PICTURE);
		$series_pic = new Picture(SERIES_PICTURE);
		$series = array();
		if ($lod >= 1)
		{
			$query = new DbQuery(
				'SELECT s.id, s.name, s.flags, s.langs, s.start_time, s.duration, s.notes, s.rules, l.id, l.name, l.flags FROM series s' . 
				' LEFT OUTER JOIN leagues l ON l.id = s.league_id', $condition);
			$query->add(' ORDER BY s.start_time DESC, s.id DESC');
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$timezone = get_timezone();
			$this->show_query($query);
			while ($row = $query->next())
			{
				$s = new stdClass();
				list ($s->id, $s->name, $s->flags, $s->langs, $s->timestamp, $s->duration, $s->notes, $rules, $s->league_id, $s->league_name, $s->league_flags) = $row;
				$s->id = (int)$s->id;
				$s->langs = (int)$s->langs;
				$s->flags = (int)$s->flags;
				$s->timestamp = (int)$s->timestamp;
				$s->duration = (int)$s->duration;
				$s->start = timestamp_to_string($s->timestamp, $timezone);
				$s->end = timestamp_to_string($s->timestamp + $s->duration, $timezone);
				$s->rules = json_decode($rules);
				
				$s->league_id = (int)$s->league_id;
				$league_pic->set($s->league_id, $s->league_name, $s->league_flags);
				$s->has_league_picture = $league_pic->has_image(true);
				$s->league_icon = $server_url . $league_pic->url(ICONS_DIR);
				$s->league_picture = $server_url . $league_pic->url(TNAILS_DIR);
					
				$series_pic->set($s->id, $s->name, $s->flags);
				$s->has_picture = $series_pic->has_image(true);
				$s->icon = $server_url . $series_pic->url(ICONS_DIR);
				$s->picture = $server_url . $series_pic->url(TNAILS_DIR);
				$s->flags = (int)$s->flags & SERIES_EDITABLE_MASK;
				
				$series[] = $s;
			}
		}
		else
		{
			$query = new DbQuery(
				'SELECT s.id, s.name, s.flags, s.langs, s.start_time, s.duration, s.notes, l.id, l.name, l.flags FROM series s JOIN leagues l ON l.id = s.league_id', $condition);
			$query->add(' ORDER BY s.start_time DESC, s.id DESC');
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$this->show_query($query);
			while ($row = $query->next())
			{
				$s = new stdClass();
				list ($s->id, $s->name, $s->flags, $s->langs, $s->timestamp, $s->duration, $s->notes, $s->league_id, $league_name, $league_flags) = $row;
				$s->id = (int)$s->id;
				$s->langs = (int)$s->langs;
				$s->league_id = (int)$s->league_id;
				$s->timestamp = (int)$s->timestamp;
				$s->duration = (int)$s->duration;
				
				$series_pic->set($s->id, $s->name, $s->flags);
				$s->has_picture = $series_pic->has_image(true);
				$s->icon = $server_url . $series_pic->url(ICONS_DIR);
				$s->picture = $server_url . $series_pic->url(TNAILS_DIR);
				$s->flags = (int)$s->flags & SERIES_EDITABLE_MASK;
				
				$series[] = $s;
			}
		}
		$this->response['series'] = $series;
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('name_contains', 'Search pattern. For example: <a href="series.php?name_contains=so">' . PRODUCT_URL . '/api/get/series.php?name_contains=so</a> returns series containing "so" in their name.', '-');
		$help->request_param('name_starts', 'Search pattern. For example: <a href="series.php?name_starts=so">' . PRODUCT_URL . '/api/get/series.php?name_starts=so</a> returns series with names starting with "so".', '-');
		$help->request_param('started_before', 'Unix timestamp, or datetime, or <q>now</q>. Returns series that are started before a certain time. For example: <a href="series.php?started_before=2017-01-01%2000:00">' . PRODUCT_URL . '/api/get/series.php?started_before=2017-01-01%2000:00</a> returns all series started before January 1, 2017. Logged user timezone is used for converting dates. If it starts from "+" (for example "+2017-01-01%2000:00"), the search is inclusive.', '-');
		$help->request_param('ended_before', 'Unix timestamp, or datetime, or <q>now</q>. Returns series that are ended before a certain time. For example: <a href="series.php?ended_before=1483228800">' . PRODUCT_URL . '/api/get/series.php?ended_before=1483228800</a> returns all series ended before January 1, 2017; <a href="series.php?ended_before=now">' . PRODUCT_URL . '/api/get/series.php?ended_before=now</a> returns all series that are already ended. Logged user timezone is used for converting dates.  If starts from "+" (for example "+2017-01-01%2000:00"), the search is inclusive.', '-');
		$help->request_param('started_after', 'Unix timestamp, or datetime, or <q>now</q>. Returns series that are started after a certain time. For example: <a href="series.php?started_after=2017-01-01%2000:00">' . PRODUCT_URL . '/api/get/series.php?started_after=2017-01-01%2000:00</a> returns all series started after January 1, 2017. Logged user timezone is used for converting dates. If starts from "+" (for example "+2017-01-01%2000:00"), the search is inclusive.', '-');
		$help->request_param('ended_after', 'Unix timestamp, or datetime, or <q>now</q>. Returns series that are ended after a certain time. For example: <a href="series.php?ended_after=1483228800">' . PRODUCT_URL . '/api/get/series.php?ended_after=1483228800</a> returns all series ended after January 1, 2017; <a href="series.php?ended_after=now&ended_after=now">' . PRODUCT_URL . '/api/get/series.php?ended_after=now&ended_after=now</a> returns all series that happening now. Logged user timezone is used for converting dates. If starts from "+" (for example "+2017-01-01%2000:00"), the search is inclusive.', '-');
		$help->request_param('tournament_id', 'Tournament id. For example: <a href="series.php?tournament_id=1">' . PRODUCT_URL . '/api/get/series.php?tournament_id=1</a> returns the series of the tournament with id 1.', '-');
		$help->request_param('series_id', 'Series id. For example: <a href="series.php?serie_id=1">' . PRODUCT_URL . '/api/get/series.php?serie_id=1</a> returns the serie with id 1.', '-');
		$help->request_param('league_id', 'League id. For example: <a href="series.php?league_id=2">' . PRODUCT_URL . '/api/get/series.php?league_id=2</a> returns all series of the American Mafia League.', '-');
		$help->request_param('club_id', 'Club id.  For example: <a href="series.php?club_id=1">' . PRODUCT_URL . '/api/get/series.php?club_id=1</a> returns all series of the leagues that Russian Mafia of Vancouver belongs to.', '-');
		$help->request_param('langs', 'Languages filter. A bit combination of language ids. For example: <a href="series.php?langs=1">' . PRODUCT_URL . '/api/get/series.php?langs=1</a> returns all series that support English as their language.' . valid_langs_help(), '-');
		$help->request_param('canceled', '0 - exclude canceled series (default); 1 - incude canceled series; 2 - canceled series only. For example: <a href="series.php?canceled=2">' . PRODUCT_URL . '/api/get/series.php?canceled=2</a> returns all canceled series.', '-');
		$help->request_param('page', 'Page number. For example: <a href="series.php?page=1">' . PRODUCT_URL . '/api/get/series.php?page=1</a> returns the second page of series by time from newest to oldest.', '-');
		$help->request_param('page_size', 'Page size. Default page_size is ' . API_DEFAULT_PAGE_SIZE . '. For example: <a href="series.php?page_size=32">' . PRODUCT_URL . '/api/get/series.php?page_size=32</a> returns first 32 series; <a href="series.php?page_size=0">' . PRODUCT_URL . '/api/get/series.php?page_size=0</a> returns series in one page; <a href="series.php">' . PRODUCT_URL . '/api/get/series.php</a> returns first ' . API_DEFAULT_PAGE_SIZE . ' series by alphabet.', '-');

		$param = $help->response_param('series', 'The array of series. Series are always sorted in time order from newest to oldest. There is no way to change sorting order in the current version of the API.');
			$param->sub_param('id', 'Series id.');
			$param->sub_param('name', 'Series name.');
			$param->sub_param('has_league_picture', 'True if series have unique picture; false if default one is used.');
			$param->sub_param('icon', 'Series icon URL.');
			$param->sub_param('picture', 'Series picture URL.');
			$param->sub_param('langs', 'A bit combination of languages used in the series.' . valid_langs_help());
			$param->sub_param('timestamp', 'Unix timestamp for the start of the series.');
			$param->sub_param('duration', 'Duration of the series in seconds.');
			$param->sub_param('start', 'Formatted date "yyyy-mm-dd HH:MM" for the start of the series. User timezone is used.', 1);
			$param->sub_param('end', 'Formatted date "yyyy-mm-dd HH:MM" for the end of the series. User timezone is used.', 1);
			$param->sub_param('notes', 'Series notes.');
			$param->sub_param('canceled', 'True for canceled series, false for others.');
			$param->sub_param('league_id', 'League id of the series.');
			$param->sub_param('league_name', 'League name.', 1);
			$param->sub_param('has_league_picture', 'True if league has unique picture; false if default one is used.', 1);
			$param->sub_param('league_icon', 'League icon URL.', 1);
			$param->sub_param('league_picture', 'League picture URL.', 1);
			api_rules_filter_help($param->sub_param('rules', 'Game rules filter. Specifies what rules are allowed in the series. Example: { "split_on_four": true, "extra_points": ["fiim", "maf-club"] } - linching 2 players on 4 must be allowed; extra points assignment is allowed in ФИИМ or maf-club styles, but no others.', 1));
		$help->response_param('count', 'Total number of series satisfying the request parameters.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Series', CURRENT_VERSION);

?>