<?php

require_once '../../include/api.php';
require_once '../../include/scoring.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		$raw = isset($_REQUEST['raw']);
		
		$user = 0;
		if (isset($_REQUEST['user']))
		{
			$user = (int)$_REQUEST['user'];
		}
		else
		{
			throw new Exc('Please specify the user id. For example player_stats.php?user=25.');
		}
		
		$before = 0;
		if (isset($_REQUEST['before']))
		{
			$before = (int)$_REQUEST['before'];
		}
		$after = 0;
		if (isset($_REQUEST['after']))
		{
			$after = (int)$_REQUEST['after'];
		}
		
		$club = 0;
		if (isset($_REQUEST['club']))
		{
			$club = (int)$_REQUEST['club'];
		}
		
		$address = 0;
		if (isset($_REQUEST['address']))
		{
			$address = (int)$_REQUEST['address'];
		}
		
		$event = 0;
		if (isset($_REQUEST['event']))
		{
			$event = (int)$_REQUEST['event'];
		}
		
		$game = 0;
		if (isset($_REQUEST['game']))
		{
			$game = (int)$_REQUEST['game'];
		}
		
		$with_user = 0;
		if (isset($_REQUEST['with_user']))
		{
			$with_user = (int)$_REQUEST['with_user'];
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
		
		$langs = LANG_ALL;
		if (isset($_REQUEST['langs']))
		{
			$langs = (int)$_REQUEST['langs'];
		}
		
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
					$role = POINTS_DARK;
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
		
		$number = 0;
		if (isset($_REQUEST['number']))
		{
			$number = (int)$_REQUEST['number'];
		}
		
		$query = new DbQuery(
			'SELECT COUNT(*), SUM(p.won), SUM(p.rating_earned), SUM(IF((p.flags & ' . SCORING_FLAG_BEST_PLAYER . ') <> 0, 1, 0)),' .
			' SUM(IF((p.flags & ' . SCORING_FLAG_BEST_MOVE . ') <> 0, 1, 0)), SUM(IF((p.flags & ' . SCORING_FLAG_GUESSED_3 . ') <> 0, 1, 0)), SUM(IF((p.flags & ' . SCORING_FLAG_GUESSED_2 . ') <> 0, 1, 0)), SUM(p.warns),' .
			' SUM(p.voted_civil), SUM(p.voted_mafia), SUM(p.voted_sheriff), SUM(p.voted_by_civil), SUM(p.voted_by_mafia), SUM(p.voted_by_sheriff),' .
			' SUM(p.nominated_civil), SUM(p.nominated_mafia), SUM(p.nominated_sheriff), SUM(p.nominated_by_civil), SUM(p.nominated_by_mafia), SUM(p.nominated_by_sheriff),' .
			' SUM(IF(p.was_arranged < 0, 0, 1)), SUM(IF(p.was_arranged <> 0, 0, 1)), SUM(IF(p.checked_by_don < 0, 0, 1)), SUM(IF(p.checked_by_sheriff < 0, 0, 1))' .
			' FROM players p JOIN games g ON  p.game_id = g.id WHERE g.result IN(1,2) AND p.user_id = ?', $user);
		$query->add(get_roles_condition($role));
		
		if ($before > 0)
		{
			$query->add(' AND g.start_time < ?', $before);
		}

		if ($after > 0)
		{
			$query->add(' AND g.start_time >= ?', $after);
		}

		if ($club > 0)
		{
			$query->add(' AND g.club_id = ?', $club);
		}

		if ($game > 0)
		{
			$query->add(' AND g.id = ?', $game);
		}
		else if ($event > 0)
		{
			$query->add(' AND g.event_id = ?', $event);
		}
		else if ($address > 0)
		{
			$query->add(' AND g.event_id IN (SELECT id FROM events WHERE address_id = ?)', $address);
		}
		else if ($city > 0)
		{
			$query->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id WHERE a1.city_id = ?)', $city);
		}
		else if ($area > 0)
		{
			$query->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.area_id = (SELECT area_id FROM cities WHERE id = ?))', $area);
		}
		else if ($country > 0)
		{
			$query->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.country_id = ?)', $country);
		}
		
		if ($langs != LANG_ALL)
		{
			$query->add(' AND (g.language & ?) <> 0', $langs);
		}
		
		if ($with_user > 0)
		{
			$query->add(' AND g.id IN (SELECT game_id FROM players WHERE user_id = ?)', $with_user);
		}
		
		if ($number > 0)
		{
			$query->add(' AND p.number = ?', $number);
		}

		if ($row = $query->next())
		{
			list ($games, $won, $rating, $best_player, $best_move, $guess_3_maf, $guess_2_maf, $warinigs, $voted_civ, $voted_maf, $voted_sheriff, $voted_by_civ, $voted_by_maf, $voted_by_sheriff, $nominated_civ, $nominated_maf, $nominated_sheriff, $nominated_by_civ, $nominated_by_maf, $nominated_by_sheriff, $arranged, $arranged_1_night, $checked_by_don, $checked_by_sheriff) = $row;
			$this->response['games'] = (int)$games;
			$this->response['won'] = (int)$won;
			$this->response['rating'] = (float)$rating;
			$this->response['best_player'] = (int)$best_player;
			$this->response['best_move'] = (int)$best_move;
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
			throw new Exc('User ' . $user . ' not found.');
		}
	}
	
	protected function get_help()
	{
		$help = new ApiHelp();
		$help->request_param('user', 'User id. This is a mandatory parameter. For example: <a href="player_stats.php?user=25"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25</a> returns stats for Fantomas.', '-');
		$help->request_param('before', 'Unix timestamp for the end of a period. For example: <a href="player_stats.php?user=25&before=1483228800"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&before=1483228800</a> returns Fantomas stats in the games played before 2017.', '-');
		$help->request_param('after', 'Unix timestamp for the beginning of a period. For example: <a href="player_stats.php?user=25&after=1483228800"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&after=1483228800</a> returns Fantomas stats in the games starting from January 1 2017; <a href="player_stats.php?user=25&after=1483228800&before=1485907200"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&after=1483228800&before=1485907200</a> returns Fantomas stats in the games played in January 2017. (If the game ended in February but started in January it is still a January game).', '-');
		$help->request_param('club', 'Club id. For example: <a href="player_stats.php?user=25&club=1"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&club=1</a> returns Fantomas stats in the games played in Vancouver Mafia Club.', '-');
		$help->request_param('game', 'Game id. For example: <a href="player_stats.php?user=25&game=1299"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&game=1299</a> returns Fantomas statis in the game 1299 played in VaWaCa-2017 tournament.', '-');
		$help->request_param('event', 'Event id. For example: <a href="player_stats.php?user=25&event=7927"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&event=7927</a> returns Fantomas stats in the VaWaCa-2017 tournament.', '-');
		$help->request_param('address', 'Address id. For example: <a href="player_stats.php?user=25&address=10"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&address=10</a> returns Fantomas stats in the games played in Tafs Cafe by Vancouver Mafia Club.', '-');
		$help->request_param('city', 'City id. For example: <a href="player_stats.php?user=25&city=49"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&city=49</a> returns Fantomas stats in the games played in Seattle. List of the cities and their ids can be obtained using <a href="cities.php?help"><?php echo PRODUCT_URL; ?>/api/get/cities.php</a>.', '-');
		$help->request_param('area', 'City id. The difference with city is that when area is set, the games from all nearby cities are also returned. For example: <a href="player_stats.php?user=25&area=1"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&area=1</a> returns Fantomas stats in the games played in Vancouver and nearby cities. Though <a href="player_stats.php?user=25&city=1"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&city=1</a> returns Fantomas stats in the games played only in Vancouver itself.', '-');
		$help->request_param('country', 'Country id. For example: <a href="player_stats.php?user=25&country=2"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&country=2</a> returns Fantomas stats in the games played in Russia. List of the countries and their ids can be obtained using <a href="countries.php?help"><?php echo PRODUCT_URL; ?>/api/get/countries.php</a>.', '-');
		$help->request_param('with_user', 'User id. For example: <a href="player_stats.php?user=25&with_user=4"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&with_user=4</a> returns Fantomas stats in the games that he played with lilya.', '-');
		$help->request_param('langs', 'Languages filter. 1 for English; 2 for Russian. Bit combination - 3 - means both (this is a default value). For example: <a href="player_stats.php?user=25&langs=1"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&langs=1</a> returns Fantomas stats in the games played in English.', '-');
		$help->request_param('role', 'Player stats in the specified role only. Possible values are:
				<ul>
					<li>a - all roles (default)</li>
					<li>r - red roles = civilian + sheriff: <a href="player_stats.php?user=25&role=r"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&role=r</a></li>
					<li>b - black roles = mafia + don: <a href="player_stats.php?user=25&role=b"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&role=b</a></li>
					<li>c - civilian but not the sheriff: <a href="player_stats.php?user=25&role=c"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&role=c</a></li>
					<li>s - sheriff: <a href="player_stats.php?user=25&role=s"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&role=s</a></li>
					<li>m - mafia but not the don: <a href="player_stats.php?user=25&role=m"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&role=m</a></li>
					<li>d - don: <a href="player_stats.php?user=25&role=d"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&role=d</a></li>
				</ul>', '-');
		$help->request_param('number', 'Number in the game (1-10). For example: <a href="player_stats.php?user=25&number=2"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php?user=25&number=2</a> returns Fantomas stats earned in the VaWaCa-2017 tournement when he was number 2.', '-');

		$help->response_param('games', 'How many games played.');
		$help->response_param('won', 'How many games were won.');
		$help->response_param('rating', 'Rating earned in the selected games.');
		$help->response_param('best_player', 'How many times the player was the best player in the selected games.');
		$help->response_param('best_move', 'How many times the player made the best move in the selected games.');
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