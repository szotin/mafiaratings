<?php

require_once 'include/page_base.php';
require_once 'include/club.php';

class Page extends PageBase
{
	protected function show_body()
	{
		check_permissions(PERMISSION_USER);
		
		$fix = isset($_REQUEST['fix']);
		
		if ($fix)
		{
			Db::begin();
		}
		
		$query = new DbQuery('SELECT e.id, e.name, e.subject, e.body, c.id, c.name, c.flags FROM email_templates e JOIN clubs c ON e.club_id = c.id');
		$rows = array();
		while ($row = $query->next())
		{
			$rows[] = $row;
		}
		
		echo '<p><a href="fix_templates.php?fix">Fix</a></p>';
		
		echo '<table class="bordered light" width="100%">';
		foreach ($rows as $row)
		{
			list($id, $name, $old_subject, $old_body, $club_id, $club_name, $club_flags) = $row;
			
			$body = str_replace('[ename]', '[event_name]', $old_body);
			$body = str_replace('[eid]', '[event_id]', $body);
			$body = str_replace('[edate]', '[event_date]', $body);
			$body = str_replace('[etime]', '[event_time]', $body);
			$body = str_replace('[addr]', '[address]', $body);
			$body = str_replace('[aurl]', '[address_url]', $body);
			$body = str_replace('[aid]', '[address_id]', $body);
			$body = str_replace('[aimage]', '[address_image]', $body);
			$body = str_replace('[uname]', '[user_name]', $body);
			$body = str_replace('[uid]', '[user_id]', $body);
			$body = str_replace('[cname]', '[club_name]', $body);
			$body = str_replace('[cid]', '[club_id]', $body);
			
			$subject = str_replace('[ename]', '[event_name]', $old_subject);
			$subject = str_replace('[eid]', '[event_id]', $subject);
			$subject = str_replace('[edate]', '[event_date]', $subject);
			$subject = str_replace('[etime]', '[event_time]', $subject);
			$subject = str_replace('[addr]', '[address]', $subject);
			$subject = str_replace('[aurl]', '[address_url]', $subject);
			$subject = str_replace('[aid]', '[address_id]', $subject);
			$subject = str_replace('[aimage]', '[address_image]', $subject);
			$subject = str_replace('[uname]', '[user_name]', $subject);
			$subject = str_replace('[uid]', '[user_id]', $subject);
			$subject = str_replace('[cname]', '[club_name]', $subject);
			$subject = str_replace('[cid]', '[club_id]', $subject);
		
			if ($fix)		
			{
				Db::exec('email', 'UPDATE email_templates SET subject = ?, body = ? WHERE id = ?', $subject, $body, $id);
			}
			
			echo '<tr>';
			echo '<td width="50" valign="center">';
			show_club_pic($club_id, $club_name, $club_flags, ICONS_DIR, 40, 40);
			echo '</td><td width="120" valign="center">' . $name . '</td>';
			echo '<td>Subject: ' . $old_subject . '<hr>' . $old_body . '</td>';
			echo '<td>Subject: ' . $subject . '<hr>' . $body . '</td>';
			echo '</tr>';
		}
		echo '</table>';
		
		if ($fix)
		{
			Db::commit();
		}
	}
}

$page = new Page();
$page->run('Fix email templates');

?>