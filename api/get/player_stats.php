<?php

require_once '../../include/api.php';
require_once '../../include/scoring.php';

define('CURRENT_VERSION', 0);

define('FLAG_FILTER_VIDEO', 0x0001);
define('FLAG_FILTER_NO_VIDEO', 0x0002);
define('FLAG_FILTER_TOURNAMENT', 0x0004);
define('FLAG_FILTER_NO_TOURNAMENT', 0x0008);
define('FLAG_FILTER_RATING', 0x0010);
define('FLAG_FILTER_NO_RATING', 0x0020);
define('FLAG_FILTER_CANCELED', 0x0040);
define('FLAG_FILTER_NO_CANCELED', 0x0080);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NO_CANCELED);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		$raw = isset($_REQUEST['raw']);
		
		$user_id = (int)get_required_param('user_id');
		$before = (int)get_optional_param('before');
		$after = (int)get_optional_param('after');
		$club_id = (int)get_optional_param('club_id');
		$address_id = (int)get_optional_param('address_id');
		$event_id = (int)get_optional_param('event_id');
		$game_id = (int)get_optional_param('game_id');
		$with_user = (int)get_optional_param('with_user');
		$country_id = (int)get_optional_param('country_id');
		$area_id = (int)get_optional_param('area_id');
		$city_id = (int)get_optional_param('city_id');
		$langs = (int)get_optional_param('langs', LANG_ALL);
		$filter_flags = (int)get_optional_param('filter_flags', FLAG_FILTER_DEFAULT);
		$number = (int)get_optional_param('number');
		$role = POINTS_ALL;
		if (isset($_REQUEST['role']))
		{
			$role = $_REQUEST['role'];
			switch($role)
			{
				case 'r';
					$role = POINTS_RED;
					break;
				case 'b';
					$role = POINTS_BLACK;
					break;
				case 'c';
					$role = POINTS_CIVIL;
					break;
				case 's';
					$role = POINTS_SHERIFF;
					break;
				case 'm';
					$role = POINTS_MAFIA;
					break;
				case 'd';
					$role = POINTS_DON;
					break;
			}
		}
		
		$condition = get_roles_condition($role);
		if ($before > 0)
		{
			$condition->add(' AND g.start_time < ?', $before);
		}

		if ($after > 0)
		{
			$condition->add(' AND g.start_time >= ?', $after);
		}

		if ($club_id > 0)
		{
			$condition->add(' AND g.club_id = ?', $club_id);
		}

		if ($game_id > 0)
		{
			$condition->add(' AND g.id = ?', $game_id);
		}
		else if ($event_id > 0)
		{
			$condition->add(' AND g.event_id = ?', $event_id);
		}
		else if ($address_id > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT id FROM events WHERE address_id = ?)', $address_id);
		}
		else if ($city_id > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id WHERE a1.city_id = ?)', $city_id);
		}
		else if ($area_id > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.area_id = (SELECT area_id FROM cities WHERE id = ?))', $area_id);
		}
		else if ($country_id > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.country_id = ?)', $country_id);
		}
		
		if ($langs != LANG_ALL)
		{
			$condition->add(' AND (g.language & ?) <> 0', $langs);
		}
		
		if ($with_user > 0)
		{
			$condition->add(' AND g.id IN (SELECT game_id FROM players WHERE user_id = ?)', $with_user);
		}
		
		if ($number > 0)
		{
			$condition->add(' AND p.number = ?', $number);
		}
		
		if ($filter_flags & FLAG_FILTER_VIDEO)
		{
			$condition->add(' AND g.video_id IS NOT NULL');
		}
		if ($filter_flags & FLAG_FILTER_NO_VIDEO)
		{
			$condition->add(' AND g.video_id IS NULL');
		}
		if ($filter_flags & FLAG_FILTER_TOURNAMENT)
		{
			$condition->add(' AND g.tournament_id IS NOT NULL');
		}
		if ($filter_flags & FLAG_FILTER_NO_TOURNAMENT)
		{
			$condition->add(' AND g.tournament_id IS NULL');
		}
		if ($filter_flags & FLAG_FILTER_RATING)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') <> 0');
		}
		if ($filter_flags & FLAG_FILTER_NO_RATING)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') = 0');
		}
		if ($filter_flags & FLAG_FILTER_CANCELED)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_CANCELED.') <> 0');
		}
		if ($filter_flags & FLAG_FILTER_NO_CANCELED)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_CANCELED.') = 0');
		}
		
		$query = new DbQuery(
			'SELECT COUNT(*), SUM(p.won), SUM(p.rating_earned), SUM(IF((p.flags & ' . SCORING_FLAG_BEST_PLAYER . ') <> 0, 1, 0)),' .
			' SUM(IF((p.flags & ' . SCORING_FLAG_BEST_MOVE . ') <> 0, 1, 0)),' .
			' SUM(IF((p.flags & ' . SCORING_FLAG_WORST_MOVE . ') <> 0, 1, 0)),' .
			' SUM(IF((p.flags & ' . SCORING_FLAG_FIRST_LEGACY_3 . ') <> 0, 1, 0)),' .
			' SUM(IF((p.flags & ' . SCORING_FLAG_FIRST_LEGACY_2 . ') <> 0, 1, 0)),' .
			' SUM(IF((p.flags & ' . SCORING_FLAG_FIRST_LEGACY_1 . ') <> 0, 1, 0)),' .
			' SUM(p.warns),' .
			' SUM(p.voted_civil), SUM(p.voted_mafia), SUM(p.voted_sheriff), SUM(p.voted_by_civil), SUM(p.voted_by_mafia), SUM(p.voted_by_sheriff),' .
			' SUM(p.nominated_civil), SUM(p.nominated_mafia), SUM(p.nominated_sheriff), SUM(p.nominated_by_civil), SUM(p.nominated_by_mafia), SUM(p.nominated_by_sheriff),' .
			' SUM(IF(p.was_arranged < 0, 0, 1)), SUM(IF(p.was_arranged <> 0, 0, 1)), SUM(IF(p.checked_by_don < 0, 0, 1)), SUM(IF(p.checked_by_sheriff < 0, 0, 1))' .
			' FROM players p JOIN games g ON  p.game_id = g.id WHERE p.user_id = ?', $user_id, $condition);
		if ($row = $query->next())
		{
			list ($games, $won, $rating, $best_player, $best_move, $worst_move, $guess_3_maf, $guess_2_maf, $guess_1_maf, $warinigs, $voted_civ, $voted_maf, $voted_sheriff, $voted_by_civ, $voted_by_maf, $voted_by_sheriff, $nominated_civ, $nominated_maf, $nominated_sheriff, $nominated_by_civ, $nominated_by_maf, $nominated_by_sheriff, $arranged, $arranged_1_night, $checked_by_don, $checked_by_sheriff) = $row;
			$this->response['games'] = (int)$games;
			$this->response['won'] = (int)$won;
			$this->response['rating'] = (float)$rating;
			$this->response['best_player'] = (int)$best_player;
			$this->response['best_move'] = (int)$best_move;
			$this->response['worst_move'] = (int)$worst_move;
			$this->response['guess_3_maf'] = (int)$guess_3_maf;
			$this->response['guess_2_maf'] = (int)$guess_2_maf;
			$this->response['warinigs'] = (int)$warinigs;
			$this->response['voted_civ'] = (int)$voted_civ;
			$this->response['voted_maf'] = (int)$voted_maf;
			$this->response['voted_sheriff'] = (int)$voted_sheriff;
			$this->response['voted_by_civ'] = (int)$voted_by_civ;
			$this->response['voted_by_maf'] = (int)$voted_by_maf;
			$this->response['voted_by_sheriff'] = (int)$voted_by_sheriff;
			$this->response['nominated_civ'] = (int)$nominated_civ;
			$this->response['nominated_maf'] = (int)$nominated_maf;
			$this->response['nominated_sheriff'] = (int)$nominated_sheriff;
			$this->response['nominated_by_civ'] = (int)$nominated_by_civ;
			$this->response['nominated_by_maf'] = (int)$nominated_by_maf;
			$this->response['nominated_by_sheriff'] = (int)$nominated_by_sheriff;
			$this->response['arranged'] = (int)$arranged;
			$this->response['arranged_1_night'] = (int)$arranged_1_night;
			$this->response['checked_by_don'] = (int)$checked_by_don;
			$this->response['checked_by_sheriff'] = (int)$checked_by_sheriff;
		}
		else
		{
			throw new Exc('User ' . $user_id . ' not found.');
		}
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('user_id', 'User id. This is a mandatory parameter. For example: <a href="player_stats.php?user_id=25">/api/get/player_stats.php?user_id=25</a> returns stats for Fantomas.', '-');
		$help->request_param('before', 'Unix timestamp for the end of a period. For example: <a href="player_stats.php?user_id=25&before=1483228800">/api/get/player_stats.php?user_id=25&before=1483228800</a> returns Fantomas stats in the games played before 2017.', '-');
		$help->request_param('after', 'Unix timestamp for the beginning of a period. For example: <a href="player_stats.php?user_id=25&after=1483228800">/api/get/player_stats.php?user_id=25&after=1483228800</a> returns Fantomas stats in the games starting from January 1 2017; <a href="player_stats.php?user_id=25&after=1483228800&before=1485907200">/api/get/player_stats.php?user_id=25&after=1483228800&before=1485907200</a> returns Fantomas stats in the games played in January 2017. (If the game ended in February but started in January it is still a January game).', '-');
		$help->request_param('club_id', 'Club id. For example: <a href="player_stats.php?user_id=25&club_id=1">/api/get/player_stats.php?user_id=25&club_id=1</a> returns Fantomas stats in the games played in Vancouver Mafia Club.', '-');
		$help->request_param('game_id', 'Game id. For example: <a href="player_stats.php?user_id=25&game_id=1299">/api/get/player_stats.php?user_id=25&game_id=1299</a> returns Fantomas statis in the game 1299 played in VaWaCa-2017 tournament.', '-');
		$help->request_param('event_id', 'Event id. For example: <a href="player_stats.php?user_id=25&event_id=7927">/api/get/player_stats.php?user_id=25&event_id=7927</a> returns Fantomas stats in the VaWaCa-2017 tournament.', '-');
		$help->request_param('address_id', 'Address id. For example: <a href="player_stats.php?user_id=25&address_id=10">/api/get/player_stats.php?user_id=25&address_id=10</a> returns Fantomas stats in the games played in Tafs Cafe by Vancouver Mafia Club.', '-');
		$help->request_param('city_id', 'City id. For example: <a href="player_stats.php?user_id=25&city_id=49">/api/get/player_stats.php?user_id=25&city_id=49</a> returns Fantomas stats in the games played in Seattle. List of the cities and their ids can be obtained using <a href="cities.php?help">/api/get/cities.php</a>.', '-');
		$help->request_param('area_id', 'City id. The difference with city is that when area is set, the games from all nearby cities are also returned. For example: <a href="player_stats.php?user_id=25&area_id=1">/api/get/player_stats.php?user_id=25&area_id=1</a> returns Fantomas stats in the games played in Vancouver and nearby cities. Though <a href="player_stats.php?user_id=25&city=1">/api/get/player_stats.php?user_id=25&city=1</a> returns Fantomas stats in the games played only in Vancouver itself.', '-');
		$help->request_param('country_id', 'Country id. For example: <a href="player_stats.php?user_id=25&country_id=2">/api/get/player_stats.php?user_id=25&country_id=2</a> returns Fantomas stats in the games played in Russia. List of the countries and their ids can be obtained using <a href="countries.php?help">/api/get/countries.php</a>.', '-');
		$help->request_param('with_user', 'User id. For example: <a href="player_stats.php?user_id=25&with_user=4">/api/get/player_stats.php?user_id=25&with_user=4</a> returns Fantomas stats in the games that he played with lilya.', '-');
		$help->request_param('langs', 'Languages filter. A bit combination of language ids. For example: <a href="player_stats.php?user_id=25&langs=1">/api/get/player_stats.php?user_id=25&langs=1</a> returns Fantomas stats in the games played in English.' . valid_langs_help(), '-');
		$param = $help->request_param('filter_flags', 'Bit flags helping to filter games. For example <a href="player_stats.php?user_id=25&filter_flags=5">' . PRODUCT_URL . '/api/get/player_stats.php?user_id=25&filter_flags=5</a> returns stats for only tournament games with video. The flags are:', '-');
			$param->sub_param('1', 'Return only the games with video.', '-');
			$param->sub_param('2', 'Return only the games without video.', '-');
			$param->sub_param('4', 'Return only tournament games.', '-');
			$param->sub_param('8', 'Return only non-tournament games.', '-');
			$param->sub_param('16', 'Return only rating games.', '-');
			$param->sub_param('32', 'Return only non-rating games.', '-');
			$param->sub_param('64', 'Return only canceled games.', '-');
			$param->sub_param('128', 'Return only non-canceled games.', '-');
		$help->request_param('role', 'Player stats in the specified role only. Possible values are:
				<ul>
					<li>a - all roles (default)</li>
					<li>r - red roles = civilian + sheriff: <a href="player_stats.php?user_id=25&role=r">/api/get/player_stats.php?user_id=25&role=r</a></li>
					<li>b - black roles = mafia + don: <a href="player_stats.php?user_id=25&role=b">/api/get/player_stats.php?user_id=25&role=b</a></li>
					<li>c - civilian but not the sheriff: <a href="player_stats.php?user_id=25&role=c">/api/get/player_stats.php?user_id=25&role=c</a></li>
					<li>s - sheriff: <a href="player_stats.php?user_id=25&role=s">/api/get/player_stats.php?user_id=25&role=s</a></li>
					<li>m - mafia but not the don: <a href="player_stats.php?user_id=25&role=m">/api/get/player_stats.php?user_id=25&role=m</a></li>
					<li>d - don: <a href="player_stats.php?user_id=25&role=d">/api/get/player_stats.php?user_id=25&role=d</a></li>
				</ul>', '-');
		$help->request_param('number', 'Number in the game (1-10). For example: <a href="player_stats.php?user_id=25&number=2">/api/get/player_stats.php?user_id=25&number=2</a> returns Fantomas stats earned in the VaWaCa-2017 tournement when he was number 2.', '-');

		$help->response_param('games', 'How many games played.');
		$help->response_param('won', 'How many games were won.');
		$help->response_param('rating', 'Rating earned in the selected games.');
		$help->response_param('best_player', 'How many times the player was the best player in the selected games.');
		$help->response_param('best_move', 'How many times the player made the best move in the selected games.');
		$help->response_param('worst_move', 'How many times the player made the worst move (aka auto-bonus was removed) in the selected games.');
		$help->response_param('guess_3_maf', 'How many times the player guessed all 3 mafs after being killed the first night.');
		$help->response_param('guess_2_maf', 'How many times the player guessed 2 out of 3 mafs after being killed the first night.');
		$help->response_param('warinigs', 'How many warnings the player got in the selected games.');
		$help->response_param('voted_civ', 'How many times the player voted against civilians in the selected games.');
		$help->response_param('voted_maf', 'How many times the player voted against mafia in the selected games.');
		$help->response_param('voted_sheriff', 'How many times the player voted against sheriff in the selected games.');
		$help->response_param('voted_by_civ', 'How many times civilians voted against the player in the selected games.');
		$help->response_param('voted_by_maf', 'How many times mafia voted against the player in the selected games.');
		$help->response_param('voted_by_sheriff', 'How many times sheriff voted against the player in the selected games.');
		$help->response_param('nominated_civ', 'How many times the player nominated civilians in the selected games.');
		$help->response_param('nominated_maf', 'How many times the player nominated mafia in the selected games.');
		$help->response_param('nominated_sheriff', 'How many times the player nominated sheriff in the selected games.');
		$help->response_param('nominated_by_civ', 'How many times civilians nominated the player in the selected games.');
		$help->response_param('nominated_by_maf', 'How many times mafia nominated the player in the selected games.');
		$help->response_param('nominated_by_sheriff', 'How many times sheriff nominated the player in the selected games.');
		$help->response_param('arranged', 'How many times the player was statically arranged to be killed by mafia in the selected games.');
		$help->response_param('arranged_1_night', 'How many times the player was statically arranged for the first night in the selected games.');
		$help->response_param('checked_by_don', 'How many times the player was checked by don in the selected games.');
		$help->response_param('checked_by_sheriff', 'How many times the player was checked by sheriff in the selected games.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Player Statistics', CURRENT_VERSION);

?>