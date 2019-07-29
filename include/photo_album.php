<?php

require_once __DIR__ . '/page_base.php';
require_once __DIR__ . '/image.php';
require_once __DIR__ . '/pages.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/club.php';

define('ALBUM_SHOW_CLUB', 1);
define('ALBUM_SHOW_OWNER', 2);

class PhotoAlbum
{
	public $id;
	public $name;
	public $event_id;
	public $event_name;
	public $event_flags;
	public $tour_id;
	public $tour_name;
	public $tour_flags;
	public $club_id;
	public $club_name;
	public $club_flags;
	public $flags;
	public $user_id;
	public $user_name;
	public $viewers;
	public $adders;
	
	function __construct($id = -1)
	{
		global $_profile;
		
		$this->id = -1;
		$this->event_id = NULL;
		$this->tour_id = NULL;
		$this->club_id = -1;
		$this->viewers = FOR_EVERYONE;
		$this->adders = FOR_MANAGERS;
		if ($_profile != NULL)
		{
			$this->user_id = $_profile->user_id;
			$this->user_name = $_profile->user_name;
		}
		$this->flags = 0;
		if ($id > 0)
		{
			$query = new DbQuery(
				'SELECT a.name, a.viewers, a.adders, a.flags, e.id, e.name, e.flags, t.id, t.name, t.flags, c.id, c.name, c.flags, u.id, u.name FROM photo_albums a' .
				' LEFT OUTER JOIN events e ON a.event_id = e.id' .
				' LEFT OUTER JOIN tournaments t ON e.tournament_id = t.id' .
				' JOIN clubs c ON a.club_id = c.id' .
				' JOIN users u ON a.user_id = u.id' .
				' WHERE a.id = ? AND ', $id, PhotoAlbum::viewers_condition());
				
			if ($row = $query->next())
			{
				$this->id = $id;
				list(
					$this->name, $this->viewers, $this->adders, $this->flags,
					$this->event_id, $this->event_name, $this->event_flags,
					$this->tour_id, $this->tour_name, $this->tour_flags,
					$this->club_id, $this->club_name, $this->club_flags,
					$this->user_id, $this->user_name) = $row;
			}
		}
	}
	
	function get_data()
	{
		global $_profile;
		if (isset($_POST['name']))
		{
			$this->name = trim($_POST['name']);
		}
		
		if (isset($_POST['viewers']))
		{
			$this->viewers = $_POST['viewers'];
		}
		
		if (isset($_POST['adders']))
		{
			$this->adders = $_POST['adders'];
		}
		
		if ($this->adders < $this->viewers)
		{
			$this->adders = $this->viewers;
		}
		
		if (isset($_REQUEST['event']))
		{
			$this->event_id = $_REQUEST['event'];
			if ($this->event_id <= 0)
			{
				$this->event_id = NULL;
			}
		}
		
		if (isset($_REQUEST['club']))
		{
			$this->club_id = $_REQUEST['club'];
		}
		else if ($this->club_id <= 0 && $_profile != NULL && count($_profile->clubs) > 0)
		{
			$this->club_id = reset($_profile->clubs)->id;
		}
	}
	
	function create()
	{
		if ($this->name == '')
		{
			throw new Exc(get_label('Please enter photo album name'));
		}
		
		Db::begin();
		
		$query = new DbQuery('SELECT name FROM photo_albums WHERE name = ?', $this->name);
		if ($query->next())
		{
			throw new Exc(get_label('Duplicate photo album name. Please try something else.'));
		}
		
		Db::exec(
			get_label('photo album'), 
			'INSERT INTO photo_albums (name, event_id, viewers, adders, club_id, flags, user_id) VALUES(?, ?, ?, ?, ?, ?, ?)',
			$this->name, $this->event_id, $this->viewers, $this->adders, $this->club_id, $this->flags, $this->user_id);
		list ($album_id) = Db::record(get_label('photo album'), 'SELECT LAST_INSERT_ID()');
		
		$log_details = new stdClass();
		$log_details->name = $this->name;
		$log_details->viewers = $this->viewers;
		$log_details->adders = $this->adders;
		$log_details->flags = $this->flags;
		$log_details->owner = $this->user_name;
		$log_details->owner_id = $this->user_id;
		if ($this->event_id)
		{
			$log_details->event_id = $this->event_id;
		}
		db_log(LOG_OBJECT_PHOTO_ALBUM, 'created', $log_details, $album_id, $this->club_id);
		
		Db::commit();
			
		$this->id = $album_id;
		return $album_id;
	}
	
