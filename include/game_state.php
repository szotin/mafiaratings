<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/game_log.php';
require_once __DIR__ . '/game_player.php';
require_once __DIR__ . '/game_voting.php';
require_once __DIR__ . '/rules.php';

define('GAME_FLAG_SIMPLIFIED_CLIENT', 2);

define('GAME_RESULT_PLAYING', 0);
define('GAME_RESULT_TOWN', 1);
define('GAME_RESULT_MAFIA', 2);
define('GAME_RESULT_TIE', 3);

// game states
define('GAME_NOT_STARTED', 0);
define('GAME_NIGHT0_START', 1);
define('GAME_NIGHT0_ARRANGE', 2);
define('GAME_DAY_START', 3);
define('GAME_DAY_KILLED_SPEAKING', 4); // deprecated the code should reach this only for the old logs
define('GAME_DAY_PLAYER_SPEAKING', 5);
define('GAME_VOTING_START', 6);
define('GAME_VOTING_KILLED_SPEAKING', 7);
define('GAME_VOTING', 8);
define('GAME_VOTING_MULTIPLE_WINNERS', 9);
define('GAME_VOTING_NOMINANT_SPEAKING', 10);
define('GAME_NIGHT_START', 11);
define('GAME_NIGHT_SHOOTING', 12);
define('GAME_NIGHT_DON_CHECK', 13);
define('GAME_NIGHT_DON_CHECK_END', 14); // deprecated the code should reach this only for the old logs
define('GAME_NIGHT_SHERIFF_CHECK', 15);
define('GAME_NIGHT_SHERIFF_CHECK_END', 16); // deprecated the code should reach this only for the old logs
define('GAME_MAFIA_WON', 17);
define('GAME_CIVIL_WON', 18);
//define('GAME_TERMINATED', 19); // no more terminated games - we just delete them
define('GAME_DAY_FREE_DISCUSSION', 20);
define('GAME_DAY_GUESS3', 21); // deprecated the code should reach this only for the old logs
define('GAME_CHOOSE_BEST_PLAYER', 22);
define('GAME_CHOOSE_BEST_MOVE', 23);

class GameState
{
    public $id;
	
	public $user_id; // user under whos account the game is played or viewed
	public $club_id;
	public $moder_id;
	public $lang;
	public $event_id;
	public $start_time;
	public $end_time;
	public $flags;
	public $is_canceled;

    public $players;

    public $gamestate;
    public $round; // 0 for the first day/night/voting; 1 - for the second day/night/voting; etc
    public $player_speaking; // the player currently speaking
    public $table_opener; // the player who is speaking first this day
    public $current_nominant; // player nominated during current speech. -1 - if nobody nominated. It is also used during voting the meaning is different: it is current nominant index in Voting.nominants

    public $votings;
    private $current_voting;

    public $shooting;

    public $log;
	
	public $best_player; // number of the best player 0-9; any value otside the range means "no best player"
	public $best_move; // number of the player who did the best move 0-9; any value otside the range means "no best move"
	public $guess3; // array containing guess (who is mafia) of the player killed the first night. 3 player numbers.
	
	public $rules_code;
	
	public $error; // error message if log is corrupted (not localized - this is for admin only)
	
	function __construct()
	{
		$this->gamestate = GAME_NOT_STARTED;
		$this->id = 0;
		$this->user_id = 0;
		$this->club_id = 0;
		$this->moder_id = 0;
		$this->lang = 0;
		$this->event_id = 0;
		$this->start_time = 0;
		$this->end_time = 0;
		$this->best_player = -1;
		$this->best_move = -1;
		$this->guess3 = NULL;
		$this->error = NULL;
		$this->is_canceled = false;
		
		$this->flags = 0;
		$this->rules_code = default_rules_code();
		$this->players = array(
			new Player(0), new Player(1), new Player(2), new Player(3), new Player(4),
			new Player(5), new Player(6), new Player(7), new Player(8), new Player(9));
	}
	
