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
	var interval;      // how long between one asking and the next
	var applied;       // the last scene we asked OBS for
	var lastAuto;      // the last scene the game asked for, carried out or not
	var lastManual;    // when the last order from a person was given

	// What was last told to the settings page, so that it has the scenes of this OBS to offer
	// and can say when a source is not allowed to switch. Asked again every time round: a
	// scene can be added, renamed or taken away while the source is running, and OBS does not
	// reload the page when that happens.
	var reportedScenes = null;
	var reportedLevel = null;

	// What OBS has said about its stream, as stretches of one state: [{from, to, up}] in this
	// machine's milliseconds. Kept here rather than sent and forgotten, because the times we
	// most need to account for are exactly the times we cannot send anything - and because the
	// site clips what it is given to the part it has no account of yet, so sending the same
	// stretch twice costs nothing and changes nothing.
	var runs = [];
	var RUN_LIMIT = 50;        // a stream that flapped more than this has said all it can
	var RUN_MEMORY = 3600000;  // an hour; the site will long since have accounted for the rest

	// When OBS went on the air, as this machine's milliseconds, and only when we watched it
	// happen. The recording begins with the first picture OBS sends, so this is the one moment
	// that ties our clock to the recording - and the site works out the delay of the stream
	// from it. A stream that was already running when this page opened tells us nothing about
	// when it began, so nothing is claimed there.
	var startedAt = null;
	var DOWN_EVIDENCE = 30000; // how long we must have watched it being off before we believe

	this.start = function(url, tableParams, delay)
	{
		if (!window.obsstudio)
		{
			return; // not inside OBS: nothing here to switch
		}

		settingsUrl = url;
		params = tableParams;
		interval = delay;
		applied = null;
		lastAuto = null;
		lastManual = 0;

		report();
		sampleStream();
		poll();
		setInterval(function() { report(); sampleStream(); poll(); }, interval);

		// OBS says so itself the moment it happens, which is closer than the next time round -
		// and it is proof that the stream has only just begun, which polling alone never is.
		window.addEventListener('obsStreamingStarted', function() { startedAt = Date.now(); note(true); });
		window.addEventListener('obsStreamingStopped', function() { note(false); });
	}

	// Whether OBS is streaming at this moment. Asked every time round rather than relied upon
	// from the events alone, because this page may have been opened in the middle of a stream
	// and have missed the event that started it.
	//
	// The answer is noted whenever it arrives and is never waited for: a getStatus that does
	// not call back must not be allowed to stop the asking, since silence towards the site is
	// itself read as the machine being off the air.
	function sampleStream()
	{
		if (!window.obsstudio.getStatus)
		{
			return; // an OBS too old to say; the site falls back to reading our silence
		}
		window.obsstudio.getStatus(function(status)
		{
			if (status && typeof status.streaming === 'boolean')
			{
				note(status.streaming);
			}
		});
	}

	// Adds this moment to the account. It joins the stretch before it when that stretch says
	// the same thing and ran up to a moment ago; a longer break than that is a break in what
	// we know, not something to paper over.
	function note(up)
	{
		var now = Date.now();
		var last = runs.length > 0 ? runs[runs.length - 1] : null;
		if (last && last.up === up && now - last.to <= interval * 3)
		{
			last.to = now;
		}
		else
		{
			// Going on the air while we were watching it be off, for long enough that we know
			// it really was off rather than that we had only just arrived. That is a start we
			// can put a time to - see startedAt.
			if (up && last && !last.up && now - last.to <= interval * 3 && last.to - last.from >= DOWN_EVIDENCE)
			{
				startedAt = now;
			}
			runs.push({ from: now, to: now, up: up });
		}

		while (runs.length > RUN_LIMIT || (runs.length > 1 && now - runs[0].to > RUN_MEMORY))
		{
			runs.shift();
		}
	}

	// The account as ages in seconds, so that it is read against the site's clock rather than
	// this machine's. A hall laptop with the wrong date would otherwise place every break in
	// the wrong century.
	function account()
	{
		var now = Date.now();
		var parts = [];
		for (var i = 0; i < runs.length; ++i)
		{
			var r = runs[i];
			parts.push(Math.round((now - r.from) / 1000) + '-' + Math.round((now - r.to) / 1000) +
				':' + (r.up ? 1 : 0));
		}
		return parts.join(',');
	}

	function poll()
	{
		// obs=1 says this is asked from inside OBS, and seen carries what OBS has been saying
		// about its stream since we last got through. Between them they tell the site what the
		// machine was doing while it was not answering, which is what settles where a game
		// sits in the recording afterwards. A silence we never explain is not taken for a
		// break - see include/broadcast.php - so the account is worth re-sending for as long
		// as we have it.
		//
		// started carries the moment OBS went on the air, where we saw it happen. It is sent
		// again and again until it ages out, because the site can only use it once it knows
		// what YouTube says about the same broadcast, and that may be a couple of minutes off.
		var query = settingsUrl + '&obs=1&seen=' + encodeURIComponent(account());
		if (startedAt !== null)
		{
			var age = Math.round((Date.now() - startedAt) / 1000);
			if (age * 1000 > RUN_MEMORY)
			{
				startedAt = null;
			}
			else
			{
				query += '&started=' + age;
			}
		}
		fetch(query + '&' + new URLSearchParams(params)).
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
