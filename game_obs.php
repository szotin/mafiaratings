<?php

require_once 'include/general_page_base.php';

define('VIEW_GLYPHS', 0);
define('VIEW_SCENE_SWITCHING', 1);
define('VIEW_COUNT', 2);

class Page extends GeneralPageBase
{
	protected function add_headers()
	{
		echo '<script src="js/obs-websocket-js.js"></script>';
	}
	
	protected function prepare()
	{
		global $_profile;
		
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
		
		$this->token = NULL; 
		$this->langs = LANG_ALL_VISUAL;
		
		$this->event_id = 0;
		$this->event_name = '';
		if (isset($_REQUEST['event_id']))
		{
			$this->event_id = (int)$_REQUEST['event_id'];
			list($club_id, $tournament_id, $this->token, $this->langs, $this->event_name) = Db::record(get_label('event'), 'SELECT club_id, tournament_id, security_token, languages, name FROM events WHERE id = ?', $this->event_id);
			if (!is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $this->event_id, $tournament_id))
			{
				$this->token = NULL;
			}
			else if (is_null($this->token))
			{
				$this->token = rand_string(32);
				Db::exec(get_label('event'), 'UPDATE events SET security_token = ? WHERE id = ?', $this->token, $this->event_id);
			}
		}
		
		$this->tournament_id = 0;
		if (isset($_REQUEST['tournament_id']))
		{
			$this->tournament_id = (int)$_REQUEST['tournament_id'];
			list($club_id, $this->token, $this->langs) = Db::record(get_label('tournament'), 'SELECT club_id, security_token, langs FROM tournaments WHERE id = ?', $this->tournament_id);
			if (!is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $this->tournament_id))
			{
				$this->token = NULL;
			}
			else if (is_null($this->token))
			{
				$this->token = rand_string(32);
				Db::exec(get_label('tournament'), 'UPDATE tournaments SET security_token = ? WHERE id = ?', $this->token, $this->tournament_id);
			}
		}
		
		$this->user_id = $_profile->user_id;
		if (isset($_REQUEST['user_id']))
		{
			$this->user_id = (int)$_REQUEST['user_id'];
			if ($this->user_id <= 0 && is_null($this->token))
			{
				$this->user_id = $_profile->user_id;
			}
		}
		
		if ($this->view == VIEW_GLYPHS)
		{
			$this->events = array();
			if ($this->event_id > 0)
			{
				$e = new stdClass();
				$e->id = $this->event_id;
				$e->name = $this->event_name;
				$this->events[] = $e;
			}
			else
			{
				$query = new DbQuery('SELECT id, name FROM events WHERE tournament_id = ?', $this->tournament_id);
				while ($row = $query->next())
				{
					list ($eid, $ename) = $row;
					$e = new stdClass();
					$e->id = $eid;
					$e->name = $ename;
					$this->events[] = $e;
				}
			}
		}
	}

	protected function show_body()
	{
		if (is_null($this->token))
		{
			$this->view == VIEW_SCENE_SWITCHING;
			$this->show_scene_switching();
		}
		else if ($this->user_id <= 0)
		{
			$this->view == VIEW_GLYPHS;
			$this->show_glyphs();
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
		
		if ($this->user_id <= 0)
		{
			echo '<td align="center">' . get_label('User account') . ': ';
			show_user_input('user', '', '', get_label('Please select a user account that will be used to referee games.'), 'changeUser');
			echo '</td>';
		}
		else
		{
			echo '<input type="hidden" value="' . $this->user_id . '">';
		}
		echo '<td align="right">' . get_label('Table') . ': <input id="table" type="number" min="1" value="1" style="width: 30px;" onchange="generateUrls()"></td>';
		echo '</tr></table></p>';
		
		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="header"><td width="180">' . get_label('Type') . '</td><td>' . get_label('URL') . '</td><td width="110">' . get_label('Recomended size') . '</td><td width="64"></td></tr>';
		echo '<tr><td class="dark">' . get_label('Glyphs and info full screen') . ':</td><td id="url-0"></td><td>1920 x 1080</td><td align="center"><button onclick="copyUrl(0)">' . get_label('Copy') . '</button></td></tr>';
		echo '<tr><td class="dark">' . get_label('Glyphs only') . ':</td><td id="url-1"></td><td>1920 x 260</td><td align="center"><button onclick="copyUrl(1)">' . get_label('Copy') . '</button></td></tr>';
		echo '<tr><td class="dark">' . get_label('Info only') . ':</td><td id="url-2"></td><td>1920 x 100</td><td align="center"><button onclick="copyUrl(2)">' . get_label('Copy') . '</button></td></tr>';
		echo '<tr><td class="dark">' . get_label('Tournament table') . ':</td><td id="url-3"></td><td>';
		echo '<table class="transp" width="100%">';
		echo '<tr><td>' . get_label('rows') . ':</td><td><input id="row" style="width: 30px;" type="number" min="1" value="10" onchange="generateUrls()"></td></tr>';
		echo '<tr><td>' . get_label('columns') . ':</td><td><input id="col" style="width: 30px;" type="number" min="1" value="1" onchange="generateUrls()"></td></tr>';
		echo '</table></td><td align="center"><button onclick="copyUrl(3)">' . get_label('Copy') . '</button></td></tr>';
		for ($i = 0; $i < count($this->events); ++$i)
		{
			$e = $this->events[$i];
			echo '<tr><td class="dark">' . get_label('Next game for [0]', $e->name) . ':</td><td id="url-' . ($i + 4) . '"></td><td>880 x 240</td><td align="center"><button onclick="copyUrl(' . ($i + 4) . ')">' . get_label('Copy') . '</button></td></tr>';
		}
		echo '</tr></table></p>';
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
			var events = <?php echo json_encode($this->events); ?>;
			var url = Array(<?php echo count($this->events) + 4; ?>).fill('');
			var userId = <?php echo $this->user_id; ?>;
			var site = '<?php echo get_server_url(); ?>';
			
			function changeUser(data)
			{
				userId = data ? data.id : 0;
				generateUrls();
			}
			
			function generateUrls()
			{
				if (userId > 0)
				{
					let params = '?user_id=' + userId + '&token=<?php echo $this->token; ?>&locale=' + $('#lang').val();
					url[1] = site + '/obs_plugins/players-overlay-plugin/#/players' + params;
					url[2] = site + '/obs_plugins/players-overlay-plugin/#/gamestats' + params;
					url[0] = site + '/obs_plugins/players-overlay-plugin/#/overlay' + params;
				}
				else
				{
					url[0] = url[1] = url[2] = '<?php echo get_label('Please select a user account that will be used to referee games.'); ?>';
				}
				
				url[3] = site + '/plugins/standings.php?id=<?php echo $this->tournament_id; ?>&rows=' + $('#row').val() + '&columns=' + $('#col').val();
				
				for (let i = 0; i < events.length; ++i)
				{
					url[i + 4] = site + '/plugins/next_game.php?table=' + $('#table').val() + '&id=' + events[i].id;
				}
				
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