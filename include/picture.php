<?php

require_once __DIR__ . '/constants.php';

define('CLUB_PICTURE', 0);
define('EVENT_PICTURE', 1);
define('TOURNAMENT_PICTURE', 2);
define('ADDRESS_PICTURE', 3);
define('USER_PICTURE', 4);
define('LEAGUE_PICTURE', 5);
define('ALBUM_PICTURE', 6);
define('USER_CLUB_PICTURE', 7);
define('USER_EVENT_PICTURE', 8);
define('USER_TOURNAMENT_PICTURE', 9);

class Picture
{
	private $type;
	public $mask;
	private $mask_offset;
	private $pic_dir;
	private $def_filename;
	private $code;
	
	private $id;
	private $secondary_id;
	private $name;
	private $flags;
	private $alt;
	
	public function __construct($type, $alt = NULL)
	{
		$this->type = $type;
		switch ($type)
		{
			case CLUB_PICTURE:
				$this->mask = CLUB_ICON_MASK;
				$this->mask_offset = CLUB_ICON_MASK_OFFSET;
				$this->pic_dir = CLUB_PICS_DIR;
				$this->def_filename = 'club.png';
				$this->code = CLUB_PIC_CODE;
				break;
			case EVENT_PICTURE:
				$this->mask = EVENT_ICON_MASK;
				$this->mask_offset = EVENT_ICON_MASK_OFFSET;
				$this->pic_dir = EVENT_PICS_DIR;
				$this->def_filename = 'event.png';
				$this->code = EVENT_PIC_CODE;
				break;
			case TOURNAMENT_PICTURE:
				$this->mask = TOURNAMENT_ICON_MASK;
				$this->mask_offset = TOURNAMENT_ICON_MASK_OFFSET;
				$this->pic_dir = TOURNAMENT_PICS_DIR;
				$this->def_filename = 'tournament.png';
				$this->code = TOURNAMENT_PIC_CODE;
				break;
			case ADDRESS_PICTURE:
				$this->mask = ADDRESS_ICON_MASK;
				$this->mask_offset = ADDRESS_ICON_MASK_OFFSET;
				$this->pic_dir = ADDRESS_PICS_DIR;
				$this->def_filename = 'address.png';
				$this->code = ADDRESS_PIC_CODE;
				break;
			case USER_PICTURE:
				$this->mask = USER_ICON_MASK;
				$this->mask_offset = USER_ICON_MASK_OFFSET;
				$this->pic_dir = USER_PICS_DIR;
				$this->def_filename = 'user.png';
				$this->code = USER_PIC_CODE;
				break;
			case LEAGUE_PICTURE:
				$this->mask = LEAGUE_ICON_MASK;
				$this->mask_offset = LEAGUE_ICON_MASK_OFFSET;
				$this->pic_dir = LEAGUE_PICS_DIR;
				$this->def_filename = 'league.png';
				$this->code = LEAGUE_PIC_CODE;
				break;
			case ALBUM_PICTURE:
				$this->mask = ALBUM_ICON_MASK;
				$this->mask_offset = ALBUM_ICON_MASK_OFFSET;
				$this->pic_dir = ALBUM_PICS_DIR;
				$this->def_filename = 'album.png';
				$this->code = ALBUM_PIC_CODE;
				break;
			case USER_CLUB_PICTURE:
				$this->mask = USER_CLUB_ICON_MASK;
				$this->mask_offset = USER_CLUB_ICON_MASK_OFFSET;
				$this->pic_dir = USER_PICS_DIR;
				$this->def_filename = 'user.png';
				$this->code = USER_CLUB_PIC_CODE;
				break;
			case USER_EVENT_PICTURE:
				$this->mask = USER_EVENT_ICON_MASK;
				$this->mask_offset = USER_EVENT_ICON_MASK_OFFSET;
				$this->pic_dir = USER_PICS_DIR;
				$this->def_filename = 'user.png';
				$this->code = USER_EVENT_PIC_CODE;
				break;
			case USER_TOURNAMENT_PICTURE:
				$this->mask = USER_TOURNAMENT_ICON_MASK;
				$this->mask_offset = USER_TOURNAMENT_ICON_MASK_OFFSET;
				$this->pic_dir = USER_PICS_DIR;
				$this->def_filename = 'user.png';
				$this->code = USER_TOURNAMENT_PIC_CODE;
				break;
			default:
				$this->mask = 0;
				$this->mask_offset = 0;
				$this->pic_dir = NULL;
				$this->def_filename = NULL;
				break;
		}
		
		$this->id = NULL;
		$this->secondary_id = NULL;
		$this->name = NULL;
		$this->flags = 0;
		$this->alt = $alt;
	}
	
