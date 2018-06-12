<?php

require_once '../../include/api.php';
require_once '../../include/game_state.php';

define('CURRENT_VERSION', 0);

class ApiPlayer
{
	public $user_id;
	public $nick_name;
	public $role;
	public $death_round;
	public $death_type;
	public $warnings;
	public $arranged_for_round;
	public $checked_by_don;
	public $checked_by_srf;
	public $best_player;
	public $best_move;
	public $mafs_guessed;
	
	function normalize()
	{
		$this->user_id = (int)$this->user_id;
		$this->role = (int)$this->role;
		$this->death_round = (int)$this->death_round;
		$this->warnings = (int)$this->warnings;
		$this->arranged_for_round = (int)$this->arranged_for_round;
		$this->checked_by_don = (int)$this->checked_by_don;
		$this->checked_by_srf = (int)$this->checked_by_srf;

		switch ($this->role)
		{
			case PLAYER_ROLE_CIVILIAN:
				$this->role = 'civ';
				break;
			case PLAYER_ROLE_SHERIFF:
				$this->role = 'srf';
				break;
			case PLAYER_ROLE_MAFIA:
				$this->role = 'maf';
				break;
			case PLAYER_ROLE_DON:
				$this->role = 'don';
				break;
		}
		
		if ($this->death_round < 0)
		{
			unset($this->death_round);
		}
		
		if ($this->user_id <= 0)
		{
			unset($this->user_id);
		}
		
		if ($this->warnings <= 0)
		{
			unset($this->warnings);
		}
		
		if ($this->arranged_for_round < 0)
		{
			unset($this->arranged_for_round);
		}
		
		if ($this->checked_by_srf < 0)
		{
			unset($this->checked_by_srf);
		}
		
		if ($this->checked_by_don < 0)
		{
			unset($this->checked_by_don);
		}
		
		if (!$this->best_player)
		{
			unset($this->best_player);
		}
		
		if (!$this->best_move)
		{
			unset($this->best_move);
		}
		
		if ($this->mafs_guessed < 0)
		{
			unset($this->mafs_guessed);
		}
	}
	
	function __construct($gs, $index)
	{
		$player = $gs->players[$index];
		$this->user_id = $player->id;
		$this->nick_name = $player->nick;
		$this->role = $player->role;
		$this->death_round = $player->kill_round;
        switch ($player->kill_reason)
        {
            case KILL_REASON_NORMAL:
                if ($player->state == PLAYER_STATE_KILLED_NIGHT)
                {
                    $this->death_type = 'night';
                }
                else if ($player->state == PLAYER_STATE_KILLED_DAY)
                {
                    $this->death_type = 'day';
                }
                break;
            case KILL_REASON_SUICIDE:
                $this->death_type = 'suicide';
                break;
            case KILL_REASON_WARNINGS:
                $this->death_type = 'warning';
                break;
            case KILL_REASON_KICK_OUT:
                $this->death_type = 'kick-out';
                break;
            default:
				unset($this->death_type);
                break;
        }
		
		$this->warnings = $player->warnings;
		$this->arranged_for_round = $player->arranged;
		$this->checked_by_don = $player->don_check;
		$this->checked_by_srf = $player->sheriff_check;
		$this->best_player = ($gs->best_player == $index);
		$this->best_move = ($gs->best_move == $index);
		$this->mafs_guessed = $gs->mafs_guessed($index);
		
		$this->normalize();
	}
}

class ApiGame
{
	public $id;
	public $club_id;
	public $event_id;
	public $start_time;
	public $end_time;
	public $language;
	public $moderator_id;
	public $winner;
	public $players;
	
