<?php

require_once __DIR__ . '/page_base.php';
require_once __DIR__ . '/image.php';
require_once __DIR__ . '/pages.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/club.php';
require_once __DIR__ . '/cards.php';

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
		global $_profile, $_lang;
		
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
				'SELECT a.name, a.viewers, a.adders, a.flags, e.id, ne.name, e.flags, t.id, nt.name, t.flags, c.id, nc.name, c.flags, u.id, nu.name FROM photo_albums a' .
				' LEFT OUTER JOIN events e ON a.event_id = e.id' .
				' LEFT OUTER JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN tournaments t ON e.tournament_id = t.id' .
				' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
				' JOIN clubs c ON a.club_id = c.id' .
				' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
				' JOIN users u ON a.user_id = u.id' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
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
		global $_profile, $_page, $_lang;
		
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
		$where = PhotoAlbum::viewers_condition();
		if ($condition != NULL)
		{
			$where->add(' AND ');
			$where->add($condition);
		}

		list ($count) = Db::record(get_label('photo album'), 'SELECT count(*) FROM photo_albums a WHERE ', $where);
		
		// Creating an album: a button at the right end of a line of its own, as on the other lists.
		if ($create_link != NULL)
		{
			echo '<div class="toolbar"><a class="btn tb-create" href="' . $create_link . '">' . PageBase::icon('plus') . '<span>' . get_label('Create [0]', get_label('album')) . '</span></a></div>';
		}
		show_pages_navigation($page_size, $count);
		
		$album_pic = new Picture(ALBUM_PICTURE, new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE))));
		$query = new DbQuery('SELECT a.id, a.name, a.flags, a.viewers, u.id, nu.name, e.id, ne.name, e.flags, t.id, nt.name, t.flags, c.id, nc.name, c.flags,' .
			' (SELECT count(*) FROM photos p WHERE p.album_id = a.id) FROM photo_albums a' .
			' JOIN users u ON u.id = a.user_id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' JOIN clubs c ON c.id = a.club_id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN events e ON e.id = a.event_id' .
			' LEFT OUTER JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' .
			' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' WHERE TRUE AND ', $where);
		$query->add(' ORDER BY a.id DESC LIMIT ' . ($_page * $page_size) . ',' . $page_size);
		
		// The cards of the v3 design (see include/event_cards.php): the picture of the album - or of
		// the game day, the tournament, the club it is from - and under it its name, where it is from
		// and how many photos it has.
		$html = '';
		while ($row = $query->next())
		{
			list($album_id, $album_name, $album_flags, $album_viewers, $owner_id, $owner_name, $e_id, $e_name, $e_flags, $t_id, $t_name, $t_flags, $c_id, $c_name, $c_flags, $photos_count) = $row;
			$url = 'album_photos.php?bck=1&id=' . $album_id;
			$album_pic->
				set($album_id, $album_name, $album_flags)->
				set($e_id, $e_name, $e_flags)->
				set($t_id, $t_name, $t_flags)->
				set($c_id, $c_name, $c_flags);
			if ($album_pic->has_image())
			{
				$pic_url = $album_pic->url(TNAILS_DIR);
				$cover = '<span class="cv-blur" style="background-image: url(\'' . $pic_url . '\');"></span><img class="cv-logo" src="' . $pic_url . '" alt="" loading="lazy" decoding="async">';
			}
			else
			{
				$cover = '<span class="pc-fallback"><span class="pc-mono">' . card_initials($album_name) . '</span></span>';
			}
			
			$html .= '<article class="pcard"><div class="pc-cover">' . $cover;
			if (PhotoAlbum::can_delete($album_id, $album_viewers, $owner_id, $c_id))
			{
				$del = '?del=' . $album_id;
				foreach ($link_params as $pname => $pval)
				{
					$del .= '&' . $pname . '=' . $pval;
				}
				$html .= '<span class="pc-actions"><button class="icon" onclick="window.location.href=\'' . $del . '\'" title="' . get_label('Delete [0]', $album_name) . '"><img src="images/delete.png" border="0"></button></span>';
			}
			$html .= '</div><div class="pc-body"><h3><a href="' . $url . '">' . $album_name . '</a></h3>';
			$html .= '<div class="pc-meta">';
			if (!is_null($t_id))
			{
				$html .= '<a href="tournament.php?bck=1&id=' . $t_id . '">' . PageBase::icon('trophy') . $t_name . '</a>';
			}
			else if (!is_null($e_id))
			{
				$html .= '<a href="event.php?bck=1&id=' . $e_id . '">' . PageBase::icon('cal') . $e_name . '</a>';
			}
			if ($flags & ALBUM_SHOW_CLUB)
			{
				$html .= '<a href="club_main.php?bck=1&id=' . $c_id . '">' . PageBase::icon('users') . $c_name . '</a>';
			}
			if ($flags & ALBUM_SHOW_OWNER)
			{
				$html .= '<a href="user_info.php?bck=1&id=' . $owner_id . '">' . PageBase::icon('user') . $owner_name . '</a>';
			}
			$html .= '</div><div class="pc-foot"><span class="pc-nums">';
			$html .= '<a href="' . $url . '" title="' . get_label('Photos') . '">' . PageBase::icon('photo') . $photos_count . '</a>';
			$html .= '</span></div></div></article>';
		}
		if ($html == '')
		{
			echo '<p class="empty">' . get_label('No albums found.') . '</p>';
		}
		else
		{
			echo '<div class="posters">' . $html . '</div>';
			show_pages_navigation($page_size, $count);
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
		// A grid of thumbnails as wide as the screen allows; each one opens the photo.
		echo '<div class="photo-grid">';
		while ($row = $query->next())
		{
			$photo_id = $row[0];
			echo '<a href="photo.php?id=' . $photo_id . '&page=' . $_page . $link_str . '&bck=1">';
			echo '<img src="' . PHOTOS_DIR . TNAILS_DIR . $photo_id . '.jpg" alt="" loading="lazy" decoding="async">';
			echo '</a>';
		}
		echo '</div>';
		show_pages_navigation(PHOTO_ROW_COUNT * PHOTO_COL_COUNT, $count);
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
					' AND a.club_id IN (SELECT club_id FROM club_regs WHERE user_id = ?))',
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
				$condition->add(' OR (p.viewers = ' . FOR_MEMBERS . ' AND a.club_id IN (SELECT club_id FROM club_regs WHERE user_id = ?))', $_profile->user_id);
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
		$album_pic->show(ICONS_DIR, false);
		echo '</td>';
		
		echo '</tr></table>';
	}
}

?>
