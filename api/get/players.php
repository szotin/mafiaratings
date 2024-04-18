<?php

require_once '../../include/api.php';
require_once '../../include/user_location.php';
require_once '../../include/rules.php';
require_once '../../include/picture.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		global $_lang;
		
		// $help->request_param('langs', 'Languages filter. Bit combination of language ids. ' . LANG_ALL . ' - means any language (this is a default value). For example: <a href="players.php?langs=1">/api/get/players.php?langs=1</a> returns all players that support English as their language.' . valid_langs_help(), '-');
		
		$name_contains = get_optional_param('name_contains');
		$name_starts = get_optional_param('name_starts');
		$user_id = (int)get_optional_param('user_id', -1);
		$club_id = (int)get_optional_param('club_id', -1);
		$main_club_id = (int)get_optional_param('main_club_id', -1);
		$game_id = (int)get_optional_param('game_id', -1);
		$event_id = (int)get_optional_param('event_id', -1);
		$tournament_id = (int)get_optional_param('tournament_id', -1);
		$series_id = (int)get_optional_param('series_id', -1);
		$city_id = (int)get_optional_param('city_id', -1);
		$area_id = (int)get_optional_param('area_id', -1);
		$country_id = (int)get_optional_param('country_id', -1);
		$langs = (int)get_optional_param('langs', 0);
		// $rules_code = get_optional_param('rules_code');
		// $scoring_id = (int)get_optional_param('scoring_id', -1);
		$lod = (int)get_optional_param('lod', 0);
		$count_only = isset($_REQUEST['count']);
		$page = (int)get_optional_param('page', 0);
		$page_size = (int)get_optional_param('page_size', API_DEFAULT_PAGE_SIZE);
		$lang = (int)get_optional_param('lang', $_lang);
		if (!is_valid_lang($lang))
		{
			$lang = $_lang;
		}
		
		$condition = new SQL(' WHERE TRUE');
		if ($name_contains != '')
		{
			$name_contains = '%' . $name_contains . '%';
			$condition->add(' AND n.name LIKE(?)', $name_contains);
		}
		
		if ($name_starts != '')
		{
			$name_starts1 = '% ' . $name_starts . '%';
			$name_starts2 = $name_starts . '%';
			$condition->add(' AND (n.name LIKE(?) OR n.name LIKE(?))', $name_starts1, $name_starts2);
		}
		
		if ($user_id > 0)
		{
			$condition->add(' AND u.id = ?', $user_id);
		}
		
		if ($club_id > 0)
		{
			$condition->add(' AND g.club_id = ?', $club_id);
		}
		
		if ($main_club_id > 0)
		{
			$condition->add(' AND u.club_id = ?', $main_club_id);
		}
		
		if ($game_id > 0)
		{
			$condition->add(' AND g.id = ?', $game_id);
		}
		
		if ($event_id > 0)
		{
			$condition->add(' AND g.event_id = ?', $event_id);
		}
		
		if ($tournament_id > 0)
		{
			$condition->add(' AND g.tournament_id = ?', $tournament_id);
		}
		
		if ($series_id > 0)
		{
			$condition->add(' AND g.tournament_id IN (SELECT tournament_id FROM series_tournaments WHERE series_id = ?)', $series_id);
		}
		
		if ($city_id > 0)
		{
			$condition->add(' AND u.city_id = ?', $city_id);
		}
		
		if ($area_id > 0)
		{
			$condition->add(' AND i.area_id = (SELECT area_id FROM cities WHERE id = ?)', $area_id);
		}
		
		if ($country_id > 0)
		{
			$condition->add(' AND i.country_id = ?', $country_id);
		}
		
		if ($langs > 0)
		{
			$condition->add(' AND (u.languages & ?) <> 0', $langs);
		}
		
		list($count) = Db::record('user', 
			'SELECT count(DISTINCT p.user_id) FROM players p'.
			' JOIN games g ON g.id = p.game_id'.
			' JOIN users u ON u.id = p.user_id'.
			' JOIN cities i ON i.id = u.city_id' .
			' JOIN names n ON n.id = u.name_id', $condition);
		$this->response['count'] = (int)$count;
		if ($count_only)
		{
			return;
		}
		
		$players = array();
		$query = new DbQuery(
			'SELECT DISTINCT u.id, nu.name, u.flags, u.languages, u.email, u.club_id, u.games, u.games_won, u.rating, u.city_id, ni.name, no.name'.
			' FROM players p' . 
			' JOIN games g ON g.id = p.game_id'.
			' JOIN users u ON u.id = p.user_id'.
			' JOIN names n ON n.id = u.name_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & ?) <> 0' .
			' JOIN cities i ON i.id = u.city_id' .
			' JOIN countries o ON o.id = i.country_id' .
			' JOIN names no ON no.id = o.name_id AND (no.langs & ?) <> 0' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ?) <> 0', $lang, $lang, $lang, $condition);
		$query->add(' ORDER BY nu.name');
		if ($page_size > 0)
		{
			$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
		}
		
		$this->show_query($query);
		$server_url = get_server_url() . '/';
		$user_pic = new Picture(USER_PICTURE);
		while ($row = $query->next())
		{
			$player = new stdClass();
			list($player->id, $player->name, $flags, $player->langs, $email, $player->club_id, $player->games, $player->games_won, $player->rating, $player->city_id, $player->city, $player->country) = $row;
			$player->id = (int)$player->id;
			$player->langs = (int)$player->langs;
			$player->club_id = (int)$player->club_id;
			$player->games = (int)$player->games;
			$player->games_won = (int)$player->games_won;
			$player->rating = (float)$player->rating;
			$player->city_id = (int)$player->city_id;
			
			if ($lod >= 1 && is_permitted(PERMISSION_CLUB_MANAGER, $player->club_id))
			{
				$player->email = $email;
			}
			
			$user_pic->set($player->id, $player->name, $flags);
			$player->icon = $server_url . $user_pic->url(ICONS_DIR);
			$player->picture = $server_url . $user_pic->url(TNAILS_DIR);
			$players[] = $player;
		}
		$this->response['players'] = $players;
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('name_contains', 'Search pattern. For example: <a href="players.php?name_contains=bat">' . PRODUCT_URL . '/api/get/players.php?name_contains=bat</a> returns players containing "co" in their name.', '-');
		$help->request_param('name_starts', 'Search pattern. For example: <a href="players.php?name_starts=ga">' . PRODUCT_URL . '/api/get/players.php?name_starts=ga</a> returns players with names starting with "ga".', '-');
		$help->request_param('user_id', 'User id. For example: <a href="players.php?user_id=25">/api/get/players.php?user_id=25</a> returns iformation about Fantomas.', '-');
		$help->request_param('club_id', 'Club id. For example: <a href="players.php?club_id=1">/api/get/players.php?club_id=1</a> returns all players who ever played in Vancouver Mafia Club.', '-');
		$help->request_param('main_club_id', 'Club id that users belong to. For example: <a href="players.php?main_club_id=1">/api/get/players.php?main_club_id=1</a> returns all players who\'s primary club is Vancouver Mafia Club.', '-');
		$help->request_param('game_id', 'Game id. For example: <a href="players.php?game_id=14170">/api/get/players.php?game_id=14170</a> returns players who played in the final game of the West Coast Express 24.', '-');
		$help->request_param('event_id', 'Event id. For example: <a href="players.php?event_id=10315">/api/get/players.php?event_id=10315</a> returns players who played in  the Vancouver Mafia Club in the event happened in March 29, 2024.', '-');
		$help->request_param('tournament_id', 'Tournament id. For example: <a href="players.php?tournament_id=146">/api/get/players.php?tournament_id=146</a> returns players who played in  the California Capital Cup 2023.', '-');
		$help->request_param('series_id', 'Series id. For example: <a href="players.php?series_id=9">/api/get/players.php?series_id=9</a> returns players who played in the AML Season 24-25.', '-');
		$help->request_param('city_id', 'City id. For example: <a href="players.php?city_id=2">/api/get/players.php?city_id=2</a> returns all players from Moscow. List of the cities and their ids can be obtained using <a href="cities.php?help">/api/get/cities.php</a>.', '-');
		$help->request_param('area_id', 'City id. The difference vs city is that when area is set, the games from all nearby cities are also returned. For example: <a href="players.php?area_id=2">/api/get/players.php?area_id=2</a> returns all players from Moscow and nearby cities like Podolsk, Himki, etc. Though <a href="players.php?city_id=2">/api/get/players.php?city_id=2</a> returns only the players from Moscow itself.', '-');
		$help->request_param('country_id', 'Country id. For example: <a href="players.php?country_id=2">/api/get/players.php?country_id=2</a> returns all players from Russia. List of the countries and their ids can be obtained using <a href="countries.php?help">/api/get/countries.php</a>.', '-');
		$help->request_param('langs', 'Languages filter. Bit combination of language ids. ' . LANG_ALL . ' - means any language (this is a default value). For example: <a href="players.php?langs=1">/api/get/players.php?langs=1</a> returns all players who speak English.' . valid_langs_help(), '-');
		$help->request_param('lang', 'Language id for returned names. For example: <a href="players.php?lang=2">/api/get/players.php?lang=2</a> returns player names in Russian.' . valid_langs_help(), 'default language for the logged in account is used. If not logged in the system tries to guess the language by ip address.');
		$help->request_param('count', 'Returns players count instead of the players themselves. For example: <a href="players.php?contains=an&count">/api/get/players.php?contains=an&count</a> returns how many players contain "an" in their name.', '-');
		$help->request_param('page', 'Page number. For example: <a href="players.php?page=1">/api/get/players.php?page=1</a> returns the second page of players by alphabet.', '-');
		$help->request_param('page_size', 'Page size. Default page_size is ' . API_DEFAULT_PAGE_SIZE . '. For example: <a href="players.php?page_size=32">/api/get/players.php?page_size=32</a> returns first 32 players; <a href="players.php?page_size=0">/api/get/players.php?page_size=0</a> returns players in one page; <a href="players.php">/api/get/players.php</a> returns first ' . API_DEFAULT_PAGE_SIZE . ' players by alphabet.', '-');

		$param = $help->response_param('players', 'The array of players. Players are always sorted in alphabetical order. There is no way to change sorting order in the current version of the API.');
			$param->sub_param('id', 'User id.');
			$param->sub_param('name', 'User name using default language for the profile.');
			$param->sub_param('icon', 'User icon URL.');
			$param->sub_param('picture', 'User picture URL.');
			$param->sub_param('langs', 'A bit combination of languages that this player speaks.' . valid_langs_help());
			$param->sub_param('email', 'Subj.', 'the user that is making the request does not have the permission to see this user email.', 1);
			$param->sub_param('club_id', 'Main club id of the player.', 'player does not have a main club.', 1);
			$param->sub_param('games', 'Total number of games of the player.');
			$param->sub_param('games_won', 'Total number of wins of the player.');
			$param->sub_param('rating', 'Current rating of the player.');
			$param->sub_param('city_id', 'City id');
			$param->sub_param('city', 'City name using default language for the profile.');
			$param->sub_param('country', 'Country name using default language for the profile.');
		$help->response_param('count', 'Total number of players satisfying the request parameters.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Players', CURRENT_VERSION);

?>