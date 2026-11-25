<?php

require_once 'include/general_page_base.php';
require_once 'include/picture.php';
require_once 'include/race.php';

// The race of the rating: whoever was ever among the top twenty, played back from the first rating
// game to now. The chart of a few players it used to be is competition_chart.php.
class Page extends GeneralPageBase
{
	private $race;

	protected function prepare()
	{
		global $_profile;
		parent::prepare();

		$this->ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$condition = new SQL('u.games > 0');
		$ccc_id = $this->ccc_filter->get_id();
		switch ($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND u.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND u.club_id IN (SELECT club_id FROM club_regs WHERE user_id = ?)', $_profile->user_id);
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND u.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND u.city_id IN (SELECT id FROM cities WHERE country_id = ?)', $ccc_id);
			break;
		}
		$this->race = race_rating_players($condition);
	}

	protected function show_body()
	{
		echo '<div class="toolbar"><div class="tb-field">';
		$this->ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('players')));
		echo '</div>' . race_switch('competition.php?' . $this->ccc_param(), 'competition_chart.php?' . $this->ccc_param(), true) . '</div>';
		show_race($this->race, get_timezone(), array('rating' => true));
	}

	private function ccc_param()
	{
		return 'ccc=' . urlencode($this->ccc_filter->get_code());
	}
}

$page = new Page();
$page->run(get_label('Competition chart'));

?>