	function update()
	{
		if ($this->name == '')
		{
			throw new Exc(get_label('Please enter photo album name'));
		}
		
		Db::begin();
		$query = new DbQuery('SELECT name FROM photo_albums WHERE name = ? AND id <> ?', $this->name, $this->id);
		if ($query->next())
		{
			throw new Exc(get_label('Duplicate photo album name. Please try something else.'));
		}
		
		// update album
		Db::exec(
			get_label('photo album'), 
			'UPDATE photo_albums SET name = ?, event_id = ?, viewers = ?, adders = ?, flags = ?, club_id = ? WHERE id = ?',
			$this->name, $this->event_id, $this->viewers, $this->adders, $this->flags, $this->club_id, $this->id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->name = $this->name;
			$log_details->viewers = $this->viewers;
			$log_details->adders = $this->adders;
			$log_details->flags = $this->flags;
			if ($this->event_id)
			{
				$log_details->event_id = $this->event_id;
			}
			db_log(LOG_OBJECT_PHOTO_ALBUM, 'changed', $log_details, $this->id, $this->club_id);
		}
		Db::commit();
	}
	
	static function delete($id)
	{
		Db::begin();
		if (!PhotoAlbum::can_delete($id))
		{
			throw new Exc(get_label('No permissions'));
		}

		list($club_id) = Db::record(get_label('photo album'), 'SELECT club_id FROM photo_albums WHERE id = ?', $id);
		
		Db::exec(get_label('photo'), 'DELETE FROM photo_comments WHERE photo_id IN (SELECT id FROM photos WHERE album_id = ?)', $id);
		Db::exec(get_label('photo'), 'DELETE FROM user_photos WHERE photo_id IN (SELECT id FROM photos WHERE album_id = ?)', $id);
		Db::exec(get_label('photo'), 'DELETE FROM photos WHERE album_id = ?', $id);
		Db::exec(get_label('photo album'), 'DELETE FROM photo_albums WHERE id = ?', $id);

		db_log(LOG_OBJECT_PHOTO_ALBUM, 'deleted', NULL, $id, $club_id);
		
		// Note that we are not deleting photo files and album icon. They can be garbage collected later.
		// There are some other peaces of the code that delete files when the associated db record is deleted. It is wrong. Garbage collection is better.
		// Change it whereever it's found.
		Db::commit();
	}
	
	function can_edit()
	{
		global $_profile;
		if ($_profile == NULL)
		{
			return false;
		}
		else if ($this->user_id == $_profile->user_id)
		{
			return true;
		}
		else if ($this->viewers >= FOR_USER)
		{
			return false;
		}
		return $_profile->is_club_manager($this->club_id);
	}
	
	function can_add()
	{
		global $_profile;
		if ($_profile == NULL)
		{
			return false;
		}
		else if ($this->user_id == $_profile->user_id)
		{
			return true;
		}
		else if ($this->adders == FOR_MEMBERS)
		{
			return isset($_profile->clubs[$this->club_id]);
		}
		else if ($this->adders == FOR_MANAGERS)
		{
			return $_profile->is_club_manager($this->club_id);
		}
		return false;
	}
	
	static function can_delete($album_id, $viewers = -1, $user_id = -1, $club_id = -1)
	{
		global $_profile;
		if ($_profile == NULL)
		{
			return false;
		}
		
		if ($viewers < 0 || $user_id <= 0 || $club_id <= 0)
		{
			list($viewers, $user_id, $club_id) = Db::record(get_label('photo album'), 'SELECT viewers, user_id, club_id FROM photo_albums WHERE id = ?', $album_id);
		}
		if ($user_id == $_profile->user_id)
		{
			return true;
		}
		else if ($viewers >= FOR_USER)
		{
			return false;
		}
		return $_profile->is_club_manager($club_id);
	}
	
	public static function prepare_list($link_params)
	{
		if (isset($_REQUEST['confirm']))
		{
			try
			{
				$album_id = $_REQUEST['dodel'];
				list($owner_id, $club_id) = Db::record(get_label('photo album'), 'SELECT user_id, club_id FROM photo_albums WHERE id = ?', $album_id);
				PhotoAlbum::delete($album_id);
			}
			catch (FatalExc $e)
			{
				throw new Exc($e->getMessage(), $e->get_details(), $e->for_log());
			}
			
			// make an additional redirect to get rid of unnecessary params
			$redirect = get_page_name();
			$delim = '?';
			foreach ($link_params as $pname => $pval)
			{
				$redirect .= $delim . $pname . '=' . $pval;
				$delim = '&';
			}
			throw new RedirectExc($redirect);
		}
	}
	
