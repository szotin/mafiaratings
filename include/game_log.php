<?php

define('CURRENT_LOG_VERSION', 11);

// log record types
define('LOGREC_NORMAL', 0);
define('LOGREC_MISSED_SPEECH', 1);
define('LOGREC_WARNING', 2);
define('LOGREC_SUICIDE', 3);
define('LOGREC_KICK_OUT', 4);

define('GAME_PARAM_DELIMITER', ':');

function read_param($input, &$offset)
{
    $next = strpos($input, GAME_PARAM_DELIMITER, $offset);
    $str  = substr($input, $offset, $next - $offset);
    $offset = $next + 1;
    return $str;
}

class LogRecord
{
    public $type;
    public $round;
    public $gamestate;
    public $player_speaking;
    public $current_nominant;
    public $player;

	function __construct($type = -1, $round = -1, $gamestate = -1, $player_speaking = -1, $current_nominant = -1, $player = -1)
    {
        if ($type >= 0)
        {
            $this->type = $type;
            $this->round = $round;
            $this->gamestate = $gamestate;
            $this->player_speaking = $player_speaking;
            $this->current_nominant = $current_nominant;
            $this->player = $player;
        }
    }
	
	function create_from_json($data)
	{
		$this->type = $data->type;
    	$this->round = $data->round;
    	$this->gamestate = $data->gamestate;
    	$this->player_speaking = $data->player_speaking;
    	$this->current_nominant = $data->current_nominant;
    	$this->player = $data->player;
	}

    function write()
    {
        return
            $this->type . GAME_PARAM_DELIMITER .
            $this->round . GAME_PARAM_DELIMITER .
            $this->gamestate . GAME_PARAM_DELIMITER .
            $this->player_speaking . GAME_PARAM_DELIMITER .
            $this->current_nominant . GAME_PARAM_DELIMITER .
            $this->player . GAME_PARAM_DELIMITER;
    }

    function read($input, $version, &$offset)
    {
        $this->type = (int) read_param($input, $offset);
        $this->round = (int) read_param($input, $offset);
        $this->gamestate = (int) read_param($input, $offset);
        $this->player_speaking = (int) read_param($input, $offset);
        $this->current_nominant = (int) read_param($input, $offset);
        $this->player = (int) read_param($input, $offset);
        return $this;
    }
}

?>