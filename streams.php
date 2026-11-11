<?php

require_once 'include/general_page_base.php';
require_once 'include/tournament.php';
require_once 'include/event.php';
require_once 'include/stream.php';
require_once 'include/broadcast_table.php';
require_once 'include/table_hiding.php';

// How the sources for a stream are set up, a tab per table: the addresses to point OBS at, and
// everything the between-games screens do. The source is pointed at a table and nothing else,
// so what is set here reaches the screens by itself - they ask the site what to do every few
// seconds. Nobody has to retype an address in OBS, or even be at the machine it runs on.
//
// The first tab is every table at once. Only the setting that is touched there is sent, and it
// goes to every table; whatever else each table was set up with stays as it was.
//
// The page hangs under a tournament or under an event, and should wear whatever it hangs
// under. The body is the same either way, so the class it extends is the only thing that
// differs. The bases want the thing's id in "id", so it is handed over here and the links
// go on saying which of the two they mean.
if (isset($_REQUEST['event_id']) && (int)$_REQUEST['event_id'] > 0)
{
	define('STREAMS_UNDER_EVENT', true);
	$_REQUEST['id'] = (int)$_REQUEST['event_id'];
	class StreamsPageBase extends EventPageBase {}
}
else
{
	define('STREAMS_UNDER_EVENT', false);
	if (isset($_REQUEST['tournament_id']))
	{
		$_REQUEST['id'] = (int)$_REQUEST['tournament_id'];
	}
	class StreamsPageBase extends TournamentPageBase {}
}

define('VIEW_ALL_TABLES', 0);

class Page extends StreamsPageBase
{
	private $owner;  // the tournament or the event the tables belong to
	private $view;   // which tab is open: a table, or every table at once
	private $tables; // how many tables are played at, as far as anything says so
	private $spare;  // whether a tab is kept past them for a table nobody has counted yet
	private $rounds; // the rounds whose broadcasts are set up at the bottom of a tab

	protected function prepare()
	{
		parent::prepare();

		// A round of a tournament is set up through its tournament: the table keeps its look
		// from round to round, so there is one set of settings rather than one per round.
		$this->owner = STREAMS_UNDER_EVENT ? stream_owner(0, $this->id) : stream_tournament($this->id);
		$this->tables = stream_table_count($this->owner);

		// A seating says how many tables there are, so where there is one the tabs are the
		// tables and that is the end of it. Where there is none - nothing seated yet, or an
		// event that never seats anything - there is no telling, and a spare tab is kept at
		// the end so that a table can be set up before anybody has played at it.
		$this->spare = (stream_seated_tables($this->owner) <= 0);
		$last = $this->tables + ($this->spare ? 1 : 0);

		// The rounds whose recordings are listed under everything else. A tournament has its
		// rounds, in the order they are played - the main ones first and the finals after them,
		// nearest last, which is the order the preparation page walks them in. An event standing
		// on its own is the one round there is. Each carries how many tables it is played at, so
		// that a round played at one table is not offered on the tab of the second.
		$this->rounds = array();
		$query = STREAMS_UNDER_EVENT
			? new DbQuery('SELECT id, name, round FROM events WHERE id = ?', $this->id)
			: new DbQuery('SELECT id, name, round FROM events WHERE tournament_id = ? ORDER BY round, start_time, id', $this->id);
		$finals = array();
		while ($row = $query->next())
		{
			list ($event_id, $name, $round_num) = $row;
			$event = new stdClass();
			$event->id = (int)$event_id;
			$event->name = ($name == '') ? get_round_name((int)$round_num) : $name;
			$event->tables = stream_event_tables((int)$event_id);
			if ((int)$round_num == 0)
			{
				$this->rounds[] = $event;
			}
			else
			{
				$finals[] = $event;
			}
		}
		for ($i = count($finals) - 1; $i >= 0; --$i)
		{
			$this->rounds[] = $finals[$i];
		}

		$this->view = VIEW_ALL_TABLES;
		if (isset($_REQUEST['view']))
		{
			$this->view = (int)$_REQUEST['view'];
			if ($this->view < VIEW_ALL_TABLES || $this->view > $last)
			{
				$this->view = VIEW_ALL_TABLES;
			}
		}
	}

	// What the form shows for one table: one value per control rather than the flags word the
	// settings are stored in, since the controls are told apart on the first tab one by one.
	//
	// The language is the one the screens actually come up in, not whether it was named at the
	// table or taken from the tournament - two tables speaking the same language agree,
	// however each of them came by it.
	private function form_settings($settings)
	{
		$form = new stdClass();
		$form->lang = $settings->lang_resolved;
		$form->delay = $settings->delay;
		$form->screens = $settings->screens;
		$form->dark = ($settings->flags & STREAM_FLAG_DARK) ? 1 : 0;
		$form->transparent = ($settings->flags & STREAM_FLAG_TRANSPARENT) ? 1 : 0;
		// Asked the way every other switch on the page is asked - as a thing to show - though
		// they are kept the other way round, so that a table nobody has set up shows its
		// sources rather than hiding them.
		$form->show_glyphs = ($settings->flags & STREAM_FLAG_HIDE_GLYPHS) ? 0 : 1;
		$form->show_between = ($settings->flags & STREAM_FLAG_HIDE_BETWEEN) ? 0 : 1;

		// The parts of the cards source, asked about the way round the page asks and stored the
		// other - see STREAM_FLAG_HIDE_PHASE. A switch standing at yes is a part that is drawn.
		foreach (stream_glyph_parts() as $name => $part)
		{
			$key = 'show_' . $name;
			$form->$key = ($settings->flags & $part['flag']) ? 0 : 1;
		}
		return $form;
	}