	public static function show_list($condition, $create_link, $link_params, $flags)
	{
		global $_profile, $_page;
		
		if (isset($_REQUEST['del']))
		{
			$album_id = $_REQUEST['del'];
			list($album_name) = Db::record(get_label('photo album'), 'SELECT name FROM photo_albums WHERE id = ?', $album_id);
			
			echo '<form method="get">';
			foreach ($link_params as $pname => $pval)
			{
				echo '<input type="hidden" name="' . $pname . '" value="' . $pval . '">';
			}
			echo '<input type="hidden" name="dodel" value="' . $album_id . '">';
			echo '<p>'.get_label('Are you sure you want to delete the photo album "' . $album_name . '"?').'</p>';
			echo '<input type="submit" name="confirm" value="'.get_label('Yes').'" class="btn norm"><input type="submit" name="cancel" value="'.get_label('No').'" class="btn norm">';
			echo '</form>';
		}
		
		$page_size = 20;
		$column_count = 0;
		$albums_count = 0;
		if ($create_link != NULL)
		{
			--$page_size;
			++$column_count;
			++$albums_count;
		}
		
		$where = PhotoAlbum::viewers_condition();
		if ($condition != NULL)
		{
			$where->add(' AND ');
			$where->add($condition);
		}

		list ($count) = Db::record(get_label('photo album'), 'SELECT count(*) FROM photo_albums a WHERE ', $where);
		
		show_pages_navigation($page_size, $count);
		
		if ($create_link != NULL)
		{
			echo '<table class="bordered" width="100%"><tr><td class="light" width="20%" align="center">';
			echo '<a href="' . $create_link . '" title="' . get_label('Create [0]', get_label('album')) . '"><img src="images/create_big.png" border="0" width="' . ICON_WIDTH . '" height="' . ICON_HEIGHT . '"></a>';
			echo '</td>';
		}
		
		$album_pic = new Picture(ALBUM_PICTURE, new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE))));
		$query = new DbQuery('SELECT a.id, a.name, a.flags, a.viewers, u.id, u.name, e.id, e.name, e.flags, t.id, t.name, t.flags, c.id, c.name, c.flags FROM photo_albums a' .
			' JOIN users u ON u.id = a.user_id' .
			' JOIN clubs c ON c.id = a.club_id' .
			' LEFT OUTER JOIN events e ON e.id = a.event_id' .
			' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' .
			' WHERE TRUE AND ', $where);
		$query->add(' ORDER BY a.id DESC LIMIT ' . ($_page * $page_size) . ',' . $page_size);
		while ($row = $query->next())
		{
			list($album_id, $album_name, $album_flags, $album_viewers, $owner_id, $owner_name, $e_id, $e_name, $e_flags, $t_id, $t_name, $t_flags, $c_id, $c_name, $c_flags) = $row;
			if ($column_count == 0)
			{
				if ($albums_count == 0)
				{
					echo '<table class="bordered" width="100%">';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			
			echo '<td class="light"';
			echo ' width="20%" align="center" valign="top"  style="position:relative;left:0px;">';
			if (PhotoAlbum::can_delete($album_id, $album_viewers, $owner_id, $c_id))
			{
				echo '<a href="?del=' . $album_id;
				foreach ($link_params as $pname => $pval)
				{
					echo '&' . $pname . '=' . $pval;
				}
				echo '" title="' . get_label('Delete [0]', $album_name) . '"><img src="images/delete.png" style="position:absolute;left:2px;top:2px;" border="0"></a>';
			}
			if ($flags & ALBUM_SHOW_CLUB)
			{
				echo '<b>' . $c_name . '</b>';
			}
			echo '<br><a href="album_photos.php?id=' . $album_id . '&bck=1" title="' . $album_name . '">';
			$album_pic->
				set($album_id, $album_name, $album_flags)->
				set($e_id, $e_name, $e_flags)->
				set($t_id, $t_name, $t_flags)->
				set($c_id, $c_name, $c_flags);
			$album_pic->show(ICONS_DIR);
			echo '<br>' . $album_name . '</a>';
			if ($flags & ALBUM_SHOW_OWNER)
			{
				echo '<br>' . get_label('By [0]', $owner_name);
			}
			echo '</td>';
			
			++$albums_count;
			++$column_count;
			if ($column_count >= 5)
			{
				$column_count = 0;
			}
		}
		if ($albums_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td class="light" colspan="' . (5 - $column_count) . '"></td>';
			}
			echo '</tr></table>';
		}
		else
		{
			echo get_label('No albums found.');
		}
	}
	
	static function show_thumbnails($condition, $link_str)
	{
		global $_page;

		$condition->add(' AND ', PhotoAlbum::photo_viewers_condition());
		
		list ($count) = Db::record(get_label('photo album'), 'SELECT count(*) FROM photos p JOIN photo_albums a ON p.album_id = a.id ', $condition);
		show_pages_navigation(PHOTO_ROW_COUNT * PHOTO_COL_COUNT, $count);
		
		$query = new DbQuery('SELECT p.id FROM photos p JOIN photo_albums a ON p.album_id = a.id ', $condition);
		$query->add(' ORDER BY p.id DESC LIMIT ' . ($_page * PHOTO_ROW_COUNT * PHOTO_COL_COUNT) . ',' . (PHOTO_ROW_COUNT * PHOTO_COL_COUNT));
		echo '<table class="photos" width="100%">';
		$col_count = 0;
		$picture_width = CONTENT_WIDTH / PHOTO_COL_COUNT - 4;
		while ($row = $query->next())
		{
			$photo_id = $row[0];
			if ($col_count == 0)
			{
				echo '<tr class="light">';
			}
			
			echo '<td width="' . $picture_width . '" align="center" valign="top"><a href="photo.php?id=' . $photo_id . '&page=' . $_page . $link_str . '&bck=1">';
			echo '<img src="' . PHOTOS_DIR . TNAILS_DIR . $photo_id . '.jpg" width="' . EVENT_PHOTO_WIDTH . '" border="0">';
			echo '</a></td>';
			
			++$col_count;
			if ($col_count == PHOTO_COL_COUNT)
			{
				$col_count = 0;
				echo '</tr>';
			}
		}
		if ($col_count > 0)
		{
			do
			{
				echo '<td width="' . $picture_width . '">&nbsp;</td>';
				++$col_count;
				
			} while ($col_count < PHOTO_COL_COUNT);
		}
		echo '</table>';
	}
	
	static function viewers_condition()
	{
		global $_profile;
		
		if ($_profile != NULL)
		{
			$condition = new SQL(
				'(a.viewers = ' . FOR_EVERYONE . 
				' OR a.user_id = ?', 
				$_profile->user_id);
			if (count($_profile->clubs) > 0)
			{
				$condition->add(
					' OR (a.viewers = ' . FOR_MEMBERS . 
					' AND a.club_id IN (SELECT club_id FROM user_clubs WHERE user_id = ?))',
					$_profile->user_id);
			}
			$condition->add(')');
		}
		else
		{
			$condition = new SQL('a.viewers = ' . FOR_EVERYONE);
		}
		return $condition;
	}
	
	static function photo_viewers_condition()
	{
		global $_profile;
		
		if ($_profile != NULL)
		{
			$condition = new SQL('(p.viewers = ' . FOR_EVERYONE . ' OR p.user_id = ?', $_profile->user_id);
			if (count($_profile->clubs) > 0)
			{
				$condition->add(' OR (p.viewers = ' . FOR_MEMBERS . ' AND a.club_id IN (SELECT club_id FROM user_clubs WHERE user_id = ?))', $_profile->user_id);
			}
			$condition->add(')');
		}
		else
		{
			$condition = new SQL('p.viewers = ' . FOR_EVERYONE);
		}
		return $condition;
	}
}