	function init_new($user_id, $club_id = -1)
	{
		$this->user_id = $user_id;
		$this->is_canceled = false;
		
		$query = new DbQuery('SELECT id, log, club_id, moderator_id, event_id, language, start_time, end_time, flags FROM games WHERE club_id = ? AND user_id = ? AND result = ' . GAME_RESULT_PLAYING, $club_id, $user_id);
		if ($row = $query->next())
		{
			$this->id = (int)$row[0];
			$log = $row[1];
			$this->club_id = (int)$row[2];
			$this->moder_id = (int)$row[3];
			$this->event_id = (int)$row[4];
			$this->lang = (int)$row[5];
			$this->start_time = (int)$row[6];
			$this->end_time = (int)$row[7];
			$this->flags = (int)$row[8];
			$this->read($log);
		}
		else
		{
			$this->club_id = $club_id;
			if ($club_id > 0 )
			{
				$query = new DbQuery('SELECT id, flags, languages FROM events WHERE start_time <= UNIX_TIMESTAMP() AND start_time + duration > UNIX_TIMESTAMP() AND (flags & ' . EVENT_FLAG_CANCELED . ') = 0 AND club_id = ?', $club_id);
				if ($row = $query->next())
				{
					$this->event_id = (int)$row[0];
					$event_flags = (int)$row[1];
					$event_langs = (int)$row[2];
					if (($event_flags & EVENT_FLAG_ALL_MODERATE) == 0)
					{
						$this->moder_id = $user_id;
					}
					if (is_valid_lang($event_langs))
					{
						$this->lang = $event_langs;
					}
				}
			}
		}
	}
	
	function init_existing($id, $log, $is_canceled)
	{
		$this->id = $id;
		if ($log == NULL)
		{
			$row = Db::record(get_label('game'), 'SELECT log, user_id, club_id, moderator_id, event_id, language, start_time, end_time, flags, canceled FROM games WHERE id = ?', $id);
			$log = $row[0];
			$this->user_id = (int)$row[1];
			$this->club_id = (int)$row[2];
			$this->moder_id = (int)$row[3];
			$this->event_id = (int)$row[4];
			$this->lang = (int)$row[5];
			$this->start_time = (int)$row[6];
			$this->end_time = (int)$row[7];
			$this->flags = (int)$row[8];
			$this->is_canceled = (bool)$row[9];
			$this->read($log);
		}
		else if ($this->read($log) < 4) // starting from version 4 all fields can be read from the log, othervise we query them from db
		{
			$row = Db::record(get_label('game'), 'SELECT user_id, club_id, moderator_id, event_id, language, start_time, end_time, flags, canceled FROM games WHERE id = ?', $id);
			$this->user_id = (int)$row[0];
			$this->club_id = (int)$row[1];
			$this->moder_id = (int)$row[2];
			$this->event_id = (int)$row[3];
			$this->lang = (int)$row[4];
			$this->start_time = (int)$row[5];
			$this->end_time = (int)$row[6];
			$this->flags = (int)$row[7];
			$this->is_canceled = (bool)$row[8];
			Db::exec(get_label('game'), 'UPDATE games SET log = ?, log_version = ' . CURRENT_LOG_VERSION . ' WHERE id = ?', $this->write(), $id);
		}
		else
		{
			$this->is_canceled = (bool)$is_canceled;
		}
	}
	
	private function result_code()
	{
		switch ($this->gamestate)
		{
			case GAME_MAFIA_WON:
				return GAME_RESULT_MAFIA;
			case GAME_CIVIL_WON:
				return GAME_RESULT_TOWN;
		}
		return GAME_RESULT_PLAYING;
	}
	
	function create_from_json($data)
	{
		//var_dump($data);
	
		$this->id = $data->id;
		$this->user_id = $data->user_id;
		$this->club_id = $data->club_id;
		$this->moder_id = $data->moder_id;
		$this->lang = $data->lang;
		$this->event_id = $data->event_id;
		$this->start_time = $data->start_time;
		$this->end_time = $data->end_time;
    	$this->gamestate = $data->gamestate;
    	$this->round = $data->round;
    	$this->player_speaking = $data->player_speaking;
    	$this->table_opener = $data->table_opener;
    	$this->current_nominant = $data->current_nominant;
		$this->flags = $data->flags;
		$this->best_player = $data->best_player;
		$this->best_move = $data->best_move;
		$this->guess3 = $data->guess3;
		$this->rules_code = $data->rules_code;
		
    	$this->shooting = array();
		for ($i = 0; $i < count($data->shooting); ++$i)
		{
			$this->shooting[] = (array) $data->shooting[$i];
		}
		
		$this->current_voting = NULL;
		$this->votings = NULL;
		if ($data->votings != NULL)
		{
			$this->votings = array();
			$v = NULL;
			foreach ($data->votings as $voting)
			{
				$v = new Voting($this);
				$v->create_from_json($voting);
				$this->votings[] = $v;
			}
			if ($v != NULL)
			{
				$this->current_voting = $v;
			}
		}
		
		for ($i = 0; $i < 10; ++$i)
		{
			$this->players[$i]->create_from_json($data->players[$i]);
		}
		
		$this->log = NULL;
		if ($data->log != NULL)
		{
			$this->log = array();
			foreach ($data->log as $r)
			{
				$rec = new LogRecord();
				$rec->create_from_json($r);
				$this->log[] = $rec;
			}
		}
	}
	
