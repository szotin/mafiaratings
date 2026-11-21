<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/club.php';
require_once 'include/event.php';
require_once 'include/image.php';
require_once 'include/languages.php';
require_once 'include/video_cards.php';

define('PAGE_SIZE', 24);

class Page extends EventPageBase
{
	private $video_type;
	
	protected function prepare()
	{
		parent::prepare();
		
		$this->video_type = -1;
		if (isset($_REQUEST['vtype']))
		{
			$this->video_type = (int)$_REQUEST['vtype'];
		}
		$this->_title = get_videos_title($this->video_type);
	}
	
	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		$langs = $this->langs;
		if (isset($_REQUEST['langs']))
		{
			$langs = (int)$_REQUEST['langs'];
			$condition = new SQL(' AND (v.lang & ?) <> 0', $langs);
		}
		else
		{
			$condition = new SQL();
		}
		
		if ($this->video_type >= 0)
		{
			$condition->add(' AND v.type = ?', $this->video_type);
		}
		
		$page_size = PAGE_SIZE;
		$can_add = is_permitted(PERMISSION_CLUB_MEMBER | PERMISSION_EVENT_MANAGER | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, $this->club_id, $this->id, $this->tournament_id);
		
		list ($count) = Db::record(get_label('video'), 'SELECT count(*) FROM videos v WHERE v.event_id = ?', $this->id, $condition);
		
		// The toolbar of a list (see new_look.css): the kind of video; the languages and adding one at
		// the right end.
		echo '<div class="toolbar">';
		show_video_type_select($this->video_type, 'vtype', 'filter()');
		echo '<div class="tb-langs">';
		langs_checkboxes($langs, $this->langs, NULL, ' ', '', 'filter()');
		echo '</div>';
		if ($can_add)
		{
			echo '<a class="btn tb-create" href="#" onclick="mr.createVideo(' . $this->video_type . ', null, ' . $this->id , ', null); return false;">' . PageBase::icon('plus') . '<span>' . get_label('Add [0]', get_label('video')) . '</span></a>';
		}
		echo '</div>';
		show_pages_navigation($page_size, $count);
		
		$query = new DbQuery(
			'SELECT v.video_time, v.id, v.video, v.name, v.lang, v.type, g.id, c.id, nc.name, c.flags, e.id, ne.name, e.flags, g.table_num, g.game_num FROM videos v' .
			' JOIN clubs c ON c.id = v.club_id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN events e ON e.id = v.event_id' .
			' LEFT OUTER JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN games g ON g.video_id = v.id' .
			' WHERE v.event_id = ?', $this->id, $condition);
		$query->add(' ORDER BY v.video_time DESC, v.post_time DESC, v.id DESC LIMIT ' . ($_page * $page_size) . ',' . $page_size);
		$videos = array();
		while ($row = $query->next())
		{
			list($v_time, $video_id, $video, $title, $lang, $type, $game_id, $club_id, $club_name, $club_flags, $event_id, $event_name, $event_flags, $table_num, $game_num) = $row;
			$v = new stdClass();
			$v->id = $video_id;
			$v->video = $video;
			$v->name = $title;
			$v->lang = $lang;
			$v->type = $type;
			$v->time = $v_time;
			$v->game_id = $game_id;
			$v->table_num = $table_num;
			$v->game_num = $game_num;
			$v->club_id = $club_id;
			$v->club_name = $club_name;
			$v->event_id = $event_id;
			$v->event_name = $event_name;
			$videos[] = $v;
		}
		// The cards: see include/video_cards.php.
		video_cards_show($videos, '&event_id=' . $this->id . '&vtype=' . $this->video_type . '&langs=' . $langs . '', get_label('No videos found.'), array('lang' => !is_valid_lang($this->langs), 'event' => false));
		show_pages_navigation($page_size, $count);
	}
	
	protected function js()
	{
		parent::js();
?>
		function filter()
		{
			goTo({ 'langs': mr.getLangs(), vtype: $('#vtype').val(), page: 0 });
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Videos'));

?>