<?php

require_once '../../include/api.php';
require_once '../../include/stream.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// set
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

		if ($owner->is_tournament)
		{
			list ($club_id) = Db::record(get_label('tournament'), 'SELECT club_id FROM tournaments WHERE id = ?', $owner->id);
			check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $owner->id);
		}
		else
		{
			list ($club_id) = Db::record(get_label('event'), 'SELECT club_id FROM events WHERE id = ?', $owner->id);
			check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER, $club_id, $owner->id);
		}

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

			Db::exec(get_label('event'),
				'INSERT INTO ' . $owner->table . ' (' . $owner->column . ', table_num, lang, delay, screens, flags) VALUES (?, ?, ?, ?, ?, ?)'.
				' ON DUPLICATE KEY UPDATE lang = ?, delay = ?, screens = ?, flags = ?',
				$owner->id, $table, $lang, $delay, $screens, $flags,
				$lang, $delay, $screens, $flags);
		}
	}

	function set_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_EVENT_MANAGER, 'Sets up the between-games screens at a table. Only the settings named in the request are changed; the rest are left as they were.');
		$help->request_param('tournament_id', 'Tournament id.', 'event_id is used.');
		$help->request_param('event_id', 'Event id. A round of a tournament sets up the tournament; an event of its own sets up itself.', 'tournament_id is used.');
		$help->request_param('table_num', 'Table number. 0 is every table there is.');
		$help->request_param('lang', 'Language of the screens: 1 english, 2 russian, 4 ukrainian. 0 is the language the games are played in.', 'left as it is.');
		$help->request_param('delay', 'Seconds one screen stays up.', 'left as it is.');
		$help->request_param('screens', 'Which screens to show and in what order, by their short names, separated by commas: results, upcoming, standings, stats.', 'left as it is.');
		$help->request_param('dark', '1 draws the screens on the dark wall instead of the pale ground they get by default.', 'left as it is.');
		$help->request_param('transparent', '1 paints no ground at all, so the scene behind the browser source shows through.', 'left as it is.');
		$help->request_param('hide_roles', '1 keeps the roles off the overlays. It belongs to the tournament rather than to one table, so it is set for the whole of it and every round follows.', 'left as it is.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Between-games screen operations', CURRENT_VERSION);

?>
