<?php

require_once 'include/db.php';
require_once 'include/names.php';
require_once 'include/constants.php';

define('SCORING_DEFAULT_ID', 10); // Default scoring system is hardcoded here to ФИИМ (FIGM)

define('SCORING_WIN_CIV', 0x1); // 1: for civilian: win the game
define('SCORING_WIN_SRF', 0x2); // 2: for sheriff: win the game
define('SCORING_WIN_MAF', 0x4); // 4: for mafia: win the game
define('SCORING_WIN_DON', 0x8); // 8: for don: win the game
define('SCORING_LOS_CIV', 0x10); // 16: for civilian: loose the game
define('SCORING_LOS_SRF', 0x20); // 32: for sheriff: loose the game
define('SCORING_LOS_MAF', 0x40); // 64: for mafia: loose the game
define('SCORING_LOS_DON', 0x80); // 128: for don: loose the game
define('SCORING_BEST_PLAYER', 0x100); // 256: for everyone: best player of the game
define('SCORING_BEST_MOVE', 0x200); // 512: for everyone: best move of the game
define('SCORING_GUESS_ALL_MAF', 0x400); // 1024: for civilian: killed the first night and successfully guessed all 3 mafs
define('SCORING_NO_VOTE_FOR_RED', 0x800); // 2048: for civilian: never voted against red (minimum 3 votes)
define('SCORING_FIND_AND_KILL_SRF_MAF', 0x1000); // 4096: for mafia: when don finds sheriff the first night and they rearange him to kill (does not count if sheriff was already arranged)
define('SCORING_FIND_AND_KILL_SRF_DON', 0x2000); // 8192: for don: when don finds sheriff the first night and they rearange him to kill (does not count if sheriff was already arranged)
define('SCORING_FIND_SRF', 0x4000); // 16384: for don: finds the sheriff the first night
define('SCORING_THREE_DARK_CHECKS', 0x8000); // 32768: for sheriff: makes 3 black checks
define('SCORING_ARRANGED_SRF', 0x10000); // 65536: for sheriff: makes 3 black checks
define('SCORING_FIRST_AVAILABLE_FLAG', 0x20000); 

define('SCORING_DIVIDE', 100); 

function format_score($score)
{
	$score = (int)$score;
	if (($score % 10) != 0)
	{
		return number_format($score/100, 2);
	}
	else if (($score % 100) != 0)
	{
		return number_format($score/100, 1);
	}
	return number_format($score/100);
}

function format_rating($rating)
{
	$fraction = 100;
	$rat = abs($rating);
	$digits = 0;
	if ($rat > 0.0001)
	{
		while ($rat < $fraction)
		{
			$fraction /= 10;
			++$digits;
		}
	}
	return number_format($rating, $digits);
}

function show_scoring_select($club_id, $scoring_id, $form_name)
{
	echo '<select name="scoring" onChange="document.' . $form_name . '.submit()">';
	$query = new DbQuery('SELECT id, name FROM scorings WHERE club_id = ? OR club_id IS NULL ORDER BY name', $club_id);
	while ($row = $query->next())
	{
		list ($sid, $sname) = $row;
		show_option($sid, $scoring_id, $sname);
	}
	echo '</select></td>';
}

define('ROLE_NAME_FLAG_LOWERCASE', 1);
define('ROLE_NAME_FLAG_SINGLE', 2);

function get_role_name($role, $flags = 0)
{
	switch ($flags & 3)
	{
		case 0:
			switch ($role)
			{
				case POINTS_ALL:
					return get_label('All roles');
				case POINTS_RED:
					return get_label('Reds');
				case POINTS_DARK:
					return get_label('Blacks');
				case POINTS_CIVIL:
					return get_label('Civilians');
				case POINTS_SHERIFF:
					return get_label('Sheriffs');
				case POINTS_MAFIA:
					return get_label('Mafiosi');
				case POINTS_DON:
					return get_label('Dons');
			}
			break;
			
		case 1:
			switch ($role)
			{
				case POINTS_ALL:
					return get_label('all roles');
				case POINTS_RED:
					return get_label('reds');
				case POINTS_DARK:
					return get_label('blacks');
				case POINTS_CIVIL:
					return get_label('civilians');
				case POINTS_SHERIFF:
					return get_label('sheriffs');
				case POINTS_MAFIA:
					return get_label('mafiosi');
				case POINTS_DON:
					return get_label('dons');
			}
			break;
			
		case 2:
			switch ($role)
			{
				case POINTS_ALL:
					return get_label('Any role');
				case POINTS_RED:
					return get_label('Red');
				case POINTS_DARK:
					return get_label('Black');
				case POINTS_CIVIL:
					return get_label('Civilian');
				case POINTS_SHERIFF:
					return get_label('Sheriff');
				case POINTS_MAFIA:
					return get_label('Mafiosi');
				case POINTS_DON:
					return get_label('Don');
			}
			break;
			
		case 3:
			switch ($role)
			{
				case POINTS_ALL:
					return get_label('any role');
				case POINTS_RED:
					return get_label('red');
				case POINTS_DARK:
					return get_label('black');
				case POINTS_CIVIL:
					return get_label('civilian');
				case POINTS_SHERIFF:
					return get_label('sheriff');
				case POINTS_MAFIA:
					return get_label('mafiosi');
				case POINTS_DON:
					return get_label('don');
			}
			break;
	}
	return '';
}