	// The first tab has no table of its own, so what it shows is whatever every table agrees
	// on, and nothing where they disagree. Each control is asked separately: tables that are
	// set up differently in one thing may well agree in all the others, and saying so about
	// every one of them would be no help to anybody.
	private function all_tables_settings()
	{
		$all = NULL;
		for ($table = 1; $table <= $this->tables; ++$table)
		{
			$form = $this->form_settings(stream_settings($this->owner, $table));
			if (is_null($all))
			{
				$all = $form;
				continue;
			}

			// NULL is "the tables do not agree", which the form shows as nothing chosen rather
			// than as some table's answer standing in for the rest.
			$named = array('lang', 'delay', 'screens', 'dark', 'transparent', 'show_glyphs', 'show_between');
			foreach (array_keys(stream_glyph_parts()) as $part)
			{
				$named[] = 'show_' . $part;
			}
			foreach ($named as $name)
			{
				if (!is_null($all->$name) && $all->$name !== $form->$name)
				{
					$all->$name = NULL;
				}
			}
		}
		return $all;
	}

	// A section of settings. The columns are nailed down rather than worked out from what is
	// in them, because rows are put away and brought back while the page stands there - see
	// toggle() - and a column that measured itself against the rows would shift the whole
	// section every time one of them went. It also lines the sections up with each other.
	private function open_settings_table()
	{
		echo '<table class="bordered light stream-settings" width="100%">';
		echo '<colgroup><col class="stream-label"><col></colgroup>';
	}

	// $group names a set of rows that stand or fall together - see toggle(), which can put
	// one away when there is no longer anything for it to say. $level is how deep this row
	// stands under the switches above it, which is said by setting it in - see stream-lvl1
	// in common.css.
	private function show_setting($name, $content, $group = NULL, $level = 0)
	{
		echo '<tr' . (is_null($group) ? '' : ' class="' . $group . '"') . '>' .
			'<td class="dark" align="right">' . $name . ':</td>' .
			'<td' . ($level > 0 ? ' class="stream-lvl' . $level . '"' : '') . '>' . $content . '</td></tr>';
	}

	// A switch that stands over the rows under it and puts them away when it is off. It is
	// written as an ordinary row so that the two columns go on lining up, and its name is set
	// in bold, which is the second thing - after where its switch stands - saying that the
	// rows below belong to it.
	private function show_group($name, $content, $group = NULL, $level = 0)
	{
		echo '<tr' . (is_null($group) ? '' : ' class="' . $group . '"') . '>' .
			'<td class="dark stream-group" align="right"><b>' . $name . '</b>:</td>' .
			'<td class="stream-group' . ($level > 0 ? ' stream-lvl' . $level : '') . '">' .
			$content . '</td></tr>';
	}

	// A yes or no that takes effect the moment it is touched. On the tab of all the tables it
	// may be neither, because they do not agree; the browser has a state for exactly that and
	// is put into it on load, and the first touch settles the question for every table.
	//
	// $hides names a group of rows that this switch puts away when it is on, and $shows a group
	// it puts away when it is off - which of the two depends on which way round the switch is
	// asked. Either way the point is the same: what is left of a part that is turned off is how
	// that part would look, and there is nothing to be gained by setting it while there is
	// nothing to look at. What was set stays set and comes back with the rows.
	private function toggle($name, $value, $hides = NULL, $shows = NULL)
	{
		return '<input type="checkbox" class="toggle" id="' . $name . '"' .
			($value === 1 ? ' checked' : '') . (is_null($value) ? ' data-mixed="1"' : '') .
			(is_null($hides) ? '' : ' data-hides="' . $hides . '"') .
			(is_null($shows) ? '' : ' data-shows="' . $shows . '"') .
			' onchange="streamToggled(\'' . $name . '\')">';
	}

	// One of two answers, or neither of them when the tables do not agree about it.
	private function choice($name, $value, $off, $on)
	{
		$html = '<select id="' . $name . '" onchange="streamChanged(\'' . $name . '\')">';
		if (is_null($value))
		{
			$html .= '<option value="" selected>&mdash;</option>';
		}
		$html .= '<option value="0"' . ($value === 0 ? ' selected' : '') . '>' . $off . '</option>';
		$html .= '<option value="1"' . ($value === 1 ? ' selected' : '') . '>' . $on . '</option>';
		return $html . '</select>';
	}

	// The address of a browser source, ready to be taken to OBS: in a box that can be read and
	// selected, with the copy button stuck to the end of it, and the size the source wants.
	private function show_url($name, $id, $url, $size)
	{
		$this->show_setting($name,
			'<span class="stream-url"><input id="' . $id . '" type="text" readonly value="' . htmlspecialchars($url) . '">' .
			'<button title="' . get_label('Copy') . '" onclick="copyUrl(\'' . $id . '\')"><img src="images/copy.png"></button></span> ' .
			$size);
	}

