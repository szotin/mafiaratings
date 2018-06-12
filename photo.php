<?php

require_once 'include/page_base.php';
require_once 'include/image.php';
require_once 'include/photo_album.php';

define('MAX_WIDTH', 700);
define('MAX_HEIGHT', 600);

class Page extends PageBase
{
	private $id;
	private $viewers;
	private $owner_id;
	private $owner_name;
	private $album_id;
	private $album_name;
	private $event_id;
	private $album_owner_id;
	private $album_viewers;
	private $club_id;
	private $club_name;
	
	private $my_photo;
	private $link_str;
	private $filename;
	private $show_delete_confirm;
	
	private $next_id;
	private $prev_id;

	protected function prepare()
	{
		global $_profile;
		
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('photo')));
		}
		$this->id = $_REQUEST['id'];

		$this->my_photo = false;

		$this->album_id = -1;
		$this->event_id = -1;
		$this->link_str = '';
		$this->filename = PHOTOS_DIR . $this->id . '.jpg';
		$this->show_delete_confirm = false;
		
		$query_base = NULL;

		if (isset($_REQUEST['album']))
		{
			$this->album_id = $_REQUEST['album'];
			$query_base = new SQL('SELECT p.id FROM photos p JOIN photo_albums a ON p.album_id = a.id WHERE p.album_id = ?', $this->album_id);
			$this->link_str = '&album=' . $this->album_id;
		}
		else if (isset($_REQUEST['event']))
		{
			$this->event_id = $_REQUEST['event'];
			$query_base = new SQL('SELECT p.id FROM photos p JOIN photo_albums a ON p.album_id = a.id WHERE a.event_id = ?', $this->event_id);
			$this->link_str = '&event=' . $this->event_id;
		}
		else if (isset($_REQUEST['user']))
		{
			$user_id = $_REQUEST['user'];
			$query_base = new SQL('SELECT p.id FROM photos p JOIN user_photos u ON u.photo_id = p.id JOIN photo_albums a ON p.album_id = a.id WHERE u.tag = TRUE AND u.user_id = ?', $user_id);
			$this->link_str = '&user=' . $user_id;
			$row = Db::record(get_label('user'), 'SELECT name FROM users WHERE id = ?', $user_id);
			$this->_title = get_label('[0]: photo', $row[0]);
		}

		$this->next_id = -1;
		$this->prev_id = -1;
		if ($query_base != NULL)
		{
			$query_base->add(' AND ', PhotoAlbum::photo_viewers_condition());
			$query = new DbQuery($query_base);
			$query->add(' AND p.id > ? ORDER BY p.id LIMIT 1', $this->id);
			if ($row = $query->next())
			{
				$this->prev_id = $row[0];
			}

			$query->set($query_base);
			$query->add(' AND p.id < ? ORDER BY p.id DESC LIMIT 1', $this->id);
			if ($row = $query->next())
			{
				$this->next_id = $row[0];
			}
		}
			
		if ($_profile != NULL)
		{
			if (isset($_REQUEST['tag_player']))
			{
				throw new RedirectExc('tag_user.php?id=' . $this->id . $this->link_str);
			}
			
			if (isset($_REQUEST['tag_me']))
			{
				$query = new DbQuery('SELECT tag FROM user_photos WHERE user_id = ? AND photo_id = ?', $_profile->user_id, $this->id);
				if ($row = $query->next())
				{
					Db::exec(get_label('photo'), 'UPDATE user_photos SET tag = TRUE WHERE user_id = ? AND photo_id = ?', $_profile->user_id, $this->id);
					throw new RedirectExc('photo.php?id=' . $this->id . $this->link_str);
				}
				else
				{
					Db::exec(get_label('photo'), 'INSERT INTO user_photos (user_id, photo_id, email_sent, tag) VALUES (?, ?, true, true)', $_profile->user_id, $this->id);
					throw new RedirectExc('photo.php?id=' . $this->id . $this->link_str);
				}
				return;
			}
			
			if (isset($_REQUEST['untag_me']))
			{
				Db::exec(get_label('photo'), 'UPDATE user_photos SET tag = FALSE WHERE user_id = ? AND photo_id = ?', $_profile->user_id, $this->id);
				throw new RedirectExc('photo.php?id=' . $this->id . $this->link_str);
			}
			
			if (isset($_REQUEST['tag']))
			{
				$tag_id = $_REQUEST['tag'];
				if ($tag_id > 0)
				{
					Db::exec(get_label('photo'), 'INSERT INTO user_photos (user_id, photo_id, email_sent, tag) VALUES (?, ?, false, true)', $tag_id, $this->id);
					throw new RedirectExc('photo.php?id=' . $this->id . $this->link_str);
				}
			}
			
			if (isset($_REQUEST['action']))
			{
				list ($a_user_id, $p_user_id) = 
					Db::record(get_label('photo'), 'SELECT a.user_id, p.user_id FROM photos p, photo_albums a WHERE p.album_id = a.id AND p.id = ?', $this->id);
					
				if ($_profile->user_id != $a_user_id && $_profile->user_id != $p_user_id)
				{
					throw new Exc(get_label('No permissions'));
				}
				
				$action = $_REQUEST['action'];
				if ($action == -1)
				{
					$this->show_delete_confirm = true;
				}
				else if ($action >= 0)
				{
					$this->viewers = $action;
					Db::exec(get_label('photo'), 'UPDATE photos SET viewers = ? WHERE id = ?', $action, $this->id);
				}
			}
			else if (isset($_REQUEST['confirm_delete']))
			{
				list ($a_user_id, $p_user_id) = 
					Db::record(get_label('photo'), 'SELECT a.user_id, p.user_id FROM photos p, photo_albums a WHERE p.album_id = a.id AND p.id = ?', $this->id);
				
				if ($_profile->user_id != $a_user_id && $_profile->user_id != $p_user_id)
				{
					throw new Exc(get_label('No permissions'));
				}
				
				$this->delete_photo();
				if ($this->next_id > 0)
				{
					throw new RedirectExc('photo.php?id=' . $this->next_id . $this->link_str);
				}
				else if ($this->prev_id > 0)
				{
					throw new RedirectExc('photo.php?id=' . $this->prev_id . $this->link_str);
				}
				redirect_back();
			}
		}
		
		list(
			$this->owner_id, $this->owner_name, $this->viewers,
			$this->album_id, $this->album_name, $this->event_id, $this->album_owner_id, $this->album_viewers,
			$this->club_id, $this->club_name) = 
				Db::record(
					get_label('photo'), 
					'SELECT u.id, u.name, p.viewers, a.id, a.name, a.event_id, a.user_id, a.viewers, c.id, c.name FROM photos p' . 
						' JOIN users u ON p.user_id = u.id' .
						' JOIN photo_albums a ON p.album_id = a.id' .
						' JOIN clubs c ON c.id = a.club_id' .
						' WHERE p.id = ? AND ',
					$this->id, PhotoAlbum::photo_viewers_condition());
	}
	
	protected function show_body()
	{
		global $_profile;
		
		if ($this->show_delete_confirm)
		{
			echo '<form method="post" action="photo.php">';
			echo '<input type="hidden" name="id" value="' . $this->id . '">';
			echo '<p>'.get_label('Are you sure you want to delete this photo?').'</p>';
			echo '<input type="submit" name="confirm_delete" value="'.get_label('Yes').'" class="btn norm"><input type="submit" name="cancel" value="'.get_label('No').'" class="btn norm">';
			echo '</form>';
		}
		
		if (file_exists($this->filename))
		{
			$dims = getimagesize($this->filename);
			$width = $dims[0];
			$height = $dims[1];
		}
		else
		{
			$width = MAX_WIDTH;
			$height = MAX_HEIGHT;
		}
		
		echo '<table class="transp" width="100%"><tr>';
		echo '<td width="20" valign="center">';
		if ($this->prev_id > 0)
		{
			echo '<a href="photo.php?id=' . $this->prev_id . $this->link_str . '" title="'.get_label('Previous photo').'"><img src="images/prev.png" border="0"></a>';
		}
		echo '</td><td align="center">';
		
		if ($this->next_id > 0)
		{
			echo '<a href="photo.php?id=' . $this->next_id . $this->link_str . '" title="'.get_label('Next photo').'">';
		}
		echo '<img src="' . $this->filename . '" border="0"';
		if ($width > MAX_WIDTH)
		{
			$height *= MAX_WIDTH / $width;
			$width = MAX_WIDTH;
			if ($height > MAX_HEIGHT)
			{
				echo ' height="' . MAX_HEIGHT . '"';
			}
			else
			{
				echo ' width="' . MAX_WIDTH . '"';
			}
		}
		else if ($height > MAX_HEIGHT)
		{
			echo ' height="' . MAX_HEIGHT . '"';
		}
		echo '>';
		if ($this->next_id > 0)
		{
			echo '</a>';
		}
		echo '</td><td width="20" align="right" valign="center">';
		if ($this->next_id > 0)
		{
			echo '<a href="photo.php?id=' . $this->next_id . $this->link_str . '" title="'.get_label('Next photo').'"><img src="images/next.png" border="0"></a>';
		}
		echo '</td></tr></table>';
		
		echo '<form method="post" action="photo.php">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
				
		echo '<table class="bordered light" width="100%">';
		echo '<tr><td>' . get_label('In this photo') . ':</td><td>';
		echo '<table class="transp" width="100%"><tr><td valign="center">';
		$query = new DbQuery('SELECT u.id, u.name FROM user_photos p, users u WHERE p.tag = TRUE AND p.user_id = u.id AND p.photo_id = ?', $this->id);
		$delim = '';
		while ($row = $query->next())
		{
			list($user_id, $user_name) = $row;
			if ($_profile != NULL && $user_id == $_profile->user_id)
			{
				$this->my_photo = true;
			}
			echo $delim . '<a href="user_photos.php?id=' . $user_id . '&bck=1">' . $user_name . '</a>';
			$delim = ', ';
		}
		echo '</td>';
	
		if ($_profile != NULL)
		{
			echo '<td align="right">';
			echo '<input type="submit" value="'.get_label('Tag player').'" name="tag_player" class="btn norm"><br>';
			if ($this->my_photo)
			{
				echo '<input type="submit" value="'.get_label('Untag me').'" name="untag_me" class="btn norm"><br>';
			}
			else
			{
				echo '<input type="submit" value="'.get_label('Tag me').'" name="tag_me" class="btn norm"><br>';
			}
			echo '</td>';
		}
		
		echo '</tr></table></td></tr>';
		
		if ($_profile != NULL && ($this->owner_id == $_profile->user_id || $this->album_owner_id == $_profile->user_id || $_profile->is_admin()))
		{
			echo '<tr><td>'.get_label('Action').':</td><td><select name="action">';
			echo '<option value="-2"></option>';
			switch ($this->album_viewers)
			{
				case FOR_EVERYONE:
					if ($this->viewers != FOR_EVERYONE)
					{
						echo '<option value="' . FOR_EVERYONE . '">' . get_label('Make it visible to everyone') . '</option>';
					}
				case FOR_MEMBERS:
					if ($this->viewers != FOR_MEMBERS)
					{
						echo '<option value="' . FOR_MEMBERS . '">' . get_label('Make it visible to [0] members only', $this->club_name) . '</option>';
					}
				case FOR_MANAGERS:
				case FOR_USER:
					if ($this->viewers != FOR_USER)
					{
						echo '<option value="' . FOR_USER . '">' . get_label('Make it visible to me only') . '</option>';
					}
			}
			echo '<option value="-1">'.get_label('Delete it').'</option>';
			echo '</select> <input type="submit" value="Go" class="btn norm"></td></tr>';
		}
		echo '<tr>';
		if ($this->viewers != FOR_EVERYONE)
		{
			echo '<td width="160">'.get_label('Visible to').':</td><td>';
			switch ($this->viewers)
			{
				case FOR_MEMBERS:
					echo get_label('[0] members', $this->club_name);
					break;
				case FOR_MANAGERS:
					echo get_label('Me and [0] managers', $this->club_name);
					break;
				case FOR_USER:
					echo get_label('You only');
					break;
			}
			echo '</td></tr>';
		}
		echo '<td width="160">' . get_label('Album') . ':</td><td><a href="album_photos.php?id=' . $this->album_id . '&bck=1">' . $this->album_name . '</a></td></tr>';
		if ($this->event_id != NULL && $this->event_id > 0)
		{
			list($event_name) = Db::record(get_label('event'), 'SELECT name FROM events WHERE id = ?', $this->event_id);
			echo '<tr><td>'.get_label('Event').':</td><td><a href="event_standings.php?id=' . $this->event_id . '&bck=1">' . $this->club_name . ': ' . $event_name . '</a></td></tr>';
		}
		else
		{
			echo '<tr><td>'.get_label('Club').':</td><td><a href="club_main.php?id=' . $this->club_id . '&bck=1">' . $this->club_name . '</a></td></tr>';
		}
		echo '<tr><td>'.get_label('Uploaded by').':</td><td>';
		if ($_profile != NULL && $_profile->user_id != $this->owner_id)
		{
			echo '<a href="user_info.php?id=' . $this->owner_id . '&bck=1">' . cut_long_name($this->owner_name, 45) . '</a> *';
			echo '</td></tr>';
			echo '</table>';
		}
		else
		{
			echo '<a href="user_photos.php?id=' . $this->owner_id . '&bck=1">' . cut_long_name($this->owner_name, 45) . '</a>';
			echo '</td></tr>';
			echo '</table>';
		}
		
		echo '<div id="comments">'
?>
		<script type="text/javascript">
			mr.showComments("photo", <?php echo $this->id; ?>, 5);
		</script>
<?php
	}
	
	function delete_photo()
	{
		Db::begin();
		Db::exec(get_label('photo'), 'DELETE FROM user_photos WHERE photo_id = ?', $this->id);
		Db::exec(get_label('comment'), 'DELETE FROM photo_comments WHERE photo_id = ?', $this->id);
		Db::exec(get_label('photo'), 'DELETE FROM photos WHERE id = ?', $this->id);
		Db::commit();
	}
}

$page = new Page();
$page->run(get_label('Photo'));

?>