function show_roles_select($roles, $form_name, $flags = 0)
{
	echo '<select name="roles" onChange="document.' . $form_name . '.submit()">';
	show_option(POINTS_ALL, $roles, get_role_name(POINTS_ALL, $flags));
	show_option(POINTS_RED, $roles, get_role_name(POINTS_RED, $flags));
	show_option(POINTS_DARK, $roles, get_role_name(POINTS_DARK, $flags));
	show_option(POINTS_CIVIL, $roles, get_role_name(POINTS_CIVIL, $flags));
	show_option(POINTS_SHERIFF, $roles, get_role_name(POINTS_SHERIFF, $flags));
	show_option(POINTS_MAFIA, $roles, get_role_name(POINTS_MAFIA, $flags));
	show_option(POINTS_DON, $roles, get_role_name(POINTS_DON, $flags));
	echo '</select>';
}

function get_roles_condition($roles)
{
	$role_condition = new SQL();
	switch ($roles)
	{
	case POINTS_RED:
		$role_condition->add(' AND p.role < 2');
		break;
	case POINTS_DARK:
		$role_condition->add(' AND p.role > 1');
		break;
	case POINTS_CIVIL:
		$role_condition->add(' AND p.role = 0');
		break;
	case POINTS_SHERIFF:
		$role_condition->add(' AND p.role = 1');
		break;
	case POINTS_MAFIA:
		$role_condition->add(' AND p.role = 2');
		break;
	case POINTS_DON:
		$role_condition->add(' AND p.role = 3');
		break;
	}
	return $role_condition;
}

class ScoringSystem
{
    public $id;
	public $club_id;
    public $name;
	public $points;
	
	function __construct($id, $club_id = -1)
	{
		if ($id <= 0)
		{
			$this->id = -1;
			$this->club_id = $club_id;
			$this->name = '';
			$this->points = array(
				SCORING_WIN_CIV => 300, SCORING_WIN_SRF => 400, SCORING_WIN_MAF => 400, SCORING_WIN_DON => 500,
				SCORING_LOS_CIV => 0, SCORING_LOS_SRF => -100, SCORING_LOS_MAF => 0, SCORING_LOS_DON => -100,
				SCORING_BEST_PLAYER => 100, SCORING_BEST_MOVE => 100, SCORING_GUESS_ALL_MAF => 100, SCORING_NO_VOTE_FOR_RED => 0,
				SCORING_FIND_AND_KILL_SRF_MAF => 0, SCORING_FIND_AND_KILL_SRF_DON => 0, SCORING_FIND_SRF => 0, SCORING_THREE_DARK_CHECKS => 0, SCORING_ARRANGED_SRF => 0
			);
		}
		else
		{
			list ($this->id, $this->club_id, $this->name) =
				Db::record(get_label('scoring system'), 'SELECT id, club_id, name FROM scorings WHERE id = ?', $id);
			$this->points = array(
				SCORING_WIN_CIV => 0, SCORING_WIN_SRF => 0, SCORING_WIN_MAF => 0, SCORING_WIN_DON => 0,
				SCORING_LOS_CIV => 0, SCORING_LOS_SRF => 0, SCORING_LOS_MAF => 0, SCORING_LOS_DON => 0,
				SCORING_BEST_PLAYER => 0, SCORING_BEST_MOVE => 0, SCORING_GUESS_ALL_MAF => 0, SCORING_NO_VOTE_FOR_RED => 0,
				SCORING_FIND_AND_KILL_SRF_MAF => 0, SCORING_FIND_AND_KILL_SRF_DON => 0, SCORING_FIND_SRF => 0, SCORING_THREE_DARK_CHECKS => 0, SCORING_ARRANGED_SRF =>0
			);
			$query = new DbQuery('SELECT flag, points FROM scoring_points WHERE scoring_id = ?', $id);
			while ($row = $query->next())
			{
				list ($flag, $points) = $row;
				$this->points[$flag] = $points;
			}
		}
	}
	
