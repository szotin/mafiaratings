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
		if (isset($_REQUEST['event_id']))
		{
			$this->event_id = (int)$_REQUEST['event_id'];
			list($club_id, $tournament_id, $this->token, $this->langs) = Db::record(get_label('event'), 'SELECT club_id, tournament_id, security_token, languages FROM events WHERE id = ?', $this->event_id);
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
		global $_profile;
		
		if (is_valid_lang($this->langs))
		{
			$lang = get_lang_code($this->langs);
		}
		else
		{
			$lang = $_lang;
		}
		
		echo '<table class="transp" width="100%"><tr>';
		echo '<td>' . get_label('Language') . ': <select id="lang" onchange="generateUrl()">';
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
		
		echo '<td align="right">' . get_label('Glyphs type') . ': <select id="gtype" onchange="generateUrl()">';
		show_option(0, 0, get_label('Full screen'));
		show_option(1, 0, get_label('Glyphs only'));
		show_option(2, 0, get_label('Info only'));
		echo '</td>';
		
		echo '</tr></table></p>';
		
		echo '<p><table class="bordered light" width="100%"><tr>';
		echo '<td width="64" class="dark">' . get_label('URL') . ':</td><td id="url"></td><td width="180" id="size"></td><td align="center" width="64"><button onclick="copyUrl()">' . get_label('Copy') . '</button></td>';
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
			var url = '';
			var userId = <?php echo $this->user_id; ?>;
			function changeUser(data)
			{
				userId = data ? data.id : 0;
				generateUrl();
			}

			function generateUrl()
			{
				let html = '';
				let htmlSize = '';
				if (userId > 0)
				{
					let params = '?user_id=' + userId + '&token=<?php echo $this->token; ?>&locale=' + $('#lang').val();
					let site = '<?php echo get_server_url(); ?>';
					htmlSize = '<?php echo get_label('Recomended size'); ?>: ';
					switch ($('#gtype').val())
					{
					case '1':
						url = site + '/obs_plugins/players-overlay-plugin/#/players' + params;
						htmlSize += '1920 x 260';
						break;
					case '2':
						url = site + '/obs_plugins/players-overlay-plugin/#/gamestats' + params;
						htmlSize += '1920 x 100';
						break;
					default:
						url = site + '/obs_plugins/players-overlay-plugin/#/overlay' + params;
						htmlSize += '1920 x 1080';
						break;
					}
					html = '<a href="' + url + '" target="_blank">' + url + '</a>';
				}
				else
				{
					html = '<?php echo get_label('Please select a user account that will be used to referee games.'); ?>';
				}
				$('#url').html(html);
				$('#size').html(htmlSize);
			}
			
			function copyUrl()
			{
				navigator.clipboard.writeText(url);
			}
			
			generateUrl();
<?php
		}
	}
}

$page = new Page();
$page->run(get_label('OBS Studio integration'));

?>