<?php

require_once '../../include/api.php';
require_once '../../include/stream.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// set
	// Who may set a table up: whoever runs the thing the table belongs to.
	// Who may set a stream up: the managers, and the referees with them. Running the stream is
	// usually the referees' job, so the page and everything it writes through are open to them.
	private function check_owner_permissions($owner)
	{
		if ($owner->is_tournament)
		{
			list ($club_id) = Db::record(get_label('tournament'), 'SELECT club_id FROM tournaments WHERE id = ?', $owner->id);
			check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $owner->id);
		}
		else
		{
			list ($club_id) = Db::record(get_label('event'), 'SELECT club_id FROM events WHERE id = ?', $owner->id);
			check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE, $club_id, $owner->id);
		}
	}

	// Whether the roles are shown is decided for the whole thing rather than at a table, so it
	// is done once. A tournament hands it down to every round of it, the same as
	// api/ops/tournament.php does when the tournament itself is edited; an event of its own
	// has only itself to set.
	private function set_roles($owner)
	{
		if (!isset($_REQUEST['hide_roles']))
		{
			return;
		}
		$hide = ((int)$_REQUEST['hide_roles'] != 0);
		if (!$owner->is_tournament)
		{
			$set = $hide ? ('flags | ' . EVENT_FLAG_HIDE_ROLES) : ('flags & ~' . EVENT_FLAG_HIDE_ROLES);
			Db::exec(get_label('event'), 'UPDATE events SET flags = ' . $set . ' WHERE id = ?', $owner->id);
			return;
		}

		list ($flags) = Db::record(get_label('tournament'), 'SELECT flags FROM tournaments WHERE id = ?', $owner->id);
		$flags = (int)$flags;
		if ($hide == (($flags & TOURNAMENT_FLAG_HIDE_ROLES) != 0))
		{
			return;
		}

		$flags = $hide ? ($flags | TOURNAMENT_FLAG_HIDE_ROLES) : ($flags & ~TOURNAMENT_FLAG_HIDE_ROLES);
		Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = ? WHERE id = ?', $flags, $owner->id);
		$set = $hide ? ('flags | ' . EVENT_FLAG_HIDE_ROLES) : ('flags & ~' . EVENT_FLAG_HIDE_ROLES);
		Db::exec(get_label('round'), 'UPDATE events SET flags = ' . $set . ' WHERE tournament_id = ?', $owner->id);
	}

	//-------------------------------------------------------------------------------------------------------
	// scene
	//-------------------------------------------------------------------------------------------------------
	// An order to show a scene, given by a person rather than by the game. The moderator's own
	// screen switches the scenes by the phase of the game; this is for when nobody is doing
	// that - the moderator is not using the automatic switching, or forgot - and somebody at
	// the commentary desk or watching the tournament switches by hand. It stands until the
	// game moves the automatic scene on by itself.
	function scene_op()
	{
		$table_num = max((int)get_required_param('table_num'), 1);
		$scene = trim(get_required_param('scene'));
		$owner = stream_owner(get_optional_param('tournament_id', 0), get_optional_param('event_id', 0));
		if (is_null($owner))
		{
			throw new Exc(get_label('Unknown [0]', get_label('tournament')));
		}
		$this->check_owner_permissions($owner);

		$settings = stream_settings($owner, $table_num);
		Db::exec(get_label('event'),
			'INSERT INTO ' . $owner->table . ' (' . $owner->column . ', table_num, lang, delay, screens, flags, scene, scene_time)'.
			' VALUES (?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP())'.
			' ON DUPLICATE KEY UPDATE scene = ?, scene_time = UNIX_TIMESTAMP()',
			$owner->id, $table_num, $settings->lang, $settings->delay, implode(',', $settings->screens), $settings->flags, $scene,
			$scene);
	}

	function scene_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE | PERMISSION_EVENT_REFEREE, 'Asks the browser sources at a table to show an OBS scene. It stands until the game being played asks for a different one by itself.');
		$help->request_param('tournament_id', 'Tournament id.', 'event_id is used.');
		$help->request_param('event_id', 'Event id.', 'tournament_id is used.');
		$help->request_param('table_num', 'Table number.');
		$help->request_param('scene', 'Name of the OBS scene to show.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// scene_events
	//-------------------------------------------------------------------------------------------------------
	// Which scene the game itself asks for in each of its phases. The game says nothing but
	// what is happening at the table; the site works out from this which scene that comes to,
	// and the browser sources ask it and do the switching. It belongs to the table rather than
	// to whoever is moderating at it, because the scenes it names are the scenes of the OBS
	// standing beside that table.
	function scene_events_op()
	{
		$table_num = max((int)get_required_param('table_num'), 1);
		$owner = stream_owner(get_optional_param('tournament_id', 0), get_optional_param('event_id', 0));
		if (is_null($owner))
		{
			throw new Exc(get_label('Unknown [0]', get_label('tournament')));
		}
		$this->check_owner_permissions($owner);

		// Read back through the same parser the settings are read with, so that whatever is
		// stored is something we would hand to the game screen - a pair we would drop there is
		// dropped here instead of being kept and quietly ignored for ever after.
		$events = stream_parse_scene_events(get_optional_param('scene_events', '[]'));
		if (count($events) > 64)
		{
			$events = array_slice($events, 0, 64);
		}
		$events = json_encode($events);

		$settings = stream_settings($owner, $table_num);
		Db::exec(get_label('event'),
			'INSERT INTO ' . $owner->table . ' (' . $owner->column . ', table_num, lang, delay, screens, flags, scene_events)'.
			' VALUES (?, ?, ?, ?, ?, ?, ?)'.
			' ON DUPLICATE KEY UPDATE scene_events = ?',
			$owner->id, $table_num, $settings->lang, $settings->delay, implode(',', $settings->screens), $settings->flags, $events,
			$events);
	}

	function scene_events_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE | PERMISSION_EVENT_REFEREE, 'Which OBS scene the game asks for in each of its phases at one table. The moderator screen reads it when a game is opened.');
		$help->request_param('tournament_id', 'Tournament id.', 'event_id is used.');
		$help->request_param('event_id', 'Event id.', 'tournament_id is used.');
		$help->request_param('table_num', 'Table number.');
		$help->request_param('scene_events', 'Json array of {"scene": name, "event": phase} pairs. The phases are "day", "night", "start", "end", "arrangement", "relaxed sitting", "voting", "shooting", "don", "sheriff" and "1" to "10" for the seat that is speaking.', 'the table is left with no automatic switching.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// scenes
	//-------------------------------------------------------------------------------------------------------
	// What a browser source reports about the OBS it is running in. Nobody is logged in there -
	// the overlays are open to anyone who has the address, the same as the pages they show - so
	// this is not behind a permission. All it can do is change the list of names the settings
	// page offers and what it says about the source's permissions, so it is kept short and
	// plain and nothing is done with it but showing it.
	function scenes_op()
	{
		$table_num = max((int)get_required_param('table_num'), 1);
		$owner = stream_owner(get_optional_param('tournament_id', 0), get_optional_param('event_id', 0));
		if (is_null($owner))
		{
			throw new Exc(get_label('Unknown [0]', get_label('tournament')));
		}

		$control_level = (int)get_optional_param('control_level', -1);
		$scenes = json_decode(get_optional_param('scenes', '[]'));
		$names = array();
		if (is_array($scenes))
		{
			foreach ($scenes as $name)
			{
				if (is_string($name) && $name != '' && mb_strlen($name) <= 128 && !in_array($name, $names))
				{
					$names[] = $name;
				}
				if (count($names) >= 64)
				{
					break;
				}
			}
		}

		$settings = stream_settings($owner, $table_num);
		$names = json_encode($names);
		Db::exec(get_label('event'),
			'INSERT INTO ' . $owner->table . ' (' . $owner->column . ', table_num, lang, delay, screens, flags, scenes, control_level)'.
			' VALUES (?, ?, ?, ?, ?, ?, ?, ?)'.
			' ON DUPLICATE KEY UPDATE scenes = ?, control_level = ?',
			$owner->id, $table_num, $settings->lang, $settings->delay, implode(',', $settings->screens), $settings->flags, $names, $control_level,
			$names, $control_level);
	}

	function scenes_op_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE, 'What a browser source reports about the OBS it runs in, so that the stream settings page has scenes to offer and can say when a source is not allowed to switch them.');
		$help->request_param('tournament_id', 'Tournament id.', 'event_id is used.');
		$help->request_param('event_id', 'Event id.', 'tournament_id is used.');
		$help->request_param('table_num', 'Table number.');
		$help->request_param('scenes', 'Json array of the scene names in that OBS.', 'nothing is reported.');
		$help->request_param('control_level', "What the source is allowed to do, as obs-browser counts it. 4 and up is what changing a scene needs.", '-1, which is "not known".');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// Only what was asked for is changed, and everything else the table was set up with stays
	// as it was. That is what lets one setting be sent to every table at once without carrying
	// the other settings of whichever table the sender happened to be looking at.
	function set_op()
	{
		$table_num = (int)get_required_param('table_num');
		$owner = stream_owner(get_optional_param('tournament_id', 0), get_optional_param('event_id', 0));
		if (is_null($owner))
		{
			throw new Exc(get_label('Unknown [0]', get_label('tournament')));
		}

		$this->check_owner_permissions($owner);
		$this->set_roles($owner);

		// Table 0 is every table there is.
		$tables = array();
		if ($table_num > 0)
		{
			$tables[] = $table_num;
		}
		else
		{
			$count = stream_table_count($owner);
			for ($table = 1; $table <= $count; ++$table)
			{
				$tables[] = $table;
			}
		}

		foreach ($tables as $table)
		{
			$settings = stream_settings($owner, $table);
			$lang = $settings->lang;
			$delay = $settings->delay;
			$screens = implode(',', $settings->screens);
			$flags = $settings->flags;

			if (isset($_REQUEST['lang']))
			{
				$lang = (int)$_REQUEST['lang'];
				if ($lang != LANG_ENGLISH && $lang != LANG_RUSSIAN && $lang != LANG_UKRAINIAN)
				{
					$lang = 0; // nothing in particular, so the tournament's own language is used
				}
			}
			if (isset($_REQUEST['delay']))
			{
				$delay = min(max((int)$_REQUEST['delay'], STREAM_MIN_DELAY), STREAM_MAX_DELAY);
			}
			if (isset($_REQUEST['screens']))
			{
				// Stored the way it came in, minus anything we do not know and anything said
				// twice. All four of them are still written out in full rather than as an
				// empty list: empty is read back as the usual order, so shortening it that way
				// would throw away the order they were put in.
				$screens = implode(',', stream_parse_screens($_REQUEST['screens']));
			}
			if (isset($_REQUEST['dark']))
			{
				$flags = ((int)$_REQUEST['dark'] != 0) ? ($flags | STREAM_FLAG_DARK) : ($flags & ~STREAM_FLAG_DARK);
			}
			if (isset($_REQUEST['transparent']))
			{
				$flags = ((int)$_REQUEST['transparent'] != 0) ? ($flags | STREAM_FLAG_TRANSPARENT) : ($flags & ~STREAM_FLAG_TRANSPARENT);
			}
			if (isset($_REQUEST['hide_glyphs']))
			{
				$flags = ((int)$_REQUEST['hide_glyphs'] != 0) ? ($flags | STREAM_FLAG_HIDE_GLYPHS) : ($flags & ~STREAM_FLAG_HIDE_GLYPHS);
			}
			if (isset($_REQUEST['hide_between']))
			{
				$flags = ((int)$_REQUEST['hide_between'] != 0) ? ($flags | STREAM_FLAG_HIDE_BETWEEN) : ($flags & ~STREAM_FLAG_HIDE_BETWEEN);
			}
			// Asked for the right way round and stored the other, so that a table nobody has
			// said anything about switches by itself, which is what it did before there was
			// anything to say. What is set up against the phases is left untouched either way:
			// turning it off is turning it off, not throwing it away.
			if (isset($_REQUEST['auto_scenes']))
			{
				$flags = ((int)$_REQUEST['auto_scenes'] != 0) ? ($flags & ~STREAM_FLAG_NO_AUTO_SCENE) : ($flags | STREAM_FLAG_NO_AUTO_SCENE);
			}

			Db::exec(get_label('event'),
				'INSERT INTO ' . $owner->table . ' (' . $owner->column . ', table_num, lang, delay, screens, flags) VALUES (?, ?, ?, ?, ?, ?)'.
				' ON DUPLICATE KEY UPDATE lang = ?, delay = ?, screens = ?, flags = ?',
				$owner->id, $table, $lang, $delay, $screens, $flags,
				$lang, $delay, $screens, $flags);
		}
	}

	function set_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE | PERMISSION_EVENT_REFEREE, 'Sets up the between-games screens at a table. Only the settings named in the request are changed; the rest are left as they were.');
		$help->request_param('tournament_id', 'Tournament id.', 'event_id is used.');
		$help->request_param('event_id', 'Event id. A round of a tournament sets up the tournament; an event of its own sets up itself.', 'tournament_id is used.');
		$help->request_param('table_num', 'Table number. 0 is every table there is.');
		$help->request_param('lang', 'Language of the screens: 1 english, 2 russian, 4 ukrainian. 0 is the language the games are played in.', 'left as it is.');
		$help->request_param('delay', 'Seconds one screen stays up.', 'left as it is.');
		$help->request_param('screens', 'Which screens to show and in what order, by their short names, separated by commas: results, upcoming, standings, stats.', 'left as it is.');
		$help->request_param('dark', '1 draws the screens on the dark wall instead of the pale ground they get by default.', 'left as it is.');
		$help->request_param('transparent', '1 paints no ground at all, so the scene behind the browser source shows through.', 'left as it is.');
		$help->request_param('hide_glyphs', '1 shows nothing at all on the sources that carry the cards over the game. They go on following the game behind it, so 0 puts up what is happening then rather than what was happening when they were switched off.', 'left as it is.');
		$help->request_param('hide_between', '1 shows nothing at all on the source that fills the time between games.', 'left as it is.');
		$help->request_param('auto_scenes', '1 switches the OBS scenes at this table by the phase of the game being played, 0 leaves them to be switched by hand. What is set up against the phases is kept either way.', 'left as it is.');
		$help->request_param('hide_roles', '1 keeps the roles off the overlays. It belongs to the tournament rather than to one table, so it is set for the whole of it and every round follows.', 'left as it is.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Between-games screen operations', CURRENT_VERSION);

?>
