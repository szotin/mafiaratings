<?php

require_once 'include/series.php';
require_once 'include/ccc_filter.php';
require_once 'include/pages.php';
require_once 'include/club.php';
require_once 'include/event.php';
require_once 'include/image.php';
require_once 'include/languages.php';

define('ROW_COUNT', DEFAULT_ROW_COUNT);
define('COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));
define('PICTURE_WIDTH', (CONTENT_WIDTH / COLUMN_COUNT) - 10);

class Page extends SeriesPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		$video_type = -1;
		if (isset($_REQUEST['vtype']))
		{
			$video_type = (int)$_REQUEST['vtype'];
		}
		
		$langs = LANG_ALL;
		if (isset($_REQUEST['langs']))
		{
			$langs = (int)$_REQUEST['langs'];
		}
		else if ($_profile != NULL)
		{
			$langs = $_profile->user_langs;
		}
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('videos')));
		show_video_type_select($video_type, 'vtype', 'filter()');
		echo '</td><td align="right">';
		langs_checkboxes($langs, LANG_ALL, NULL, ' ', '', 'filter()');
		echo '</tr></table></p>';
		
		$condition = new SQL(' WHERE (v.lang & ?) <> 0 AND st.series_id = ?', $langs, $this->id);
		if ($video_type >= 0)
		{
			$condition->add(' AND v.type = ?', $video_type);
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
		
		$page_size = ROW_COUNT * COLUMN_COUNT;
		$video_count = 0;
		$column_count = 0;
		$can_add = $_profile != NULL && $_profile->user_club_id != NULL;
		
		if ($can_add)
		{
			--$page_size;
			++$video_count;
			++$column_count;
		}
		
		list ($count) = Db::record(get_label('video'), 'SELECT count(*) FROM videos v JOIN series_tournaments st ON st.tournament_id = v.tournament_id', $condition);
		
		show_pages_navigation($page_size, $count);
		
		if ($can_add)
		{
			echo '<table class="bordered light" width="100%">';
			echo '<tr><td align="center" width="' . COLUMN_WIDTH . '%"><a href="#" onclick="mr.createVideo(' . $video_type . ', ' . $_profile->user_club_id , ', null, null)">';
			echo '<br><img src="images/create_big.png" border="0" width="' . ICON_WIDTH . '" title="' . get_label('Add [0]', get_label('video')) . '">';
			echo '</td>';
		}
		
		$query = new DbQuery(
			'SELECT v.id, v.video, v.name, v.lang, v.type, g.id, c.id, c.name, c.flags, e.id, e.name, e.flags FROM videos v' .
			' JOIN clubs c ON c.id = v.club_id' .
			' JOIN series_tournaments st ON st.tournament_id = v.tournament_id' .
			' LEFT OUTER JOIN events e ON e.id = v.event_id' .
			' LEFT OUTER JOIN games g ON g.video_id = v.id', $condition);
		$query->add(' ORDER BY v.video_time DESC, v.post_time DESC, v.id DESC LIMIT ' . ($_page * $page_size) . ',' . $page_size);
		while ($row = $query->next())
		{
			list($video_id, $video, $title, $lang, $type, $game_id, $club_id, $club_name, $club_flags, $event_id, $event_name, $event_flags) = $row;
			if ($column_count == 0)
			{
				if ($video_count == 0)
				{
					echo '<table class="bordered" width="100%">';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			
			echo '<td valign="top"';
			echo ' width="' . COLUMN_WIDTH . '%" align="center" valign="center">';
			
			echo '<table width="100%" class="transp"><tr class="darker" style="height: 30px;" align="center"><td><b>';
			if (is_null($game_id))
			{
				echo get_video_title($type);
			}
			else
			{
				echo get_label('Game [0]', $game_id);
			}
			echo '</b></td></tr>';
			
			echo '<tr><td><span style="position:relative;">';
			echo '<a href="video.php?bck=1&id=' . $video_id . '&vtype=' . $video_type . '&langs=' . $langs . '"><img src="https://img.youtube.com/vi/' . $video . '/0.jpg" width="' . PICTURE_WIDTH . '" title="' . $title . '">';
			echo '<img src="images/' . ICONS_DIR . 'lang' . $lang . '.png" title="' . $title . '" width="24" style="position:absolute; margin-left:-28px;">';
			echo '</a></span></td></tr>';
			echo '<tr><td align="center">' . $title . '</td></tr>';
			echo '</table>';
			
			++$video_count;
			++$column_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		if ($video_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td class="light" colspan="' . (COLUMN_COUNT - $column_count) . '"></td>';
			}
			echo '</tr></table>';
		}
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