    function write()
    {
        $out =
			CURRENT_LOG_VERSION . GAME_PARAM_DELIMITER .
			$this->rules_code . GAME_PARAM_DELIMITER .
			$this->club_id . GAME_PARAM_DELIMITER .
			$this->event_id . GAME_PARAM_DELIMITER .
			$this->user_id . GAME_PARAM_DELIMITER .
			$this->moder_id . GAME_PARAM_DELIMITER .
			$this->lang . GAME_PARAM_DELIMITER .
			$this->start_time . GAME_PARAM_DELIMITER .
			($this->end_time - $this->start_time) . GAME_PARAM_DELIMITER .
			$this->best_player . GAME_PARAM_DELIMITER .
			$this->best_move . GAME_PARAM_DELIMITER .
			$this->flags . GAME_PARAM_DELIMITER;
		
		if ($this->guess3 == NULL)
		{
			$out .= '-1' . GAME_PARAM_DELIMITER . '-1' . GAME_PARAM_DELIMITER . '-1' . GAME_PARAM_DELIMITER;
		}
		else
		{
			for ($i = 0; $i < count($this->guess3) && $i < 3; ++$i)
			{
				$out .= $this->guess3[$i] . GAME_PARAM_DELIMITER;
			}
			for (; $i < 3; ++$i)
			{
				$out .= '-1' . GAME_PARAM_DELIMITER;
			}
		}

        for ($i = 0; $i < 10; ++$i)
        {
            $out .= $this->players[$i]->write();
        }

        $out .=
            $this->gamestate . GAME_PARAM_DELIMITER . 
            $this->round . GAME_PARAM_DELIMITER .
            $this->player_speaking . GAME_PARAM_DELIMITER .
            $this->table_opener . GAME_PARAM_DELIMITER .
            $this->current_nominant . GAME_PARAM_DELIMITER;

        $votings_count = count($this->votings);
        $out .= $votings_count . GAME_PARAM_DELIMITER;
        for ($i = 0; $i < $votings_count; ++$i)
        {
            $out .= $this->votings[$i]->write();
        }

        $shooting_count = count($this->shooting);
        $out .= $shooting_count . GAME_PARAM_DELIMITER;
        for ($i = 0; $i < $shooting_count; ++$i)
        {
            $shooting = $this->shooting[$i];
            $out .= count($shooting) . GAME_PARAM_DELIMITER;
            foreach ($shooting as $key => $value)
            {
                $out .= $key . GAME_PARAM_DELIMITER . $value . GAME_PARAM_DELIMITER;
            }
        }

        $log_count = count($this->log);
        $out .= $log_count . GAME_PARAM_DELIMITER;
        for ($i = 0; $i < $log_count; ++$i)
        {
            $out .= $this->log[$i]->write();
        }

        return $out;
    }

