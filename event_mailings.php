<?php

require_once 'include/page_base.php';
require_once 'include/event.php';
require_once 'include/event_mailing.php';

function type_to_str($type)
{
	switch ($type)
	{
		case EVENT_EMAIL_INVITE:
			return get_label('invitation');
		case EVENT_EMAIL_CANCEL:
			return get_label('cancelling');
		case EVENT_EMAIL_CHANGE_ADDRESS:
			return get_label('change address');
		case EVENT_EMAIL_CHANGE_TIME:
			return get_label('change time');
		case EVENT_EMAIL_RESTORE:
			return get_label('restore');
	}
	return '';
}

class Page extends EventPageBase
{
	protected function show_body()
	{
		global $_profile;
		
		if (!$this->is_manager)
		{
			no_permission();
		}
		
		echo '<table class="bordered light" width="100%">';
		if (isset($_REQUEST['msg']))
		{
			if ($_REQUEST['msg'] == 0)
			{
				echo '<tr><td colspan="6" class="light">' . get_label('You have canceled the event. Please check the existing mailings, probably you want to cancel some of them or send new emails about your action.') . '</td></tr>';
			}
		}
		
		echo '<tr height="36" class="th darker">';
		echo '<td width="60"><button class="icon" onclick="mr.createEventMailing(' . $this->id . ')" title="' . get_label('New mailing') . '">';
		echo '<img src="images/create.png" border="0"></button></td>';
		echo '<td width="150">' . get_label('Date') . '</td><td>' . get_label('Recipients') . '</td><td width="80">' . get_label('Type') . '</td><td width="80">' . get_label('Status') . '</td><td width="80">' . get_label('Emails sent') . '</td></tr>';
		
		$query = new DbQuery('SELECT id, send_time, send_count, status, langs, flags, type FROM event_mailings WHERE event_id = ? ORDER BY send_time', $this->id);
		while ($row = $query->next())
		{
			list($mail_id, $send_time, $send_count, $status, $langs, $flags, $type) = $row;
			
			echo '<tr height="36">';
			switch ($status)
			{
				case MAILING_WAITING:
					echo '<td class="dark">';
					echo '<button class="icon" onclick="mr.editEventMailing(' . $mail_id . ')" title="' . get_label('Edit mailing') . '"><img src="images/edit.png" border="0"></button>';
					echo '<button class="icon" onclick="deleteMailing(' . $mail_id . ')" title="' . get_label('Delete mailing') . '"><img src="images/delete.png" border="0"></button>';
					echo '</td>';
					echo '<td>' . format_date($this->start_time - $send_time, get_timezone(), true) . '</td>';
					echo '<td>' . get_email_recipients($flags, $langs) . '</td>';
					echo '<td>' . type_to_str($type) . '</td>';
					echo '<td>' . get_label('waiting') . '</td><td></td>';
					break;
					
				case MAILING_SENDING:
					echo '<td class="dark"></td>';
					echo '<td><a href="view_event_mailing.php?id='.$this->id.'&mailing_id='.$mail_id.'&bck=1" title="' . get_label('View') . '">' . format_date($this->start_time - $send_time, get_timezone(), true) . '</a></td>';
					echo '<td>' . get_email_recipients($flags, $langs) . '</td>';
					echo '<td>' . type_to_str($type) . '</td>';
					echo '<td>' . get_label('sending') . '</td><td>' . $send_count . '</td>';
					break;
					
				case MAILING_COMPLETE:
					echo '<td class="dark"></td>';
					echo '<td><a href="view_event_mailing.php?id='.$this->id.'&mailing_id='.$mail_id.'&bck=1" title="' . get_label('View') . '">' . format_date($this->start_time - $send_time, get_timezone(), true) . '</a></td>';
					echo '<td>' . get_email_recipients($flags, $langs) . '</td>';
					echo '<td>' . type_to_str($type) . '</td>';
					echo '<td>' . get_label('complete') . '</td><td>' . $send_count . '</td>';
					break;
			}
			echo '</tr>';
		}
		echo '</table>';
	}
	
	protected function js()
	{
		parent::js();
?>
		function deleteMailing(mailingId)
		{
			dlg.yesNo("<?php echo get_label('Are you sure you want to delete event mailing?'); ?>", null, null, function() { mr.deleteEventMailing(mailingId) });
		}
<?php	
	}
}

$page = new Page();
$page->run('Mailing');

?>
