<?php

require_once 'include/event.php';
require_once 'include/club.php';

define('COMMENTS_WIDTH', 300);

class Page extends EventPageBase
{
	protected function show_body()
	{
		global $_profile;
		
		// if (isset($_PROFILE['attend']))
		// {
		// }
		// else if (isset($_PROFILE['decline']))
		// {
			// Db::begin();
			// list($count) = Db::record(get_label('registration'), 'SELECT count(*) FROM event_users WHERE event_id = ? AND user_id = ?', $event_id, $_profile->user_id);
			// if ($count > 0)
			// {
				// Db::exec(get_label('event'), 'UPDATE event_users SET coming_odds = 0 WHERE event_id = ? AND user_id = ?', $event_id, $_profile->user_id);
			// }
			// else
			// {
				// Db::exec(get_label('event'), 'INSERT INTO event_users (event_id, user_id, coming_odds, people_with_me) VALUES (?, ?, 0, 0)', $event_id, $_profile->user_id);
			// }
			// Db::exec(get_label('registration'), 'DELETE FROM registrations WHERE event_id = ? AND user_id = ?', $event_id, $_profile->user_id);
			// Db::commit();
		// }
			
		if ($_profile != NULL && ($this->event->flags & EVENT_FLAG_CANCELED) == 0 && time() < $this->event->timestamp + $this->event->duration)
		{
			echo '<table class="transp" width="100%"><tr>';
			echo '<td><input type="submit" value="'.get_label('Attend').'" class="btn norm" onclick="attend()">';
			echo '<input type="submit" value="'.get_label('Pass').'" class="btn norm" onclick="decline()"></td>';
			echo '</tr></table>';
		}
		
		echo '<table width="100%"><tr valign="top"><td>';
		$this->event->show_details();
		echo '</td><td id="comments" width="' . COMMENTS_WIDTH . '"></td></tr></table>';
	}
	
	
	protected function js_on_load()
	{
		echo 'mr.showComments("event", ' . $this->event->id . ", 5)\n";
		if (isset($_REQUEST['attend']))
		{
			echo 'attend();';
		}
		else if (isset($_REQUEST['decline']))
		{
			echo 'decline();';
		}
	}
	
	protected function js()
	{
		parent::js();
?>
		function attend()
		{
			mr.attendEvent(<?php echo $this->event->id; ?>, "event_info.php?id=<?php echo $this->event->id; ?>");
		}
		
		function decline()
		{
			mr.passEvent(<?php echo $this->event->id; ?>, "event_info.php?id=<?php echo $this->event->id; ?>", "<?php echo get_label('Thank you for letting us know. See you next time.'); ?>");
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Event info'), PERM_ALL);

?>
