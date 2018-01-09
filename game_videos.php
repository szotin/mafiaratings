<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/club.php';
require_once 'include/image.php';

define('NUM_COLUMNS', 5);
define('COLUMN_COUNT', 4);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends GeneralPageBase
{
	private $link_params = array();

	protected function prepare()
	{
		parent::prepare();
		$this->ccc_title = get_label('Filter game videos by club, city, or country.');
	}
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		$condition = new SQL();
		$ccc_id = $this->ccc_filter->get_id();
		switch($this->ccc_filter->get_type())
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
			$condition->add(' AND v.club_id IN (SELECT id FROM clubs WHERE city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?))', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND v.club_id IN (SELECT c.id FROM clubs c JOIN cities ct ON ct.id = c.city_id WHERE ct.country_id = ?)', $ccc_id);
			break;
		}
		
		
		$page_size = COLUMN_COUNT * NUM_COLUMNS;
		$column_count = 0;
		$albums_count = 0;
		--$page_size;
		++$column_count;
		++$albums_count;
		
		list ($count1) = Db::record(get_label('game'), 'SELECT count(*) FROM games v WHERE v.video IS NOT NULL', $condition);
		list ($count2) = Db::record(get_label('game'), 'SELECT count(*) FROM videos v WHERE v.type = ?', VIDEO_TYPE_GAME, $condition);
		$count = $count1 + $count2;
		
		show_pages_navigation($page_size, $count);
		
		echo '<table class="bordered" width="100%"><tr><td class="light" width="' . COLUMN_WIDTH . '%" align="center">';
		echo '<a href="javascript:createVideo(' . VIDEO_TYPE_GAME . ')" title="' . get_label('Create [0]', get_label('game video')) . '"><img src="images/create_big.png" border="0" width="' . ICON_WIDTH . '" height="' . ICON_HEIGHT . '"></a>';
		echo '</td>';
		
		$parsed_condition = $condition->get_parsed_sql();
		$query = new DbQuery('(SELECT v.video as video, v.start_time as time FROM games v WHERE v.video IS NOT NULL' . $parsed_condition . ') UNION (SELECT v.video as video, v.time as time FROM videos v WHERE v.type = ?' . $parsed_condition . ') ORDER BY time DESC LIMIT ' . ($_page * $page_size) . ',' . $page_size, VIDEO_TYPE_GAME);
		while ($row = $query->next())
		{
			list($video, $time) = $row;
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
			echo ' width="' . COLUMN_WIDTH . '%" align="center" valign="center"  style="position:relative;left:0px;">';
		
			echo '<p><iframe title="YouTube video player" width="200" height="150" src="https://www.youtube.com/embed/' . $video . '" frameborder="0" allowfullscreen></iframe></p>';
			echo '</td>';
			
			++$albums_count;
			++$column_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		if ($albums_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td class="light" colspan="' . (COLUMN_COUNT - $column_count) . '"></td>';
			}
			echo '</tr></table>';
		}
		else
		{
			echo get_label('No videos found.');
		}
	}
}

$page = new Page();
$page->run(get_label('Videos'), PERM_ALL);

?>