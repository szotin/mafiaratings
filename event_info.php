<?php

require_once 'include/event.php';
require_once 'include/club.php';

define('COMMENTS_WIDTH', 300);

class Page extends EventPageBase
{
	protected function show_body()
	{
		global $_profile;
		
		if ($_profile != NULL && ($this->event->flags & EVENT_FLAG_CANCELED) == 0 && time() < $this->event->timestamp + $this->event->duration)
		{
			echo '<table class="transp" width="100%"><tr>';
			echo '<td><input type="submit" value="'.get_label('Attend').'" class="btn norm" onclick="mr.attendEvent(' . $this->event->id . ')">';
			echo '<input type="submit" value="'.get_label('Pass').'" class="btn norm" onclick="mr.passEvent(' . $this->event->id . ')"></td>';
			echo '</tr></table>';
		}
		
		echo '<table width="100%"><tr valign="top"><td>';
		$this->event->show_details();
		echo '</td><td id="comments" width="' . COMMENTS_WIDTH . '"></td></tr></table>';
?>
		<script type="text/javascript">
			mr.showComments("event", <?php echo $this->event->id; ?>, 5);
		</script>
<?php
	}
}

$page = new Page();
$page->run(get_label('Event info'), PERM_ALL);

?>
