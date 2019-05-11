<?php

require_once 'include/tournament.php';
require_once 'include/club.php';

define('COMMENTS_WIDTH', 300);

class Page extends TournamentPageBase
{
	protected function show_body()
	{
		global $_profile;
		
		if ($_profile != NULL && ($this->flags & EVENT_FLAG_CANCELED) == 0 && time() < $this->start_time + $this->duration)
		{
			echo '<table class="transp" width="100%"><tr>';
			echo '<td><input type="submit" value="'.get_label('Attend').'" class="btn norm" onclick="attend()">';
			echo '<input type="submit" value="'.get_label('Pass').'" class="btn norm" onclick="decline()"></td>';
			echo '</tr></table>';
		}
		
		echo '<table width="100%"><tr valign="top"><td>';
		// $this->show_details();
		echo '</td><td id="comments" width="' . COMMENTS_WIDTH . '"></td></tr></table>';
	}
	
	
	protected function js_on_load()
	{
		global $_profile;
		
		echo 'mr.showComments("tournament", ' . $this->id . ", 5);\n";
		if (isset($_REQUEST['approve']) && $_profile != NULL && (!isset($_REQUEST['_login_']) || $_REQUEST['_login_'] == $_profile->user_id))
		{
			$league_id = (int)$_REQUEST['approve'];
			if ($league_id > 0 && is_permitted(PERMISSION_LEAGUE_MANAGER, $league_id))
			{
?>
				mr.approveTournament(<?php echo $this->id; ?>, <?php echo $league_id; ?>);
<?php
			}
		}
	}
	
	protected function js()
	{
		parent::js();
	}
}

$page = new Page();
$page->run(get_label('Main Page'));

?>
