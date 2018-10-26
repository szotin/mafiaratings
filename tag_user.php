<?php

require_once 'include/page_base.php';
require_once 'include/image.php';
require_once 'include/pages.php';

define("PAGE_SIZE", 20);

class Page extends PageBase
{
	private $id;

	protected function show_title()
	{
		$picture_width = CONTENT_WIDTH / PHOTO_COL_COUNT - 20;
		echo '<table class="head" width="100%"><tr>';
		echo '<td valign="top" width="' . $picture_width . '"><img src="' . PHOTOS_DIR . TNAILS_DIR . $this->id . '.jpg" width="' . $picture_width . '" border="0"></td>';
		echo '<td valign="top">' . $this->standard_title() . '</td><td align="right" valign="top">'; 
		show_back_button();
		echo '</td></tr></table>';
	}
	
	protected function prepare()
	{
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('photo')));
		}
		$this->id = $_REQUEST['id'];
		check_permissions(PERMISSION_USER);
	}
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		$my_id = -1;
		if ($_profile != NULL)
		{
			$my_id = $_profile->user_id;
		}

		$filter = '';
		$event_only = true;
		if (isset($_REQUEST['filter']))
		{
			$filter = $_REQUEST['filter'];
			$event_only = isset($_REQUEST['event_only']);
		}
		
		$link_str = 'photo.php?id=' . $this->id;
		$input_str = '';
		$event_id = NULL;
		if (isset($_REQUEST['album']))
		{
			$album_id = $_REQUEST['album'];
			$link_str .= '&album=' . $album_id;
			$input_str = '<input type="hidden" name="album" value="' . $album_id . '">';
		}
		else if (isset($_REQUEST['event']))
		{
			$event_id = $_REQUEST['event'];
			$link_str .= '&event=' . $event_id;
			$input_str = '<input type="hidden" name="event" value="' . $event_id . '">';
		}
		else if (isset($_REQUEST['user']))
		{
			$user_id = $_REQUEST['user'];
			$link_str .= '&user=' . $user_id;
			$input_str = '<input type="hidden" name="user" value="' . $user_id . '">';
		}
		$link_str .= '&tag=';
		
		if ($event_id == NULL)
		{
			list ($event_id) = (new DbQuery('SELECT a.event_id FROM photos p, photo_albums a WHERE p.album_id = a.id AND p.id = ?', $this->id))->next();
		}
		
		echo '<p><form method="get" name="filterForm" action="tag_user.php">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">' . $input_str;
		echo get_label('Filter').':&nbsp;<input name="filter" value="' . $filter . '" onChange="document.filterForm.submit()">';
		if ($event_id != NULL)
		{
			echo '&nbsp;<input type="checkbox" name="event_only" onClick="document.filterForm.submit()"' . ($event_only ? ' checked' : '') . '>';
			echo ' '.get_label('show only the users registered for the event');
		}
		echo '</form>';
		
		if ($event_id != NULL && $event_only)
		{
			$from_str = new SQL('FROM users u, registrations r WHERE r.user_id = u.id AND r.event_id = ? AND ', $event_id);
		}
		else
		{
			$from_str = new SQL('FROM users u WHERE ');
		}
		$from_str->add('u.id NOT IN (SELECT user_id FROM user_photos WHERE photo_id = ?)', $this->id);
		if ($filter != '')
		{
			$from_str->add(' AND u.name LIKE ?', $filter . '%');
		}
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(*) ', $from_str);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered" width="100%">';
		echo '<tr class="darker"><td>'.get_label('Player').'</td></tr>';
		
		$query = new DbQuery('SELECT u.id, u.name ', $from_str);
		$query->add(' ORDER BY u.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			list ($u_id, $u_name) = $row;
		
			if ($u_id == $my_id)
			{
				echo '<tr class="lighter">';
			}
			else
			{
				echo '<tr class="light">';
			}
			echo '<td><a href="' . $link_str . $u_id . '">' . cut_long_name($u_name, 80) . '</a></td></tr>';
		}
		echo '</table>';
		echo '<form method="get" name="form" action="photo.php">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">' . $input_str;
		echo '<input type="submit" class="btn norm" value="'.get_label('Cancel').'">';
		echo '</form>';
	}
}

$page = new Page();
$page->run(get_label('Tag player'));

?>