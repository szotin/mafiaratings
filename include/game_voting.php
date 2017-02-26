<?php

require_once 'include/game_log.php';

class Nominant
{
	public $player_num;
	public $nominated_by;
	public $count;

	function __construct($player_num = -1, $nominated_by_num = -1)
	{
		if ($player_num >= 0)
		{
			$this->player_num = $player_num;
			$this->nominated_by = $nominated_by_num;
		}
		$this->count = 0;
	}
	
	function create_from_json($data)
	{
		$this->player_num = $data->player_num;
		$this->nominated_by = $data->nominated_by;
		$this->count = $data->count;
	}

	function write()
	{
		return
			$this->player_num . GAME_PARAM_DELIMITER .
			$this->nominated_by . GAME_PARAM_DELIMITER;
	}

	function read($input, $version, &$offset)
	{
		$this->player_num = (int) read_param($input, $offset);
		$this->nominated_by = (int) read_param($input, $offset);
		if ($version < 2)
		{
			read_param($input, $offset); // we used to write count, but it can be calculated on the fly. Removed it to minimize redundancy.
		}
		return $this;
	}
}
		
class Voting
{
	private $gs;
	public $round;
	public $nominants;
	public $votes;
	public $voting_round;
	public $multiple_kill;
	public $canceled;

	function __construct($gs)
	{
		$this->gs = $gs;
		if ($gs->flags & GAME_FLAG_SIMPLIFIED_CLIENT)
		{
			$this->votes = NULL;
		}
		else
		{
			$this->votes = array(-1, -1, -1, -1, -1, -1, -1, -1, -1, -1);
		}
		$this->nominants = array();
	}
	
	function create_from_json($data)
	{
		$this->round = $data->round;
		$this->voting_round = $data->voting_round;
		$this->multiple_kill = $data->multiple_kill;
		$this->canceled = $data->canceled;
		
		foreach ($data->nominants as $n)
		{
			$nominant = new Nominant();
			$nominant->create_from_json($n);
			$this->nominants[] = $nominant;
		}
		
		if (($this->gs->flags & GAME_FLAG_SIMPLIFIED_CLIENT) == 0)
		{
			$this->votes = $data->votes;
		}
	}
	
	function is_canceled()
	{
		return $this->canceled > 0;
	}
	
	function write()
	{
		$out =
			$this->round . GAME_PARAM_DELIMITER .
			$this->voting_round . GAME_PARAM_DELIMITER .
			$this->multiple_kill . GAME_PARAM_DELIMITER .
			$this->canceled . GAME_PARAM_DELIMITER;

		$nominants_count = count($this->nominants);
		$out .= $nominants_count . GAME_PARAM_DELIMITER;
		for ($i = 0; $i < $nominants_count; ++$i)
		{
			$out .= $this->nominants[$i]->write();
		}

		if (($this->gs->flags & GAME_FLAG_SIMPLIFIED_CLIENT) == 0)
		{
			for ($i = 0; $i < 10; ++$i)
			{
				$out .= $this->votes[$i] . GAME_PARAM_DELIMITER;
			}
		}
		return $out;
	}
	
	function check_player($num, $who)
	{
		if ($this->gs->error != NULL)
		{
			return;
		}
		
		if ($num < 0 || $num >= 10)
		{
			$this->gs->error = $who . ' player has incorrect number ' . ($num + 1) . ' in the votings of the ' . ($this->round + 1) . ' round.';
		}
		else
		{
			$player = $this->gs->players[$num];
			if ($player->kill_round >= 0 && $player->kill_round < $this->round)
			{
				$this->gs->error = $who . ' player' . ($num + 1) . ' is dead in the votings of the ' . ($this->round + 1) . ' round.';
			}
		}
	}

	function read($input, $version, &$offset)
	{
		$this->round = (int) read_param($input, $offset);
		$this->voting_round = (int) read_param($input, $offset);
		$this->multiple_kill = (bool) read_param($input, $offset);
		$this->canceled = (int) read_param($input, $offset);

		$nominants_count = (int) read_param($input, $offset);
		for ($i = 0; $i < $nominants_count; ++$i)
		{
			$nominant = new Nominant();
			$this->nominants[] = $nominant->read($input, $version, $offset);
			
			// check the consistency
			foreach ($this->nominants as $nominant)
			{
				$this->check_player($nominant->player_num, 'Nominated');
				if ($this->voting_round == 0)
				{
					$this->check_player($nominant->nominated_by, 'Nominating');
				}
				else
				{
					$nominant->nominated_by = -1;
				}
			}
		}

		if (($this->gs->flags & GAME_FLAG_SIMPLIFIED_CLIENT) == 0)
		{
			for ($i = 0; $i < 10; ++$i)
			{
				$nom_num = (int)read_param($input, $offset);
				$player = $this->gs->players[$i];
				if (($player->kill_round < 0 || $player->kill_round >= $this->round) && !$player->is_dummy() && !$this->canceled && $nominants_count > 0)
				{
					if ($nom_num >= 0 && $nom_num < $nominants_count)
					{
						++$this->nominants[$nom_num]->count;
					}
					else if ($this->gs->error == NULL)
					{
						$this->gs->error = 'Player' . ($i + 1) . ' votes for incorrect nominant ' . $nom_num . ' in the votings of the ' . ($this->round + 1) . ' round.';
					}
				}
				else
				{
					$nom_num = -1;
				}
				$this->votes[$i] = $nom_num;
			}
			
			if ($version < 2)
			{
				// We used to write voting winners, but they can be calculated using votes. Removed it to reduce redundancy.
				$winners_count = (int) read_param($input, $offset);
				for ($i = 0; $i < $winners_count; ++$i)
				{
					read_param($input, $offset);
				}
			}
		}
		return $this;
	}
}

?>