<?php

require_once '../../include/session.php';
require_once '../../include/api.php';
require_once '../../include/scoring.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		$scoring = 0;
		if (isset($_REQUEST['scoring']))
		{
			$scoring = (int)$_REQUEST['scoring'];
		}
		
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
		
		if ($scoring <= 0)
		{
			if ($game > 0)
			{
				list($scoring) = Db::record('scoring', 'SELECT e.scoring_id FROM games g JOIN events e ON g.event_id = e.id WHERE g.id = ?', $game);
			}
			else if ($event > 0)
			{
				list($scoring) = Db::record('scoring', 'SELECT scoring_id FROM events WHERE id = ?', $event);
			}
			else if ($address > 0)
			{
				list($scoring) = Db::record('scoring', 'SELECT c.scoring_id FROM addresses a JOIN clubs c ON a.club_id = c.id WHERE a.id = ?', $address);
			}
			else if ($club > 0)
			{
				list($scoring) = Db::record('scoring', 'SELECT scoring_id FROM clubs WHERE id = ?', $club);
			}
			else
			{
				$scoring = SCORING_DEFAULT_ID;
			}
		}
		$this->response['scoring_id'] = (int)$scoring;
		
		$condition = new SQL();
		$scope_condition = new SQL(' AND (u.flags & ' . USER_FLAG_BANNED . ') = 0 AND u.games > 0');
		$this->response['role']	= $role;
		
		$scope_condition->add(get_roles_condition($role));
		if (isset($contains))
		{
			$scope_condition->add(' AND u.name LIKE(?)', '%' . $contains . '%');
		}
		
		if (isset($starts))
		{
			$scope_condition->add(' AND (u.name LIKE(?) OR u.name LIKE(?))', $starts . '%', '% ' . $starts . '%');
		}
		
		if ($number > 0)
		{
			$scope_condition->add(' AND p.number = ?', $number);
		}
			
		if ($club > 0)
		{
			$condition->add(' AND g.club_id = ?', $club);
		}
		
		if ($game > 0)
		{
			$scope_condition->add(' AND g.id = ?', $game);
		}
		
		if ($event > 0)
		{
			$condition->add(' AND g.event_id = ?', $event);
		}
		else if ($address > 0)
		{
			$condition->add(' AND g.id IN (SELECT g1.id FROM games g1 JOIN events e1 ON g1.event_id = e1.id WHERE e1.address_id = ?)', $address);
		}
		else if ($city > 0)
		{
			$condition->add(' AND g.id IN (SELECT g1.id FROM games g1 JOIN events e1 ON g1.event_id = e1.id JOIN addresses a1 ON e1.address_id = a1.id WHERE a1.city_id = ?)', $city);
		}
		else if ($area > 0)
		{
			$condition->add(' AND g.id IN (SELECT g1.id FROM games g1 JOIN events e1 ON g1.event_id = e1.id JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.area_id = (SELECT area_id FROM cities WHERE id = ?))', $area);
		}
		else if ($country > 0)
		{
			$condition->add(' AND g.id IN (SELECT g1.id FROM games g1 JOIN events e1 ON g1.event_id = e1.id JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.country_id = ?)', $country);
		}
		else if ($game > 0)
		{
			$condition->add(' AND g.event_id = (SELECT event_id FROM games WHERE id = ?)', $game);
		}
		
		if ($with_user > 0)
		{
			$scope_condition->add(' AND g.id IN (SELECT game_id FROM players WHERE user_id = ?)', $with_user);
		}
		
		if ($before > 0)
		{
			$scope_condition->add(' AND g.start_time < ?', $before);
		}
		
		if ($after > 0)
		{
			$scope_condition->add(' AND g.start_time >= ?', $after);
		}
			
		if ($langs != LANG_ALL)
		{
			$scope_condition->add(' AND (g.language & ?) <> 0', $langs);
		}
		
		$scope_condition->add(get_roles_condition($role));
		
		// $this->response['condition'] = $condition->get_parsed_sql();
		// $this->response['scope_condition'] = $scope_condition->get_parsed_sql();
			
		$scoring_system = new ScoringSystem($scoring);
		$scores = new Scores($scoring_system, $condition, $scope_condition);
		$players_count = count($scores->players);
		
		$this->response['count'] = $players_count;
		if (!$count_only)
		{
			$dst_scores = array();
			if ($page_size > 0)
			{
				$page_start = $page * $page_size;
				if ($players_count > $page_start + $page_size)
				{
					$players_count = $page_start + $page_size;
				}
			}
			else
			{
				$page_start = 0;
			}
			for ($number = $page_start; $number < $players_count; ++$number)
			{
				$score = $scores->players[$number];
				$dst_score = new stdClass();
				$dst_score->id = $score->id;
				$dst_score->name = $score->name;
				$dst_score->languages = $score->langs;
				$dst_score->points = $score->points;
				$dst_score->num_games = $score->games_played;
				$dst_score->games_won = $score->games_won;
				$dst_score->club_id = $score->club_id;
				$dst_score->num = (int)$number + 1;
				
				if (($score->flags & USER_FLAG_MALE) != 0)
				{
					$dst_score->male = true;
				}
				if (($score->flags & USER_ICON_MASK) != 0)
				{
					$dst_score->image = USER_PICS_DIR . TNAILS_DIR . $score->id . '.png?' . (($score->flags & USER_ICON_MASK) >> USER_ICON_MASK_OFFSET);
					$dst_score->icon = USER_PICS_DIR . ICONS_DIR . $score->id . '.png?' . (($score->flags & USER_ICON_MASK) >> USER_ICON_MASK_OFFSET);
				}
				$dst_scores[] = $dst_score;
			}
			$this->response['scores'] = $dst_scores;
		}
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('contains', 'Search pattern. For example: <a href="scores.php?contains=al">/api/get/scores.php?contains=al</a> returns players containing "al" in their name.', '-');
		$help->request_param('starts', 'Search pattern. For example: <a href="scores.php?starts=bo">/api/get/scores.php?starts=bo</a> returns players with names starting with "bo". Note that "Bad Boy" is also returned.', '-');
		$help->request_param('scoring', 'Scoring system id. For example: <a href="scores.php?club=1&scoring=13">/api/get/scores.php?club=1&scoring=13</a> returns scores for all players of Vancouver Mafia Club using 3-4-4-5 scoring system. If missing, the club scoring system (if club is specified) or event scoring system (if event is specified) or default scoring system is used.', '-');
		$help->request_param('club', 'Club id. For example: <a href="scores.php?club=1">/api/get/scores.php?club=1</a> returns the scores in Vancouver Mafia Club. Club scoring system is used by default.', '-');
		$help->request_param('game', 'Game id. For example: <a href="scores.php?game=1299">/api/get/scores.php?game=1299</a> returns the scores of the game 1299, played in VaWaCa-2017 tournament. VaWaCa-2017 event scoring system is used by default because this game belongs to this event.', '-');
		$help->request_param('event', 'Event id. For example: <a href="scores.php?event=7927">/api/get/scores.php?event=7927</a> returns the scores of VaWaCa-2017 tournament. VaWaCa-2017 event scoring system is used by default.', '-');
		$help->request_param('address', 'Address id. For example: <a href="scores.php?address=10">/api/get/scores.php?address=10</a> returns the scores of the games played in Tafs Cafe in Vancouver Mafia Club. Vancouver Mafia Club scoring system is used by default.', '-');
		$help->request_param('city', 'City id. For example: <a href="scores.php?city=49">/api/get/scores.php?city=49</a> returns the scores of the games played in Seattle. Default scoring system is used by default (currently it is ФИИМ).', '-');
		$help->request_param('area', 'City id. The difference with city is that when area is set, the games from all nearby cities are also returned. For example: <a href="scores.php?area=1">/api/get/scores.php?area=1</a> returns the scores of the games played in Vancouver and nearby cities like Delta, Burnaby, Surrey, etc. Though <a href="scores.php?city=1">/api/get/scores.php?city=1</a> returns only the scores in Vancouver itself. Default scoring system is used by default (currently it is ФИИМ).', '-');
		$help->request_param('country', 'Country id. For example: <a href="scores.php?country=2">/api/get/scores.php?country=2</a> returns the sores of the games played in Russia. Default scoring system is used by default (currently it is ФИИМ).', '-');
		$help->request_param('with_user', 'User id. For example: <a href="scores.php?with_user=4">/api/get/scores.php?with_user=4</a> returns the scores for the games where lilya participated. Default scoring system is used by default (currently it is ФИИМ).', '-');
		$help->request_param('langs', 'Languages filter. A bit combination of language ids. For example: <a href="scores.php?langs=1">/api/get/scores.php?langs=1</a> returns the scores for games played in English. Default scoring system is used by default (currently it is ФИИМ).' . valid_langs_help(), '-');
		$help->request_param('role', 'The score for the specified role only. Possible values are:
				<ul>
					<li>a - all roles (default)</li>
					<li>r - red roles = civilian + sheriff: <a href="scores.php?role=r">/api/get/scores.php?role=r</a></li>
					<li>b - black roles = mafia + don: <a href="scores.php?role=b">/api/get/scores.php?role=b</a></li>
					<li>c - civilian but not the sheriff: <a href="scores.php?role=c">/api/get/scores.php?role=c</a></li>
					<li>s - sheriff: <a href="scores.php?role=s">/api/get/scores.php?role=s</a></li>
					<li>m - mafia but not the don: <a href="scores.php?role=m">/api/get/scores.php?role=m</a></li>
					<li>d - don: <a href="scores.php?role=d">/api/get/scores.php?role=d</a></li>
				</ul>', '-');
		$help->request_param('number', 'Number in the game (1-10). For example: <a href="scores.php?number=2&event=7927">/api/get/scores.php?number=2&event=7927</a> returns scores earned by players playing on number 2 in VaWaCa-2017 tournement. In the other words: who is the best number 2 in the tournament.', '-');
		$help->request_param('before', 'Unix timestamp. For example: <a href="scores.php?before=1483228800">/api/get/scores.php?before=1483228800</a> returns scores earned before 2017. In other words it returns scores as they were at the end of 2016.', '-');
		$help->request_param('after', 'Unix timestamp. For example: <a href="scores.php?after=1483228800">/api/get/scores.php?after=1483228800</a> returns scores earned after January 1, 2017; <a href="scores.php?after=1483228800&before=1485907200">/api/get/scores.php?after=1483228800&before=1485907200</a> returns scores earned in January 2017', '-');
		$help->request_param('count', 'Returns game count instead of players list. For example: <a href="scores.php?club=1&count">/api/get/scores.php?club=1&count</a> returns how many players with scores are there in Vancouver Mafia Club; <a href="scores.php?event=7927&count">/api/get/scores.php?event=7927&count</a> returns how many players with scores participated in VaWaCa-2017 tournament.', '-');
		$help->request_param('page', 'Page number. For example: <a href="scores.php?club=1&page=1">/api/get/scores.php?club=1&page=1</a> returns the second page of scores for Vancouver Mafia Club players.', '-');
		$help->request_param('page_size', 'Page size. Default page_size is ' . API_DEFAULT_PAGE_SIZE . '. For example: <a href="scores.php?club=1&page_size=32">/api/get/scores.php?club=1&page_size=32</a> returns top 32 players for Vancouver Mafia Club; <a href="scores.php?club=6&page_size=0">/api/get/scores.php?club=6&page_size=0</a> returns all players for Empire of Mafia club in one page; <a href="scores.php?club=1">/api/get/scores.php?club=1</a> returns top ' . API_DEFAULT_PAGE_SIZE . ' players for Vancouver Mafia Club;', '-');

		$param = $help->response_param('scores', 'The array of scores. scores are always sorted in descending order. There is no way to change sorting order in the current version of the API.');
			$param->sub_param('num', 'Number in the current list.');
			$param->sub_param('id', 'User id. Unique user identifier.');
			$param->sub_param('name', 'User name.');
			$param->sub_param('image', 'A link to the user image at mafiaratings.com. Not set when the image is not uploaded by the user.');
			$param->sub_param('icon', 'A link to the user icon at mafiaratings.com. Not set when the icon is not uploaded by the user.');
			$param->sub_param('points', 'The score.');
			$param->sub_param('num_games', 'Number of games played by the player.');
			$param->sub_param('games_won', 'Number of games won by the player.');
			$param->sub_param('male', 'True for males; not set for females.');
			$param->sub_param('club_id', 'This player\'s main club id.');
		$help->response_param('count', 'The total number of players satisfying the request parameters.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Scores', CURRENT_VERSION);

?>