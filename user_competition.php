<?php

require_once 'include/user.php';
require_once 'include/picture.php';
require_once 'include/race.php';

define('USER_RACE_ABOVE', 10); // the players above this one in the rating the race is of

// The race of a player against their neighbours in the rating: the twenty around them now, played
// back from the first rating game of any of them.
class Page extends UserPageBase
{
	private $race;

	protected function prepare()
	{
		parent::prepare();

		$ids = array();
		$query = new DbQuery('SELECT rating, games, games_won FROM users WHERE id = ?', $this->id);
		if ($row = $query->next())
		{
			list ($rating, $games, $won) = $row;
			list ($pos) = Db::record(get_label('rating'),
				'SELECT count(*) FROM users WHERE games > 0 AND (rating > ? OR (rating = ? AND (games < ? OR (games = ? AND (games_won > ? OR (games_won = ? AND id < ?))))))',
				$rating, $rating, $games, $games, $won, $won, $this->id);
			$query = new DbQuery('SELECT u.id FROM users u WHERE u.games > 0 ORDER BY u.rating DESC, u.games, u.games_won DESC, u.id LIMIT ' . max(0, $pos - USER_RACE_ABOVE) . ',' . RACE_TOP);
			while ($row = $query->next())
			{
				$ids[] = (int)$row[0];
			}
		}
		$this->race = race_rating_players(new SQL('TRUE'), $ids);
	}

	protected function show_body()
	{
		show_race($this->race, get_timezone(), array('rating' => true, 'me' => $this->id));
	}
}

$page = new Page();
$page->run(get_label('Competition Chart'));

?>
