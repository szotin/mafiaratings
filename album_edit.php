<?php

require_once 'include/photo_album.php';
require_once 'include/event.php';

class Page extends AlbumPageBase
{
	protected function prepare()
	{
		if (isset($_REQUEST['cancel']))
		{
			redirect_back();
			return;
		}
		
		parent::prepare();
		check_permissions(PERMISSION_USER);
		$this->album->get_data();
		if (isset($_POST['update']))
		{
			$this->album->update();
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
	
		echo '<table class="transp" width="100%"><tr><td align="right" valign="center"></td><td height="52" width="60">';
		start_upload_logo_button($this->album->id);
		echo get_label('Change logo') . '<br>';
		end_upload_logo_button(ALBUM_PIC_CODE, $this->album->id);
		echo '</td></tr></table>';
	
		echo '<form method="post" name="updateForm" action="album_edit.php">';
		echo '<input type="hidden" name="id" value="' . $this->album->id . '">';
		echo '<table class="bordered" width="100%">';
		echo '<tr><td class="dark" width="200">'.get_label('Album name').':</td><td class="light"><input name="name" value="' . htmlspecialchars($this->album->name, ENT_QUOTES) . '" size="50"></td></tr>';
		
		echo '<tr><td class="dark">' . get_label('Club') . ':</td><td class="light">';
		echo '<select name="club" onChange="document.updateForm.submit()">';
		foreach($_profile->clubs as $club)
		{
			show_option($club->id, $this->album->club_id, $club->name);
		}
		echo '</select>';
		echo '</td></tr>';
		
		$event_found = false;
		$query = new DbQuery(
			'SELECT e.id, e.name, e.start_time, c.timezone FROM events e' .
				' JOIN addresses a ON e.address_id = a.id' .
				' JOIN cities c ON a.city_id = c.id' .
				' WHERE e.club_id = ? AND e.start_time <= UNIX_TIMESTAMP() AND (e.flags & ' . (EVENT_FLAG_CANCELED | EVENT_FLAG_HIDDEN_AFTER) .  ') = 0' .
				' ORDER BY e.start_time DESC LIMIT 50',
			$this->album->club_id);
		echo '<tr><td class="dark">'.get_label('Event').':</td><td class="light"><select name="event" onChange="onSelectEvent()">';
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
			$query->set(
				'SELECT e.id, e.name, e.start_time, c.timezone FROM events e' .
					' JOIN addresses a ON e.address_id = a.id' .
					' JOIN cities c ON a.city_id = c.id' .
					' WHERE e.id = ? AND e.club_id = ?',
				$this->album->event_id, $this->album->club_id);
			while ($row = $query->next())
			{
				$this->show_event_option($row);
			}
		}
		echo '</select></td></tr>';
		
		$for_options = array(get_label('Everyone'), $this->album->club_name, get_label('Me and [0] managers', $this->album->club_name), get_label('Me only'));
		
		echo '<tr><td class="dark">'.get_label('Who can view').':</td><td class="light"><select name="viewers" onChange="document.updateForm.submit()">';
		for ($i = 0; $i < 4; ++$i)
		{
			show_option($i, $this->album->viewers, $for_options[$i]);
		}
		echo '</select></td></tr>';
		
		echo '<tr><td class="dark">'.get_label('Who can add photos').':</td><td class="light"><select name="adders">';
		for ($i = $this->album->viewers; $i < 4; ++$i)
		{
			show_option($i, $this->album->adders, $for_options[$i]);
		}
		echo '</select></td></tr>';
		
		echo '</table>';
		echo '<p><input type="submit" class="btn norm" value="'.get_label('Change album').'" name="update"><input type="submit" class="btn norm" value="' . get_label('Cancel') . '" name="cancel"></p>';
		echo '</form>';
	}
	
	protected function js()
	{
?>		
		function onSelectEvent()
		{
			var enentSelect = document.createForm.event;
			var nameInput = document.createForm.name;
			var index = enentSelect.selectedIndex;
			nameInput.value = enentSelect.options[index].text;
		}
		
		function uploadLogo(albumId, onSuccess)
		{
			json.upload('api/ops/album.php', 
			{
				op: "change",
				album_id: albumId,
				logo: document.getElementById("upload").files[0]
			}, 
			<?php echo UPLOAD_LOGO_MAX_SIZE; ?>, 
			onSuccess);
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Edit photo album'));

?>