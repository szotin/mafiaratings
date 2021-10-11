<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/game_log.php';

// player states
define('PLAYER_STATE_ALIVE', 0);
define('PLAYER_STATE_KILLED_NIGHT', 1);
define('PLAYER_STATE_KILLED_DAY', 2);

// kill reasons
define('KILL_REASON_NORMAL', 0);
define('KILL_REASON_GIVE_UP', 1);
define('KILL_REASON_WARNINGS', 2);
define('KILL_REASON_KICK_OUT', 3);

class Player
{
    public $id;
	public $number;
    public $nick;
    public $is_male;
	public $has_immunity;
    public $role;
    public $warnings;
    public $state;
    public $kill_round;
    public $kill_reason;
    public $arranged; // what night the player is arranged to be killed. -1 if not arranged.
    public $don_check; // round num when don checked the player; -1 if he didn't
    public $sheriff_check; // round num when sheriff checked the player; -1 if he didn't
    public $mute; // number of round when player misses his speach because of warnings
	public $extra_points;
	public $comment;
	
	function __construct($number)
    {
		$this->number = $number;
		$this->id = -1;
		$this->nick = '';
		$this->is_male = 1;
		$this->has_immunity = false;
		$this->role = ROLE_CIVILIAN;
		$this->warnings = 0;
		$this->state = PLAYER_STATE_ALIVE;
		$this->kill_round = -1;
		$this->kill_reason = -1;
		$this->arranged = -1;
		$this->don_check = -1;
		$this->sheriff_check = -1;
		$this->mute = -1;
		$this->extra_points = 0;
		$this->comment = '';
    }
	
	function create_from_json($data)
	{
		$this->id = $data->id;
		$this->number = $data->number;
		$this->nick = $data->nick;
    	$this->is_male = $data->is_male;
		$this->has_immunity = $data->has_immunity;
    	$this->role = $data->role;
    	$this->warnings = $data->warnings;
    	$this->state = $data->state;
    	$this->kill_round = $data->kill_round;
    	$this->kill_reason = $data->kill_reason;
    	$this->arranged = $data->arranged;
    	$this->don_check = $data->don_check;
    	$this->sheriff_check = $data->sheriff_check;
    	$this->mute = $data->mute;
		if (isset($data->extra_points))
		{
			$this->extra_points = $data->extra_points;
		}
		if (isset($data->comment))
		{
			$this->comment = str_replace(GAME_PARAM_DELIMITER, GAME_PARAM_DELIMITER_REPLACEMENT, $data->comment);
		}
	}
	
	function init($id, $club_id, $event_id, $registered_only)
	{
		$this->id = $id;
		if ($id > 0)
		{
			if (!$registered_only)
			{
				$query = new DbQuery('SELECT name, flags FROM users WHERE id = ?', $id);
			}
			else if ($event_id > 0)
			{
				$query = new DbQuery(
					'SELECT r.nick_name, u.flags FROM registrations r, users u ' .
						'WHERE r.user_id = u.id AND r.event_id = ? AND r.user_id = ?', 
					$event_id, $id);
			}
			else
			{
				$query = new DbQuery(
					'SELECT r.nick_name, u.flags FROM registrations r, users u ' .
						'WHERE r.user_id = u.id AND r.event_id IS NULL AND r.club_id = ? AND r.user_id = ? ' .
						'AND UNIX_TIMESTAMP() < r.start_time + r.duration AND UNIX_TIMESTAMP() >= r.start_time',
					$club_id, $id);
			}
			$row = $query->next();
			
			if (!$row)
			{
				throw new FatalExc(get_label('Registration has expired for player [0]', $number + 1));
			}
			list ($this->nick, $flags) = $row;
			$this->is_male = (($flags & USER_FLAG_MALE) != 0);
			$this->has_immunity = (($flags & USER_FLAG_IMMUNITY) != 0);
		}
		else
		{
			$this->nick = get_label('[Dummy player]');
			$this->is_male = true;
			$this->has_immunity = false;
		}
	}

    function warnings_text()
    {
        if ($this->warnings <= 0)
        {
            return '&nbsp;';
        }
        if ($this->warnings == 1)
        {
            return $this->warnings . ' '.get_label('warning');
        }
        return $this->warnings . ' '.get_label('warnings');
    }

    function role_text($show_civils)
    {
        switch ($this->role)
        {
            case ROLE_SHERIFF:
                return get_label('sheriff');
            case ROLE_DON:
                return get_label('don');
            case ROLE_MAFIA:
                return get_label('mafia');
        }
        if ($show_civils)
        {
            return get_label('civilian');
        }
        return '&nbsp;';
    }