    function read($input)
    {
        $offset = 0;
        $version = (int) read_param($input, $offset); 
		
		if ($version > 2)
		{
			if ($version > 6)
			{
				$this->rules_code = read_param($input, $offset);
			}
			else
			{
				$this->rules_code = set_rule(default_rules_code(), RULES_SPLIT_ON_FOUR, RULES_SPLIT_ON_FOUR_PROHIBITED); // Only Vancouver Mafia rules were used before version 7
			}
			$this->club_id = (int) read_param($input, $offset); 
			$this->event_id = (int) read_param($input, $offset);
			$this->user_id = (int) read_param($input, $offset);
			$this->moder_id = (int) read_param($input, $offset);
			$this->lang = (int) read_param($input, $offset);
			if ($version > 3)
			{
				$this->start_time = (int) read_param($input, $offset);
				$this->end_time = $this->start_time + (int) read_param($input, $offset);
				if ($version > 4)
				{
					$this->best_player = (int) read_param($input, $offset);
					if ($version > 7)
					{
						$this->best_move = (int) read_param($input, $offset);
						if ($version > 8)
						{
							$this->flags = (int) read_param($input, $offset);
						}
						$this->guess3 = NULL;
						for ($i = 0; $i < 3; ++$i)
						{
							$g = (int) read_param($input, $offset);
							if ($g >= 0 && $g < 10)
							{
								if ($this->guess3 == NULL)
								{
									$this->guess3 = array();
								}
								$this->guess3[] = $g;
							}
						}
					}
				}
			}
		}

        for ($i = 0; $i < 10; ++$i)
        {
            $player = $this->players[$i];
            $player->read($input, $version, $offset);
        }

        $this->gamestate = (int) read_param($input, $offset);
        $this->round = (int) read_param($input, $offset);
        $this->player_speaking = (int) read_param($input, $offset);
        $this->table_opener = (int) read_param($input, $offset);
        $this->current_nominant = (int) read_param($input, $offset);
		
        $votings_count = (int) read_param($input, $offset);
        $this->votings = array();
        for ($i = 0; $i < $votings_count; ++$i)
        {
            $voting = new Voting($this);
            $this->votings[] = $this->current_voting = $voting->read($input, $version, $offset);
        }

        $shooting_count = (int) read_param($input, $offset);
        $this->shooting = array();
        for ($i = 0; $i < $shooting_count; ++$i)
        {
            $this->shooting[$i] = array();
            $scount = (int) read_param($input, $offset);
            for ($j = 0; $j < $scount; ++$j)
            {
                $key = (int) read_param($input, $offset);
                $this->shooting[$i][$key] = (int) read_param($input, $offset);
            }
        }

        $log_count = (int) read_param($input, $offset);
        $this->log = array();
        for ($i = 0; $i < $log_count; ++$i)
        {
            $log_rec = new LogRecord();
            $this->log[] = $log_rec->read($input, $version, $offset);
        }

		if ($version <= 9)
		{
			for ($i = 0; $i < 10; ++$i)
			{
				$player = $this->players[$i];
				if ($player->mute < -1)
				{
					switch ($this->gamestate)
					{
						case GAME_NOT_STARTED:
						case GAME_NIGHT0_START:
						case GAME_NIGHT0_ARRANGE:
						case GAME_DAY_START:
						case GAME_DAY_KILLED_SPEAKING:
						case GAME_DAY_FREE_DISCUSSION:
						case GAME_DAY_GUESS3:
							$player->mute = $this->round;
							break;
						
						case GAME_DAY_PLAYER_SPEAKING:
							$player->mute = $this->round;
							if ($player_speaking >= $table_opener)
							{
								if ($i <= $player_speaking && $i >= $table_opener)
								{
									++$player->mute;
								}
							}
							else if ($i <= $player_speaking || $i >= $table_opener)
							{
								++$player->mute;
							}
							break;
						
						default:
							$player->mute = $this->round + 1;
							break;
					}
				}
			}
		}
        return $version;
    }

