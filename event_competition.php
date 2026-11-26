<?php

require_once 'include/event.php';
require_once 'include/general_page_base.php';
require_once 'include/scoring.php';
require_once 'include/picture.php';
require_once 'include/race.php';

// The race of a game day: its top twenty after every game, played back.
class Page extends EventPageBase
{
	private $race;

	protected function prepare()
	{
		parent::prepare();

		list($scoring) = Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->scoring_id, $this->scoring_version);
		$scoring = json_decode($scoring);
		$scoring->id = (int)$this->scoring_id; // it is needed for caching
		$scoring->version = (int)$this->scoring_version;
		$players = event_scores($this->id, NULL, SCORING_LOD_HISTORY, $scoring, json_decode($this->scoring_options), $this->tournament_flags, $this->round_num);

		$pic =
			new Picture(USER_EVENT_PICTURE,
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic)));
		$id = $this->id;
		$tournament_id = $this->tournament_id;
		$club_id = $this->club_id;
		$params = '&id=' . $this->id . $this->show_all;
		$this->race = race_scoring_players($players,
			function($p) use ($pic, $id, $tournament_id, $club_id)
			{
				$pic->
					set($p->id, $p->nickname, $p->event_reg_flags, 'e' . $id)->
					set($p->id, $p->name, $p->tournament_reg_flags, 't' . $tournament_id)->
					set($p->id, $p->name, $p->club_reg_flags, 'c' . $club_id)->
					set($p->id, $p->name, $p->flags);
				return card_avatar_of($pic, $p->id, $p->name, '');
			},
			function($p) use ($params)
			{
				return 'event_player.php?bck=1&user_id=' . $p->id . $params;
			});
	}

	protected function show_body()
	{
		if (!$this->show_hidden_table_message())
		{
			return;
		}
		show_race($this->race, $this->timezone, array('step' => get_label('Game [0] of [1]')));
	}
}

$page = new Page();
$page->run(get_label('Competition Chart'));

?>
