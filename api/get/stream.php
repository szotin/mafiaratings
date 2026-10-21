<?php

require_once '../../include/api.php';
require_once '../../include/stream.php';

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

		$this->response['stream'] = $stream;
	}

	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE, 'How the between-games screens are set up at one table of a tournament.');
		$help->request_param('tournament_id', 'Tournament id.', 'event_id is used.');
		$help->request_param('event_id', 'Event id. When it is a round of a tournament, the settings of the tournament are returned; when it is an event of its own, its own.', 'tournament_id is used.');
		$help->request_param('table_num', 'Table number.', '1 is used.');
		$param = $help->response_param('stream', 'How the screens at this table are set up.');
			$param->sub_param('screens', 'The screens to show, in the order they are shown, as the pages they are fetched from.');
			$param->sub_param('fallback', 'The page to put up when every screen had nothing to say.');
			$param->sub_param('delay', 'How long one screen stays up, in milliseconds.');
			$param->sub_param('locale', 'The language the screens are shown in.');
			$param->sub_param('background', 'The ground they stand on: "dark" or "light".');
			$param->sub_param('transparent', '1 when nothing is painted behind them, 0 otherwise.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Between-games screen settings', CURRENT_VERSION);

?>