class AlbumPageBase extends PageBase
{
	protected $album;

	protected function prepare()
	{
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('photo album')));
		}
		$this->album = new PhotoAlbum($_REQUEST['id']);
		$this->_title = $this->album->name;
	}
	
	protected function show_title()
	{
		global $_profile;
		if ($this->album == NULL)
		{
			parent::show_title();
			return;
		}
		
		$can_edit = $this->album->can_edit();
		$can_add = $this->album->can_add();
		
		echo '<table class="head" width="100%">';
		if ($can_edit || $can_add)
		{
			$menu = array
			(
				new MenuItem('album_photos.php?id=' . $this->album->id, get_label('Photos'), get_label('Games statistics'))
			);
			if ($can_edit)	
			{
				$menu[] = new MenuItem('album_edit.php?id=' . $this->album->id, get_label('Change'), get_label('Edit photo album'));
			}
			if ($can_add)
			{
				$menu[] = new MenuItem('album_upload.php?id=' . $this->album->id, get_label('Upload'), get_label('Add photos to the album'));
			}
		
			echo '<tr><td valign="top" colspan="4">';
			PageBase::show_menu($menu);
			echo '</td></tr>';	
		}
		echo '<tr>';
		
/*		echo '<td align="right" valign="top" width="' . ICON_WIDTH . '">';
		echo '</td>';*/
		
		echo '<td valign="top">' . $this->standard_title() . '</td><td align="right" valign="top">';
		show_back_button();
		echo '</td>';
		
		echo '<td valign="top" width="1">';
		$album_pic = new Picture(ALBUM_PICTURE, new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE))));
		$album_pic->
			set($this->album->id, $this->album->name, $this->album->flags)->
			set($this->album->event_id, $this->album->event_name, $this->album->event_flags)->
			set($this->album->tour_id, $this->album->tour_name, $this->album->tour_flags)->
			set($this->album->club_id, $this->album->club_name, $this->album->club_flags);
		$album_pic->show(ICONS_DIR);
		echo '</td>';
		
		echo '</tr></table>';
	}
}

?>
