<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/scoring.php';

define("AVERAGE_PLAYER", -1);
define("ROLE_FLAG_CIVIL", 1);
define("ROLE_FLAG_SHERIFF", 2);
define("ROLE_FLAG_MAFIA", 4);
define("ROLE_FLAG_DON", 8);
define("ROLE_FLAG_ANY", 15);

define("SURVIVED", 0);
define("DAY_KILL", 1);
define("NIGHT_KILL", 2);
define("WARNINGS_KILL", 3);
define("GIVE_UP_KILL", 4);
define("KICK_OUT_KILL", 5);

class Surviving
{
	public $round;
	public $type;
	public $count;
	
	function __construct($round, $type, $count)
	{
		$this->round = $round;
		$this->type = $type;
		$this->count = $count;
	}
}

class PlayerStats
{
	public $club_id;
    public $user_id;
	public $games_played;
	public $games_won;
	public $rating;
	public $best_player;
	public $best_move;
	public $bonus;
	public $guess3maf;
	public $guess2maf;
	public $killed_first_night;
	
	public $roles;
	
    public $voted_civil;
    public $voted_mafia;
    public $voted_sheriff;
    public $voted_by_mafia;
    public $voted_by_civil;
    public $voted_by_sheriff;
    public $nominated_civil;
    public $nominated_mafia;
    public $nominated_sheriff;
    public $nominated_by_mafia;
    public $nominated_by_civil;
    public $nominated_by_sheriff;
	
	public $warnings;
	public $arranged;
	public $checked_by_don;
	public $checked_by_sheriff;
	
	public $surviving;
	
	// if $user_id <= 0: gives stats of an average player
	// if $club_id <= 0: gives stats for all clubs
	function __construct($user_id, $club_id, $roles, $condition)
	{
		$this->club_id = $club_id;
		$this->user_id = $user_id;
		$this->roles = $roles;
		
		$this->games_played = 0;
		$this->games_won = 0;
		$this->rating = 0;
		$this->voted_civil = 0;
		$this->voted_mafia = 0;
		$this->voted_sheriff = 0;
		$this->voted_by_mafia = 0;
		$this->voted_by_civil = 0;
		$this->voted_by_sheriff = 0;
		$this->nominated_civil = 0;
		$this->nominated_mafia = 0;
		$this->nominated_sheriff = 0;
		$this->nominated_by_mafia = 0;
		$this->nominated_by_civil = 0;
		$this->nominated_by_sheriff = 0;
		$this->warnings = 0;
		$this->arranged = 0;
		$this->checked_by_don = 0;
		$this->checked_by_sheriff = 0;
		$this->surviving = array();
		
		$condition->add(get_roles_condition($roles));
		if ($club_id > 0)
		{
			$condition->add(' AND g.club_id = ?', $club_id);
		}
		$count = 1; 
		if ($user_id > 0)
		{
			$condition->add(' AND p.user_id = ?', $user_id);
		}
		else
		{
			list ($count) = Db::record(get_label('player'), 'SELECT count(DISTINCT p.user_id) FROM players p JOIN games g ON g.id = p.game_id WHERE TRUE', $condition);
			if ($count <= 0)
			{
				$count = 1;
			}
		}
		
		$query = new DbQuery(
			'SELECT count(*), SUM(p.won), SUM(p.rating_earned), ' .
				'SUM(p.voted_civil), SUM(p.voted_mafia), SUM(p.voted_sheriff), ' .
				'SUM(p.voted_by_civil), SUM(p.voted_by_mafia), SUM(p.voted_by_sheriff), ' .
				'SUM(p.nominated_civil), SUM(p.nominated_mafia), SUM(p.nominated_sheriff), ' .
				'SUM(p.nominated_by_civil), SUM(p.nominated_by_mafia), SUM(p.nominated_by_sheriff), ' .
				'SUM(p.warns), SUM(IF(p.was_arranged >= 0, 1, 0)), ' .
				'SUM(IF(p.checked_by_don >= 0, 1, 0)), SUM(IF(p.checked_by_sheriff >= 0, 1, 0)), ' .
				'SUM(IF((p.flags & ' . SCORING_FLAG_BEST_PLAYER . ') <> 0, 1, 0)), ' .
				'SUM(IF((p.flags & ' . SCORING_FLAG_BEST_MOVE . ') <> 0, 1, 0)), ' .
				'SUM(IF((p.flags & ' . SCORING_FLAG_FIRST_LEGACY_3 . ') <> 0, 1, 0)), ' .
				'SUM(IF((p.flags & ' . SCORING_FLAG_FIRST_LEGACY_2 . ') <> 0, 1, 0)), ' .
				'SUM(IF((p.flags & ' . SCORING_FLAG_FIRST_LEGACY_1 . ') <> 0, 1, 0)), ' .
				'SUM(IF((p.flags & ' . SCORING_FLAG_KILLED_FIRST_NIGHT . ') <> 0, 1, 0)), ' .
				'SUM(p.extra_points) ' .
				' FROM players p JOIN games g ON g.id = p.game_id WHERE TRUE',
			$condition);
		
		$row = $query->record(get_label('player'));
		$this->games_played = $row[0] / $count;
		if ($this->games_played > 0)
		{
			$this->games_won = $row[1] / $count;
			$this->rating = $row[2] / $count;
			$this->voted_civil = $row[3] / $count;
			$this->voted_mafia = $row[4] / $count;
			$this->voted_sheriff = $row[5] / $count;
			$this->voted_by_civil = $row[6] / $count;
			$this->voted_by_mafia = $row[7] / $count;
			$this->voted_by_sheriff = $row[8] / $count;
			$this->nominated_civil = $row[9] / $count;
			$this->nominated_mafia = $row[10] / $count;
			$this->nominated_sheriff = $row[11] / $count;
			$this->nominated_by_civil = $row[12] / $count;
			$this->nominated_by_mafia = $row[13] / $count;
			$this->nominated_by_sheriff = $row[14] / $count;
			$this->warnings = $row[15] / $count;
			$this->arranged = $row[16] / $count;
			$this->checked_by_don = $row[17] / $count;
			$this->checked_by_sheriff = $row[18] / $count;
			$this->best_player = $row[19] / $count;
			$this->best_move = $row[20] / $count;
			$this->guess3maf = $row[21] / $count;
			$this->guess2maf = $row[22] / $count;
			$this->guess1maf = $row[23] / $count;
			$this->killed_first_night = $row[24] / $count;
			$this->bonus = $row[25] / $count;
		}
		
		$query = new DbQuery('SELECT p.kill_round, p.kill_type, count(*) FROM players p JOIN games g ON g.id = p.game_id WHERE TRUE', $condition);
		$query->add(' GROUP BY p.kill_type, p.kill_round ORDER BY p.kill_round, p.kill_type');
		while ($row = $query->next())
		{
			$this->surviving[] = new Surviving($row[0] + 1, $row[1], $row[2] / $count);
		}
	}
}

