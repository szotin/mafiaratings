<?php

require_once '../../include/api.php';
require_once '../../include/stream.php';
require_once '../../include/broadcast.php';

define('CURRENT_VERSION', 0);

// How the between-games screens are set up at one table. A browser source in OBS is pointed at
// a tournament and a table and nothing else; this is what it asks, every few seconds, to know
// what to show and how - so a change made on the site reaches a source that is already running.
class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		$table_num = max((int)get_optional_param('table_num', 1), 1);

		// A source may be pointed at a round rather than at the tournament, and the settings
		// belong to the tournament either way - a table keeps its look as the rounds go by. An
		// event played on its own has settings of its own.
		$owner = stream_owner(get_optional_param('tournament_id', 0), get_optional_param('event_id', 0));
		if (is_null($owner))
		{
			throw new Exc(get_label('Unknown [0]', get_label('tournament')));
		}

		// Word from the machine streaming this table. Only a source running inside OBS sends
		// it: the moderator's screen asks the same question from a different machine, and
		// counting that would paper over the very silence we are listening for.
		//
		// It carries what OBS has been saying about its stream, the part it could not deliver
		// at the time included, because being able to reach this site and being able to reach
		// YouTube are two different things. See include/broadcast.php.
		if ((int)get_optional_param('obs', 0) != 0)
		{
			broadcast_note_heartbeat($owner, $table_num, get_optional_param('seen', ''));

			// And, when OBS has just gone on the air and the overlay saw it happen, the moment
			// it did. That is where the recording begins, and the broadcast's own start is that
			// same moment plus however long the stream is delayed - see include/broadcast.php.
			broadcast_note_stream_start($owner, $table_num, get_optional_param('started', ''));
		}
		else
		{
			// Asked from somewhere other than the streaming machine - above all the
			// moderator's own screen, which polls this every few seconds all through a round.
			// That makes it the place to keep what we know about this table's broadcasts up to
			// date, and it is what lets a game be tied to its recording as it is written down:
			// until a broadcast has said when it went on the air there is nothing to measure
			// from. How often it is actually gone out for is settled in broadcast_list().
			//
			// Deliberately not done on the heartbeat above. That one is how long this table
			// was off the air gets measured, and a slow answer from the broadcast would sit
			// inside it and be read as a break in the stream that never happened.
			// A source pointed at the tournament does not say which round is being played, and
			// that is most of them - a table keeps one address from the first round to the last.
			$event_id = (int)get_optional_param('event_id', 0);
			if ($event_id <= 0)
			{
				$event_id = broadcast_current_event($owner, $table_num);
			}
			if ($event_id > 0)
			{
				broadcast_list($event_id, $table_num);
			}
		}

		$settings = stream_settings($owner, $table_num);
		$all = stream_screens();

		// Everything the browser source needs to put a screen up, in the shape it uses it: the
		// pages to fetch rather than their short names, and the delay in milliseconds.
		$stream = new stdClass();
		$stream->screens = array();
		foreach ($settings->screens as $name)
		{
			$stream->screens[] = $all[$name];
		}
		// Put up when every one of them had nothing to say, so that the source is never a blank
		// rectangle.
		$stream->fallback = 'screen_title.php';
		$stream->delay = $settings->delay * 1000;
		$stream->locale = get_lang_code($settings->lang_resolved);
		$stream->background = ($settings->flags & STREAM_FLAG_DARK) ? 'dark' : 'light';
		$stream->transparent = ($settings->flags & STREAM_FLAG_TRANSPARENT) ? 1 : 0;
		// Whether this table is showing anything at all. Asked for by the sources themselves
		// rather than settled when they were addressed, so that a table can be made bare and
		// brought back without anybody going to the machine OBS is running on.
		$stream->hideGlyphs = ($settings->flags & STREAM_FLAG_HIDE_GLYPHS) ? 1 : 0;
		$stream->hideBetween = ($settings->flags & STREAM_FLAG_HIDE_BETWEEN) ? 1 : 0;

		// Which OBS scene this table should be showing. A browser source can change the scene
		// itself, so these are what it needs to know: what the game is asking for as it is
		// played, and what somebody has asked for by hand. The order from a person carries the
		// time it was given, which is how a source tells a new one from the one it has already
		// carried out - it stands until the game moves the automatic scene on by itself.
		$stream->scene = new stdClass();
		$stream->scene->auto = stream_played_scene($owner, $table_num, $settings);
		$stream->scene->manual = $settings->scene;
		$stream->scene->manualTime = $settings->scene_time;
		// And what somebody could ask for. The moderator's screen offers these as buttons when
		// the game is not switching the scenes by itself, so that the scene can be changed
		// without leaving the game for OBS.
		$stream->scene->names = $settings->scenes;
		$stream->scene->switching = (($settings->flags & STREAM_FLAG_NO_AUTO_SCENE) == 0) ? 1 : 0;

		$this->response['stream'] = $stream;
	}

	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE, 'How the between-games screens are set up at one table of a tournament.');
		$help->request_param('tournament_id', 'Tournament id.', 'event_id is used.');
		$help->request_param('event_id', 'Event id. When it is a round of a tournament, the settings of the tournament are returned; when it is an event of its own, its own.', 'tournament_id is used.');
		$help->request_param('table_num', 'Table number.', '1 is used.');
		$help->request_param('obs', 'Set to 1 by a browser source running inside the OBS that is streaming this table. It is what tells the site the machine is on the air.', 'the request is not counted as word from the streaming machine.');
		$help->request_param('started', 'How long ago, in seconds, OBS went on the air at this table. Sent together with obs=1, and only for a start the overlay watched happen: it is what the delay of the stream is measured from.', 'nothing is said about when the stream began.');
		$help->request_param('seen', 'What OBS has been saying about its stream, as a comma separated list of &lt;started&gt;-&lt;ended&gt;:&lt;on the air&gt;, where the first two are ages in seconds and the last is 1 or 0. Sent together with obs=1, and carrying whatever could not be delivered earlier.', 'nothing is known beyond the request itself.');
		$param = $help->response_param('stream', 'How the screens at this table are set up.');
			$param->sub_param('screens', 'The screens to show, in the order they are shown, as the pages they are fetched from.');
			$param->sub_param('fallback', 'The page to put up when every screen had nothing to say.');
			$param->sub_param('delay', 'How long one screen stays up, in milliseconds.');
			$param->sub_param('locale', 'The language the screens are shown in.');
			$param->sub_param('background', 'The ground they stand on: "dark" or "light".');
			$param->sub_param('transparent', '1 when nothing is painted behind them, 0 otherwise.');
			$param->sub_param('hideGlyphs', '1 when the sources carrying the cards over the game are to show nothing at all, 0 otherwise.');
			$param->sub_param('hideBetween', '1 when the source filling the time between games is to show nothing at all, 0 otherwise.');
			$param->sub_param('scene', 'Which OBS scene the table should be showing. "auto" is what the game being played is asking for, "manual" what somebody asked for by hand, and "manualTime" when they asked. A browser source follows the manual one when its time is newer than the last it carried out, and the automatic one whenever that changes. "names" are all the scenes the OBS at this table reported, and "switching" is 1 when the scenes are changed by the phase of the game and 0 when they are left to be changed by hand.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Between-games screen settings', CURRENT_VERSION);

?>
