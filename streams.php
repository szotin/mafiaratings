<?php

require_once 'include/general_page_base.php';
require_once 'include/tournament.php';
require_once 'include/event.php';
require_once 'include/stream.php';

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
			foreach (array('lang', 'delay', 'screens', 'dark', 'transparent') as $name)
			{
				if (!is_null($all->$name) && $all->$name !== $form->$name)
				{
					$all->$name = NULL;
				}
			}
		}
		return $all;
	}

	private function show_setting($name, $content)
	{
		echo '<tr><td class="dark" width="220" align="right">' . $name . ':</td><td>' . $content . '</td></tr>';
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
		$locale = is_null($settings->lang) ? '' : '&locale=' . get_lang_code($settings->lang);
		$scope = (STREAMS_UNDER_EVENT ? 'event_id=' : 'tournament_id=') . $this->id . '&table_num=' . $table;

		echo '<div class="tabcontent">';

		// Switching the scene by hand. The moderator's own screen does it by the phase of the
		// game, so this is for when nobody is - the moderator is not using that, or forgot -
		// and somebody at the commentary desk or watching the tournament switches instead. It
		// stands until the game asks for a different scene by itself.
		//
		// Only on the tab of one table: a scene belongs to the OBS standing beside it, and
		// there is no sense in asking every table to show the same one. And only once that
		// OBS has said what scenes it has, which a browser source of ours does as soon as it
		// is opened - until then there is nothing to choose from and nothing worth saying, so
		// neither of these sections is there at all.
		$stored = ($table == VIEW_ALL_TABLES) ? NULL : stream_settings($this->owner, $table);
		if (!is_null($stored) && !empty($stored->scenes))
		{
			echo '<h3 class="stream-section">' . get_label('Scene') . '</h3>';
			echo '<table class="bordered light" width="100%">';

			$html = '<select id="scene" onchange="sceneChanged()">';
			foreach ($stored->scenes as $name)
			{
				$html .= '<option value="' . htmlspecialchars($name) . '"' .
					($stored->scene === $name ? ' selected' : '') . '>' . htmlspecialchars($name) . '</option>';
			}
			$html .= '</select>';
			$this->show_setting(get_label('Scene'), $html);

			if ($stored->control_level >= 0 && $stored->control_level < STREAM_CONTROL_ADVANCED)
			{
				$this->show_setting('', '<b>' . get_label('The browser source is not allowed to change scenes. Set Page permissions to Advanced in its properties in OBS Studio.') . '</b>');
			}
			echo '</table>';

			// Switching by itself, which is what happens the rest of the time: the site reads
			// the phase the game at this table is in and answers with the scene set against it,
			// and the browser sources follow. The rows are drawn by the script, since they are
			// added and taken away as they are set up.
			echo '<h3 class="stream-section">' . get_label('Automatic scene switching') . '</h3>';

			// Turned off at a table that is switched by hand. The rows stay where they are -
			// they are just not acted on - so turning it back on brings back what was set up.
			// It is stored the other way round, as "do not switch", so that a table nobody has
			// said anything about goes on switching as it always did.
			$auto = ($stored->flags & STREAM_FLAG_NO_AUTO_SCENE) == 0;
			echo '<table class="bordered light" width="100%">';
			$this->show_setting(get_label('Turn on'),
				'<input type="checkbox" id="auto-scenes" onchange="autoScenesChanged()"' . ($auto ? ' checked' : '') . '>');
			echo '</table>';
			echo '<div id="scene-events"' . ($auto ? '' : ' style="display: none"') . '></div>';
		}

		// The cards over the game itself. They are three sources rather than one so that a
		// stream can put the players and the info bar wherever it likes.
		echo '<h3 class="stream-section">' . get_label('Glyphs') . '</h3>';
		echo '<table class="bordered light" width="100%">';
		if ($table != VIEW_ALL_TABLES)
		{
			$this->show_url(get_label('Glyphs and info full screen'), 'url-overlay', $site . '/plugins/obs/overlay.php?' . $scope . $locale, '1920 x 1080');
			$this->show_url(get_label('Glyphs only'), 'url-players', $site . '/plugins/obs/players.php?' . $scope . $locale, '1920 x 260');
			$this->show_url(get_label('Info only'), 'url-gamestats', $site . '/plugins/obs/gamestats.php?' . $scope . $locale, '1920 x 100');
		}

		// Whether the roles are shown is decided for the tournament as a whole and handed down
		// to every round, so it reads the same on every tab and changing it anywhere changes it
		// everywhere. See TOURNAMENT_FLAG_HIDE_ROLES.
		$hide_roles = STREAMS_UNDER_EVENT ? EVENT_FLAG_HIDE_ROLES : TOURNAMENT_FLAG_HIDE_ROLES;
		$show_roles = (($this->flags & $hide_roles) == 0) ? 1 : 0;
		$html = '<select id="roles" onchange="rolesChanged()">';
		$html .= '<option value="1"' . ($show_roles ? ' selected' : '') . '>' . get_label('Yes') . '</option>';
		$html .= '<option value="0"' . ($show_roles ? '' : ' selected') . '>' . get_label('No') . '</option>';
		$html .= '</select>';
		$this->show_setting(get_label('Show roles'), $html);
		echo '</table>';

		// One source for everything shown between games: the game just played, who sits down
		// next, the tournament table and how it has been going.
		echo '<h3 class="stream-section">' . get_label('Between games') . '</h3>';
		echo '<table class="bordered light" width="100%">';
		if ($table != VIEW_ALL_TABLES)
		{
			$this->show_url(get_label('URL'), 'url-between', $site . '/plugins/obs/between.php?' . $scope, '1920 x 1080');
		}

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

		$html = '<input id="delay" type="number" min="' . STREAM_MIN_DELAY . '" max="' . STREAM_MAX_DELAY . '"' .
			(is_null($settings->delay) ? '' : ' value="' . $settings->delay . '"') .
			' style="width: 70px" onchange="streamChanged(\'delay\')"> ' . get_label('sec');
		$this->show_setting(get_label('Delay'), $html);

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
			$html .= '<label><input type="checkbox"' . $checked . ' onchange="streamChanged(\'screens\')"> ' . stream_screen_label($name) . '</label>';
			$html .= '<button class="icon" title="' . get_label('Move right') . '" onclick="moveScreen(this, 1)">&#9654;</button>';
			$html .= '</span>';
		}
		$html .= '</div>';
		$this->show_setting(get_label('Screens'), $html);

		$this->show_setting(get_label('Background'),
			$this->choice('dark', $settings->dark, get_label('Light'), get_label('Dark')));
		$this->show_setting(get_label('Transparent'),
			$this->choice('transparent', $settings->transparent, get_label('No'), get_label('Yes')));

		echo '</table>';
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
				, hide_roles: ($('#roles').val() == '1') ? 0 : 1
			}, owner),
			saved);
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
