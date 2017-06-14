<?php

require_once 'include/db.php';
require_once 'include/game_log.php';
require_once 'include/game_player.php';
require_once 'include/game_voting.php';
require_once 'include/game_rules.php';

define('GAME_FLAG_SIMPLIFIED_CLIENT', 2);

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
define('GAME_TERMINATED', 19);
define('GAME_DAY_FREE_DISCUSSION', 20);
define('GAME_DAY_GUESS3', 21);
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
	
	public $rules;
	public $rules_id;
	
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
		
		$this->flags = 0;
		$this->rules_id = 0;
		$this->players = array(
			new Player(0), new Player(1), new Player(2), new Player(3), new Player(4),
			new Player(5), new Player(6), new Player(7), new Player(8), new Player(9));
	}
	
	function init_new($user_id, $club_id = -1)
	{
		$this->user_id = $user_id;
		
		$query = new DbQuery('SELECT id, log, club_id, moderator_id, event_id, language, start_time, end_time, flags FROM games WHERE club_id = ? AND user_id = ? AND result = 0', $club_id, $user_id);
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
				$query = new DbQuery('SELECT id, flags, languages FROM events WHERE start_time <= UNIX_TIMESTAMP() AND start_time + duration > UNIX_TIMESTAMP() AND club_id = ?', $club_id);
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
	
	function init_existing($id, $log = NULL)
	{
		$this->id = $id;
		if ($log == NULL)
		{
			$row = Db::record(get_label('game'), 'SELECT log, user_id, club_id, moderator_id, event_id, language, start_time, end_time, flags FROM games WHERE id = ?', $id);
			$log = $row[0];
			$this->user_id = (int)$row[1];
			$this->club_id = (int)$row[2];
			$this->moder_id = (int)$row[3];
			$this->event_id = (int)$row[4];
			$this->lang = (int)$row[5];
			$this->start_time = (int)$row[6];
			$this->end_time = (int)$row[7];
			$this->flags = (int)$row[8];
			$this->read($log);
		}
		else if ($this->read($log) < 4) // starting from version 4 all fields can be read from the log, othervise we query them from db
		{
			$row = Db::record(get_label('game'), 'SELECT user_id, club_id, moderator_id, event_id, language, start_time, end_time, flags FROM games WHERE id = ?', $id);
			$this->user_id = (int)$row[0];
			$this->club_id = (int)$row[1];
			$this->moder_id = (int)$row[2];
			$this->event_id = (int)$row[3];
			$this->lang = (int)$row[4];
			$this->start_time = (int)$row[5];
			$this->end_time = (int)$row[6];
			$this->flags = (int)$row[7];
			Db::exec(get_label('game'), 'UPDATE games SET log = ?, log_version = ' . CURRENT_LOG_VERSION . ' WHERE id = ?', $this->write(), $id);
		}
	}
	
	private function result_code()
	{
		switch ($this->gamestate)
		{
			case GAME_MAFIA_WON:
				return 2;
			case GAME_CIVIL_WON:
				return 1;
			case GAME_TERMINATED:
				return 3;
		}
		return 0;
	}
	
	function create_from_log($user_id, $log)
	{
		$this->user_id = $user_id;
		$this->read($log);
		
		$moder_id = $this->moder_id;
		if ($moder_id <= 0)
		{
			$moder_id = NULL;
		}
	
		$query = new DbQuery(
			'INSERT INTO games (club_id, event_id, moderator_id, user_id, language, log, start_time, end_time, result, rules_id, flags, log_version) ' .
				'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ' . CURRENT_LOG_VERSION . ')',
			$this->club_id, $this->event_id, $moder_id, $this->user_id, $this->lang,
			$log, $this->start_time, $this->end_time, $this->result_code(), $this->rules_id,
			$this->flags);
		$query->exec(get_label('game'));
		list ($this->id) = Db::record(get_label('game'), 'SELECT LAST_INSERT_ID()');
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
		$this->rules_id = $data->rules_id;
		
    	$this->shooting = array();
		for ($i = 0; $i < count($data->shooting); ++$i)
		{
			$this->shooting[] = (array) $data->shooting[$i];
		}
		
		$this->rules = NULL;
		if ($this->rules_id > 0)
		{
			$this->rules = new GameRules();
			$this->rules->load($this->rules_id);
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
	
    function terminate()
    {
        $this->log[] = new LogRecord(LOGREC_NORMAL, $this->round, $this->gamestate, $this->player_speaking, $this->current_nominant, -1);
        $this->gamestate = GAME_TERMINATED;
    }

    function write()
    {
        $out =
			CURRENT_LOG_VERSION . GAME_PARAM_DELIMITER .
			$this->rules_id . GAME_PARAM_DELIMITER .
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
			$this->rules = new GameRules();
			if ($version > 6)
			{
				$this->rules_id = (int) read_param($input, $offset);
				$this->rules->load($this->rules_id);
			}
			else
			{
				$this->rules->flags = RULES_FLAG_DAY1_NO_KILL | RULES_FLAG_NO_CRASH_4; // Only Vancouver Mafia rules were used before version 7
				$this->rules_id = $this->rules->id = $this->rules->save();
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

    function save($update_time = true)
    {
		$log = $this->write();
		if ($update_time)
		{
			$this->end_time = time();
		}
		
		if ($this->id > 0)
		{
			Db::exec(get_label('game'), 'UPDATE games SET log = ?, end_time = ? WHERE id = ?', $log, $this->end_time, $this->id);
        }
	}
	
	function full_save()
	{
		if ($this->event_id <= 0)
		{
			return;
		}
	
		if ($this->gamestate == GAME_NOT_STARTED)
		{
			Db::exec(get_label('game'), 'DELETE FROM games WHERE result = 0 AND user_id = ?', $this->user_id);
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
					'rules_id = ?, flags = ?, log_version = ' . CURRENT_LOG_VERSION . ' WHERE id = ?',
				$log, $this->end_time, $this->club_id, $this->event_id, $moder_id,
				$this->user_id, $this->lang, $this->start_time, $this->end_time, $this->result_code(),
				$this->rules_id, $this->flags, $this->id);
		}
		else
		{
			Db::exec(get_label('game'),
				'INSERT INTO games (club_id, event_id, moderator_id, user_id, language, log, start_time, end_time, result, rules_id, flags, log_version) ' .
					'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ' . CURRENT_LOG_VERSION . ')',
				$this->club_id, $this->event_id, $moder_id, $this->user_id, $this->lang,
				$log, $this->start_time, $this->end_time, $this->result_code(), $this->rules_id,
				$this->flags);
			list ($this->id) = Db::record(get_label('game'), 'SELECT LAST_INSERT_ID()');
		}
	}
	
	function is_good_guesser($num)
	{
		$player = $this->players[$num];
		if ($player->kill_round == 0 && $player->state == PLAYER_STATE_KILLED_NIGHT && $this->guess3 != NULL && count($this->guess3) >= 3)
		{
			for ($i = 0; $i < 3; ++$i)
			{
				$n = $this->guess3[$i];
				if ($n < 0 || $n >= 10)
				{
					return false;
				}
				$g = $this->players[$n];
				if ($g->role != PLAYER_ROLE_DON && $g->role != PLAYER_ROLE_MAFIA)
				{
					return false;
				}
			}
			return true;
		}
		return false;
	}

    function get_rating($p)
    {
        $rating = 0;
		$player = $this->players[$p];
        if ($this->gamestate == GAME_MAFIA_WON)
        {
            switch ($player->role)
            {
                case PLAYER_ROLE_SHERIFF:
                    $rating = -1;
                    break;
                case PLAYER_ROLE_MAFIA:
                    $rating = 4;
                    break;
                case PLAYER_ROLE_DON:
                    $rating = 5;
                    break;
            }
        }
        else if ($this->gamestate == GAME_CIVIL_WON)
        {
            switch ($player->role)
            {
                case PLAYER_ROLE_CIVILIAN:
                    $rating = 3;
                    break;
                case PLAYER_ROLE_SHERIFF:
                    $rating = 4;
                    break;
                case PLAYER_ROLE_DON:
                    $rating = -1;
                    break;
            }
        }
		if ($p == $this->best_player)
		{
			$rating += 1;
		}
		if ($p == $this->best_move)
		{
			$rating += 1;
		}
		if ($this->is_good_guesser($p))
		{
			$rating += 1;
		}
        return $rating;
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
			case GAME_TERMINATED:
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
	
	function change_user($user_id, $new_user_id)
	{
		if ($user_id == -1)
		{
			return false;
		}
		
		$changed = false;
		if ($this->moder_id == $user_id)
		{
			$this->moder_id = $new_user_id;
			$changed = true;
		}
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->players[$i];
			if ($player->id == $user_id)
			{
				$player->id = $new_user_id;
				$changed = true;
			}
		}
		return $changed;
	}
}

?>