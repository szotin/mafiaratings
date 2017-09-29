<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/club.php';
require_once 'include/image.php';
require_once 'include/photo_album.php';

class Page extends GeneralPageBase
{
	private $link_params = array();

	protected function prepare()
	{
		parent::prepare();
		$this->link_params = array('ccc' => $this->ccc_filter->get_code());
		PhotoAlbum::prepare_list($this->link_params);
		$this->ccc_title = get_label('Filter photo albums by club, city, or country.');
	}
	
	protected function show_body()
	{
		global $_profile;
		
		$create_link = NULL;
		$condition = NULL;
		$ccc_id = $this->ccc_filter->get_id();
		switch($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition = new SQL('a.club_id = ?', $ccc_id);
				if ($_profile != NULL && isset($_profile->clubs[$ccc_id]))
				{
					$create_link = 'create_album.php?bck=1&club=' . $ccc_id;
				}
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition = new SQL('a.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$condition = new SQL('a.club_id IN (SELECT id FROM clubs WHERE city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?))', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition = new SQL('a.club_id IN (SELECT c.id FROM clubs c JOIN cities ct ON ct.id = c.city_id WHERE ct.country_id = ?)', $ccc_id);
			break;
		}
		PhotoAlbum::show_list($condition, $create_link, $this->link_params, ALBUM_SHOW_CLUB | ALBUM_SHOW_OWNER);
	}
}

$page = new Page();
$page->run(get_label('Photo albums'), PERM_ALL);

?>