	function save()
	{
		if ($this->event_id <= 0)
		{
			return;
		}
	
		$moder_id = $this->moder_id;
		if ($moder_id <= 0)
		{
			$moder_id = NULL;
		}
	
		$log = $this->write();
		list($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games WHERE id = ?', $this->id);
		if ($count > 0)
		{
			Db::exec(get_label('game'),
				'UPDATE games SET log = ?, end_time = ?, club_id = ?, event_id = ?, moderator_id = ?, ' .
					'user_id = ?, language = ?, start_time = ?, end_time = ?, result = ?, ' .
					'rules = ?, flags = ?, log_version = ' . CURRENT_LOG_VERSION . ' WHERE id = ?',
				$log, $this->end_time, $this->club_id, $this->event_id, $moder_id,
				$this->user_id, $this->lang, $this->start_time, $this->end_time, $this->result_code(),
				$this->rules_code, $this->flags, $this->id);
		}
		else
		{
			Db::exec(get_label('game'),
				'INSERT INTO games (club_id, event_id, moderator_id, user_id, language, log, start_time, end_time, result, rules, flags, log_version) ' .
					'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ' . CURRENT_LOG_VERSION . ')',
				$this->club_id, $this->event_id, $moder_id, $this->user_id, $this->lang,
				$log, $this->start_time, $this->end_time, $this->result_code(), $this->rules_code,
				$this->flags);
			list ($this->id) = Db::record(get_label('game'), 'SELECT LAST_INSERT_ID()');
		}
	}
	
	function mafs_guessed($num)
	{
		$player = $this->players[$num];
		if ($player->kill_round == 0 && $player->state == PLAYER_STATE_KILLED_NIGHT && $this->guess3 != NULL)
		{
			$mafs = 0;
			for ($i = 0; $i < count($this->guess3); ++$i)
			{
				$n = $this->guess3[$i];
				if ($n < 0 || $n >= 10)
				{
					continue;
				}
				
				$g = $this->players[$n];
				if ($g->role == PLAYER_ROLE_DON || $g->role == PLAYER_ROLE_MAFIA)
				{
					++$mafs;
				}
			}
			return $mafs;
		}
		return -1;
	}

	function get_last_gametime($log_num = -1) // returns when last gametime and round: 0 - not started; 1 - initial night; 2 - day 1; 3 - night 1; etc
	{
		if ($log_num >= 0)
		{
			$log = $this->log[$log_num];
			$gamestate = $log->gamestate;
			$round = $log->round;
		}
		else
		{
			$gamestate = $this->gamestate;
			$round = $this->round;
		}
		
		switch ($gamestate)
		{
			case GAME_NOT_STARTED:
				return 0;
			case GAME_NIGHT0_START:
			case GAME_NIGHT0_ARRANGE:
				return 1;
			case GAME_DAY_START:
			case GAME_DAY_KILLED_SPEAKING: // deprecated - left to support old logs
			case GAME_DAY_PLAYER_SPEAKING:
			case GAME_VOTING_START:
			case GAME_VOTING_KILLED_SPEAKING:
			case GAME_VOTING:
			case GAME_VOTING_MULTIPLE_WINNERS:
			case GAME_VOTING_NOMINANT_SPEAKING:
			case GAME_DAY_FREE_DISCUSSION:
			case GAME_DAY_GUESS3:
				return $round * 2 + 2;
				
			case GAME_NIGHT_START:
			case GAME_NIGHT_SHOOTING:
			case GAME_NIGHT_DON_CHECK:
			case GAME_NIGHT_DON_CHECK_END:
			case GAME_NIGHT_SHERIFF_CHECK:
			case GAME_NIGHT_SHERIFF_CHECK_END:
				return $round * 2 + 3;
				
			case GAME_MAFIA_WON:
			case GAME_CIVIL_WON:
			case GAME_CHOOSE_BEST_PLAYER:
			case GAME_CHOOSE_BEST_MOVE:
				if ($log_num < 0)
				{
					$log_num = count($this->log);
				}
				if ($log_num > 0)
				{
					return $this->get_last_gametime($log_num - 1);
				}
				break;
		}
		return 0;
	}
	
	function has_played($user_id)
	{
		if ($this->moder_id == $user_id)
		{
			return true;
		}
		for ($i = 0; $i < 10; ++$i)
		{
			if ($this->players[$i]->id == $user_id)
			{
				return true;
			}
		}
		return false;
	}
	
	function change_user($user_id, $new_user_id, $nickname = NULL)
	{
		if ($user_id == -1 || !$this->has_played($user_id))
		{
			return false;
		}
		
		if ($this->has_played($new_user_id))
		{
			throw new Exc(get_label('Unable to change one user to another in the game because they both participated in it.'));
		}
		
		if ($this->moder_id != $user_id)
		{
			for ($i = 0; $i < 10; ++$i)
			{
				$player = $this->players[$i];
				if ($player->id == $user_id)
				{
					$player->id = $new_user_id;
					if ($nickname != NULL)
					{
						$player->nick = $nickname;
					}
					break;
				}
			}
		}
		else
		{
			$this->moder_id = $new_user_id;
		}
		return true;
	}
}

?>