	function show_edit_form()
	{
		echo '<table class="dialog_form" width="100%">';
		echo '<tr><td width="600">'.get_label('Scoring system name').':</td><td><input id="form-name" value="' . $this->name . '"></td></tr>';
		echo '<tr class="dark"><td colspan="2" align="center">'.get_label('Points').':</td></tr>';
		echo '<tr><td>'.get_label('Best player').':</td><td><input id="form-' . SCORING_BEST_PLAYER . '" value="' . $this->points[SCORING_BEST_PLAYER] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('Best move').':</td><td><input id="form-' . SCORING_BEST_MOVE . '" value="' . $this->points[SCORING_BEST_MOVE] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For civilian. Winning the game').':</td><td><input id="form-' . SCORING_WIN_CIV . '" value="' . $this->points[SCORING_WIN_CIV] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For sheriff. Winning the game').':</td><td><input id="form-' . SCORING_WIN_SRF . '" value="' . $this->points[SCORING_WIN_SRF] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For mafiosi. Winning the game').':</td><td><input id="form-' . SCORING_WIN_MAF . '" value="' . $this->points[SCORING_WIN_MAF] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For don. Winning the game').':</td><td><input id="form-' . SCORING_WIN_DON . '" value="' . $this->points[SCORING_WIN_DON] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For civilian. Loosing the game').':</td><td><input id="form-' . SCORING_LOS_CIV . '" value="' . $this->points[SCORING_LOS_CIV] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For sheriff. Loosing the game').':</td><td><input id="form-' . SCORING_LOS_SRF . '" value="' . $this->points[SCORING_LOS_SRF] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For mafiosi. Loosing the game').':</td><td><input id="form-' . SCORING_LOS_MAF . '" value="' . $this->points[SCORING_LOS_MAF] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For don. Loosing the game').':</td><td><input id="form-' . SCORING_LOS_DON . '" value="' . $this->points[SCORING_LOS_DON] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For civilian. Killed the first night and successfuly guessed all 3 mafs').':</td><td><input id="form-' . SCORING_GUESS_ALL_MAF . '" value="' . $this->points[SCORING_GUESS_ALL_MAF] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For civilian. Never voting against red players (minimum 3 votes)').':</td><td><input id="form-' . SCORING_NO_VOTE_FOR_RED . '" value="' . $this->points[SCORING_NO_VOTE_FOR_RED] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For mafiosi. Don finds the sheriff the first night and they killed him the next night (does not count if sheriff was arranged)').':</td><td><input id="form-' . SCORING_FIND_AND_KILL_SRF_MAF . '" value="' . $this->points[SCORING_FIND_AND_KILL_SRF_MAF] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For don. Don finds the sheriff the first night and they killed him the next night (does not count if sheriff was arranged)').':</td><td><input id="form-' . SCORING_FIND_AND_KILL_SRF_DON . '" value="' . $this->points[SCORING_FIND_AND_KILL_SRF_DON] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For don. Finding the sheriff the first night').':</td><td><input id="form-' . SCORING_FIND_SRF . '" value="' . $this->points[SCORING_FIND_SRF] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For don. Arranged sheriff for the first night').':</td><td><input id="form-' . SCORING_ARRANGED_SRF . '" value="' . $this->points[SCORING_ARRANGED_SRF] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For sheriff. Making 3 black checks in a row').':</td><td><input id="form-' . SCORING_THREE_DARK_CHECKS . '" value="' . $this->points[SCORING_THREE_DARK_CHECKS] / SCORING_DIVIDE . '"></td></tr>';
		echo '</table>';
		
?>
		<script>
		$(function()
		{
			for (var flag = 1; flag < <?php echo SCORING_FIRST_AVAILABLE_FLAG; ?>; flag <<= 1)
			{
				$("#form-" + flag).spinner({ step: 0.05 });
			}
		});
		</script>
<?php
	}
}

?>