	public function set($id, $name, $flags, $secondary_id = NULL)
	{
		$this->id = $id;
		$this->secondary_id = $secondary_id;
		$this->name = $name;
		$this->flags = (int)$flags;
		return $this->alt;
	}
	
	public function reset()
	{
		$this->id = NULL;
		$this->secondary_id = NULL;
		$this->name = NULL;
		$this->flags = 0;
		return $this->alt;
	}
	
	public function has_image($own_only = false)
	{
		if (!is_null($this->id) && $this->id > 0 && ($this->flags & $this->mask) != 0)
		{
			return true;
		}
		if (!$own_only &&!is_null($this->alt))
		{
			return $this->alt->has_image();
		}
		return false;
	}
	
	private function _url($dir)
	{
		$url = NULL;
		if (!is_null($this->id) && $this->id > 0 && ($this->flags & $this->mask) != 0)
		{
			$url = $this->pic_dir . $dir . $this->id;
			if (!is_null($this->secondary_id))
			{
				$url .= '-' . $this->secondary_id;
			}
			$url .= '.png?' . (($this->flags & $this->mask) >> $this->mask_offset);
		}
		else if (!is_null($this->alt))
		{
			$url = $this->alt->_url($dir);
		}
		return $url;
	}
	
	public function url($dir)
	{
		if (is_null($this->id) || $this->id <= 0)
		{
			return 'images/transp.png';
		}
		
		$url = $this->_url($dir);
		if (is_null($url))
		{
			return 'images/' . $dir . $this->def_filename;
		}
		return $url;
	}
	
	public function title()
	{
		if (isset($this->custom_title))
		{
			return $this->custom_title;
		}
		for ($alt = $this->alt; !is_null($alt); $alt = $alt->alt)
		{
			if (!is_null($alt->id) && $alt->id >= 0)
			{
				if (is_null($this->name) || $this->name == $alt->name)
				{
					return $alt->name;
				}
				return $this->name . ': ' . $alt->name;
			}
		}
		return $this->name;
	}
	
	public function hyperlink()
	{
		switch ($this->type)
		{
			case CLUB_PICTURE:
				return 'club_main.php?bck=1&id=' . $this->id;
			case EVENT_PICTURE:
				return 'event_standings.php?bck=1&id=' . $this->id;
			case TOURNAMENT_PICTURE:
				return 'tournament_standings.php?bck=1&id=' . $this->id;
			case ADDRESS_PICTURE:
				return 'address_info.php?bck=1&id=' . $this->id;
			case USER_PICTURE:
			case USER_CLUB_PICTURE:
				return 'user_info.php?bck=1&id=' . $this->id;
			case LEAGUE_PICTURE:
				return 'league_main.php?bck=1&id=' . $this->id;
			case ALBUM_PICTURE:
				return 'album_photos.php?bck=1&id=' . $this->id;
			case USER_EVENT_PICTURE:
				return 'event_player_games.php?bck=1&user_id=' . $this->id . '&id=' . substr($this->secondary_id, 1);
			case USER_TOURNAMENT_PICTURE:
				return 'tournament_player_games.php?bck=1&user_id=' . $this->id . '&id=' . substr($this->secondary_id, 1);
		}
		return '';
	}
	
