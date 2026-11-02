<?php

require_once '../../include/api.php';
require_once '../../include/broadcast.php';

define('CURRENT_VERSION', 0);

// Which tables have a game going on right now. It is the one question a page of broadcasts keeps
// asking while somebody has it open: the squares there carry a light that is green at a table that
// is playing and red at a table between games, and a game turns over every twenty minutes all
// evening - see include/broadcast.php.
//
// It is asked about the same thing the page is showing, and so it takes the same parameter the
// page was opened with: a tournament, a round, a club, a league, a series, an address, or nothing
// at all for the whole site. That is why the answer names the tables rather than numbering them -
// table 1 of one tournament and table 1 of another are both on the page of their club.
//
// Deliberately small. The question is asked by every reader of the page every few seconds, and
// the answer it needs is a handful of names; api/get/current_game.php answers the same thing for
// one table, but it builds the whole game to do it.
class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		$playing = array_keys(stream_live_keys(broadcast_request_scope()->condition));
		sort($playing);
		$this->response['playing'] = $playing;
	}

	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE, 'The tables where a game is being played right now.');
		$help->request_param('tournament_id', 'Tournament id. Every round of it is looked at, because a table keeps its number from round to round.', 'one of the other ids is used.');
		$help->request_param('event_id', 'Event id. Only the games of this event are looked at.', 'one of the other ids is used.');
		$help->request_param('club_id', 'Club id: everything the club is playing right now.', 'one of the other ids is used.');
		$help->request_param('league_id', 'League id: everything in the tournaments of the league\'s series.', 'one of the other ids is used.');
		$help->request_param('series_id', 'Series id: everything in the tournaments of the series and of its subseries.', 'one of the other ids is used.');
		$help->request_param('address_id', 'Address id: everything being played in that hall.', 'one of the other ids is used.');
		$help->response_param('playing', 'The tables with a game under way, each named by what it belongs to and by its number, counting from 1: "t636-2" is table 2 of tournament 636, "e15359-1" table 1 of an event played on its own. A round of a tournament is named by its tournament, because the tables are kept from round to round. A game is under way from the moment the roles start being handed out to the moment it is played out: a table where the screen is open but nothing has happened yet, and one where the game is over and the referee is entering the bonus points, are not among them.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Tables Being Played At', CURRENT_VERSION);

?>