    function sheriff_check_text()
    {
        if ($this->sheriff_check >= 0)
        {
            return get_label('Night').' ' . ($this->sheriff_check + 1);
        }
        return '&nbsp;';
    }

    function don_check_text()
    {
        if ($this->don_check >= 0)
        {
            return get_label('Night').' ' . ($this->don_check + 1);
        }
        return '&nbsp;';
    }

    function arranged_text()
    {
        if ($this->arranged >= 0)
        {
            return get_label('Night').' ' . ($this->arranged + 1);
        }
        return '&nbsp;';
    }

    function killed_text()
    {
        $row = '&nbsp;';
        switch ($this->state)
        {
            case PLAYER_STATE_ALIVE:
                return $row;
            case PLAYER_STATE_KILLED_NIGHT:
                $row = get_label('Night').' ';
                break;
            case PLAYER_STATE_KILLED_DAY:
                $row = get_label('Day').' ';
                break;
        }

        $row .= ($this->kill_round + 1);

        switch ($this->kill_reason)
        {
            case KILL_REASON_GIVE_UP:
                $row .= ' '.get_label('gave up');
                break;
            case KILL_REASON_WARNINGS:
                $row .= ' '.get_label('warnings');
                break;
            case KILL_REASON_KICK_OUT:
                $row .= ' '.get_label('kicked out');
                break;
        }
        return $row;
    }
	
    function write()
    {
		$flags = 0;
		if ($this->is_male)
		{
			$flags |= 1;
		}
		if ($this->has_immunity)
		{
			$flags |= 2;
		}
		
        return
			$this->id . GAME_PARAM_DELIMITER .
			$this->nick . GAME_PARAM_DELIMITER .
			$flags . GAME_PARAM_DELIMITER .
			$this->role . GAME_PARAM_DELIMITER .
			$this->warnings . GAME_PARAM_DELIMITER .
			$this->state . GAME_PARAM_DELIMITER .
			$this->kill_round . GAME_PARAM_DELIMITER .
			$this->kill_reason . GAME_PARAM_DELIMITER .
			$this->arranged . GAME_PARAM_DELIMITER .
			$this->don_check . GAME_PARAM_DELIMITER .
			$this->sheriff_check . GAME_PARAM_DELIMITER .
			$this->mute . GAME_PARAM_DELIMITER .
			$this->extra_points . GAME_PARAM_DELIMITER .
			$this->comment . GAME_PARAM_DELIMITER;
    }

    function read($input, $version, &$offset)
    {
        $this->id = (int) read_param($input, $offset);
        $this->nick = read_param($input, $offset);
        $flags = (int) read_param($input, $offset);
        $this->role = (int) read_param($input, $offset);
        $this->warnings = (int) read_param($input, $offset);
        $this->state = (int) read_param($input, $offset);
        $this->kill_round = (int) read_param($input, $offset);
        $this->kill_reason = (int) read_param($input, $offset);
        $this->arranged = (int) read_param($input, $offset);
        $this->don_check = (int) read_param($input, $offset);
        $this->sheriff_check = (int) read_param($input, $offset);
		if ($version > 9)
		{
			$this->mute = (int) read_param($input, $offset);
			if ($version > 10)
			{
				$this->extra_points = (float) read_param($input, $offset);
				if ($version > 11)
				{
					$this->comment = read_param($input, $offset);
				}
			}
		}
		else
		{
			if ($version == 0)
			{
				$misses_speech = (bool) read_param($input, $offset);
			}
			else
			{
				$misses_speech = (($flags & 4) != 0);
				if ($version < 6)
				{
					read_param($input, $offset); // announced_sheriff param - it is not used any more
				}
			}
			
			$this->mute = $misses_speech ? -2 : -1; // later -2 will be replaced with the current round (check the GameState read function).
		}
		
		$this->is_male = (($flags & 1) != 0);
		$this->has_immunity = (($flags & 2) != 0);
		
        return $this;
    }
	
	function is_dead()
	{
		return $this->state != PLAYER_STATE_ALIVE;
	}
	
	function is_alive()
	{
		return $this->state == PLAYER_STATE_ALIVE;
	}
	
	function is_red()
	{
		return $this->role == ROLE_CIVILIAN || $this->role == ROLE_SHERIFF;
	}
	
	function is_dark()
	{
		return $this->role == ROLE_MAFIA || $this->role == ROLE_DON;
	}
}

?>