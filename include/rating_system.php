<?php

require_once 'include/db.php';
require_once 'include/names.php';

define('RATING_WIN_CIV', 0x1); // 1: for civilian: win the game
define('RATING_WIN_SRF', 0x2); // 2: for sheriff: win the game
define('RATING_WIN_MAF', 0x4); // 4: for mafia: win the game
define('RATING_WIN_DON', 0x8); // 8: for don: win the game
define('RATING_LOS_CIV', 0x10); // 16: for civilian: loose the game
define('RATING_LOS_SRF', 0x20); // 32: for sheriff: loose the game
define('RATING_LOS_MAF', 0x40); // 64: for mafia: loose the game
define('RATING_LOS_DON', 0x80); // 128: for don: loose the game
define('RATING_BEST_PLAYER', 0x100); // 256: for everyone: best player of the game
define('RATING_BEST_MOVE', 0x200); // 512: for everyone: best move of the game
define('RATING_GUESS_ALL_MAF', 0x400); // 1024: for civilian: killed the first night and successfully guessed all 3 mafs
define('RATING_NO_VOTE_FOR_RED', 0x800); // 2048: for civilian: never voted against red (minimum 3 votes)
define('RATING_FIND_AND_KILL_SRF_MAF', 0x1000); // 4096: for mafia: when don finds sheriff the first night and they rearange him to kill (does not count if sheriff was already arranged)
define('RATING_FIND_AND_KILL_SRF_DON', 0x2000); // 8192: for don: when don finds sheriff the first night and they rearange him to kill (does not count if sheriff was already arranged)
define('RATING_FIND_SRF', 0x4000); // 16384: for don: finds the sheriff the first night
define('RATING_THREE_DARK_CHECKS', 0x8000); // 32768: for sheriff: makes 3 dark checks
define('RATING_ARRANGED_SRF', 0x10000); // 65536: for sheriff: makes 3 dark checks
define('RATING_FIRST_AVAILABLE_FLAG', 0x20000); 

class RatingSystem
{
    public $id;
	public $club_id;
    public $name;
	public $digits;
	public $points;
	
