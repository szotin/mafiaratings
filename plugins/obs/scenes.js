//  Changes the OBS scene from inside a browser source, without obs-websocket.
//
//  A page shown as a browser source gets window.obsstudio from OBS itself, and that object can
//  change the scene. So the pages already standing in the scene collection - the glyphs, the
//  info bar, the between-games screens - can do the switching between them, and there is
//  nothing to install, no server to enable, no port and no password. The source has to be set
//  to Page permissions: Advanced, which is the control level the switching needs.
//
//  The page is told what to switch to by the site: api/get/stream.php says what the game being
//  played is asking for and what, if anything, a person has asked for by hand. Several sources
//  may be running at once and all of them do this; they all read the same answer and ask for
//  the same scene, and asking OBS for the scene it is already showing does nothing, so they do
//  not need to know about each other.
//
//  Outside OBS - in an ordinary browser - window.obsstudio is not there and this does nothing
//  at all, which is what a page opened for a look should do.

var obsScenes = new function()
{
	var settingsUrl;   // where to ask what scene this table should be showing
	var params;        // which table that is
	var applied;       // the last scene we asked OBS for
	var lastAuto;      // the last scene the game asked for, carried out or not
	var lastManual;    // when the last order from a person was given

	// What was last told to the settings page, so that it has the scenes of this OBS to offer
	// and can say when a source is not allowed to switch. Asked again every time round: a
	// scene can be added, renamed or taken away while the source is running, and OBS does not
	// reload the page when that happens.
	var reportedScenes = null;
	var reportedLevel = null;

	this.start = function(url, tableParams, interval)
	{
		if (!window.obsstudio)
		{
			return; // not inside OBS: nothing here to switch
		}

		settingsUrl = url;
		params = tableParams;
		applied = null;
		lastAuto = null;
		lastManual = 0;

		report();
		poll();
		setInterval(function() { report(); poll(); }, interval);
	}

	function poll()
	{
		fetch(settingsUrl + '&' + new URLSearchParams(params)).
			then(function(response) { return response.ok ? response.json() : Promise.reject(response.status); }).
			then(function(answer)
			{
				if (answer && answer.stream && answer.stream.scene)
				{
					follow(answer.stream.scene);
				}
			}).
			catch(function(error) { /* the site is not answering: leave the scene alone */ });
	}

	// An order from a person is carried out once, when it is newer than the last one we saw,
	// and then it stands: the automatic scene is followed again only when the game moves it on
	// by itself. That is what lets somebody switch by hand in the middle of a phase without the
	// next second putting it straight back.
	function follow(scene)
	{
		if (scene.manualTime > lastManual)
		{
			lastManual = scene.manualTime;
			lastAuto = scene.auto;
			switchTo(scene.manual);
			return;
		}

		if (scene.auto != lastAuto)
		{
			lastAuto = scene.auto;
			switchTo(scene.auto);
		}
	}

	function switchTo(name)
	{
		if (!name || name == applied || !window.obsstudio.setCurrentScene)
		{
			return;
		}
		applied = name;
		window.obsstudio.setCurrentScene(name);
	}

	// What OBS this source is running in, as far as the settings page needs to know: the scenes
	// it has, so there is something to choose from, and what this source is allowed to do, so
	// the page can say when it is not allowed to switch at all. Asking OBS costs nothing - it
	// is the program this page is running inside - so it is asked every time round and the
	// site is only written to when the answer is not the one it already has.
	function report()
	{
		if (!window.obsstudio.getControlLevel)
		{
			return;
		}

		window.obsstudio.getControlLevel(function(level)
		{
			if (!window.obsstudio.getScenes)
			{
				send(level, []);
				return;
			}
			window.obsstudio.getScenes(function(scenes) { send(level, scenes); });
		});
	}

	function send(level, scenes)
	{
		var list = JSON.stringify(scenes ? scenes : []);
		if (list === reportedScenes && level === reportedLevel)
		{
			return; // the site already knows this
		}
		reportedScenes = list;
		reportedLevel = level;

		var body = new URLSearchParams(params);
		body.set('op', 'scenes');
		body.set('control_level', level);
		body.set('scenes', list);
		fetch('../../api/ops/stream.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		}).catch(function(error)
		{
			// It did not get there, so it is not something the site knows after all.
			reportedScenes = null;
			reportedLevel = null;
		});
	}
}