	public function show($dir, $with_link, $width = 0, $height = 0, $attributes = NULL)
	{
		global $_lang_code;
		
		$w = $width;
		$h = $height;
		if ($dir == ICONS_DIR)
		{
			if ($w <= 0)
			{
				$w = ICON_WIDTH;
			}
			if ($h <= 0)
			{
				$h = ICON_HEIGHT;
			}
		}
		else if ($dir == TNAILS_DIR)
		{
			if ($w <= 0)
			{
				$w = TNAIL_WIDTH;
			}
			if ($h <= 0)
			{
				$h = TNAIL_HEIGHT;
			}
		}
		
		if ($width <= 0 && $height <= 0)
		{
			$width = $w;
			$height = $h;
		}
		
		$close_link = '';
		if ($with_link && !is_null($this->id) && $this->id > 0)
		{
			echo '<a href="' . $this->hyperlink() . '">';
			$close_link = '</a>';
		}
		
		$title = $this->title();
		$origin = $this->pic_dir . $dir . $this->id . '.png';
		$code = $this->code . $this->id . (is_null($this->secondary_id) ? '' : $this->secondary_id);
		$origin = $this->pic_dir . $dir . $this->id . (is_null($this->secondary_id) ? '' : '-' . $this->secondary_id);
		echo '<span style="position:relative;"><img code="' . $code . '" origin="' . $origin . '.png" src="';
		echo $this->url($dir);
		echo '" title="' . $title . '" border="0"';

		if ($width > 0)
		{
			echo ' width="' . $width . '"';
		}
		if ($height > 0)
		{
			echo ' height="' . $height . '"';
		}
		if ($attributes != NULL)
		{
			echo ' ' . $attributes;
		}
		echo '>';
		
		switch ($this->type)
		{
			case CLUB_PICTURE:
				if ($this->flags & CLUB_FLAG_RETIRED)
				{
					echo '<img src="images/' . $dir . $_lang_code . '/closed.png" title="' . $title . ' (' . get_label('closed') . ')" style="position:absolute; left:50%; margin-left:-' . ($w / 2) . 'px;"';
					if ($width > 0)
					{
						echo ' width="' . $width . '"';
					}
					if ($height > 0)
					{
						echo ' height="' . $height . '"';
					}
					echo '>';
				}
				break;
			case EVENT_PICTURE:
				if ($this->flags & EVENT_FLAG_CANCELED)
				{
					echo '<img src="images/' . $dir . $_lang_code . '/cancelled.png" style="position:absolute; left:50%; margin-left:-' . ($w / 2) . 'px;" title="' . $title . '"';
					if ($width > 0)
					{
						echo ' width="' . $width . '"';
					}
					if ($height > 0)
					{
						echo ' height="' . $height . '"';
					}
					echo '>';
				}
				break;
			case TOURNAMENT_PICTURE:
				if ($this->flags & TOURNAMENT_FLAG_CANCELED)
				{
					echo '<img src="images/' . $dir . $_lang_code . '/cancelled.png" style="position:absolute; left:50%; margin-left:-' . ($w / 2) . 'px;" title="' . $title . '"';
					if ($width > 0)
					{
						echo ' width="' . $width . '"';
					}
					if ($height > 0)
					{
						echo ' height="' . $height . '"';
					}
					echo '>';
				}
				break;
			case ADDRESS_PICTURE:
				if ($this->flags & ADDRESS_FLAG_NOT_USED)
				{
					echo '<img src="images/' . $dir . $_lang_code . '/closed.png" title="' . $title . ' (' . get_label('closed') . ')" style="position:absolute; left:50%; margin-left:-' . ($w / 2) . 'px;"';
					if ($width > 0)
					{
						echo ' width="' . $width . '"';
					}
					if ($height > 0)
					{
						echo ' height="' . $height . '"';
					}
					echo '>';
				}
				break;
			case LEAGUE_PICTURE:
				if ($this->flags & LEAGUE_FLAG_RETIRED)
				{
					echo '<img src="images/' . $dir . $_lang_code . '/closed.png" title="' . $title . ' (' . get_label('closed') . ')" style="position:absolute; left:50%; margin-left:-' . ($w / 2) . 'px;"';
					if ($width > 0)
					{
						echo ' width="' . $width . '"';
					}
					if ($height > 0)
					{
						echo ' height="' . $height . '"';
					}
					echo '>';
				}
				break;
			default:
				break;
		}
		echo '</span>' . $close_link;
	}
}
	
?>