	function __construct($id, $club_id = -1)
	{
		if ($id <= 0)
		{
			$this->id = -1;
			$this->club_id = $club_id;
			$this->name = '';
			$this->digits = 0;
			$this->points = array(
				RATING_WIN_CIV => 3, RATING_WIN_SRF => 4, RATING_WIN_MAF => 4, RATING_WIN_DON => 5,
				RATING_LOS_CIV => 0, RATING_LOS_SRF => -1, RATING_LOS_MAF => 0, RATING_LOS_DON => -1,
				RATING_BEST_PLAYER => 1, RATING_BEST_MOVE => 1, RATING_GUESS_ALL_MAF => 1, RATING_NO_VOTE_FOR_RED => 0,
				RATING_FIND_AND_KILL_SRF_MAF => 0, RATING_FIND_AND_KILL_SRF_DON => 0, RATING_FIND_SRF => 0, RATING_THREE_DARK_CHECKS => 0, RATING_ARRANGED_SRF => 0
			);
		}
		else
		{
			list ($this->id, $this->club_id, $this->name, $this->digits) =
				Db::record(get_label('rating system'), 'SELECT id, club_id, name, digits FROM systems WHERE id = ?', $id);
			$this->points = array(
				RATING_WIN_CIV => 0, RATING_WIN_SRF => 0, RATING_WIN_MAF => 0, RATING_WIN_DON => 0,
				RATING_LOS_CIV => 0, RATING_LOS_SRF => 0, RATING_LOS_MAF => 0, RATING_LOS_DON => 0,
				RATING_BEST_PLAYER => 0, RATING_BEST_MOVE => 0, RATING_GUESS_ALL_MAF => 0, RATING_NO_VOTE_FOR_RED => 0,
				RATING_FIND_AND_KILL_SRF_MAF => 0, RATING_FIND_AND_KILL_SRF_DON => 0, RATING_FIND_SRF => 0, RATING_THREE_DARK_CHECKS => 0, RATING_ARRANGED_SRF =>0
			);
			$query = new DbQuery('SELECT flag, points FROM points WHERE system_id = ?', $id);
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
		echo '<tr><td width="600">'.get_label('Rating system name').':</td><td><input id="form-name" value="' . $this->name . '"></td></tr>';
		echo '<tr><td>'.get_label('Digits after decimal point').':</td><td><input id="form-digits" value="' . $this->digits . '"></td></tr>';
		echo '<tr class="dark"><td colspan="2" align="center">'.get_label('Points').':</td></tr>';
		echo '<tr><td>'.get_label('Best player').':</td><td><input id="form-' . RATING_BEST_PLAYER . '" value="' . $this->points[RATING_BEST_PLAYER] . '"></td></tr>';
		echo '<tr><td>'.get_label('Best move').':</td><td><input id="form-' . RATING_BEST_MOVE . '" value="' . $this->points[RATING_BEST_MOVE] . '"></td></tr>';
		echo '<tr><td>'.get_label('For civilian. Winning the game').':</td><td><input id="form-' . RATING_WIN_CIV . '" value="' . $this->points[RATING_WIN_CIV] . '"></td></tr>';
		echo '<tr><td>'.get_label('For sheriff. Winning the game').':</td><td><input id="form-' . RATING_WIN_SRF . '" value="' . $this->points[RATING_WIN_SRF] . '"></td></tr>';
		echo '<tr><td>'.get_label('For mafiosy. Winning the game').':</td><td><input id="form-' . RATING_WIN_MAF . '" value="' . $this->points[RATING_WIN_MAF] . '"></td></tr>';
		echo '<tr><td>'.get_label('For don. Winning the game').':</td><td><input id="form-' . RATING_WIN_DON . '" value="' . $this->points[RATING_WIN_DON] . '"></td></tr>';
		echo '<tr><td>'.get_label('For civilian. Loosing the game').':</td><td><input id="form-' . RATING_LOS_CIV . '" value="' . $this->points[RATING_LOS_CIV] . '"></td></tr>';
		echo '<tr><td>'.get_label('For sheriff. Loosing the game').':</td><td><input id="form-' . RATING_LOS_SRF . '" value="' . $this->points[RATING_LOS_SRF] . '"></td></tr>';
		echo '<tr><td>'.get_label('For mafiosy. Loosing the game').':</td><td><input id="form-' . RATING_LOS_MAF . '" value="' . $this->points[RATING_LOS_MAF] . '"></td></tr>';
		echo '<tr><td>'.get_label('For don. Loosing the game').':</td><td><input id="form-' . RATING_LOS_DON . '" value="' . $this->points[RATING_LOS_DON] . '"></td></tr>';
		echo '<tr><td>'.get_label('For civilian. Killed the first night and successfuly guessed all 3 mafs').':</td><td><input id="form-' . RATING_GUESS_ALL_MAF . '" value="' . $this->points[RATING_GUESS_ALL_MAF] . '"></td></tr>';
		echo '<tr><td>'.get_label('For civilian. Never voting against red players (minimum 3 votes)').':</td><td><input id="form-' . RATING_NO_VOTE_FOR_RED . '" value="' . $this->points[RATING_NO_VOTE_FOR_RED] . '"></td></tr>';
		echo '<tr><td>'.get_label('For mafiosy. Don finds the sheriff the first night and they killed him the next night (does not count if sheriff was arranged)').':</td><td><input id="form-' . RATING_FIND_AND_KILL_SRF_MAF . '" value="' . $this->points[RATING_FIND_AND_KILL_SRF_MAF] . '"></td></tr>';
		echo '<tr><td>'.get_label('For don. Don finds the sheriff the first night and they killed him the next night (does not count if sheriff was arranged)').':</td><td><input id="form-' . RATING_FIND_AND_KILL_SRF_DON . '" value="' . $this->points[RATING_FIND_AND_KILL_SRF_DON] . '"></td></tr>';
		echo '<tr><td>'.get_label('For don. Finding the sheriff the first night').':</td><td><input id="form-' . RATING_FIND_SRF . '" value="' . $this->points[RATING_FIND_SRF] . '"></td></tr>';
		echo '<tr><td>'.get_label('For don. Arranged sheriff for the first night').':</td><td><input id="form-' . RATING_ARRANGED_SRF . '" value="' . $this->points[RATING_ARRANGED_SRF] . '"></td></tr>';
		echo '<tr><td>'.get_label('For sheriff. Making 3 dark checks in a row').':</td><td><input id="form-' . RATING_THREE_DARK_CHECKS . '" value="' . $this->points[RATING_THREE_DARK_CHECKS] . '"></td></tr>';
		echo '</table>';
		
?>
		<script>
		$(function()
		{
			$("#form-digits").spinner(
			{
				spin: function(event, ui)
				{
					if ( ui.value < 0 )
					{
						$(this).spinner("value", 0);
						return false;
					}
				}
			});
			for (var flag = 1; flag < <?php echo RATING_FIRST_AVAILABLE_FLAG; ?>; flag <<= 1)
			{
				$("#form-" + flag).spinner();
			}
		});
		</script>
<?php
	}
}

?>