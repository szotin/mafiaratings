<?php

require_once 'include/general_page_base.php';
require_once 'include/tournament.php';
require_once 'include/event.php';

define('VIEW_GLYPHS', 0);
define('VIEW_SCENE_SWITCHING', 1);
define('VIEW_COUNT', 2);

// The page is opened from a tournament, from an event, or from the game screen of either, and it
// should wear whatever it hangs under instead of coming up looking like the main page. The body
// is the same in all three cases, so the class it extends is the only thing that differs - and
// that much PHP is happy to decide right here. The bases want the thing's id in "id", while
// every link here has always passed tournament_id or event_id, so the links are left as they are
// and the id is handed over below.
if (isset($_REQUEST['tournament_id']) && (int)$_REQUEST['tournament_id'] > 0)
{
	define('OBS_UNDER', 'tournament');
	$_REQUEST['id'] = (int)$_REQUEST['tournament_id'];
	class ObsPageBase extends TournamentPageBase {}
}
else if (isset($_REQUEST['event_id']) && (int)$_REQUEST['event_id'] > 0)
{
	define('OBS_UNDER', 'event');
	$_REQUEST['id'] = (int)$_REQUEST['event_id'];
	class ObsPageBase extends EventPageBase {}
}
else
{
	// Under nothing in particular: there is no event to set overlays up for, only the moderator's
	// own scene switching.
	define('OBS_UNDER', '');
	class ObsPageBase extends GeneralPageBase {}
}

class Page extends ObsPageBase
{
	protected function add_headers()
	{
		echo '<script src="' . versioned_asset('js/obs-websocket-js.js') . '"></script>';
	}
	
