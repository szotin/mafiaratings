<?php

require_once '../../include/api.php';
require_once '../../include/stream.php';

define('CURRENT_VERSION', 0);

// Which tables of a tournament, or of an event played on its own, have a game going on right
// now. It is the one question the page of broadcasts keeps asking while somebody has it open:
// the squares there carry a light that is green at a table that is playing and red at a table
// between games, and a game turns over every twenty minutes all evening - see
// include/broadcast.php.
//
// Deliberately small. The question is asked by every reader of the page every few seconds, and
// the answer it needs is a handful of numbers; api/get/current_game.php answers the same thing
// for one table, but it builds the whole game to do it.
class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		$tournament_id = (int)get_optional_param('tournament_id', 0);
		$event_id = (int)get_optional_param('event_id', 0);
		if ($tournament_id <= 0 && $event_id <= 0)
		{
			throw new Exc('One of event_id or tournament_id must be set.');
		}

		// A round is asked about as a round. A tournament keeps its tables from round to round,
		// so asking about the tournament would answer for whichever round is being played, and
		// the page of this one would light up for games at another - see stream_live_tables().
		$tables = array_keys(stream_live_tables($tournament_id, $event_id));
		sort($tables);
		$this->response['tables'] = $tables;
	}

	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE, 'The tables where a game is being played right now.');
		$help->request_param('tournament_id', 'Tournament id. Every round of it is looked at, because a table keeps its number from round to round.', 'event_id is used.');
		$help->request_param('event_id', 'Event id. Only the games of this event are looked at.', 'tournament_id is used.');
		$help->response_param('tables', 'Table numbers, starting from 1, of the tables with a game under way: from the moment the roles start being handed out to the moment the game is played out. A table where the screen is open but nothing has happened yet, and one where the game is over and the referee is entering the bonus points, are not among them.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Tables Being Played At', CURRENT_VERSION);

?>