class SheriffStats
{
	public $club_id;
    public $user_id;
	public $games_played;
	
    public $civil_found;
    public $mafia_found;
	
	// if $user_id <= 0: gives stats of an average player
	// if $club_id <= 0: gives stats for all clubs
	function __construct($user_id, $club_id)
	{
		$this->games_played = 0;
		$this->civil_found = 0;
		$this->mafia_found = 0;

		$this->club_id = $club_id;
		$this->user_id = $user_id;
		
		$count = 1; 
		if ($user_id <= 0)
		{
			if ($club_id <= 0)
			{
				$query = new DbQuery('SELECT count(DISTINCT user_id) FROM sheriffs');
			}
			else
			{
				$query = new DbQuery('SELECT count(DISTINCT s.user_id) FROM sheriffs s, games g WHERE g.id = s.game_id AND g.club_id = ?', $club_id);
			}
			
			list ($count) = $query->record(get_label('player'));
			if ($count <= 0)
			{
				$count = 1;
			}
		}
		
		$query = new DbQuery('SELECT count(*), SUM(s.civil_found), SUM(s.mafia_found) FROM sheriffs s, games g WHERE g.id = s.game_id');
		if ($user_id > 0)
		{
			$query->add(' AND s.user_id = ?', $user_id);
		}
		if ($club_id > 0)
		{
			$query->add(' AND g.club_id = ?', $club_id);
		}
		
		$row = $query->record(get_label('player'));
		$this->games_played = $row[0] / $count;
		if ($this->games_played > 0)
		{
			$this->civil_found = $row[1] / $count;
			$this->mafia_found = $row[2] / $count;
		}
	}
}

class MafiaStats
{
	public $club_id;
    public $user_id;
	public $games_played;
	
	public $roles;
	
    public $shots1_ok;
    public $shots1_miss;
    public $shots2_ok;        // 2 mafia players are alive: successful shots
    public $shots2_miss;      // 2 mafia players are alive: missed shot
    public $shots2_blank;     // 2 mafia players are alive: this player didn't shoot
    public $shots2_rearrange; // 2 mafia players are alive: killed a player who was not arranged
    public $shots3_ok;        // 3 mafia players are alive: successful shots
    public $shots3_miss;      // 3 mafia players are alive: missed shot
    public $shots3_blank;     // 3 mafia players are alive: this player didn't shoot
    public $shots3_fail;      // 3 mafia players are alive: missed because of this player (others shoot the same person)
    public $shots3_rearrange; // 3 mafia players are alive: killed a player who was not arranged
	
