<?php

require_once 'include/page_base.php';
require_once 'include/event.php';
require_once 'include/event_mailing.php';

class Page extends PageBase
{
	private $event;

	protected function prepare()
	{
		global $_profile;
	
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('event')));
		}
		$this->event = new Event();
		$this->event->load($_REQUEST['id']);
		if ($_profile == NULL || !$_profile->is_manager($this->event->club_id))
		{
			throw new FatalExc(get_label('No permissions'));
		}
		
		if (isset($_REQUEST['cancel']))
		{
			$mailing_id = $_REQUEST['cancel'];
			list ($status) = Db::record(get_label('email'), 'SELECT status FROM event_emails WHERE id = ?', $mailing_id);
			if ($status != MAILING_CANCELED)
			{
				if ($status != MAILING_WAITING)
				{
					throw new Exc(get_label('Some emails are already sent. This mailing can not be canceled.'));
				}
				Db::begin();
				Db::exec(get_label('email'), 'UPDATE event_emails SET status = ' . MAILING_CANCELED . ' WHERE id = ?', $mailing_id);
				if (Db::affected_rows() > 0)
				{
					db_log('event_emails', 'Canceled', NULL, $mailing_id, $this->event->club_id);
				}
				Db::commit();
			}
		}
		else if (isset($_REQUEST['restore']))
		{
			$mailing_id = $_REQUEST['restore'];
			list ($status) = Db::record(get_label('email'), 'SELECT status FROM event_emails WHERE id = ?', $mailing_id);
			if ($status == MAILING_CANCELED)
			{
				Db::begin();
				Db::exec(get_label('email'), 'UPDATE event_emails SET status = ' . MAILING_WAITING . ' WHERE id = ?', $mailing_id);
				if (Db::affected_rows() > 0)
				{
					db_log('event_emails', 'Restored', NULL, $mailing_id, $this->event->club_id);
				}
				Db::commit();
			}
		}
		
		$this->_title = get_label('Mailing for [0]', $this->event->get_full_name());
	}
	
	protected function show_body()
	{
		global $_profile;
		
		echo '<table class="bordered light" width="100%">';
		if (isset($_REQUEST['msg']))
		{
			if ($_REQUEST['msg'] == 0)
			{
				echo '<tr><td colspan="6" class="light">' . get_label('You have canceled the event. Please check the existing mailings, probably you want to cancel some of them or send new emails about your action.') . '</td></tr>';
			}
		}
		
		echo '<tr class="th darker">';
		echo '<td width="52"><a href="create_event_mailing.php?events=' . $this->event->id . '&bck=1" title="' . get_label('New mailing') . '">';
		echo '<img src="images/create.png" border="0"></a></td>';
		echo '<td width="150">' . get_label('Date') . '</td><td>' . get_label('Recipients') . '</td><td width="80">' . get_label('Language') . '</td><td width="80">' . get_label('Status') . '</td><td width="80">' . get_label('Emails sent') . '</td></tr>';
		
		$query = new DbQuery('SELECT id, send_time, send_count, status, lang, flags FROM event_emails WHERE event_id = ? ORDER BY send_time', $this->event->id);
		while ($row = $query->next())
		{
			list($mail_id, $send_time, $send_count, $status, $lang, $flags) = $row;
			
			echo '<tr>';
			switch ($status)
			{
				case MAILING_WAITING:
					echo '<td class="dark"><a href="event_mailings.php?id=' . $this->event->id . '&cancel=' . $mail_id . '" title="' . get_label('Cancel') . '">';
					echo '<img src="images/delete.png" border="0"></a>';
					echo '<a href="edit_event_mailing.php?id=' . $mail_id . '&bck=1" title="' . get_label('Edit') . '">';
					echo '<img src="images/edit.png" border="0"></a></td>';
					break;
					
				case MAILING_SENDING:
				case MAILING_COMPLETE:
					echo '<td class="dark">&nbsp;</td>';
					break;
					
				case MAILING_CANCELED:
					echo '<td class="dark"><a href="event_mailings.php?id=' . $this->event->id . '&restore=' . $mail_id . '" title="' . get_label('Uncancel') . '">';
					echo '<img src="images/undelete.png" border="0"></a></td>';
					break;
			}
			
			echo '<td><a href="view_event_mailing.php?id=' . $mail_id . '&bck=1" title="' . get_label('View') . '">' . format_date('F d, Y, H:i', $send_time, $_profile->timezone) . '</a></td>';
			echo '<td>' . get_email_recipients($flags, $lang) . '</td>';
			echo '<td>' . get_lang_str($lang) . '</td>';
			
			switch ($status)
			{
				case MAILING_WAITING:
					echo '<td>' . get_label('waiting') . '</td><td>&nbsp;</td>';
					break;
				case MAILING_SENDING:
					echo '<td>' . get_label('sending') . '</td><td>' . $send_count . '</td>';
					break;
				case MAILING_COMPLETE:
					echo '<td>' . get_label('complete') . '</td><td>' . $send_count . '</td>';
					break;
				case MAILING_CANCELED:
					echo '<td>' . get_label('canceled') . '</td><td>&nbsp;</td>';
					break;
			}
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run('Mailing', UC_PERM_MANAGER);

?>
