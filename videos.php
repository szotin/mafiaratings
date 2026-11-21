<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/club.php';
require_once 'include/event.php';
require_once 'include/image.php';
require_once 'include/languages.php';
require_once 'include/video_cards.php';

define('PAGE_SIZE', 24);

class Page extends GeneralPageBase
{
	private $video_type;
	private $langs;

	protected function prepare()
	{
		global $_profile;

		parent::prepare();
		$this->video_type = -1;
		if (isset($_REQUEST['vtype']))
		{
			$this->video_type = (int)$_REQUEST['vtype'];
		}

		$this->langs = LANG_ALL;
		if (isset($_REQUEST['langs']))
		{
			$this->langs = (int)$_REQUEST['langs'];
		}
		else if ($_profile != NULL)
		{
			$this->langs = $_profile->user_langs;
		}
	}

	// Adding a video, on the band beside the title, as "New club" is on the list of clubs.
	protected function title_actions()
	{
		global $_profile;
		if ($_profile == NULL || $_profile->user_club_id == NULL)
		{
			return '';
		}
		return '<a class="btn" href="#" onclick="mr.createVideo(' . $this->video_type . ', ' . $_profile->user_club_id . ', null, null); return false;">' . PageBase::icon('plus') . '<span>' . get_label('Add [0]', get_label('video')) . '</span></a>';
	}

	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		// The toolbar of a list (see new_look.css): the place and the kind of video; the languages at
		// the right end.
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		echo '<div class="toolbar"><div class="tb-field">';
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('videos')));
		echo '</div>';
		show_video_type_select($this->video_type, 'vtype', 'filter()');
		echo '<div class="tb-langs">';
		langs_checkboxes($this->langs, LANG_ALL, NULL, ' ', '', 'filter()');
		echo '</div></div>';
		
		$condition = new SQL(' AND (v.lang & ?) <> 0', $this->langs);
		if ($this->video_type >= 0)
		{
			$condition->add(' AND v.type = ?', $this->video_type);
		}
		
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND v.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND v.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$condition->add(
				' AND ((v.tournament_id IS NULL AND v.event_id IS NULL AND v.club_id IN (SELECT c1.id FROM clubs c1 JOIN cities i1 ON i1.id = c1.city_id WHERE i1.id = ? OR i1.area_id = ?))' .
				' OR (v.event_id IS NULL AND v.event_id IN (SELECT e2.id FROM events e2 JOIN addresses a2 ON a2.id = e2.address_id JOIN cities i2 ON i2.id = a2.city_id WHERE i2.id = ? OR i2.area_id = ?))' .
				' OR v.tournament_id IN (SELECT t3.id FROM tournaments t3 JOIN addresses a3 ON a3.id = t3.address_id JOIN cities i3 ON i3.id = a3.city_id WHERE i3.id = ? OR i3.area_id = ?))'
				, $ccc_id, $ccc_id, $ccc_id, $ccc_id, $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(
				' AND ((v.tournament_id IS NULL AND v.event_id IS NULL AND v.club_id IN (SELECT c1.id FROM clubs c1 JOIN cities i1 ON i1.id = c1.city_id WHERE i1.country_id = ?))' .
				' OR (v.event_id IS NULL AND v.event_id IN (SELECT e2.id FROM events e2 JOIN addresses a2 ON a2.id = e2.address_id JOIN cities i2 ON i2.id = a2.city_id WHERE i2.country_id = ?))' .
				' OR v.tournament_id IN (SELECT t3.id FROM tournaments t3 JOIN addresses a3 ON a3.id = t3.address_id JOIN cities i3 ON i3.id = a3.city_id WHERE i3.country_id = ?))'
				, $ccc_id, $ccc_id, $ccc_id, $ccc_id, $ccc_id, $ccc_id);
			break;
		}
		
		list ($count) = Db::record(get_label('video'), 'SELECT count(*) FROM videos v WHERE TRUE', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT v.id, v.video, v.name, v.lang, v.type, v.video_time, g.id, g.table_num, g.game_num, c.id, nc.name, e.id, ne.name, t.id, nt.name FROM videos v' .
			' JOIN clubs c ON c.id = v.club_id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN events e ON e.id = v.event_id' .
			' LEFT OUTER JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN tournaments t ON t.id = v.tournament_id' .
			' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN games g ON g.video_id = v.id' .
			' WHERE TRUE', $condition);
		$query->add(' ORDER BY v.video_time DESC, v.post_time DESC, v.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		$videos = array();
		while ($row = $query->next())
		{
			$v = new stdClass();
			list($v->id, $v->video, $v->name, $v->lang, $v->type, $v->time, $v->game_id, $v->table_num, $v->game_num, $v->club_id, $v->club_name, $v->event_id, $v->event_name, $v->tournament_id, $v->tournament_name) = $row;
			$videos[] = $v;
		}
		video_cards_show($videos, '&vtype=' . $this->video_type . '&langs=' . $this->langs, get_label('No videos found.'));
		show_pages_navigation(PAGE_SIZE, $count);
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