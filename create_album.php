<?php

require_once 'include/page_base.php';
require_once 'include/photo_album.php';
require_once 'include/event.php';
require_once 'include/club.php';

class Page extends PageBase
{
	private $album;
	
	protected function prepare()
	{
		check_permissions(PERMISSION_USER);
		
		$this->album = new PhotoAlbum();
		$this->album->get_data();
		if (isset($_POST['create']))
		{
			redirect_back('album=' . $this->album->create());
		}
		else if (isset($_POST['cancel']))
		{
				redirect_back();
		}
	}
	
	private function show_event_option($row)
	{
		$result = false;
		list ($id, $name, $timestamp, $timezone) = $row;
		echo '<option value="' . $id . '"';
		if ($id == $this->album->event_id)
		{
			$result = true;
			echo ' selected';
		}
		echo '>' . $name . ': ' . format_date($timestamp, $timezone) . '</option>';
		return $result;
	}
	
	protected function show_body()
	{
		global $_profile;
	
		echo '<form method="post" name="createForm">';
		echo '<table class="bordered" width="100%">';
		echo '<tr><td width="80">'.get_label('Album name').':</td><td><input name="name" value="' . htmlspecialchars($this->album->name, ENT_QUOTES) . '" size="50"></td></tr>';
		
		echo '<tr><td>' . get_label('Club') . ':</td><td>';
		echo '<select name="club" onChange="document.createForm.submit()">';
		foreach($_profile->clubs as $club)
		{
			show_option($club->id, $this->album->club_id, $club->name);
		}
		echo '</select>';
		echo '</td></tr>';
		
		$event_found = false;
		echo '<tr><td>'.get_label('Event').':</td><td>';
		$query = new DbQuery(
			'SELECT e.id, e.name, e.start_time, c.timezone FROM events e' .
				' JOIN addresses a ON e.address_id = a.id' .
				' JOIN cities c ON a.city_id = c.id' .
				' WHERE e.club_id = ? AND e.start_time <= UNIX_TIMESTAMP() AND (e.flags & ' . EVENT_FLAG_CANCELED . ') = 0' .
				' ORDER BY e.start_time DESC LIMIT 50',
			$this->album->club_id);
		echo '<select name="event" onChange="onSelectEvent()">';
		echo '<option value="-1"></option>';
		while ($row = $query->next())
		{
			if ($this->show_event_option($row))
			{
				$event_found = true;
			}
		}
		if (!$event_found && $this->album->event_id != NULL)
		{
			$query = new DbQuery(
				'SELECT e.id, e.name, e.start_time, c.timezone FROM events e' .
				' JOIN addresses a ON e.address_id = a.id' .
				' JOIN cities c ON a.city_id = c.id' .
				' WHERE e.id = ? AND e.club_id = ?', $this->album->event_id, $this->album->club_id);
			while ($row = $query->next())
			{
				$this->show_event_option($row);
			}
		}
		echo '</select></td></tr>';
		
		$for_options = array(get_label('Everyone'), $this->album->club_name, get_label('Me and [0] managers', $this->album->club_name), get_label('Me only'));
		
		echo '<tr><td>'.get_label('Who can view').':</td><td><select name="viewers" onChange="document.createForm.submit()">';
		for ($i = 0; $i < 4; ++$i)
		{
			show_option($i, $this->album->viewers, $for_options[$i]);
		}
		echo '</select></td></tr>';
		
		echo '<tr><td>'.get_label('Who can add photos').':</td><td><select name="adders">';
		for ($i = $this->album->viewers; $i < 4; ++$i)
		{
			show_option($i, $this->album->adders, $for_options[$i]);
		}
		echo '</select></td></tr>';
		
		echo '</table>';
		echo '<p><input type="submit" class="btn norm" value="'.get_label('Create [0]', get_label('album')).'" name="create"><input type="submit" class="btn norm" value="'.get_label('Cancel').'" name="cancel"></p>';
		echo '</form>';
	}
}

$page = new Page();
$page->run(get_label('New photo album'));

?>

<script>
<!--

	function onSelectEvent()
	{
		var eventSelect = document.createForm.event;
		var nameInput = document.createForm.name;
		var index = eventSelect.selectedIndex;
		nameInput.value = eventSelect.options[index].text;
	}
	onSelectEvent();
//-->
</script>