	// Whether a round has anything to do with the table whose tab is open. A seating says how
	// many tables a round is played at, so a final played at one has nothing to say on the tab
	// of the second; where nothing says - no seating, no games, no scheme - every table is
	// offered, because there is no telling. And a table that already has a broadcast of this
	// round against it is always offered, whatever the count says: what is written down has to
	// be there to be corrected.
	private function round_on_table($round, $table)
	{
		if ($round->tables <= 0 || $table <= $round->tables)
		{
			return true;
		}
		list ($written) = Db::record(get_label('broadcast'),
			'SELECT count(*) FROM event_broadcasts WHERE event_id = ? AND table_num = ?', $round->id, $table - 1);
		return (int)$written > 0;
	}

	protected function show_body()
	{
		// The referees as well as the managers: at most tournaments it is the referees who run
		// the stream, and the scene somebody has to switch when the moderator forgets is
		// theirs to switch.
		check_permissions(STREAMS_UNDER_EVENT
			? (PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE)
			: (PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE), $this->club_id, $this->id);

		echo '<div class="tab">';
		echo '<button ' . ($this->view == VIEW_ALL_TABLES ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_ALL_TABLES . '})">' . get_label('All tables') . '</button>';
		for ($table = 1; $table <= $this->tables + ($this->spare ? 1 : 0); ++$table)
		{
			echo '<button ' . ($this->view == $table ? 'class="active" ' : '') . 'onclick="goTo({view:' . $table . '})">' . get_label('Table [0]', $table) . '</button>';
		}
		echo '</div>';

		$table = $this->view;
		$settings = ($table == VIEW_ALL_TABLES)
			? $this->all_tables_settings()
			: $this->form_settings(stream_settings($this->owner, $table));
		$langs = array(LANG_ENGLISH => 'English', LANG_RUSSIAN => 'Русский', LANG_UKRAINIAN => 'Українська');
		$site = get_server_url();
		$scope = (STREAMS_UNDER_EVENT ? 'event_id=' : 'tournament_id=') . $this->id . '&table_num=' . $table;

		echo '<div class="tabcontent">';

		// What belongs to the broadcast of this table as a whole rather than to one of the
		// sources standing on it, in the order a stream reaches for it: the roles, which are
		// turned on the moment a game is over and the cards may be shown; the scene, switched
		// by hand when nobody is switching it by itself; and the language, set once and then
		// left alone.
		echo '<h3 class="stream-section">' . get_label('Broadcast') . '</h3>';
		$this->open_settings_table();

		// Whether the roles are shown is decided for the tournament as a whole and handed down
		// to every round, so it reads the same on every tab and changing it anywhere changes it
		// everywhere. See TOURNAMENT_FLAG_HIDE_ROLES.
		$hide_roles = STREAMS_UNDER_EVENT ? EVENT_FLAG_HIDE_ROLES : TOURNAMENT_FLAG_HIDE_ROLES;
		$show_roles = (($this->flags & $hide_roles) == 0);
		$this->show_setting(get_label('Show roles'),
			'<input type="checkbox" class="toggle" id="roles"' . ($show_roles ? ' checked' : '') .
			' onchange="rolesChanged()">');

		// Whether the games are streamed, for the whole tournament as well. All it changes is the
		// game screen, which takes one more step at the end of the night so that the stream can
		// show the night before the moderator says what came of it. See EVENT_FLAG_STREAMING.
		$streaming_flag = STREAMS_UNDER_EVENT ? EVENT_FLAG_STREAMING : TOURNAMENT_FLAG_STREAMING;
		$this->show_setting(get_label('Video streaming games'),
			'<input type="checkbox" class="toggle" id="streaming"' . (($this->flags & $streaming_flag) ? ' checked' : '') .
			' onchange="streamingChanged()">');

		// Hiding the scoring table from now on - from the tour after the last one played, or
		// altogether before anything is played. It is on whenever the table is hidden by now,
		// including a hiding set up in advance on the preparation page once the tournament has
		// got to it. See is_table_hidden_now() in include/table_hiding.php. A tournament thing,
		// so it is not there for an event of its own, which has no tournament table.
		if (!STREAMS_UNDER_EVENT && ($this->flags & TOURNAMENT_FLAG_FINISHED) == 0)
		{
			$this->show_setting(get_label('Hide the table'),
				'<input type="checkbox" class="toggle" id="hide-table"' . (is_table_hidden_now($this->id, $this->flags) ? ' checked' : '') .
				' onchange="hideTableChanged()">');
		}

		// Switching the scene by hand. The moderator's own screen does it by the phase of the
		// game, so this is for when nobody is - the moderator is not using that, or forgot -
		// and somebody at the commentary desk or watching the tournament switches instead. It
		// stands until the game asks for a different scene by itself.
		//
		// Only on the tab of one table: a scene belongs to the OBS standing beside it, and
		// there is no sense in asking every table to show the same one. And only once that
		// OBS has said what scenes it has - until then there is nothing to choose from, so
		// the row is not there at all. What to do about that is said at the bottom of the
		// tab, under the automatic switching, which is where somebody looking for the scenes
		// goes.
		$stored = ($table == VIEW_ALL_TABLES) ? NULL : stream_settings($this->owner, $table);
		if (!is_null($stored) && !empty($stored->scenes))
		{
			$html = '<select id="scene" onchange="sceneChanged()">';
			foreach ($stored->scenes as $name)
			{
				$html .= '<option value="' . htmlspecialchars($name) . '"' .
					($stored->scene === $name ? ' selected' : '') . '>' . htmlspecialchars($name) . '</option>';
			}
			$html .= '</select>';
			$this->show_setting(get_label('Current scene in OBS Studio'), $html);

			if ($stored->control_level >= 0 && $stored->control_level < STREAM_CONTROL_ADVANCED)
			{
				$this->show_setting('', '<b>' . get_label('The browser source is not allowed to change scenes. Set Page permissions to Advanced in its properties in OBS Studio.') . '</b>');
			}
		}

		// The language everything standing at this table is shown in: the cards, the screens
		// between games and the text broadcast alike, since none of them carries a language in
		// its address any more - see stream_request_lang().
		//
		// A table that has never been given a language of its own is shown with the one the
		// tournament is played in, which is what its screens come up in anyway. There is no
		// entry for "whichever the tournament is" - it is already the one standing there.
		$html = '<select id="lang" onchange="streamChanged(\'lang\')">';
		if (is_null($settings->lang))
		{
			$html .= '<option value="" selected>&mdash;</option>';
		}
		foreach ($langs as $lang => $lang_name)
		{
			$html .= '<option value="' . $lang . '"' . ($settings->lang === $lang ? ' selected' : '') . '>' . $lang_name . '</option>';
		}
		$html .= '</select>';
		$this->show_setting(get_label('Language'), $html);
		echo '</table>';

		// The cards over the game itself: one source, the whole screen, transparent where
		// nothing is drawn. The halves of it - plugins/obs/players.php and gamestats.php, the
		// same drawing with one half left out - are not offered here. They were, so that a
		// stream could place the cards and the info bar apart, but nothing has been set up
		// that way and they have not kept up with the overlay itself. The files stay where
		// they are; an address somebody already has in OBS goes on working.
		echo '<h3 class="stream-section">' . get_label('Glyphs') . '</h3>';
		$this->open_settings_table();
		if ($table != VIEW_ALL_TABLES)
		{
			$this->show_url(get_label('URL'), 'url-overlay', $site . '/plugins/obs/overlay.php?' . $scope, '1920 x 1080');
		}

		// The switches of this source, as a tree: whether it shows anything at all, then the two
		// halves of the drawing, then what each half is made of. Where a switch stands says what
		// it belongs to, and a switch that is off puts the rows under it away - there is nothing
		// to set about a part that is not drawn, and what was set stays set until it comes back.
		//
		// A switch also goes off by itself when the last thing under it does; the script says
		// which - see follow(). The cards are the exception: with all three of their extras off
		// they still show who is sitting where, so they never go off on their own.
		$this->show_group(get_label('Show'),
			$this->toggle('show_glyphs', $settings->show_glyphs, NULL, 'glyphs-detail'));

		$this->show_group(get_label('Top bar'),
			$this->toggle('show_top', $settings->show_top, NULL, 'top-detail'), 'glyphs-detail', 1);
		$this->show_setting(get_label('Game stage'),
			$this->toggle('show_phase', $settings->show_phase), 'glyphs-detail top-detail', 2);
		$this->show_setting(get_label('Tournament, table and game'),
			$this->toggle('show_game', $settings->show_game), 'glyphs-detail top-detail', 2);
		$this->show_setting(get_label('Referee'),
			$this->toggle('show_judge', $settings->show_judge), 'glyphs-detail top-detail', 2);
		$this->show_setting(get_label('Don\'s checks'),
			$this->toggle('show_don_checks', $settings->show_don_checks), 'glyphs-detail top-detail', 2);
		$this->show_setting(get_label('Sheriff\'s checks'),
			$this->toggle('show_sheriff_checks', $settings->show_sheriff_checks), 'glyphs-detail top-detail', 2);
		$this->show_setting(get_label('Nominees'),
			$this->toggle('show_nominees', $settings->show_nominees), 'glyphs-detail top-detail', 2);

		$this->show_group(get_label('Player glyphs'),
			$this->toggle('show_cards', $settings->show_cards, NULL, 'cards-detail'), 'glyphs-detail', 1);
		$this->show_setting(get_label('Voting'),
			$this->toggle('show_votes', $settings->show_votes), 'glyphs-detail cards-detail', 2);
		$this->show_setting(get_label('Warnings'),
			$this->toggle('show_warnings', $settings->show_warnings), 'glyphs-detail cards-detail', 2);
		$this->show_setting(get_label('Best move'),
			$this->toggle('show_best_move', $settings->show_best_move), 'glyphs-detail cards-detail', 2);
		$this->show_setting(get_label('Highlight the speaker'),
			$this->toggle('show_speaker', $settings->show_speaker), 'glyphs-detail cards-detail', 2);
		echo '</table>';

		// One source for everything shown between games: the game just played, who sits down
		// next, the tournament table and how it has been going.
		echo '<h3 class="stream-section">' . get_label('Between games') . '</h3>';
		$this->open_settings_table();
		if ($table != VIEW_ALL_TABLES)
		{
			$this->show_url(get_label('URL'), 'url-between', $site . '/plugins/obs/between.php?' . $scope, '1920 x 1080');
		}

		// Asked the same way round as the switches of the cards above, so that the page reads
		// one way throughout: a switch standing at yes is a thing that is shown.
		$this->show_group(get_label('Show'),
			$this->toggle('show_between', $settings->show_between, NULL, 'between-detail'));

		$html = '<input id="delay" type="number" min="' . STREAM_MIN_DELAY . '" max="' . STREAM_MAX_DELAY . '"' .
			(is_null($settings->delay) ? '' : ' value="' . $settings->delay . '"') .
			' style="width: 70px" onchange="streamChanged(\'delay\')"> ' . get_label('sec');
		$this->show_setting(get_label('Delay'), $html, 'between-detail', 1);

		// In the order they are shown, since that is what the arrows are for. The ones switched
		// off keep a place in the list too - they have to stand somewhere, and where they stand
		// is where they come back when they are switched on again.
		$html = '<div class="stream-screens" id="screens">';
		$shown = is_null($settings->screens) ? array() : $settings->screens;
		foreach (stream_screen_order($shown) as $name)
		{
			$checked = in_array($name, $shown) ? ' checked' : '';
			$html .= '<span class="stream-screen" data-screen="' . $name . '">';
			$html .= '<button class="icon" title="' . get_label('Move left') . '" onclick="moveScreen(this, -1)">&#9664;</button>';
			$html .= '<label><input type="checkbox" class="toggle"' . $checked . ' onchange="streamChanged(\'screens\')"> ' . stream_screen_label($name) . '</label>';
			$html .= '<button class="icon" title="' . get_label('Move right') . '" onclick="moveScreen(this, 1)">&#9654;</button>';
			$html .= '</span>';
		}
		$html .= '</div>';
		$this->show_setting(get_label('Screens'), $html, 'between-detail', 1);

		$this->show_setting(get_label('Theme'),
			$this->choice('dark', $settings->dark, get_label('Light'), get_label('Dark')), 'between-detail', 1);
		$this->show_setting(get_label('Transparent background'),
			$this->toggle('transparent', $settings->transparent), 'between-detail', 1);

		echo '</table>';

		// Switching by itself, which is what happens the rest of the time: the site reads the
		// phase the game at this table is in and answers with the scene set against it, and
		// the browser sources follow. The rows are drawn by the script, since they are added
		// and taken away as they are set up.
		//
		// Last on the tab, and the longest thing on it: it is set up once for a tournament and
		// then left alone, while everything above it is looked at again every time somebody
		// opens this page.
		//
		// On the tab of one table, as the scene showing now is: a scene belongs to the OBS
		// standing beside one table, and the collection beside one table is not the collection
		// beside the next, so there is nothing here to set for all of them at once.
		//
		// But on that tab it is there whatever the OBS has reported. It used to appear only
		// where there was something to choose from, and the one line that could have explained
		// why there was nothing - the one about Page permissions - stood inside it, so it was
		// never shown when it was needed. An empty place where the scenes were set up last time
		// says nothing at all; this says what is missing.
		if (!is_null($stored))
		{
			echo '<h3 class="stream-section">' . get_label('Automatic scene switching') . '</h3>';
			if (empty($stored->scenes))
			{
				// In the settings table all the same, under the name the switch stands under
				// and with the sentence where the switch would have been, so that the section
				// reads as it does when there is something to switch rather than as a loose
				// paragraph under a heading.
				//
				// Two silences, and they are not the same. A source that has told us about
				// itself and not about the scenes was not allowed to; one that has told us
				// nothing at all either is not running there or is a page from before this was
				// built, which is the one case a refresh puts right.
				$this->open_settings_table();
				if ($stored->control_level >= 0 && $stored->control_level < STREAM_CONTROL_ADVANCED)
				{
					$this->show_setting(get_label('Turn on'), '<b>' . get_label('The browser source is not allowed to change scenes. Set Page permissions to Advanced in its properties in OBS Studio.') . '</b>');
				}
				else
				{
					$this->show_setting(get_label('Turn on'), get_label('The OBS Studio at this table has not said what scenes it has. A browser source of ours says it by itself, once its Page permissions are set to Advanced in its properties in OBS Studio - and a source that was already open when that was set has to be refreshed once. Until then there is nothing here to point a phase of the game at.'));
				}
				echo '</table>';
			}
			else
			{
				// Turned off at a table that is switched by hand. The rows stay where they are
				// - they are just not acted on - so turning it back on brings back what was set
				// up. It is stored the other way round, as "do not switch", so that a table
				// nobody has said anything about goes on switching as it always did.
				$auto = ($stored->flags & STREAM_FLAG_NO_AUTO_SCENE) == 0;
				$this->open_settings_table();
				$this->show_setting(get_label('Turn on'),
					'<input type="checkbox" class="toggle" id="auto-scenes" onchange="autoScenesChanged()"' . ($auto ? ' checked' : '') . '>');
				echo '</table>';
				echo '<div id="scene-events"' . ($auto ? '' : ' style="display: none"') . '></div>';
			}
		}

		// The other end of a broadcast: not what OBS puts on the screen but where what it put
		// there can be watched afterwards, and what the games of this table are linked into. It
		// stands last because it is the one thing on the page that is not about how the stream
		// looks, and because it is filled in after the tournament rather than before it.
		//
		// The same rows the preparation page shows, written by the same operations - see
		// include/broadcast_table.php. On the tab of one table only that table is listed, and
		// only the rounds that are played at it.
		echo '<h3 class="stream-section">' . get_label('YouTube streams') . '</h3>';
		$listed = 0;
		foreach ($this->rounds as $round)
		{
			if ($table != VIEW_ALL_TABLES && !$this->round_on_table($round, $table))
			{
				continue;
			}
			++$listed;
			if (count($this->rounds) > 1)
			{
				echo '<h4 class="stream-round">' . htmlspecialchars($round->name) . '</h4>';
			}
			show_broadcast_table($this->owner, $round->id, $round->name,
				$table == VIEW_ALL_TABLES ? 0 : $table);
		}
		if ($listed == 0)
		{
			echo '<p>' . get_label('No broadcasts yet.') . '</p>';
		}

		echo '<p id="stream-saved" style="visibility: hidden"><b>' . get_label('Saved.') . '</b></p>';
		echo '</div>';
	}

	protected function js()
	{
		parent::js();
?>
		// Which of the two the tables belong to, named the way the api asks for it.
		var owner = { <?php echo STREAMS_UNDER_EVENT ? 'event_id' : 'tournament_id'; ?>: <?php echo $this->id; ?> };
		var tableNum = <?php echo $this->view; ?>;
<?php
		// Everything below belongs to a tab that has the scene sections on it, which is the
		// tab of one table whose OBS has said what scenes it has. Where they are not shown
		// there is nothing here for them to work on either.
		$stored = ($this->view == VIEW_ALL_TABLES) ? NULL : stream_settings($this->owner, $this->view);
		if (!is_null($stored) && !empty($stored->scenes))
		{
			$events = array();
			foreach (stream_game_events() as $key => $label)
			{
				$events[] = array($key, $label);
			}
?>
		// Asked for by hand. The browser sources at that table pick it up within a couple of
		// seconds and change the scene themselves.
		function sceneChanged()
		{
			json.post("api/ops/stream.php",
			Object.assign({ op: "scene", table_num: tableNum, scene: $('#scene').val() }, owner),
			saved);
		}

		// The scenes this table's OBS reported, and which of them the game asks for in each of
		// its phases. The phases are read as the sources ask, so a change here reaches a game
		// already under way within a second or two.
		var sceneNames = <?php echo json_encode($stored->scenes); ?>;
		var sceneEvents = <?php echo json_encode($stored->scene_events); ?>;
		var gameEvents = <?php echo json_encode($events); ?>;

		function renderSceneEvents()
		{
			let html = '<table class="bordered light" width="100%"><tr class="header">' +
				'<td width="32"><button class="icon" onclick="addSceneEvent()"><img src="images/create.png"></button></td>' +
				// The phase is as wide as it has to be and no wider; the scene takes the rest of
				// the table, since a scene can be called anything at all.
				'<td width="280"><?php echo get_label('When'); ?></td>' +
				'<td><?php echo get_label('Scene'); ?><span style="float: right"><button onclick="autoSceneEvents()">' +
				'<?php echo get_label('Assign scenes automatically'); ?></button></span></td></tr>';

			for (let i = 0; i < sceneEvents.length; ++i)
			{
				html += '<tr><td><button class="icon" onclick="deleteSceneEvent(' + i + ')"><img src="images/delete.png"></button></td>' +
					'<td>' + picker('se-event-' + i, gameEvents, sceneEvents[i].event, takenEvents(i)) + '</td>' +
					'<td>' + picker('se-scene-' + i, sceneNames, sceneEvents[i].scene, []) + '</td></tr>';
			}
			$('#scene-events').html(html + '</table>');
		}

		// The phases the other rows have taken. A phase belongs to one row and no more: two
		// rows naming the same one would be two answers to the same question, and nothing
		// could say which of them is meant. Rows that have not been given a phase yet are not
		// in anybody's way.
		function takenEvents(row)
		{
			let taken = [];
			for (let i = 0; i < sceneEvents.length; ++i)
			{
				if (i != row && sceneEvents[i].event != '')
					taken.push(sceneEvents[i].event);
			}
			return taken;
		}

		// One dropdown of the rows above. The list is either plain names - the scenes - or
		// pairs of what is stored and what it is called, which is what the phases are. What
		// another row has taken is left out, except for this row's own answer.
		function picker(id, list, chosen, taken)
		{
			let html = '<select id="' + id + '" onchange="sceneEventsChanged()">';
			let found = false;
			for (const item of list)
			{
				let value = Array.isArray(item) ? item[0] : item;
				let text = Array.isArray(item) ? item[1] : item;
				let selected = (value == chosen);
				if (!selected && taken.indexOf(value) >= 0)
					continue;
				found = found || selected;
				html += '<option value="' + escapeHtml(value) + '"' + (selected ? ' selected' : '') + '>' + escapeHtml(text) + '</option>';
			}

			// A scene that has been renamed or taken out of OBS since this was set up is still
			// shown, so that it is plain what the row says rather than the row quietly
			// standing for something else.
			if (!found && chosen != '')
				html = '<select id="' + id + '" onchange="sceneEventsChanged()"><option value="' + escapeHtml(chosen) + '" selected>' + escapeHtml(chosen) + '</option>' + html.substring(html.indexOf('>') + 1);

			return html + '</select>';
		}

		function escapeHtml(text)
		{
			return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
		}

		// Turning the switching off hides the rows rather than forgetting them: nothing is sent
		// but the one flag, and what is set up against the phases stays as it is.
		function autoScenesChanged()
		{
			let on = document.getElementById('auto-scenes').checked;
			document.getElementById('scene-events').style.display = on ? '' : 'none';
			json.post("api/ops/stream.php",
			Object.assign({ op: "set", table_num: tableNum, auto_scenes: on ? 1 : 0 }, owner),
			saved);
		}

		function addSceneEvent()
		{
			sceneEvents.push({ scene: sceneNames.length > 0 ? sceneNames[0] : '', event: '' });
			renderSceneEvents();
			sceneEventsChanged();
		}

		// The three scenes nearly every table has, and what people call them. These are names
		// to match against what the OBS reported, not anything shown on the page, so they are
		// in the languages a scene collection is likely to be built in rather than in the
		// language of whoever is reading this page.
		var sceneGuesses = [
			{ what: <?php echo json_encode(get_label('Day')); ?>, events: ['day'],
				names: ['day', 'день', 'дневная', 'денна', 'game', 'игра', 'гра', 'table', 'стол', 'стіл'] },
			{ what: <?php echo json_encode(get_label('Night')); ?>, events: ['night', 'start'],
				names: ['night', 'ночь', 'ночная', 'ніч', 'нічна'] },
			{ what: <?php echo json_encode(get_label('Break')); ?>, events: ['end'],
				names: ['break', 'перерыв', 'перерва', 'pause', 'пауза', 'brb', 'intermission', 'заставка'] }];

		// Fills in what can be guessed and leaves alone what cannot. A whole name counts
		// before a name that is only part of a longer one, and the night and the break are
		// looked for before the day, so that a scene called "Game night" goes to the night
		// rather than to the day for the sake of the word "game" in it.
		//
		// A phase that is already set is left as it is - this adds, it does not overwrite.
		// Except where the scene it names is not among the ones the OBS reported: that row
		// does nothing at all, so it is treated as empty and filled in rather than left to
		// point at a scene that has been renamed or taken away.
		function autoSceneEvents()
		{
			let known = {};
			for (const name of sceneNames)
				known[name] = true;

			let row = {}, busy = {};
			for (let i = 0; i < sceneEvents.length; ++i)
			{
				let pair = sceneEvents[i];
				if (pair.event == '')
					continue;
				row[pair.event] = i;
				if (known[pair.scene])
					busy[pair.event] = true;
			}

			let taken = {}, found = {};
			for (let whole = 1; whole >= 0; --whole)
			{
				for (const g of [1, 2, 0])
				{
					if (found[g])
						continue;
					for (const scene of sceneNames)
					{
						if (taken[scene])
							continue;
						let name = scene.toLowerCase().trim();
						let hit = sceneGuesses[g].names.some(function(n)
							{ return whole ? (name == n) : (name.indexOf(n) >= 0); });
						if (hit)
						{
							found[g] = scene;
							taken[scene] = true;
							break;
						}
					}
				}
			}

			let added = 0;
			for (let g = 0; g < sceneGuesses.length; ++g)
			{
				if (!found[g])
					continue;
				for (const event of sceneGuesses[g].events)
				{
					if (busy[event])
						continue;
					if (event in row)
						sceneEvents[row[event]].scene = found[g]; // the row was pointing at nothing
					else
						sceneEvents.push({ scene: found[g], event: event });
					busy[event] = true;
					++added;
				}
			}

			// What could not be found is worth saying outright, whether or not anything else
			// was filled in: it is the one thing the person has to finish by hand, and naming
			// it saves them going through the list to work out which.
			let missing = [];
			for (let g = 0; g < sceneGuesses.length; ++g)
			{
				if (!found[g])
					missing.push(sceneGuesses[g].what);
			}

			if (added > 0)
			{
				// The rows first, because what is sent is read back out of them.
				renderSceneEvents();
				sceneEventsChanged();
			}

			if (missing.length > 0)
			{
				dlg.info(<?php echo json_encode(get_label('There is nothing among the scenes of this table that looks like [0]. Set it by hand, or rename the scenes in OBS Studio and try again.', '%WHAT%')); ?>
					.replace('%WHAT%', missing.join(', ')));
			}
			else if (added == 0)
			{
				dlg.info(<?php echo json_encode(get_label('Every scene that could be recognised is set up already.')); ?>);
			}
		}

		function deleteSceneEvent(i)
		{
			sceneEvents.splice(i, 1);
			renderSceneEvents();
			sceneEventsChanged();
		}

		// Read back out of the rows rather than kept in step one control at a time, so that
		// what is sent is what the page shows.
		function sceneEventsChanged()
		{
			sceneEvents = [];
			for (let i = 0; ; ++i)
			{
				let scene = document.getElementById('se-scene-' + i);
				if (scene == null)
					break;
				sceneEvents.push({ scene: scene.value, event: document.getElementById('se-event-' + i).value });
			}

			renderSceneEvents();
			json.post("api/ops/stream.php",
			Object.assign({
				op: "scene_events"
				, table_num: tableNum
				, scene_events: JSON.stringify(sceneEvents)
			}, owner),
			saved);
		}

		renderSceneEvents();
<?php
		}
?>

		function copyUrl(id)
		{
			let box = document.getElementById(id);
			box.select();
			navigator.clipboard.writeText(box.value);
		}

		// Whether the roles are shown belongs to the tournament rather than to one table, so it
		// goes to the tournament and every round follows it.
		function rolesChanged()
		{
			json.post("api/ops/stream.php",
			Object.assign({
				op: "set"
				, table_num: tableNum
				, hide_roles: document.getElementById('roles').checked ? 0 : 1
			}, owner),
			saved);
		}

		function streamingChanged()
		{
			json.post("api/ops/stream.php",
			Object.assign({
				op: "set"
				, table_num: tableNum
				, streaming: document.getElementById('streaming').checked ? 1 : 0
			}, owner),
			saved);
		}

		function hideTableChanged()
		{
			let box = document.getElementById('hide-table');
			json.post("api/ops/tournament.php",
			{
				op: "hide_table"
				, tournament_id: <?php echo $this->id; ?>
				, hide: box.checked ? 1 : 0
			},
			function(data)
			{
				box.checked = data.hidden;
				saved();
			});
		}

		// The order the screens are shown in is the order they stand in on the page, so moving
		// one is moving the thing itself rather than setting a number somewhere.
		function moveScreen(button, step)
		{
			let screen = button.parentNode;
			let next = (step < 0) ? screen.previousElementSibling : screen.nextElementSibling;
			if (next == null)
				return;

			if (step < 0)
				screen.parentNode.insertBefore(screen, next);
			else
				screen.parentNode.insertBefore(next, screen);
			streamChanged('screens');
		}

		// Saved as it is changed rather than on a button: there is nothing here that is only
		// half said, and a manager watching the screens wants to see what a change does.
		//
		// Only the setting that was touched is sent. On the tab of one table that makes no
		// difference, but on the first tab it is the whole point: it goes to every table, and
		// a table keeps everything it was set up with apart from the one thing being changed.
		function streamChanged(setting)
		{
			let params = Object.assign({ op: "set", table_num: tableNum }, owner);

			if (setting == 'screens')
			{
				let screens = [];
				let shown = document.getElementById('screens').children;
				for (let i = 0; i < shown.length; ++i)
				{
					if (shown[i].querySelector('input').checked)
						screens.push(shown[i].dataset.screen);
				}
				params.screens = screens.join(',');
			}
			else
			{
				let value = $('#' + setting).val();
				if (value === '' || value === null)
					return; // the tables do not agree and nothing has been chosen yet
				params[setting] = value;
			}

			json.post("api/ops/stream.php", params, saved);
		}

		// A switch says yes or no and nothing else, so what it sends is what it stands at. On
		// the tab of all the tables one of these can start off neither - that is what the
		// browser's third state is for - and touching it is what settles it; from then on it
		// is an ordinary switch.
		// What hangs under what, among the switches of the cards source. Only the ones that
		// answer for others are in here, and only where the answer really follows: a half with
		// nothing left in it draws nothing, and a source with both halves off is a source
		// showing nothing. The cards are a parent of nothing - with all three of their extras
		// off they still show who is sitting where - which is why they are not in this list.
		const settingTree = [
			{ parent: 'show_glyphs', children: ['show_top', 'show_cards'] },
			{ parent: 'show_top', children: ['show_phase', 'show_game', 'show_judge',
				'show_don_checks', 'show_sheriff_checks', 'show_nominees'] }];

		function streamToggled(setting)
		{
			let box = document.getElementById(setting);
			box.indeterminate = false;
			let changed = {};
			changed[setting] = box.checked ? 1 : 0;
			follow(setting, changed);
			showDetails();
			json.post("api/ops/stream.php",
				Object.assign({ op: "set", table_num: tableNum }, changed, owner),
				saved);
		}

		// Everything else that the switch just touched settles, gathered into the one request
		// with it so that the page and the table never stand at different things.
		//
		// Upwards: the last thing inside a part going off takes the part with it. Downwards: a
		// part switched back on with nothing inside it brings all of it back - it would draw
		// nothing otherwise, and there would be no way to fill it again, since the rows inside
		// a part only stand while that part is on.
		//
		// A switch standing at neither - the tab of all the tables, where they do not agree -
		// counts as on. Some table has that part, and nothing here may turn it off behind
		// their back.
		function follow(setting, changed)
		{
			for (const group of settingTree)
			{
				if (group.parent == setting)
				{
					if (document.getElementById(setting).checked && allOff(group))
					{
						for (const child of group.children)
						{
							setSwitch(child, true, changed);
							follow(child, changed);
						}
					}
				}
				else if (group.children.indexOf(setting) >= 0 && allOff(group) && isOn(group.parent))
				{
					setSwitch(group.parent, false, changed);
					follow(group.parent, changed);
				}
			}
		}

		function isOn(name)
		{
			let box = document.getElementById(name);
			return box.checked || box.indeterminate;
		}

		function allOff(group)
		{
			for (const child of group.children)
			{
				if (isOn(child))
					return false;
			}
			return true;
		}

		function setSwitch(name, on, changed)
		{
			let box = document.getElementById(name);
			box.indeterminate = false;
			box.checked = on;
			changed[name] = on ? 1 : 0;
		}

		// A part that is turned off has no look to set, so the rows belonging to it are put away
		// rather than left standing there to no purpose. Nothing is forgotten by it: the rows
		// come back with whatever was in them.
		//
		// Every such switch is applied together, and the rows of a group are shown before any
		// group is put away. Taken one switch at a time, a group inside a group would bring back
		// rows that the group around it had just put away - the parts of the top bar would stand
		// there after the whole source had been switched off.
		//
		// A switch standing at neither - the tab of all the tables, where they do not agree -
		// leaves the rows where they are. Some table has that part on, and its rows are what
		// somebody opened the tab to set.
		function showDetails()
		{
			let off = [];
			$('input[data-hides], input[data-shows]').each(function()
			{
				let group = this.dataset.hides ? this.dataset.hides : this.dataset.shows;
				let on = this.indeterminate || (this.dataset.hides ? !this.checked : this.checked);
				$('tr.' + group).show();
				if (!on)
					off.push(group);
			});
			for (const group of off)
				$('tr.' + group).hide();
		}

		$(function()
		{
			$('input[data-mixed]').prop('indeterminate', true);
			showDetails();
		});

		function saved()
		{
			let note = document.getElementById('stream-saved');
			note.style.visibility = 'visible';
			setTimeout(function() { note.style.visibility = 'hidden'; }, 1500);
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Stream settings'));

?>
