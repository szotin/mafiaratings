<?php

require_once 'include/page_base.php';
require_once 'include/event.php';
require_once 'include/email_template.php';
require_once 'include/languages.php';
require_once 'include/event_mailing.php';
require_once 'include/editor.php';

class Page extends PageBase
{
	private $events_str;
	private $events;
	private $event;
	
	private $send_time;
	private $subj;
	private $body;
	private $name;
	private $lang;
	private $flags;
	private $template_id;

	protected function prepare()
	{
		global $_profile;
		if (isset($_POST['cancel']))
		{
			redirect_back();
			return;
		}
		
		if (!isset($_REQUEST['events']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('event')));
		}
		
		$this->events_str = $_REQUEST['events'];
		$this->events = explode(',', $this->events_str);
		$events_count = count($this->events);
		if ($events_count == 0)
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('event')));
		}

		$this->template_id = 0;
		if (isset($_REQUEST['tid']))
		{
			$this->template_id = $_REQUEST['tid'];
		}
		
		$this->send_time = 3;
		if (isset($_POST['send_time']))
		{
			$this->send_time = $_POST['send_time'];
		}
		
		$this->subj = '';
		if (isset($_POST['subj']))
		{
			$this->subj = $_POST['subj'];
		}
		
		$this->body = '';
		if (isset($_POST['body']))
		{
			$this->body = $_POST['body'];
		}
		
		$this->name = '';
		if (isset($_POST['name']))
		{
			$this->name = $_POST['name'];
		}
		
		$this->lang = $_profile->user_def_lang;
		$this->flags = MAILING_FLAG_AUTODETECT_LANG | MAILING_FLAG_TO_ALL;
		if (isset($_POST['lang']))
		{
			$this->lang = $_POST['lang'];
			
			$this->flags = $_POST['lang_to'];
			$this->flags += isset($_POST['autodetect']) ? MAILING_FLAG_AUTODETECT_LANG : 0;
			$this->flags += isset($_POST['to_attended']) ? MAILING_FLAG_TO_ATTENDED : 0;
			$this->flags += isset($_POST['to_declined']) ? MAILING_FLAG_TO_DECLINED : 0;
			$this->flags += isset($_POST['to_desiding']) ? MAILING_FLAG_TO_DESIDING : 0;
		}
		
		if (isset($_POST['send']))
		{
			create_event_mailing($this->events, $this->body, $this->subj, $this->send_time, $this->lang, $this->flags);
			redirect_back();
		}
		else if (isset($_POST['overwrite']))
		{
			update_template($this->template_id, $this->name, $this->subj, $this->body, -1);
		}
		else if (isset($_POST['copy']))
		{
			$template_id = $_POST['copy'];
			if ($template_id > 0)
			{
				$this->template_id = $template_id;
				list ($this->name, $this->subj, $this->body) =
					Db::record(get_label('email template'), 'SELECT name, subject, body FROM email_templates WHERE id = ?', $template_id);
			}
		}
		
		$this->event = new Event();
		$this->event->load($this->events[0]);
		check_permissions(PERMISSION_CLUB_MANAGER, $this->event->club_id);
		
		if (isset($_REQUEST['for']))
		{
			$query = new DbQuery('SELECT id, name, subject, body FROM email_templates WHERE club_id = ? AND default_for = ? ORDER BY id DESC', $this->event->club_id, $_REQUEST['for']);
			if ($row = $query->next())
			{
				list($this->template_id, $this->name, $this->subj, $this->body) = $row;
			}
		}
		
		if ($_profile == NULL || !$_profile->is_club_manager($this->event->club_id))
		{
			throw new FatalExc(get_label('No permissions'));
		}
	
		if ($events_count == 1)
		{
			$this->_title = get_label('Create mailing for [0]', $this->event->get_full_name());
		}
		else
		{
			$this->_title = get_label('Create mailing for [0] and [1] other events', $this->event->get_full_name(), $events_count);
		}
	}
	
	protected function show_body()
	{
		global $_profile;
		
		if (isset($_POST['save']))
		{
			if ($this->template_id > 0)
			{
				echo '<form method="post" name="overwriteForm" action="create_event_mailing.php">';
				echo '<input type="hidden" name="events" value="' . $this->events_str . '">';
				echo '<input type="hidden" name="name" value="' . $this->name . '">';
				echo '<input type="hidden" name="subj" value="' . $this->subj . '">';
				echo '<input type="hidden" name="body" value="' . htmlspecialchars($this->body, ENT_QUOTES) . '">';
				echo '<input type="hidden" name="send_time" value="' . $this->send_time . '">';
				echo '<input type="hidden" name="tid" value="' . $this->template_id . '">';
				
				echo '<p>' . get_label('Saved email [0] already exists. Do you want to overwrite it?', $this->name) . '</p>';
				echo '<p><input type="submit" class="btn norm" value="' . get_label('Yes') . '" name="overwrite"><input type="submit" class="btn norm" value="' . get_label('No') . '"></p>';
				echo '</form>';
			}
			else
			{
				create_template($this->name, $this->subj, $this->body, $this->event->club_id);
			}
		}
		
		echo '<form method="post" name="createForm" action="create_event_mailing.php">';
		echo '<input type="hidden" name="events" value="' . $this->events_str . '">';
		if ($this->template_id > 0)
		{
			echo '<input type="hidden" name="tid" value="' . $this->template_id . '">';
		}
		
		list($p_body, $p_subj, $detected_lang) = $this->event->parse_sample_email($_profile->user_email, $this->body, $this->subj);
		if (($this->flags & MAILING_FLAG_AUTODETECT_LANG) != 0)
		{
			$this->lang = $detected_lang;
		}
		
		echo '<table class="transp" width="100%"><tr><td>';
		$query = new DbQuery('SELECT id, name FROM email_templates WHERE club_id = ? ORDER BY name', $this->event->club_id);
		echo get_label('Copy from a saved email') . ': <select name="copy" onChange = "document.createForm.submit()"><option value="0"></option>';
		while ($row = $query->next())
		{
			echo '<option value="' . $row[0] . '">' . $row[1] . '</option>';
		}
		echo '</select></td><td align="right">';
		echo get_label('Save email as') . ': <input name="name" value="' . $this->name . '"><input type="submit" value="' . get_label('Save') . '" class="btn norm" name="save"></td><tr></table>';
	
		echo '<table class="bordered light" width="100%">';
		if (isset($_POST['overwrite']))
		{
			echo '<tr><td colspan="2" class="lighter">' . get_label('The email is saved as [0]', $this->name) . '</td></tr>';
		}
		else if (isset($_REQUEST['msg']))
		{
			$events_count = count($this->events);
			switch ($_REQUEST['msg'])
			{
				case 0:
					if ($events_count == 1)
					{
						echo '<tr><td colspan="2" class="lighter">' . get_label('You have created the event.<br>Do you want to send emails about it?') . '</td></tr>';
					}
					else
					{
						echo '<tr><td colspan="2" class="lighter">' . get_label('You have created [0] events.<br>Do you want to send emails about it?', $events_count) . '</td></tr>';
					}
					break;
				case 1:
					echo '<tr><td colspan="2" class="lighter">' . get_label('You have created the event.<br>Do you want to send emails about it?') . '</td></tr>';
					break;
				case 2:
					echo '<tr><td colspan="2" class="lighter">' . get_label('You have restored the event.<br>Do you want to send emails about it?') . '</td></tr>';
					break;
			}
		}
		
		
		echo '<tr><td class="dark" width="100" valign="top">' . get_label('Send time') . ':</td><td>';
		echo '<select name="send_time">';
		show_option(0, $this->send_time, get_label('As soon as possible'));
		for ($i = 1; $i <= 30; ++$i)
		{
			show_option($i, $this->send_time, get_label('[0] days before the event', $i));
		}
		echo '</select></td></tr>';
		
		echo '<tr><td class="dark" valign="top">' . get_label('To') . ':</td><td>';
		echo '<input type="checkbox" name="to_attended"' . (($this->flags & MAILING_FLAG_TO_ATTENDED)?' checked':'') . '> ' . get_label('to attending players');
		echo ' <input type="checkbox" name="to_declined"' . (($this->flags & MAILING_FLAG_TO_DECLINED)?' checked':'') . '> ' . get_label('to declined players');
		echo ' <input type="checkbox" name="to_desiding"' . (($this->flags & MAILING_FLAG_TO_DESIDING)?' checked':'') . '> ' . get_label('to other players');
		echo '<br><select name="lang_to">';
		echo '<option value="0"' . (($this->flags & MAILING_FLAG_LANG_MASK) == 0 ? ' selected' : '') . '>' . get_label('To players with any language') . '</option>';
		echo '<option value="' . MAILING_FLAG_LANG_TO_SET_ONLY . '"' . (($this->flags & MAILING_FLAG_LANG_TO_SET_ONLY) ? ' selected' : '') . '>' . get_label('To players who understand [0]', get_lang_str($this->lang)) . '</option>';
		echo '<option value="' . MAILING_FLAG_LANG_TO_DEF_ONLY . '"' . (($this->flags & MAILING_FLAG_LANG_TO_DEF_ONLY) ? ' selected' : '') . '>' . get_label('To players with [0] as a default language.', get_lang_str($this->lang)) . '</option>';
		echo '</select></td></tr>';
		 
		echo '<tr><td class="dark" valign="top">' . get_label('Subject') . ':</td><td><input name="subj" value="' . htmlspecialchars($this->subj, ENT_QUOTES) . '"></td></tr>';
		
		echo '<tr><td class="dark" valign="top">' . get_label('Body') . ':</td><td>';
		show_single_editor('body', $this->body, event_tags());
		echo '</td></tr>';
		
		echo '<tr><td class="dark" valign="top">' . get_label('Language') . ':</td>';
		echo '<td><input type="checkbox" name="autodetect"';
		if (($this->flags & MAILING_FLAG_AUTODETECT_LANG) != 0)
		{
			echo ' checked';
		}
		echo '> ' . get_label('Auto-detect') . ' ';
		show_lang_select('lang', $this->lang);
		echo '</td></tr>';
		
		echo '<tr><td class="dark" valign="top">' . get_label('Preview') . ':</td><td align="right">';
		echo '<table class="bordered lighter" width="100%">';
		echo '<tr class="darker"><td>' . $p_subj . '</td>';
		echo '<td align="center" width="16"><button type="submit" class="icon" title="' . get_label('Refresh preview') . '"><img src="images/refresh.png"></button></td></tr>';
		echo '<tr><td colspan="2">' . $p_body . '</td></tr>';
		echo '</table></td></tr>';
		echo '</table>';
		
		echo '<input type="submit" class="btn norm" value="'.get_label('Create').'" name="send">';
		echo '<input type="submit" class="btn norm" value="'.get_label('Do not send').'" name="cancel">';
		echo '</form>';
	}
}

$page = new Page();
$page->run(get_label('Create mailing'));

?>
