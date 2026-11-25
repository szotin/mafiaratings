<?php

require_once 'include/club.php';
require_once 'include/picture.php';
require_once 'include/race.php';

// The race of the rating of the players of a club: whoever of them was ever among its top twenty,
// played back from their first rating game to now. The chart of a few players it used to be is
// club_competition_chart.php.
class Page extends ClubPageBase
{
	private $race;

	protected function prepare()
	{
		parent::prepare();
		$this->race = race_rating_players(new SQL('u.games > 0 AND u.club_id = ?', $this->id));
	}

	protected function show_body()
	{
		echo '<div class="toolbar">' . race_switch('club_competition.php?id=' . $this->id, 'club_competition_chart.php?id=' . $this->id, true) . '</div>';
		show_race($this->race, get_timezone(), array('rating' => true));
	}
}

$page = new Page();
$page->run(get_label('Competition chart'));

?>
