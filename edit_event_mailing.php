<?php

require_once 'include/page_base.php';
require_once 'include/event.php';
require_once 'include/email_template.php';
require_once 'include/event_mailing.php';
require_once 'include/editor.php';

class Page extends PageBase
{
	private $id;
	private $subj;
	private $body;
	private $lang;
	private $name;
	private $flags;
	private $event;
	
	private $hour;
	private $minute;
	private $day;
	private $year;
	private $month;
	
	private function parse_event_time($timestamp, $timezone)
	{
		date_default_timezone_set($timezone);
		$this->day = date('j', $timestamp);
		$this->month = date('n', $timestamp);
		$this->year = date('Y', $timestamp);
		$this->hour = date('G', $timestamp);
		$this->minute = round(date('i', $timestamp) / 10) * 10;
	}

	protected function prepare()
	{
		global $_profile;
		
		if (isset($_POST['cancel']))
		{
			redirect_back();
		}
		
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('event mailing')));
		}
		$this->id = $_REQUEST['id'];
		$send_time = time();
		$this->subj = '';
		$this->body = '';
		$this->lang = $_profile->user_def_lang;
		$this->flags = MAILING_FLAG_AUTODETECT_LANG | MAILING_FLAG_TO_ALL;;
		$this->hour = 0;
		$this->minute = 0;
		$this->day = 0;
		$this->year = 0;
		$this->month = 0;
		$this->name = '';
		
		list($event_id, $send_time, $this->subj, $this->body, $this->lang, $this->flags) =
			Db::record(get_label('email'), 'SELECT event_id, send_time, subject, body, lang, flags FROM event_emails WHERE id = ?', $this->id);
			
		$this->event = new Event();
		$this->event->load($event_id);
		if ($_profile == NULL || !$_profile->is_manager($this->event->club_id))
		{
			throw new FatalExc(get_label('No permissions'));
		}
			
		$this->parse_event_time($send_time, $_profile->timezone);
		
		if (isset($_POST['subj']))
		{
			$this->subj = $_POST['subj'];
			$this->body = $_POST['body'];
			$this->hour = $_POST['hour'];
			$this->minute = $_POST['minute'];
			$this->day = $_POST['day'];
			$this->year = $_POST['year'];
			$this->month = $_POST['month'];
			$this->lang = $_POST['lang'];
			$this->name = $_POST['name'];
			
			$this->flags = $_POST['lang_to'];
			$this->flags += isset($_POST['autodetect']) ? MAILING_FLAG_AUTODETECT_LANG : 0;
			$this->flags += isset($_POST['to_attended']) ? MAILING_FLAG_TO_ATTENDED : 0;
			$this->flags += isset($_POST['to_declined']) ? MAILING_FLAG_TO_DECLINED : 0;
			$this->flags += isset($_POST['to_desiding']) ? MAILING_FLAG_TO_DESIDING : 0;
			
			date_default_timezone_set($_profile->timezone);
			$send_time = mktime($this->hour, $this->minute, 0, $this->month, $this->day, $this->year);
		}

		if (isset($_POST['update']))
		{
			update_event_mailing($this->id, $this->body, $this->subj, $send_time, $this->lang, $this->flags);
			redirect_back();
		}
		else if (isset($_POST['overwrite']))
		{
			update_template($_POST['tid'], $this->name, $this->subj, $this->body);
		}
		else if (isset($_POST['copy']))
		{
			$template_id = $_POST['copy'];
			if ($template_id > 0)
			{
				list ($this->name, $this->subj, $this->body) =
					Db::record(get_label('email template'), 'SELECT name, subject, body FROM email_templates WHERE id = ?', $template_id);
			}
		}
		$this->_title = get_label('Edit mailing for [0]', $this->event->get_full_name());
	}
	
	protected function show_body()
	{
		global $_profile;
		if (isset($_POST['save']))
		{
			$query = new DbQuery('SELECT id FROM email_templates WHERE name = ?', $this->name);
			if ($row = $query->next())
			{
				// not working fix with js
				echo '<form method="post">';
				echo '<input type="hidden" name="tid" value="' . $row[0] . '">';
				echo '<input type="hidden" name="id" value="' . $this->id . '">';
				echo '<input type="hidden" name="name" value="' . $this->name . '">';
				echo '<p>' . get_label('Saved email [0] already exists. Do you want to overwrite it?', $this->name) . '</p>';
				echo '<p><input type="submit" class="btn norm" value="' . get_label('Yes') . '" name="overwrite"><input type="submit" class="btn norm" value="' . get_label('No') . '"></p></form>';
			}
			else
			{
				create_template($this->name, $this->subj, $this->body, $this->event->club_id);
				$_message = get_label('The email is saved as [0]', $this->name);
			}
		}
			
		echo '<form method="post" name="updateForm" action="edit_event_mailing.php">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		
		list($p_body, $p_subj, $detected_lang) = $this->event->parse_sample_email($_profile->user_email, $this->body, $this->subj);
		if (($this->flags & MAILING_FLAG_AUTODETECT_LANG) != 0)
		{
			$this->lang = $detected_lang;
		}
		
		$query = new DbQuery('SELECT id, name FROM email_templates WHERE club_id = ? ORDER BY name', $this->event->club_id);
		echo '<table class="transp" width="100%"><tr><td>';
		echo get_label('Copy from a saved email') . ': <select name="copy" onChange = "document.updateForm.submit()"><option value="0"></option>';
		while ($row = $query->next())
		{
			echo '<option value="' . $row[0] . '">' . $row[1] . '</option>';
		}
		echo '</select></td><td align="right">';
		echo get_label('Save email as') . ': <input name="name" value="' . $this->name . '"><input type="submit" value="' . get_label('Save') . '" class="btn norm" name="save">';
		echo '</td><tr></table>';

		echo '<table class="bordered light" width="100%">';
		if (isset($_POST['overwrite']))
		{
			echo '<tr><td class="lighter" colspan="2">' . get_label('The email is saved as [0]', $this->name) . '</td></tr>';
		}
		echo '<tr><td class="dark" width="100">' . get_label('Send date') . ':</td><td>';
		show_date_controls($this->day, $this->month, $this->year);
		echo '</td></tr>';
		
		echo '<tr><td class="dark">' . get_label('Send time') . ':</td><td>';
		show_time_controls($this->hour, $this->minute);
		echo '</td></tr>';
		
		echo '<tr><td class="dark" valign="top">' . get_label('To') . ':</td><td>';
		echo '<input type="checkbox" name="to_attended"' . (($this->flags & MAILING_FLAG_TO_ATTENDED)?' checked':'') . '> ' . get_label('to attending players');
		echo ' <input type="checkbox" name="to_declined"' . (($this->flags & MAILING_FLAG_TO_DECLINED)?' checked':'') . '> ' . get_label('to declined players');
		echo ' <input type="checkbox" name="to_desiding"' . (($this->flags & MAILING_FLAG_TO_DESIDING)?' checked':'') . '> ' . get_label('to other players');
		echo '<br><select name="lang_to">';
		show_option(0, $this->flags & MAILING_FLAG_LANG_MASK, get_label('To players with any language'));
		show_option(MAILING_FLAG_LANG_TO_SET_ONLY, $this->flags & MAILING_FLAG_LANG_TO_SET_ONLY, get_label('To players who understand [0]', get_lang_str($this->lang)));
		show_option(MAILING_FLAG_LANG_TO_DEF_ONLY, $this->flags & MAILING_FLAG_LANG_TO_DEF_ONLY, get_label('To players with [0] as a default language.', get_lang_str($this->lang)));
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
		echo '> ' . get_label('Auto-detect') . ' <select name="lang">';
		
		$l = LANG_NO;
		while (($l = get_next_lang($l)) != LANG_NO)
		{
			show_option($l, $this->lang, get_lang_str($l));
		}
		echo '</select></td></tr>';
		
		echo '<tr><td class="dark" valign="top">' . get_label('Preview') . ':</td><td align="right">';
		echo '<table class="bordered lighter" width="100%">';
		echo '<tr class="darker"><td>' . $p_subj . '</td>';
		echo '<td align="center" width="16"><button type="submit" class="icon" title="' . get_label('Refresh preview') . '"><img src="images/refresh.png"></button></td></tr>';
		echo '<tr><td colspan="2">' . $p_body . '</td></tr>';
		echo '</table></td></tr>';
		
		echo '</table>';
		
		echo '<hr>';
		echo '<input type="submit" class="btn norm" value="'.get_label('Update').'" name="update">';
		echo '<input type="submit" class="btn norm" value="'.get_label('Cancel').'" name="cancel">';
		echo '</form>';
	}
}

$page = new Page();
$page->run(get_label('Edit mailing'), UC_PERM_MANAGER);

?>
