<?php

require_once '../../include/api.php';
require_once '../../include/picture.php';
require_once '../../include/series.php';

define('SEARCH_DEFAULT_NUM', 5);
define('SEARCH_MAX_NUM', 20);

// The search of the header: players, clubs, tournaments, leagues and series whose name - in any of
// its languages - contains the term. Within a kind the exact name comes first, then the names
// starting with the term, then the rest.
class ApiPage extends ControlApiPageBase
{
	private $term;
	private $wildcard;
	private $prefix;
	private $num;

	// How well a name matches: 0 - exactly, 1 - starts with the term, 2 - contains it.
	private function match_sql()
	{
		return 'MIN(IF(n.name = ?, 0, IF(n.name LIKE ?, 1, 2)))';
	}

	private function add($type, $id, $name, $sub, $url, $pic)
	{
		$item = new stdClass();
		$item->type = $type;
		$item->id = (int)$id;
		$item->name = $name;
		$item->sub = $sub;
		$item->url = $url;
		$item->pic = ($pic != NULL && $pic->has_image()) ? $pic->url(ICONS_DIR) : NULL;
		$this->response['results'][] = $item;
	}

	private function players()
	{
		global $_lang;

		$pic = new Picture(USER_PICTURE);
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, ni.name, nc.name, ' . $this->match_sql() . ' AS mtch' .
			' FROM names n' .
			' JOIN users u ON u.name_id = n.id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0' .
			' JOIN cities i ON i.id = u.city_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
			' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' WHERE n.name LIKE ?' .
			' GROUP BY u.id, nu.name, u.flags, ni.name, nc.name, u.games, u.rating' .
			' ORDER BY mtch, u.games = 0, u.rating DESC, nu.name LIMIT ' . $this->num,
			$this->term, $this->prefix, $this->wildcard);
		while ($row = $query->next())
		{
			list ($id, $name, $flags, $city, $club) = $row;
			$pic->set($id, $name, $flags);
			$this->add('player', $id, $name, empty($club) ? $city : $city . ' · ' . $club, 'user_info.php?bck=1&id=' . $id, $pic);
		}
	}

	private function clubs()
	{
		global $_lang;

		$pic = new Picture(CLUB_PICTURE);
		$query = new DbQuery(
			'SELECT c.id, nc.name, c.flags, ni.name, ' . $this->match_sql() . ' AS mtch' .
			' FROM names n' .
			' JOIN clubs c ON c.name_id = n.id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' JOIN cities i ON i.id = c.city_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
			' WHERE n.name LIKE ?' .
			' GROUP BY c.id, nc.name, c.flags, ni.name' .
			' ORDER BY (c.flags & ' . CLUB_FLAG_CLOSED . ') <> 0, mtch, nc.name LIMIT ' . $this->num,
			$this->term, $this->prefix, $this->wildcard);
		while ($row = $query->next())
		{
			list ($id, $name, $flags, $city) = $row;
			$pic->set($id, $name, $flags);
			$this->add('club', $id, $name, $city, 'club_main.php?bck=1&id=' . $id, $pic);
		}
	}

	private function tournaments()
	{
		global $_lang;

		$pic = new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE));
		$query = new DbQuery(
			'SELECT t.id, nt.name, t.flags, t.start_time, t.duration, i.timezone, ni.name, c.id, nc.name, c.flags, ' . $this->match_sql() . ' AS mtch' .
			' FROM names n' .
			' JOIN tournaments t ON t.name_id = n.id' .
			' JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' JOIN addresses a ON a.id = t.address_id' .
			' JOIN cities i ON i.id = a.city_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
			' JOIN clubs c ON c.id = t.club_id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' WHERE n.name LIKE ? AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0' .
			' GROUP BY t.id, nt.name, t.flags, t.start_time, t.duration, i.timezone, ni.name, c.id, nc.name, c.flags' .
			' ORDER BY mtch, t.start_time DESC LIMIT ' . $this->num,
			$this->term, $this->prefix, $this->wildcard);
		while ($row = $query->next())
		{
			list ($id, $name, $flags, $start, $duration, $timezone, $city, $club_id, $club_name, $club_flags) = $row;
			$pic->set($id, $name, $flags)->set($club_id, $club_name, $club_flags);
			$this->add('tournament', $id, $name, format_date_period($start, $duration, $timezone) . ' · ' . $city, 'tournament.php?bck=1&id=' . $id, $pic);
		}
	}

	private function leagues()
	{
		global $_lang;

		$pic = new Picture(LEAGUE_PICTURE);
		$query = new DbQuery(
			'SELECT l.id, nl.name, l.flags, ' . $this->match_sql() . ' AS mtch' .
			' FROM names n' .
			' JOIN leagues l ON l.name_id = n.id' .
			' JOIN names nl ON nl.id = l.name_id AND (nl.langs & '.$_lang.') <> 0' .
			' WHERE n.name LIKE ?' .
			' GROUP BY l.id, nl.name, l.flags' .
			' ORDER BY (l.flags & ' . LEAGUE_FLAG_CLOSED . ') <> 0, mtch, nl.name LIMIT ' . $this->num,
			$this->term, $this->prefix, $this->wildcard);
		while ($row = $query->next())
		{
			list ($id, $name, $flags) = $row;
			$pic->set($id, $name, $flags);
			$this->add('league', $id, $name, get_label('League'), 'league_main.php?bck=1&id=' . $id, $pic);
		}
	}

	private function series()
	{
		global $_lang;

		$pics = new SeriesPicture();
		$timezone = get_timezone();
		$query = new DbQuery(
			'SELECT s.id, ns.name, s.flags, s.start_time, s.duration, ' . series_owner_fields() . ', ' . $this->match_sql() . ' AS mtch' .
			' FROM names n' .
			' JOIN series s ON s.name_id = n.id' .
			' JOIN names ns ON ns.id = s.name_id AND (ns.langs & '.$_lang.') <> 0' .
			series_owner_join() .
			' WHERE n.name LIKE ? AND (s.flags & ' . SERIES_FLAG_CANCELED . ') = 0' .
			' GROUP BY s.id, ns.name, s.flags, s.start_time, s.duration, sol.id, nsol.name, sol.flags, soc.id, nsoc.name, soc.flags' .
			' ORDER BY mtch, s.start_time DESC LIMIT ' . $this->num,
			$this->term, $this->prefix, $this->wildcard);
		while ($row = $query->next())
		{
			list ($id, $name, $flags, $start, $duration, $league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags) = $row;
			$owner = SeriesOwner::from_row($league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags);
			$this->add('series', $id, $name, $owner->name . ' · ' . series_period($start, $duration, $timezone), 'series_standings.php?bck=1&id=' . $id, $pics->set($id, $name, $flags, $owner));
		}
	}

	protected function prepare_response()
	{
		$this->response['results'] = array();

		$this->term = isset($_REQUEST['term']) ? trim($_REQUEST['term']) : '';
		if (mb_strlen($this->term, 'UTF-8') < 2)
		{
			return;
		}
		// The term is searched for as it is: the wildcards of LIKE in it stand for themselves.
		$escaped = addcslashes($this->term, '\\%_');
		$this->wildcard = '%' . $escaped . '%';
		$this->prefix = $escaped . '%';

		$this->num = SEARCH_DEFAULT_NUM;
		if (isset($_REQUEST['num']) && is_numeric($_REQUEST['num']))
		{
			$this->num = max(1, min(SEARCH_MAX_NUM, (int)$_REQUEST['num']));
		}

		$this->players();
		$this->clubs();
		$this->tournaments();
		$this->leagues();
		$this->series();
	}

	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE, 'The search of the site header.');
		$help->request_param('term', 'What to look for: at least two characters. Players, clubs, tournaments, leagues and series with the term in their name, in any language, are returned. For example <a href="search.php?term=cup">/api/control/search.php?term=cup</a>.');
		$help->request_param('num', 'How many results of each kind to return, at most ' . SEARCH_MAX_NUM . '.', SEARCH_DEFAULT_NUM . '.');
		$param = $help->response_param('results', 'What was found: the players first, then the clubs, tournaments, leagues and series. Within a kind the exact name comes first, then the names starting with the term, then the rest.');
			$param->sub_param('type', 'One of: player, club, tournament, league, series.');
			$param->sub_param('id', 'Its id.');
			$param->sub_param('name', 'Its name in the language of the request.');
			$param->sub_param('sub', 'A line about it: the city and club of a player, the dates and city of a tournament...');
			$param->sub_param('url', 'Its page, relative to the site.');
			$param->sub_param('pic', 'The url of its icon, or null when it has none.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Search');

?>
