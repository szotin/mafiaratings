<?php

require_once 'include/db.php';
require_once 'include/names.php';

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
define('SCORING_THREE_DARK_CHECKS', 0x8000); // 32768: for sheriff: makes 3 dark checks
define('SCORING_ARRANGED_SRF', 0x10000); // 65536: for sheriff: makes 3 dark checks
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
		echo '<tr><td>'.get_label('For mafiosy. Winning the game').':</td><td><input id="form-' . SCORING_WIN_MAF . '" value="' . $this->points[SCORING_WIN_MAF] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For don. Winning the game').':</td><td><input id="form-' . SCORING_WIN_DON . '" value="' . $this->points[SCORING_WIN_DON] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For civilian. Loosing the game').':</td><td><input id="form-' . SCORING_LOS_CIV . '" value="' . $this->points[SCORING_LOS_CIV] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For sheriff. Loosing the game').':</td><td><input id="form-' . SCORING_LOS_SRF . '" value="' . $this->points[SCORING_LOS_SRF] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For mafiosy. Loosing the game').':</td><td><input id="form-' . SCORING_LOS_MAF . '" value="' . $this->points[SCORING_LOS_MAF] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For don. Loosing the game').':</td><td><input id="form-' . SCORING_LOS_DON . '" value="' . $this->points[SCORING_LOS_DON] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For civilian. Killed the first night and successfuly guessed all 3 mafs').':</td><td><input id="form-' . SCORING_GUESS_ALL_MAF . '" value="' . $this->points[SCORING_GUESS_ALL_MAF] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For civilian. Never voting against red players (minimum 3 votes)').':</td><td><input id="form-' . SCORING_NO_VOTE_FOR_RED . '" value="' . $this->points[SCORING_NO_VOTE_FOR_RED] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For mafiosy. Don finds the sheriff the first night and they killed him the next night (does not count if sheriff was arranged)').':</td><td><input id="form-' . SCORING_FIND_AND_KILL_SRF_MAF . '" value="' . $this->points[SCORING_FIND_AND_KILL_SRF_MAF] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For don. Don finds the sheriff the first night and they killed him the next night (does not count if sheriff was arranged)').':</td><td><input id="form-' . SCORING_FIND_AND_KILL_SRF_DON . '" value="' . $this->points[SCORING_FIND_AND_KILL_SRF_DON] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For don. Finding the sheriff the first night').':</td><td><input id="form-' . SCORING_FIND_SRF . '" value="' . $this->points[SCORING_FIND_SRF] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For don. Arranged sheriff for the first night').':</td><td><input id="form-' . SCORING_ARRANGED_SRF . '" value="' . $this->points[SCORING_ARRANGED_SRF] / SCORING_DIVIDE . '"></td></tr>';
		echo '<tr><td>'.get_label('For sheriff. Making 3 dark checks in a row').':</td><td><input id="form-' . SCORING_THREE_DARK_CHECKS . '" value="' . $this->points[SCORING_THREE_DARK_CHECKS] / SCORING_DIVIDE . '"></td></tr>';
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