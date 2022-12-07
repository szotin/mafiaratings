<?php

require_once '../../include/session.php';
require_once '../../include/api.php';
require_once '../../include/scoring.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		if (isset($_REQUEST['contains']))
		{
			$contains = $_REQUEST['contains'];
		}
		
		$starts = '';
		if (isset($_REQUEST['starts']))
		{
			$starts = $_REQUEST['starts'];
		}
		
		$club = 0;
		if (isset($_REQUEST['club']))
		{
			$club = (int)$_REQUEST['club'];
		}
		
		$club_members = 0;
		if (isset($_REQUEST['club_members']))
		{
			$club_members = (int)$_REQUEST['club_members'];
		}
		
		$game = 0;
		if (isset($_REQUEST['game']))
		{
			$game = (int)$_REQUEST['game'];
		}
		
		$event = 0;
		if (isset($_REQUEST['event']))
		{
			$event = (int)$_REQUEST['event'];
		}
		
		$address = 0;
		if (isset($_REQUEST['address']))
		{
			$address = (int)$_REQUEST['address'];
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
		
		$user = 0;
		if (isset($_REQUEST['user']))
		{
			$user = (int)$_REQUEST['user'];
		}
		
		$with_user = 0;
		if (isset($_REQUEST['with_user']))
		{
			$with_user = (int)$_REQUEST['with_user'];
		}
		
		$langs = LANG_ALL;
		if (isset($_REQUEST['langs']))
		{
			$langs = (int)$_REQUEST['langs'];
		}

		$in_set = false;
		$in_role = POINTS_ALL;
		if (isset($_REQUEST['in_role']))
		{
			$in_role = $_REQUEST['in_role'];
			$in_set = true;
			switch($in_role)
			{
				case 'r';
					$in_role = POINTS_RED;
					break;
				case 'b';
					$in_role = POINTS_DARK;
					break;
				case 'c';
					$in_role = POINTS_CIVIL;
					break;
				case 's';
					$in_role = POINTS_SHERIFF;
					break;
				case 'm';
					$in_role = POINTS_MAFIA;
					break;
				case 'd';
					$in_role = POINTS_DON;
					break;
				default:
					$in_set = false;
					break;
			}
		}
		
		$in_number = 0;
		if (isset($_REQUEST['in_number']))
		{
			$in_set = true;
			$in_number = (int)$_REQUEST['in_number'];
		}

		$in_club = 0;
		if (isset($_REQUEST['in_club']))
		{
			$in_set = true;
			$in_club = (int)$_REQUEST['in_club'];
		}
		
		$in_game = 0;
		if (isset($_REQUEST['in_game']))
		{
			$in_set = true;
			$in_game = (int)$_REQUEST['in_game'];
		}
		
		$in_event = 0;
		if (isset($_REQUEST['in_event']))
		{
			$in_set = true;
			$in_event = (int)$_REQUEST['in_event'];
		}
		
		$in_address = 0;
		if (isset($_REQUEST['in_address']))
		{
			$in_set = true;
			$in_address = (int)$_REQUEST['in_address'];
		}
		
		$in_city = 0;
		if (isset($_REQUEST['in_city']))
		{
			$in_set = true;
			$in_city = (int)$_REQUEST['in_city'];
		}
		
		$in_area = 0;
		if (isset($_REQUEST['in_area']))
		{
			$in_set = true;
			$in_area = (int)$_REQUEST['in_area'];
		}
		
		$in_country = 0;
		if (isset($_REQUEST['in_country']))
		{
			$in_set = true;
			$in_country = (int)$_REQUEST['in_country'];
		}
		
		$in_with_user = 0;
		if (isset($_REQUEST['in_with_user']))
		{
			$in_set = true;
			$in_with_user = (int)$_REQUEST['in_with_user'];
		}
		
		$in_langs = 0;
		if (isset($_REQUEST['in_langs']))
		{
			$in_set = true;
			$in_langs = (int)$_REQUEST['in_langs'];
		}
		
		$in_before = 0;
		if (isset($_REQUEST['in_before']))
		{
			$in_set = true;
			$in_before = (int)$_REQUEST['in_before'];
		}
		
		$in_after = 0;
		if (isset($_REQUEST['in_after']))
		{
			$in_set = true;
			$in_after = (int)$_REQUEST['in_after'];
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
		
		$count_only = isset($_REQUEST['count']);
		
		$condition = new SQL(' WHERE (u.flags & ' . USER_FLAG_BANNED . ') = 0 AND u.games > 0');
		
		if (isset($contains))
		{
			$condition->add(' AND u.name LIKE(?)', '%' . $contains . '%');
		}
		
		if (isset($starts))
		{
			$condition->add(' AND (u.name LIKE(?) OR u.name LIKE(?))', $starts . '%', '% ' . $starts . '%');
		}
		
		if ($club > 0)
		{
			$condition->add(' AND u.club_id = ?', $club);
		}
		else if ($club_members > 0)
		{
			$condition->add(' AND u.id IN (SELECT user_id FROM club_users WHERE club_id = ?)', $club_members);
		}
		
		if ($game > 0)
		{
			$condition->add(' AND u.id IN (SELECT user_id FROM players WHERE game_id = ?)', $game);
		}
		else if ($event > 0)
		{
			$condition->add(' AND u.id IN (SELECT p1.user_id FROM players p1 JOIN games g1 ON g1.id = p1.game_id WHERE g1.event_id = ?)', $event);
		}
		else if ($address > 0)
		{
			$condition->add(' AND u.id IN (SELECT DISTINCT p1.user_id FROM players p1 JOIN games g1 ON p1.game_id = g1.id JOIN events e1 ON g1.event_id = e1.id WHERE e1.address_id = ? AND g1.is_canceled = FALSE AND g1.result > 0)', $address);
		}
		else if ($city > 0)
		{
			$condition->add(' AND u.city_id = ?', $city);
		}
		else if ($area > 0)
		{
			$condition->add(' AND u.city_id IN (SELECT id FROM cities WHERE area_id = (SELECT area_id FROM cities WHERE id = ?))', $area);
		}
		else if ($country > 0)
		{
			$condition->add(' AND u.city_id IN (SELECT id FROM cities WHERE country_id = ?)', $country);
		}
		
		if ($user > 0)
		{
			$condition->add(' AND u.id = ?', $user);
		}
		
		if ($with_user > 0)
		{
			$condition->add(' AND u.id IN (SELECT DISTINCT p1.user_id FROM players p1 JOIN players p2 ON p1.game_id = p2.game_id WHERE p2.user_id = ?)', $with_user);
		}
		
		if ($langs != LANG_ALL)
		{
			$condition->add(' AND (u.languages & ?) <> 0', $langs);
		}
			
		if ($in_set)
		{
			$condition->add(get_roles_condition($in_role));
			if ($in_number > 0)
			{
				$condition->add(' AND p.number = ?', $in_number);
			}
			
			if ($in_langs > 0)
			{
				$condition->add(' AND (g.language & ?) <> 0', $in_langs);
			}
			
			if ($in_before > 0)
			{
				$condition->add(' AND g.start_time < ?', $in_before);
			}
			
			if ($in_after > 0)
			{
				$condition->add(' AND g.start_time >= ?', $in_after);
			}
			
			if ($in_with_user > 0)
			{
				$condition->add(' AND g.id IN (SELECT game_id FROM players WHERE user_id = ?)', $in_with_user);
			}
			
			if ($in_game > 0)
			{
				$condition->add(' AND g.id = ?', $in_game);
			}
			else if ($in_event > 0)
			{
				$condition->add(' AND g.event_id = ?', $in_event);
			}
			else if ($in_address > 0)
			{
				$condition->add(' AND g.event_id IN (SELECT id FROM events WHERE address_id = ?)', $in_address);
			}
			else if ($in_club > 0)
			{
				$condition->add(' AND g.club_id = ?', $in_club);
			}
			else if ($in_city > 0)
			{
				$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id WHERE a1.city_id = ?)', $in_city);
			}
			else if ($in_area > 0)
			{
				$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.area_id = (SELECT area_id FROM cities WHERE id = ?))', $in_area);
			}
			else if ($in_country > 0)
			{
				$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.country_id = ?)', $in_country, $in_country);
			}
			
			$query = new DbQuery(
				'SELECT u.id, u.name, u.flags, u.languages, (SELECT count(*) FROM users u1 WHERE u1.rating >= u.rating) as pos, ' . USER_INITIAL_RATING . ' + SUM(p.rating_earned) as rating, count(*) as games, SUM(p.won) as won, c.id, c.name FROM users u' . 
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
				' JOIN players p ON p.user_id = u.id' .
				' JOIN games g ON p.game_id = g.id AND g.is_canceled = FALSE AND g.result > 0', $condition); 
			$query->add(' GROUP BY u.id ');
			$count_query = new DbQuery('SELECT count(DISTINCT u.id) FROM users u JOIN players p ON p.user_id = u.id JOIN games g ON p.game_id = g.id AND g.is_canceled = FALSE AND g.result > 0', $condition);
		}
		else
		{
			$query = new DbQuery(
				'SELECT u.id, u.name, u.flags, u.languages, (SELECT count(*) FROM users u1 WHERE u1.rating >= u.rating) as pos, u.rating as rating, u.games as games, u.games_won as won, c.id, c.name FROM users u' . 
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id', $condition);
			$count_query = new DbQuery('SELECT count(*) FROM users u', $condition);	
		}
		
		$query->add(' ORDER BY rating DESC, won DESC, games DESC');
		$num = 0;
		if ($page_size > 0)
		{
			$num = $page * $page_size;
			$query->add(' LIMIT ' . $num . ',' . $page_size);
		}
		
		list($count) = Db::record('rating', $count_query);
		$this->response['count'] = (int)$count;
		if (!$count_only)
		{
			$ratings = array();
			while ($row = $query->next())
			{
				++$num;
				$rating = new stdClass();
				list($rating->id, $rating->name, $user_flags, $rating->languages, $rating->pos, $rating->rating, $rating->num_games, $rating->games_won, $rating->club_id, $rating->club_name) = $row;
				$rating->num = (int)$num;
				$rating->pos = (int)$rating->pos;
				$rating->id = (int)$rating->id;
				$rating->languages = (int)$rating->languages;
				$rating->rating = (float)$rating->rating;
				$rating->num_games = (int)$rating->num_games;
				$rating->games_won = (int)$rating->games_won;
				$rating->club_id = (int)$rating->club_id;
				if (($user_flags & USER_FLAG_MALE) != 0)
				{
					$rating->male = true;
				}
				if (($user_flags & USER_ICON_MASK) != 0)
				{
					$rating->image = USER_PICS_DIR . TNAILS_DIR . $rating->id . '.png?' . (($user_flags & USER_ICON_MASK) >> USER_ICON_MASK_OFFSET);
					$rating->icon = USER_PICS_DIR . ICONS_DIR . $rating->id . '.png?' . (($user_flags & USER_ICON_MASK) >> USER_ICON_MASK_OFFSET);
				}
				$ratings[] = $rating;
			}
			$this->response['ratings'] = $ratings;
		}
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('contains', 'Search pattern. For example: <a href="ratings.php?contains=al">' . PRODUCT_URL . '/api/get/ratings.php?contains=al</a> returns players containing "al" in their name.', '-');
		$help->request_param('starts', 'Search pattern. For example: <a href="ratings.php?starts=bo">' . PRODUCT_URL . '/api/get/ratings.php?starts=bo</a> returns players with names starting with "bo". Note that "Bad Boy" is also returned.', '-');
		$help->request_param('club', 'Club id. For example: <a href="ratings.php?club=1">' . PRODUCT_URL . '/api/get/ratings.php?club=1</a> returns ratings for all players of Vancouver Mafia Club. If missing, all players for all clubs are returned.', '-');
		$help->request_param('club_members', 'Club id. For example: <a href="ratings.php?club_members=42">' . PRODUCT_URL . '/api/get/ratings.php?club_members=42</a> returns ratings for all members of Seattle Mafia Club. The difference with the "club" parameter is that "club" returns only the players who consider Seattle Mafia Club as their main club. Though "club_members" returns all members even if their main club is different. Note that Fantomas and Tigra are returned in the sample request, though their main club is Vancouver.', '-');
		$help->request_param('game', 'Game id. For example: <a href="ratings.php?game=1299">' . PRODUCT_URL . '/api/get/ratings.php?game=1299</a> returns ratings for all players participated in the game 1299, played in VaWaCa-2017 tournament.', '-');
		$help->request_param('event', 'Event id. For example: <a href="ratings.php?event=7927">' . PRODUCT_URL . '/api/get/ratings.php?event=7927</a> returns ratings for all players participated in VaWaCa-2017 tournament. If missing, all players for all events are returned.', '-');
		$help->request_param('address', 'Address id. For example: <a href="ratings.php?address=10">' . PRODUCT_URL . '/api/get/ratings.php?address=10</a> returns ratings for all players who played in Tafs Cafe in Vancouver Mafia Club.', '-');
		$help->request_param('city', 'City id. For example: <a href="ratings.php?city=49">' . PRODUCT_URL . '/api/get/ratings.php?city=49</a> returns ratings for all players from Seattle. List of the cities and their ids can be obtained using <a href="cities.php?help">' . PRODUCT_URL . '/api/get/cities.php</a>.', '-');
		$help->request_param('area', 'City id. The difference with city is that when area is set, the games from all nearby cities are also returned. For example: <a href="ratings.php?area=1">' . PRODUCT_URL . '/api/get/ratings.php?area=1</a> returns all players from Vancouver and nearby cities like Delta, Burnaby, Surrey, etc. Though <a href="ratings.php?city=1">' . PRODUCT_URL . '/api/get/ratings.php?city=1</a> returns only the players from Vancouver itself.', '-');
		$help->request_param('country', 'Country id. For example: <a href="ratings.php?country=2">' . PRODUCT_URL . '/api/get/ratings.php?country=2</a> returns all players from Russia. List of the countries and their ids can be obtained using <a href="countries.php?help">' . PRODUCT_URL . '/api/get/countries.php</a>.', '-');
		$help->request_param('user', 'User id. For example: <a href="ratings.php?user=4">' . PRODUCT_URL . '/api/get/ratings.php?user=4</a> returns only lilya\'s rating.', '-');
		$help->request_param('with_user', 'User id. For example: <a href="ratings.php?with_user=4">' . PRODUCT_URL . '/api/get/ratings.php?with_user=4</a> returns all players who played with lilya at least once.', '-');
		$help->request_param('langs', 'Languages filter. A bit combination of language ids. For example: <a href="ratings.php?langs=1">' . PRODUCT_URL . '/api/get/ratings.php?langs=1</a> returns ratings for players who speak English; <a href="ratings.php?club=1&langs=3">' . PRODUCT_URL . '/api/get/ratings.php?club=1&langs=3</a> returns ratings for players who can speak English and Russian.' . valid_langs_help(), '-');
		$help->request_param('in_role', 'The rating for the specified role only. Possible values are:
				<ul>
					<li>a - all roles (default)</li>
					<li>r - red roles = civilian + sheriff: <a href="ratings.php?in_role=r">' . PRODUCT_URL . '/api/get/ratings.php?in_role=r</a></li>
					<li>b - black roles = mafia + don: <a href="ratings.php?in_role=b">' . PRODUCT_URL . '/api/get/ratings.php?in_role=b</a></li>
					<li>c - civilian but not the sheriff: <a href="ratings.php?in_role=c">' . PRODUCT_URL . '/api/get/ratings.php?in_role=c</a></li>
					<li>s - sheriff: <a href="ratings.php?in_role=s">' . PRODUCT_URL . '/api/get/ratings.php?in_role=s</a></li>
					<li>m - mafia but not the don: <a href="ratings.php?in_role=m">' . PRODUCT_URL . '/api/get/ratings.php?in_role=m</a></li>
					<li>d - don: <a href="ratings.php?in_role=d">' . PRODUCT_URL . '/api/get/ratings.php?in_role=d</a></li>
				</ul>
				If any of the parameters staring with "in_" is set, the rating calculation is changed. For example in_role shows only rating earned in a specific roles; in_country - only the rating earned in a specific country; etc.
			', '-');
		$help->request_param('in_number', 'Number in the game (1-10). For example: <a href="ratings.php?in_number=8">' . PRODUCT_URL . '/api/get/ratings.php?in_number=8</a> returns only rating points earned by players playing on number 8. <a href="ratings.php?in_club=1&club=42">' . PRODUCT_URL . '/api/get/ratings.php?in_club=1&club=42</a> returns rating points of Seattle club players earned in Vancouver.', '-');
		$help->request_param('in_club', 'Club id. For example: <a href="ratings.php?in_club=1">' . PRODUCT_URL . '/api/get/ratings.php?in_club=1</a> returns only rating points earned in Vancouver Mafia Club. <a href="ratings.php?in_club=1&club=42">' . PRODUCT_URL . '/api/get/ratings.php?in_club=1&club=42</a> returns rating points of Seattle club players earned in Vancouver.', '-');
		$help->request_param('in_game', 'Game id. For example: <a href="ratings.php?in_game=1299">' . PRODUCT_URL . '/api/get/ratings.php?in_game=1299</a> returns rating points earned in the game 1299, played in VaWaCa-2017 tournament.', '-');
		$help->request_param('in_event', 'Event id. For example: <a href="ratings.php?in_event=7927">' . PRODUCT_URL . '/api/get/ratings.php?in_event=7927</a> returns ratings points earned in VaWaCa-2017 tournament.', '-');
		$help->request_param('in_address', 'Address id. For example: <a href="ratings.php?in_address=10">' . PRODUCT_URL . '/api/get/ratings.php?in_address=10</a> returns rating points earned in Tafs Cafe in Vancouver Mafia Club.', '-');
		$help->request_param('in_city', 'City id. For example: <a href="ratings.php?in_city=49">' . PRODUCT_URL . '/api/get/ratings.php?in_city=49</a> returns rating points earned in Seattle. List of the cities and their ids can be obtained using <a href="cities.php?help">' . PRODUCT_URL . '/api/get/cities.php</a>.', '-');
		$help->request_param('in_area', 'City id. The difference with city is that when area is set, the games from all nearby cities are also returned. For example: <a href="ratings.php?in_area=1">' . PRODUCT_URL . '/api/get/ratings.php?in_area=1</a> returns all rating points earned in Vancouver and nearby cities like Delta, Burnaby, Surrey, etc. Though <a href="ratings.php?in_city=1">' . PRODUCT_URL . '/api/get/ratings.php?in_city=1</a> returns only rating points earned in Vancouver itself.', '-');
		$help->request_param('in_country', 'Country id. For example: <a href="ratings.php?in_country=2">' . PRODUCT_URL . '/api/get/ratings.php?in_country=2</a> returns rating points earned in Russia. List of the countries and their ids can be obtained using <a href="countries.php?help">' . PRODUCT_URL . '/api/get/countries.php</a>.', '-');
		$help->request_param('in_with_user', 'User id. For example: <a href="ratings.php?in_with_user=4">' . PRODUCT_URL . '/api/get/ratings.php?in_with_user=4</a> returns rating points in the games where lilya played.', '-');
		$help->request_param('in_langs', 'Languages filter. A bit combination of language ids. For example: <a href="ratings.php?in_langs=1">' . PRODUCT_URL . '/api/get/ratings.php?in_langs=1</a> returns rating points earned in games played in English; <a href="ratings.php?club=1&in_langs=3">' . PRODUCT_URL . '/api/get/ratings.php?club=1&in_langs=3</a> returns rating points earned in English and Russian games by the players of Vancouver club.' . valid_langs_help(), '-');
		$help->request_param('in_before', 'Unix timestamp. For example: <a href="ratings.php?in_before=1483228800">' . PRODUCT_URL . '/api/get/ratings.php?in_before=1483228800</a> returns ratings earned before 2017. In other words it returns ratings as they were at the end of 2016.', '-');
		$help->request_param('in_after', 'Unix timestamp. For example: <a href="ratings.php?in_after=1483228800">' . PRODUCT_URL . '/api/get/ratings.php?in_after=1483228800</a> returns ratings earned after January 1, 2017; <a href="ratings.php?in_after=1483228800&in_before=1485907200">' . PRODUCT_URL . '/api/get/ratings.php?in_after=1483228800&in_before=1485907200</a> returns ratings earned in January 2017', '-');
		$help->request_param('count', 'Returns game count instead of players list. For example: <a href="ratings.php?club=1&count">' . PRODUCT_URL . '/api/get/ratings.php?club=1&count</a> returns how many players with ratings are there in Vancouver Mafia Club; <a href="ratings.php?event=7927&count">' . PRODUCT_URL . '/api/get/ratings.php?event=7927&count</a> returns how many players with ratings participated in VaWaCa-2017 tournament.', '-');
		$help->request_param('page', 'Page number. For example: <a href="ratings.php?club=1&page=1">' . PRODUCT_URL . '/api/get/ratings.php?club=1&page=1</a> returns the second page of ratings for Vancouver Mafia Club players.', '-');
		$help->request_param('page_size', 'Page size. Default page_size is ' . API_DEFAULT_PAGE_SIZE . '. For example: <a href="ratings.php?club=1&page_size=32">' . PRODUCT_URL . '/api/get/ratings.php?club=1&page_size=32</a> returns top 32 players for Vancouver Mafia Club; <a href="ratings.php?club=6&page_size=0">' . PRODUCT_URL . '/api/get/ratings.php?club=6&page_size=0</a> returns all players for Empire of Mafia club in one page; <a href="ratings.php?club=1">' . PRODUCT_URL . '/api/get/ratings.php?club=1</a> returns top ' . API_DEFAULT_PAGE_SIZE . ' players for Vancouver Mafia Club;', '-');

		$param = $help->response_param('ratings', 'The array of ratings. Ratings are always sorted in descending order. There is no way to change sorting order in the current version of the API.');
			$param->sub_param('num', 'Number in the current list.');
			$param->sub_param('pos', 'Position in the global rating.');
			$param->sub_param('id', 'User id. Unique user identifier.');
			$param->sub_param('name', 'User name.');
			$param->sub_param('image', 'A link to the user image at mafiaratings.com. Not set when the image is not uploaded by the user.');
			$param->sub_param('icon', 'A link to the user icon at mafiaratings.com. Not set when the icon is not uploaded by the user.');
			$param->sub_param('rating', 'The Elo rating.');
			$param->sub_param('num_games', 'Number of games played by the player.');
			$param->sub_param('games_won', 'Number of games won by the player.');
			$param->sub_param('male', 'True for males; not set for females.');
			$param->sub_param('club_id', 'This player\'s main club id.');
			$param->sub_param('club_name', 'This player\'s main club name.');
		$help->response_param('count', 'The total number of players satisfying the request parameters.');
		
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Ratings', CURRENT_VERSION);

?>