	function __construct($gs)
	{
		$this->id = $gs->id;
		$this->club_id = $gs->club_id;
		if (is_null($gs->event_id))
		{
			unset($this->event_id);
		}
		else
		{
			$this->event_id = $gs->event_id;
		}
		$this->start_time = $gs->start_time;
		$this->end_time = $gs->end_time;
		
		$this->language = (int)$gs->lang;
		switch ($gs->gamestate)
		{
			case GAME_MAFIA_WON:
				$this->winner = 'maf';
				break;
			case GAME_CIVIL_WON:
				$this->winner = 'civ';
				break;
			default:
				unset($this->winner);
				break;
		}
		$this->moderator_id = $gs->moder_id;
		
		$this->players = array();
		for ($i = 0; $i < 10; ++$i)
		{
			$this->players[$i] = new ApiPlayer($gs, $i);
		}
		
		// $this->voting = $gs->votings;
		foreach ($gs->votings as $voting)
		{
			$round_key = 'round_' . $voting->round;
			if ($voting->voting_round == 0)
			{
				foreach ($voting->nominants as $nominant)
				{
					if ($nominant->nominated_by >= 0 && $nominant->nominated_by < 10)
					{
						$this->players[$nominant->nominated_by]->nominating[$round_key] = $nominant->player_num;
					}
				}
			}
			
			for ($i = 0; $i < 10; ++$i)
			{
				$vote = $voting->votes[$i];
				if ($vote >= 0)
				{
					$player = $this->players[$i];
					if (!isset($player->voting) || !isset($player->voting[$round_key]))
					{
						$player->voting[$round_key] = $voting->nominants[$vote]->player_num;
					}
					else if (is_array($player->voting[$round_key]))
					{
						$player->voting[$round_key][] = $voting->nominants[$vote]->player_num;
					}
					else
					{
						$player->voting[$round_key] = array($player->voting[$round_key], $voting->nominants[$vote]->player_num);
					}
				}
			}
		}
		
		$round = 0;
		foreach ($gs->shooting as $shots)
		{
			foreach ($shots as $shoter => $shooted)
			{
				if ($shooted >= 0 && $shooted < 10)
				{
					$this->players[$shoter]->shooting['round_' . $round] = $shooted;
				}
			}
			++$round;
		}
	}
}

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		$raw = isset($_REQUEST['raw']);
		
		$after = 0;
		if (isset($_REQUEST['after']))
		{
			$after = (int)$_REQUEST['after'];
		}
		
		$before = 0;
		if (isset($_REQUEST['before']))
		{
			$before = (int)$_REQUEST['before'];
		}
		
		$club = 0;
		if (isset($_REQUEST['club']))
		{
			$club = (int)$_REQUEST['club'];
		}
		
		$event = 0;
		if (isset($_REQUEST['event']))
		{
			$event = (int)$_REQUEST['event'];
		}
		
		$user = 0;
		if (isset($_REQUEST['user']))
		{
			$user = (int)$_REQUEST['user'];
		}
		
		$langs = LANG_ALL;
		if (isset($_REQUEST['langs']))
		{
			$langs = (int)$_REQUEST['langs'];
		}
		
		$game = 0;
		if (isset($_REQUEST['game']))
		{
			$game = (int)$_REQUEST['game'];
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
		
		$page_size = 16;
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
		
		$condition = new SQL('');
		if ($before > 0)
		{
			$condition->add(' AND g.start_time < ?', $before);
		}

		if ($after > 0)
		{
			$condition->add(' AND g.start_time >= ?', $after);
		}

		if ($club > 0)
		{
			$condition->add(' AND g.club_id = ?', $club);
		}

		if ($game > 0)
		{
			$condition->add(' AND g.id = ?', $game);
		}
		else if ($event > 0)
		{
			$condition->add(' AND g.event_id = ?', $event);
		}
		else if ($address > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT id FROM events WHERE address_id = ?)', $address);
		}
		else if ($city > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id WHERE a1.city_id = ?)', $city);
		}
		else if ($area > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.area_id = (SELECT area_id FROM cities WHERE id = ?))', $area);
		}
		else if ($country > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.country_id = ?)', $country);
		}
		
		if ($langs != LANG_ALL)
		{
			$condition->add(' AND (g.language & ?) <> 0', $langs);
		}
		
		$condition->add(' ORDER BY g.start_time DESC');
		
		if ($user > 0)
		{
			$count_query = new DbQuery('SELECT count(*) FROM players p JOIN games g ON p.game_id = g.id WHERE g.result IN(1,2) AND p.user_id = ?', $user, $condition);
			$query = new DbQuery('SELECT g.id, g.log FROM players p JOIN games g ON  p.game_id = g.id WHERE g.result IN(1,2) AND p.user_id = ?', $user, $condition);
		}
		else
		{
			$count_query = new DbQuery('SELECT count(*) FROM games g WHERE g.result IN(1,2)', $condition);
			$query = new DbQuery('SELECT g.id, g.log FROM games g WHERE g.result IN(1,2)', $condition);
		}
		
		list ($count) = $count_query->record('game');
		$this->response['count'] = (int)$count;
		if (!$count_only)
		{
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$games = array();
			while ($row = $query->next())
			{
				list ($id, $log) = $row;
				$gs = new GameState();
				$gs->init_existing($id, $log);
				if ($raw)
				{
					$game = $gs;
				}
				else
				{
					$game = new ApiGame($gs);
				}
				$games[] = $game;
			}
			$this->response['games'] = $games;
		}
	}
	
	protected function get_help()
	{
		$help = new ApiHelp();
		$help->request_param('before', 'Unix timestamp for the latest game to return. For example: <a href="games.php?before=1483228800"><?php echo PRODUCT_URL; ?>/api/get/games.php?before=1483228800</a> returns all games started before 2017', '-');
		$help->request_param('after', 'Unix timestamp for the earliest game to return. For example: <a href="games.php?after=1483228800"><?php echo PRODUCT_URL; ?>/api/get/games.php?after=1483228800</a> returns all games started after January 1, 2017 inclusive; <a href="games.php?after=1483228800&before=1485907200"><?php echo PRODUCT_URL; ?>/api/get/games.php?after=1483228800&before=1485907200</a> returns all games played in January 2017. (Using start time - if the game ended in February but started in January it is still a January game).', '-');
		$help->request_param('club', 'Club id. For example: <a href="games.php?club=1"><?php echo PRODUCT_URL; ?>/api/get/games.php?club=1</a> returns all games for Vancouver Mafia Club. If missing, all games for all clubs are returned.', '-');
		$help->request_param('game', 'Game id. For example: <a href="games.php?game=1299"><?php echo PRODUCT_URL; ?>/api/get/games.php?game=1299</a> returns only one game played in VaWaCa-2017 tournament.', '-');
		$help->request_param('event', 'Event id. For example: <a href="games.php?event=7927"><?php echo PRODUCT_URL; ?>/api/get/games.php?event=7927</a> returns all games for VaWaCa-2017 tournament. If missing, all games for all events are returned.', '-');
		$help->request_param('address', 'Address id. For example: <a href="games.php?address=10"><?php echo PRODUCT_URL; ?>/api/get/games.php?address=10</a> returns all games played in Tafs Cafe by Vancouver Mafia Club.', '-');
		$help->request_param('city', 'City id. For example: <a href="games.php?city=49"><?php echo PRODUCT_URL; ?>/api/get/games.php?city=49</a> returns all games played in Seattle. List of the cities and their ids can be obtained using <a href="cities.php?help"><?php echo PRODUCT_URL; ?>/api/get/cities.php</a>.', '-');
		$help->request_param('area', 'City id. The difference with city is that when area is set, the games from all nearby cities are also returned. For example: <a href="games.php?area=1"><?php echo PRODUCT_URL; ?>/api/get/games.php?area=1</a> returns all games played in Vancouver and nearby cities. Though <a href="games.php?city=1"><?php echo PRODUCT_URL; ?>/api/get/games.php?city=1</a> returns only the games played in Vancouver itself.', '-');
		$help->request_param('country', 'Country id. For example: <a href="games.php?country=2"><?php echo PRODUCT_URL; ?>/api/get/games.php?country=2</a> returns all games played in Russia. List of the countries and their ids can be obtained using <a href="countries.php?help"><?php echo PRODUCT_URL; ?>/api/get/countries.php</a>.', '-');
		$help->request_param('user', 'User id. For example: <a href="games.php?user=25"><?php echo PRODUCT_URL; ?>/api/get/games.php?user=25</a> returns all games where Fantomas played. If missing, all games for all users are returned.', '-');
		$help->request_param('langs', 'Languages filter. 1 for English; 2 for Russian. Bit combination - 3 - means both (this is a default value). For example: <a href="games.php?langs=1"><?php echo PRODUCT_URL; ?>/api/get/games.php?langs=1</a> returns all games played in English; <a href="games.php?club=1&langs=3"><?php echo PRODUCT_URL; ?>/api/get/games.php?club=1&langs=3</a> returns all English and Russian games of Vancouver Mafia Club', '-');
		$help->request_param('count', 'Returns game count but does not return the games. For example: <a href="games.php?user=25&count"><?php echo PRODUCT_URL; ?>/api/get/games.php?user=25&count</a> returns how many games Fantomas have played; <a href="games.php?event=7927&count"><?php echo PRODUCT_URL; ?>/api/get/games.php?event=7927&count</a> returns how many games were played in VaWaCa-2017 tournament.', '-');
		$help->request_param('page', 'Page number. For example: <a href="games.php?club=1&page=1"><?php echo PRODUCT_URL; ?>/api/get/games.php?club=1&page=1</a> returns the second page for Vancouver Mafia Club.', '-');
		$help->request_param('page_size', 'Page size. Default page_size is 16. For example: <a href="games.php?club=1&page_size=32"><?php echo PRODUCT_URL; ?>/api/get/games.php?club=1&page_size=32</a> returns last 32 games for Vancouver Mafia Club; <a href="games.php?club=6&page_size=0"><?php echo PRODUCT_URL; ?>/api/get/games.php?club=6&page_size=0</a> returns all games for Empire of Mafia club in one page; <a href="games.php?club=1"><?php echo PRODUCT_URL; ?>/api/get/games.php?club=1</a> returns last 16 games for Vancouver Mafia Club;', '-');

		$param = $help->response_param('games', 'The array of games. Games are always sorted from latest to oldest. There is no way to change sorting order in the current version of the API.');
			$param->sub_param('id', 'Game id. Unique game identifier.');
			$param->sub_param('club_id', 'Club id. Unique club identifier.');
			$param->sub_param('event_id', 'Event id. Unique event identifier.');
			$param->sub_param('start_time', 'Unix timestamp for the game start.');
			$param->sub_param('end_time', 'Unix timestamp for the game end.');
			$param->sub_param('language', 'Language of the game. Possible values are: 1 for English; 2 for Russian. Other languages are not supported in the current version.');
			$param->sub_param('moderator_id', 'User id of the user who moderated the game.');
			$param->sub_param('winner', 'Who won the game. Possible values: "civ" or "maf". Tie is not supported in the current version.');
			$param1 = $param->sub_param('players', 'The array of players who played. Array size is always 10. Players index in the array matches their number at the table.');
				$param1->sub_param('user_id', 'User id. <i>Optional:</i> missing when someone not registered in mafiaratings played.');
				$param1->sub_param('nick_name', 'Nick name used in this game.');
				$param1->sub_param('role', 'One of: "civ", "maf", "srf", or "don".');
				$param1->sub_param('death_round', 'The round number (starting from 0) when this player was killed. <i>Optional:</i> missing if the player survived.');
				$param1->sub_param('death_type', 'How this player was killed. Possible values: "day" - killed by day votings; "night" - killed by night shooting; "warning" - killed by 4th warning; "suicide" - left the game by theirself; "kick-out" - kicked out by the moderator. <i>Optional:</i> missing if the player survived.');
				$param1->sub_param('warnings', 'Number of warnings. <i>Optional:</i> missing when 0.');
				$param1->sub_param('arranged_for_round', 'Was arranged by mafia to be shooted down in the round (starting from 0). <i>Optional:</i> missing when the player was not arranged.');
				$param1->sub_param('checked_by_don', 'The round (starting from 0) when the don checked this player. <i>Optional:</i> missing when the player was not checked by the don.');
				$param1->sub_param('checked_by_srf', 'The round (starting from 0) when the sheriff checked this player. <i>Optional:</i> missing when the player was not checked by the sheriff.');
				$param1->sub_param('best_player', 'True if this is the best player. <i>Optional:</i> missing when false.');
				$param1->sub_param('best_move', 'True if the player did the best move of the game. <i>Optional:</i> missing when false.');
				$param1->sub_param('mafs_guessed', 'Number of mafs guessed right by the player killed the first night. <i>Optional:</i> missing when player was not killed in night 0, or when they guessed wrong.');
				$param1->sub_param('voting', 'How the player was voting. An assotiated array in the form <i>round_N: M</i>. Where N is day number (starting from 0); M is the number of player for whom he/she voted (0 to 9).');
				$param1->sub_param('nominating', 'How the player was nominating. An assotiated array in the form <i>round_N: M</i>. Where N is day number (starting from 0); M is the number of player who was nominated (0 to 9).');
				$param1->sub_param('shooting', 'For mafia only. An assotiated array in the form <i>round_N: M</i>. . Where N is day number (starting from 0); M is the number of player who was nominated (0 to 9). For example: { round_0: 0, round_1: 8, round_2: 9 } means that this player was shooting player 1(index 0) the first night; player 9 the second night; and player 10 the third night.');
		$help->response_param('count', 'The total number of games sutisfying the request parameters. It is set only when the parameter <i>count</i> is set.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Games', CURRENT_VERSION);

?>