	// if $user_id <= 0: gives stats of an average player
	// if $club_id <= 0: gives stats for all clubs
	function __construct($user_id, $club_id, $roles)
	{
		$this->club_id = $club_id;
		$this->user_id = $user_id;
		$this->roles = $roles;
		
		$this->games_played = 0;
		$this->shots1_ok = 0;
		$this->shots1_miss = 0;
		$this->shots2_ok = 0;       
		$this->shots2_miss = 0;     
		$this->shots2_blank = 0;    
		$this->shots2_rearrange = 0;
		$this->shots3_ok = 0;       
		$this->shots3_miss = 0;     
		$this->shots3_blank = 0;    
		$this->shots3_fail = 0;     
		$this->shots3_rearrange = 0;
		
		$role_query = NULL;
		switch ($roles & (ROLE_MAFIA | ROLE_DON))
		{
			case ROLE_MAFIA:
				$role_query = new SQL('m.is_don = false');
				break;
			case ROLE_DON:
				$role_query = new SQL('m.is_don = true');
				break;
			case 0:
				return;
		}
		
		$count = 1; 
		if ($user_id <= 0)
		{
			if ($club_id <= 0)
			{
				$query = new DbQuery('SELECT count(DISTINCT m.user_id) FROM mafiosos m');
				if ($role_query != NULL)
				{
					$query->add(' WHERE ', $role_query);
				}
			}
			else
			{
				$query = new DbQuery('SELECT count(DISTINCT m.user_id) FROM mafiosos m, games g WHERE g.id = m.game_id AND g.club_id = ?', $club_id);
				if ($role_query != NULL)
				{
					$query->add(' AND ', $role_query);
				}
			}
			
			list ($count) = $query->record(get_label('player'));
			if ($count <= 0)
			{
				$count = 1;
			}
		}
		
		$query = new DbQuery(
			'SELECT count(*), ' .
				'SUM(m.shots1_ok), SUM(m.shots1_miss), ' .
				'SUM(m.shots2_ok), SUM(m.shots2_miss), SUM(m.shots2_blank), SUM(m.shots2_rearrange), ' .
				'SUM(m.shots3_ok), SUM(m.shots3_miss), SUM(m.shots3_blank), SUM(m.shots3_fail), SUM(m.shots3_rearrange) FROM mafiosos m, games g WHERE m.game_id = g.id');
		if ($role_query != NULL)
		{
			$query->add(' AND ', $role_query);
		}
			
		if ($user_id > 0)
		{
			$query->add(' AND m.user_id = ?', $user_id);
		}
		if ($club_id > 0)
		{
			$query->add(' AND g.club_id = ?', $club_id);
		}
		
		
		$row = $query->record(get_label('player'));
		$this->games_played = $row[0] / $count;
		if ($this->games_played > 0)
		{
			$this->shots1_ok = $row[1] / $count;
			$this->shots1_miss = $row[2] / $count;
			$this->shots2_ok = $row[3] / $count;
			$this->shots2_miss = $row[4] / $count;
			$this->shots2_blank = $row[5] / $count;
			$this->shots2_rearrange = $row[6] / $count;
			$this->shots3_ok = $row[7] / $count;
			$this->shots3_miss = $row[8] / $count;
			$this->shots3_blank = $row[9] / $count;
			$this->shots3_fail = $row[10] / $count;
			$this->shots3_rearrange = $row[11] / $count;
		}
	}
}

class DonStats
{
    public $club_id;
    public $user_id;
	public $games_played;
	
    public $sheriff_found;
    public $sheriff_arranged;
	
	// if $user_id <= 0: gives stats of an average player
	// if $club_id <= 0: gives stats for all clubs
	function __construct($user_id, $club_id)
	{
		$this->club_id = $club_id;
		$this->user_id = $user_id;
		$this->games_played = 0;
		$this->sheriff_found = 0;
		$this->sheriff_arranged = 0;
		
		$count = 1; 
		if ($user_id <= 0)
		{
			if ($club_id <= 0)
			{
				$query = new DbQuery('SELECT count(DISTINCT user_id) FROM dons');
			}
			else
			{
				$query = new DbQuery('SELECT count(DISTINCT d.user_id) FROM dons d, games g WHERE g.id = d.game_id AND g.club_id = ?', $club_id);
			}
			
			if ($row = $query->next())
			{
				$count = $row[0];
				if ($count <= 0)
				{
					$count = 1;
				}
			}
		}
		
		$query = new DbQuery('SELECT count(*), SUM(IF(d.sheriff_found >= 0, 1, 0)), SUM(IF(d.sheriff_arranged >= 0, 1, 0)) FROM dons d, games g WHERE d.game_id = g.id');
		if ($user_id > 0)
		{
			$query->add(' AND d.user_id = ?', $user_id);
		}
		if ($club_id > 0)
		{
			$query->add(' AND g.club_id = ?', $club_id);
		}
		
		$row = $query->record(get_label('player'));
		$this->games_played = $row[0] / $count;
		if ($this->games_played > 0)
		{
			$this->sheriff_found = $row[1] / $count;
			$this->sheriff_arranged = $row[2] / $count;
		}
	}
}

?>