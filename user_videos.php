<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/club.php';
require_once 'include/event.php';
require_once 'include/user.php';
require_once 'include/image.php';
require_once 'include/languages.php';
require_once 'include/video_cards.php';

define('PAGE_SIZE', 24);

class Page extends UserPageBase
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
		
		$page_size = PAGE_SIZE;
		
		$condition = new SQL();
		if ($this->video_type >= 0)
		{
			$condition->add(' AND v.type = ?', $this->video_type);
		}
		
		list ($count1) = Db::record(get_label('video'), 'SELECT count(*) FROM user_videos u JOIN videos v ON v.id = u.video_id WHERE u.user_id = ?', $this->id, $condition);
		list ($count2) = Db::record(get_label('video'), 'SELECT count(*) FROM players p JOIN games g ON g.id = p.game_id JOIN videos v ON v.id = g.video_id WHERE p.user_id = ?', $this->id, $condition);
		$count = $count1 + $count2;
		
		// The toolbar of a list (see new_look.css): the kind of video; the languages and adding one at
		// the right end.
		echo '<div class="toolbar">';
		show_video_type_select($this->video_type, 'vtype', 'filter()');
		echo '<div class="tb-langs">';
		echo '</div>';
		echo '</div>';
		show_pages_navigation($page_size, $count);
		
		$query = new DbQuery();
		if ($this->video_type <= VIDEO_TYPE_GAME)
		{
			$query->add('(');
		}
		$query->add(
			'SELECT v.video_time, v.id as video_id, v.video, v.name, v.lang, v.type, v.post_time as post_time, g.id, g.start_time as start_time, c.id, nc.name, c.flags, e.id, ne.name, e.flags, g.table_num, g.game_num FROM user_videos u' .
			' JOIN videos v ON u.video_id = v.id' .
			' JOIN clubs c ON c.id = v.club_id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN events e ON e.id = v.event_id' .
			' LEFT OUTER JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN games g ON g.video_id = v.id' .
			' WHERE u.user_id = ?', $this->id, $condition);
		if ($this->video_type <= VIDEO_TYPE_GAME)
		{
			$query->add(
				') UNION (SELECT v.video_time, v.id as video_id, v.video, v.name, v.lang, v.type, v.post_time as post_time, g.id, g.start_time as start_time, c.id, nc.name, c.flags, e.id, ne.name, e.flags, g.table_num, g.game_num FROM players p' .
				' JOIN games g ON p.game_id = g.id' .
				' JOIN videos v ON g.video_id = v.id' .
				' JOIN clubs c ON c.id = v.club_id' .
				' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN events e ON e.id = v.event_id' .
				' LEFT OUTER JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
				' WHERE p.user_id = ?', $this->id, $condition);
			$query->add(')');
		}
		$query->add(' ORDER BY start_time DESC, post_time DESC, video_id DESC LIMIT ' . ($_page * $page_size) . ',' . $page_size);
		$videos = array();
		while ($row = $query->next())
		{
			list($v_time, $video_id, $video, $title, $lang, $type, $post_time, $game_id, $game_start_time, $club_id, $club_name, $club_flags, $event_id, $event_name, $event_flags, $table_num, $game_num) = $row;
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
		video_cards_show($videos, '&user_id=' . $this->id . '&vtype=' . $this->video_type . '', get_label('No videos found.'), array('lang' => !is_valid_lang($this->langs)));
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