	protected function prepare()
	{
		global $_profile;

		// Loads the tournament or the event this page hangs under, which is what gives the page
		// its title and its menu.
		parent::prepare();
		check_permissions(PERMISSION_USER);

		$this->view = VIEW_GLYPHS;
		if (isset($_REQUEST['view']))
		{
			$this->view = (int)$_REQUEST['view'];
			if ($this->view < 0 || $this->view >= VIEW_COUNT)
			{
				$this->view = VIEW_GLYPHS;
			}
		}
		
		// The overlay URLs used to carry a secret token, which this page made up and stored on the
		// event. They name a table now - what happens at a table is on the stream anyway - so no
		// token is made any more and all that is left of it is this permission check.
		//
		// The base has already loaded whatever we hang under, so nothing is read from the
		// database here. Note that for an event it has also filled $this->tournament_id with the
		// tournament that event belongs to, which is not the same as having been opened from the
		// tournament - OBS_UNDER is what says which of the two happened.
		$this->permitted = false;
		$this->event_id = 0;
		$this->event_name = '';
		if (OBS_UNDER == 'tournament')
		{
			$this->tournament_id = $this->id;
			$this->permitted = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $this->club_id, $this->id);
		}
		else if (OBS_UNDER == 'event')
		{
			$this->event_id = $this->id;
			$this->event_name = $this->name;
			$this->permitted = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $this->club_id, $this->id, $this->tournament_id);
		}
		else
		{
			$this->tournament_id = 0;
			$this->langs = LANG_ALL_VISUAL;
		}

		$this->user_id = $_profile->user_id;
		if (isset($_REQUEST['user_id']))
		{
			$this->user_id = (int)$_REQUEST['user_id'];
			if ($this->user_id <= 0)
			{
				$this->user_id = $_profile->user_id;
			}
		}
		
		// The rounds of the tournament used to be listed here, one next-game source per round.
		// between.php follows the rounds by itself now, so there is nothing left to list.
	}

	protected function show_body()
	{
		if (!$this->permitted)
		{
			// Somebody who does not run this event has nothing to set up for it, but their own
			// scene switching is still theirs.
			$this->view = VIEW_SCENE_SWITCHING;
			$this->show_scene_switching();
		}
		else
		{
			echo '<div class="tab">';
			echo '<button ' . ($this->view == VIEW_GLYPHS ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_GLYPHS . '})">' . get_label('Glyphs') . '</button>';
			echo '<button ' . ($this->view == VIEW_SCENE_SWITCHING ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_SCENE_SWITCHING . '})">' . get_label('Scene switching') . '</button>';
			echo '</div><p>';
			
			if ($this->view == VIEW_SCENE_SWITCHING)
			{
				$this->show_scene_switching();
			}
			else
			{
				$this->show_glyphs();
			}
			
			echo '</p>';
		}
	}
	
	function show_glyphs()
	{
		global $_profile, $_lang;
		
		if (is_valid_lang($this->langs))
		{
			$lang = get_lang_code($this->langs);
		}
		else
		{
			$lang = $_lang;
		}
		
		echo '<table class="transp" width="100%"><tr>';
		echo '<td>' . get_label('Language') . ': <select id="lang" onchange="generateUrls()">';
		$l = LANG_NO;
		while (($l = get_next_lang($l, LANG_ALL_VISUAL)) != LANG_NO)
		{
			show_option(get_lang_code($l), $lang, get_lang_str($l));
		}
		echo '</td>';
		
		// The overlays name a table rather than a referee now, so there is no account to pick.
		echo '<td align="right">' . get_label('Table') . ': <input id="table" type="number" min="1" value="1" style="width: 30px;" onchange="generateUrls()"></td>';
		echo '</tr></table></p>';
		
		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="header"><td width="180">' . get_label('Type') . '</td><td>' . get_label('URL') . '</td><td width="110">' . get_label('Recomended size') . '</td><td width="64"></td></tr>';
		echo '<tr><td class="dark">' . get_label('Glyphs and info full screen') . ':</td><td id="url-0"></td><td>1920 x 1080</td><td align="center"><button onclick="copyUrl(0)">' . get_label('Copy') . '</button></td></tr>';
		echo '<tr><td class="dark">' . get_label('Glyphs only') . ':</td><td id="url-1"></td><td>1920 x 260</td><td align="center"><button onclick="copyUrl(1)">' . get_label('Copy') . '</button></td></tr>';
		echo '<tr><td class="dark">' . get_label('Info only') . ':</td><td id="url-2"></td><td>1920 x 100</td><td align="center"><button onclick="copyUrl(2)">' . get_label('Copy') . '</button></td></tr>';
		// One source for everything shown between games: the result of the game just played, who
		// sits down next, and the tournament table, one after another. It replaces the separate
		// standings and next-game sources there used to be here.
		echo '<tr><td class="dark">' . get_label('Between games') . ':</td><td id="url-3"></td><td>1920 x 1080</td>';
		echo '<td align="center"><button onclick="copyUrl(3)">' . get_label('Copy') . '</button></td></tr>';
		echo '</table></p>';
	}
	
	function show_scene_switching()
	{
		global $_profile;
		
		$query = new DbQuery('SELECT obs_scenes FROM game_settings WHERE user_id = ?', $this->user_id);
		$this->obs = NULL;
		if ($row = $query->next())
		{
			list ($json) = $row;
			if (!is_null($json))
			{
				$obs = json_decode($json);
				if (!isset($obs->url))
				{
					$obs->url = 'ws://localhost:4455';
				}
				if (!isset($obs->password))
				{
					$obs->password = '';
				}
				if (!isset($obs->scenes))
				{
					$obs->scenes = array();
				}
				$this->obs = json_encode($obs);
			}
		}
		if (is_null($this->obs))
		{
			$this->obs = '{url:"ws://localhost:4455",password:"",scenes:[]}';
		}
		
		echo '<div id="obs"></div>';
	}
	
	protected function js()
	{
		parent::js();
		if ($this->view == VIEW_SCENE_SWITCHING)
		{
?>
		var obs = <?php echo $this->obs; ?>;
		var dirty = false;
		var gameEvents = [
			["", ""],
			["day", "<?php echo get_label('In the daytime'); ?>"],
			["night", "<?php echo get_label('In the nighttime') ?>"],
			["start", "<?php echo get_label('During roles distribution') ?>"],
			["end", "<?php echo get_label('On game end') ?>"],
			["arrangement", "<?php echo get_label('During arrangement') ?>"],
			["relaxed sitting", "<?php echo get_label('During relaxed sitting') ?>"],
			["1", "<?php echo get_label('On player [0] speach', 1) ?>"],
			["2", "<?php echo get_label('On player [0] speach', 2) ?>"],
			["3", "<?php echo get_label('On player [0] speach', 3) ?>"],
			["4", "<?php echo get_label('On player [0] speach', 4) ?>"],
			["5", "<?php echo get_label('On player [0] speach', 5) ?>"],
			["6", "<?php echo get_label('On player [0] speach', 6) ?>"],
			["7", "<?php echo get_label('On player [0] speach', 7) ?>"],
			["8", "<?php echo get_label('On player [0] speach', 8) ?>"],
			["9", "<?php echo get_label('On player [0] speach', 9) ?>"],
			["10", "<?php echo get_label('On player [0] speach', 10) ?>"],
			["voting", "<?php echo get_label('During voting') ?>"],
			["shooting", "<?php echo get_label('During shooting') ?>"],
			["don", "<?php echo get_label('During don\'s check') ?>"],
			["sheriff", "<?php echo get_label('During sheriff\'s check') ?>"]];
			
		function render()
		{
			let html = 
				'<p><table class="transp" width="100%"><tr><td>OBS Studio URL: <input type="url" id="url" value="' + obs.url + '" oninput="urlChanged()"></td>' +
				'<td align="center"><?php echo get_label('Password'); ?>: <input type="password" id="password" value="' + obs.password + '" oninput="passwordChanged()"></td>' +
				'<td align="right"><button onclick="importScenes()"><?php echo get_label('Import scenes'); ?></button></td>' +
				'</tr></table></p>';
				
			html += '<table class="bordered light" width="100%"><tr class="header"><td width="32"><button class="icon" onclick="addScene()"><img src="images/create.png"></button></td><td><?php echo get_label('Scene'); ?></td><td><?php echo get_label('Scene switch event'); ?></td></tr>';
			for (let i = 0; i < obs.scenes.length; ++i)
			{
				let scene = obs.scenes[i];
				html += '<tr><td><button class="icon"><img src="images/delete.png" onclick="deleteScene(' + i + ')"></button></td><td><input id="s' + i + '" value="' + scene.scene + '" oninput="sceneChanged('+i+')"></td><td><select id="ge' + i + '" onchange="eventChanged('+i+')">';
				for (let e of gameEvents)
				{
					html += '<option value="' + e[0] + '"';
					if (e[0] == scene.event)
					{
						html += ' selected';
					}
					html += '>' + e[1] + '</option>';
				}
				html += '</select></td></tr>';
			}
			html += '</table>';
			$('#obs').html(html);
		}
		
		function urlChanged()
		{
			obs.url = $('#url').val();
			dirty = true;
		}
		
		function passwordChanged()
		{
			obs.password = $('#password').val();
			dirty = true;
		}
		
		function addScene()
		{
			obs.scenes.push({scene: "Scene", event: 0});
			dirty = true;
			render();
		}
		
		function deleteScene(i)
		{
			obs.scenes.splice(i, 1);
			dirty = true;
			render();
		}
		
		function sceneChanged(i)
		{
			obs.scenes[i].scene = $('#s'+i).val();
			dirty = true;
		}
		
		function eventChanged(i)
		{
			obs.scenes[i].event = $('#ge'+i).val();
			dirty = true;
		}
		
		async function importScenes()
		{
			let scenes = [];
			try
			{
				const socket = new OBSWebSocket();
				await socket.connect(obs.url, obs.password);
				scenes = await socket.call('GetSceneList');
				await socket.disconnect();
			}
			catch (error)
			{
				dlg.error(error.message);
			}
			
			for (let i = 0; i < obs.scenes.length; )
			{
				let s1 = obs.scenes[i];
				let remove = true;
				for (const s2 of scenes.scenes)
					if (s1.scene == s2.sceneName)
					{
						remove = false;
						break;
					}
					
				if (remove)
				{
					obs.scenes.splice(i, 1);
					dirty = true;
				}
				else
					++i;
			}
			
			for (let s1 of scenes.scenes)
			{
				let add = true;
				for (let s2 of obs.scenes)
					if (s1.sceneName == s2.scene)
					{
						add = false;
						break;
					}

				if (add)
				{
					obs.scenes.push({scene: s1.sceneName, event: ""});
					dirty = true;
				}
			}
			
			if (dirty)
				render();
		}
		
		function saveObs()
		{
			if (dirty)
				json.post("api/ops/game.php",
				{
					op: "settings"
					, user_id: <?php echo $this->user_id; ?>
					, obs_scenes: JSON.stringify(obs)
				},
				function() { dirty = false; }, function(error) { dirty = false; });
		}
		
		setInterval(saveObs, 1000);
		render();
	
<?php
		}
		else
		{
?>
			var url = Array(4).fill('');
			var site = '<?php echo get_server_url(); ?>';

			// A tournament overlay follows its table from round to round; an event one stays in
			// the one event. Either way the URL survives the whole thing and never needs retyping.
			var scope = '<?php echo OBS_UNDER == 'tournament' ? 'tournament_id=' . $this->tournament_id : 'event_id=' . $this->event_id; ?>';

			function generateUrls()
			{
				let params = '?' + scope + '&table_num=' + $('#table').val() + '&locale=' + $('#lang').val();
				url[0] = site + '/plugins/obs/overlay.php' + params;
				url[1] = site + '/plugins/obs/players.php' + params;
				url[2] = site + '/plugins/obs/gamestats.php' + params;

				// The same table as the overlays. It takes no language of its own and nothing
				// else either: what it shows is set under Management / Stream settings, and it
				// reads that as it runs, so this address never has to be typed again.
				url[3] = site + '/plugins/obs/between.php?' + scope + '&table_num=' + $('#table').val();

				for (let i = 0; i < url.length; ++i)
				{
					if (url[i].startsWith("htt"))
						$('#url-' + i).html(url[i].length > 0 ? '<a href="' + url[i] + '" target="_blank">' + url[i] + '</a>' : '');
					else
						$('#url-' + i).html('<b>'+url[i]+'</b>');
				}
			}
			
			function copyUrl(i)
			{
				navigator.clipboard.writeText(url[i]);
			}
			
			generateUrls();
<?php
		}
	}
}

$page = new Page();
$page->run(get_label('OBS Studio integration'));

?>