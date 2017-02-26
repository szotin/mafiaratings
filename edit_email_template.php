<?php

require_once 'include/page_base.php';
require_once 'include/email.php';
require_once 'include/message.php';
require_once 'include/email_template.php';
require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/editor.php';

class Page extends PageBase
{
	private $id;
	private $name;
	private $subj;
	private $body;
	private $club_id;
	
	protected function prepare()
	{
		global $_profile;
		
		if (isset($_POST['cancel']))
		{
			redirect_back();
			return;
		}
		
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('email template')));
		}
		$this->id = $_REQUEST['id'];
		
		list($this->name, $this->subj, $this->body, $this->club_id) = 
			Db::record(get_label('email template'), 'SELECT name, subject, body, club_id FROM email_templates WHERE id = ?', $this->id);
			
		if ($_profile == NULL || !$_profile->is_manager($this->club_id))
		{
			throw new FatalExc(get_label('No permissions'));
		}
		
		if (isset($_POST['name']))
		{
			$this->name = $_POST['name'];
			$this->subj = $_POST['subj'];
			$this->body = $_POST['body'];
		}
		
		if (isset($_POST['update']))
		{
			update_template($this->id, $this->name, $this->subj, $this->body);
			redirect_back();
		}
	}
	
	protected function show_body()
	{
		global $_profile;
		
		echo '<form method="post" name="updateForm" action="edit_email_template.php">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		
		echo '<table class="bordered" width="100%">';
		echo '<td width="100">' . get_label('Name') . ':</td><td><input name="name" value="' . htmlspecialchars($this->name, ENT_QUOTES) . '"></td></tr>';
		
		echo '<tr><td valign="top">' . get_label('Subject') . ':</td><td><input name="subj" value="' . htmlspecialchars($this->subj, ENT_QUOTES) . '"></td></tr>';
		
		echo '<tr><td valign="top">' . get_label('Body') . ':</td><td>';
		show_single_editor('body', $this->body, event_tags());
		echo '</td></tr>';
		
		echo '<tr><td valign="top">' . get_label('Preview') . ':</td><td align="right">';
			
		$club = $_profile->clubs[$this->club_id];
			
		$event = new Event();
		$event->id = 0;
		$event->name = get_label('My event');
		$event->timestamp = time();
		$event->timezone = $_profile->timezone;
		$event->duration = 6 * 3600;
		$event->addr_id = 0;
		$event->addr = get_label('111 My Street, My City, My Country');
		$event->addr_url = 'http://maps.google.com/maps?f=q&source=s_q&hl=en&geocode=&q=White+House,+Washington+D.C.,+DC,+United+States&aq=&sll=38.900201,-77.040317&sspn=0.012157,0.021865&ie=UTF8&hq=White+House,+Washington+D.C.,+DC,+United+States&hnear=White+House,+1600+Pennsylvania+Ave+NW,+Washington+D.C.,+District+of+Columbia+20500-0004&ll=38.897346,-77.037935&spn=0.024315,0.060081&z=15';
		$event->addr_flags = (1 << ADDR_ICON_MASK_OFFSET);
		$event->club_id = 0;
		$event->club_name = $club->name;
		$event->notes = get_label('my event notes');
		$event->langs = $club->langs;
		
		list($b, $s, $lang) = $event->parse_sample_email($_profile->user_email, $this->body, $this->subj);
			
		echo '<table class="bordered" width="100%">';
		echo '<tr class="darker"><td>' . $s . '</td><td width="120">' . get_label('Language') . ': ' . get_lang_str($lang) . '</td>';
		echo '<td align="center" width="16"><button type="submit" class="icon" title="' . get_label('Refresh preview') . '"><img src="images/refresh.png"></button></td></tr>';
		echo '<tr><td colspan="3">' . $b . '</td></tr></table></td></tr>';
		
		echo '</table>';
		
		echo '<p><input type="submit" class="btn norm" value="'.get_label('Update').'" name="update">';
		echo '<input type="submit" class="btn norm" value="'.get_label('Cancel').'" name="cancel"></p>';
		echo '</form>';
	}
}

$page = new Page();
$page->run(get_label('Edit email template'), UC_PERM_MANAGER);

?>
