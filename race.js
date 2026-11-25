// The race of a competition - see include/race.php. Plain JavaScript, loaded as it is: it is not
// compiled and it does not use jQuery, like new_look.js.
//
// The race moves along the games: position k is the moment game k ended, and between two of them
// every score is a straight line from one to the other, so the numbers and the bars grow smoothly
// and the rows change places as the scores cross. Who is in the top is asked at every frame, of
// everybody who has played by then: a row slides in from below when its player climbs into the top
// and fades away below when they fall out of it.
function raceStart(params)
{
	'use strict';

	var times = params.times;
	var n = times.length;
	var top = params.top;
	var players = params.players;
	var count = players.length;

	// The running total of each player after each moment, what the order goes by at that moment (the
	// total itself, unless the race says otherwise - see race_player()), and the first moment they
	// have one.
	var values = [];
	var keys = [];
	var first = [];
	for (var i = 0; i < count; ++i)
	{
		var h = players[i].h;
		var v = new Array(n);
		var w = new Array(n);
		var j = 0;
		var last = null;
		var lastKey = null;
		first[i] = n;
		for (var k = 0; k < n; ++k)
		{
			while (j < h.length && h[j][0] <= times[k])
			{
				last = h[j][1];
				lastKey = h[j].length > 2 ? h[j][2] : last;
				++j;
			}
			v[k] = last;
			w[k] = lastKey;
			if (last !== null && first[i] == n)
			{
				first[i] = k;
			}
		}
		values[i] = v;
		keys[i] = w;
	}

	var list = document.getElementById('race-list');
	var dateEl = document.getElementById('race-date');
	var stepEl = document.getElementById('race-step');
	var playBtn = document.getElementById('race-play');
	var resetBtn = document.getElementById('race-reset');
	var speedBox = document.getElementById('race-speed');
	var range = document.getElementById('race-pos');
	var bar = document.getElementById('race-bar');

	// A row for everybody, out of sight below the top until they are in it.
	var rows = [];
	for (var i = 0; i < count; ++i)
	{
		var p = players[i];
		var li = document.createElement('li');
		li.className = 'race-row c' + ((p.id % 6) + 1) + (p.id == params.me ? ' me' : '');
		li.innerHTML = '<span class="rb"></span><span class="rn"></span><a class="who" href="' + p.url + '">' + p.av +
			'<span class="who-name"></span>' + (p.club || '') + '</a><span class="rv"></span>';
		li.querySelector('.who-name').innerHTML = p.name;
		list.appendChild(li);
		rows.push({ li: li, bar: li.querySelector('.rb'), num: li.querySelector('.rn'), val: li.querySelector('.rv'), rank: -1, shown: false, y: 0, target: 0, snap: true });
	}
	var rowHeight = 0;
	function measure()
	{
		rowHeight = rows.length > 0 ? rows[0].li.offsetHeight : 0;
		list.style.height = (Math.min(top, count) * rowHeight) + 'px';
		for (var i = 0; i < count; ++i)
		{
			rows[i].rank = -2; // to be placed again
			rows[i].snap = true; // and put there at once rather than slid
		}
	}

	// The moment on top: the day and the hour of a game, or only the day where the race says so - a
	// rating, whose moments are stretches of days, and a series.
	var dateOpts = { day: 'numeric', month: 'long', year: 'numeric' };
	if (!params.days)
	{
		dateOpts.hour = '2-digit';
		dateOpts.minute = '2-digit';
	}
	var fmtDate;
	try
	{
		dateOpts.timeZone = params.timezone;
		fmtDate = new Intl.DateTimeFormat(params.lang, dateOpts);
	}
	catch (e)
	{
		delete dateOpts.timeZone;
		fmtDate = new Intl.DateTimeFormat(params.lang, dateOpts);
	}

	function format(v)
	{
		if (params.rating)
		{
			return Math.round(v).toLocaleString(params.lang);
		}
		return (Math.round(v * 100) / 100).toLocaleString(params.lang, { maximumFractionDigits: 2 });
	}

	var order = [];
	for (var i = 0; i < count; ++i)
	{
		order.push(i);
	}
	var now = [];
	var nowKey = [];

	function draw(pos)
	{
		var k = Math.floor(pos);
		if (k >= n - 1)
		{
			k = n - 1;
		}
		var f = pos - k;
		for (var i = 0; i < count; ++i)
		{
			if (first[i] > k + (f > 0 ? 1 : 0))
			{
				now[i] = null; // has not played yet
				continue;
			}
			var a = values[i][k];
			var b = k + 1 < n ? values[i][k + 1] : a;
			var ka = keys[i][k];
			var kb = k + 1 < n ? keys[i][k + 1] : ka;
			if (a === null)
			{
				// Their first game is on the way: they start from nothing, or from the thousand
				// everybody's rating starts at.
				a = params.rating ? 1000 : 0;
				ka = kb - b + a;
			}
			now[i] = a + (b - a) * f;
			nowKey[i] = ka + (kb - ka) * f;
		}
		// The order of the moment; the one of the moment before settles a tie, so rows do not jump
		// about while two players stand level.
		order.sort(function(x, y)
		{
			if (now[x] === null || now[y] === null)
			{
				return (now[x] === null) - (now[y] === null) || x - y;
			}
			return nowKey[y] - nowKey[x] || rows[x].rank - rows[y].rank || x - y;
		});

		// How long a bar is. Points are measured from nothing; a rating, which starts at a thousand
		// for everybody, from a little below the lowest one shown - or every bar would be nearly full.
		var max = 0;
		var min = Infinity;
		for (var r = 0; r < order.length && r < top; ++r)
		{
			var v = now[order[r]];
			if (v !== null)
			{
				max = Math.max(max, params.rating ? v : Math.abs(v));
				min = Math.min(min, v);
			}
		}
		var base = 0;
		if (params.rating && min < Infinity)
		{
			base = min - Math.max(20, (max - min) * 0.25);
		}

		for (var r = 0; r < order.length; ++r)
		{
			var i = order[r];
			var row = rows[i];
			var shown = r < top && now[i] !== null;
			if (shown)
			{
				row.target = r * rowHeight;
				if (row.rank != r)
				{
					row.num.textContent = r + 1;
					row.rank = r;
				}
				row.val.textContent = format(now[i]);
				// The bar runs under the name, from the place to the points - see .race-row .rb.
				row.bar.style.width = 'calc((100% - var(--race-track-off)) * ' + (max > base ? Math.max(0, now[i] - base) / (max - base) : 0) + ')';
			}
			else
			{
				// Out of the top: it sinks below the last place as it fades.
				row.target = Math.min(top, count) * rowHeight;
				row.rank = top + r;
			}
			if (shown != row.shown)
			{
				row.li.classList.toggle('on', shown);
				row.shown = shown;
			}
		}

		var at = Math.min(n - 1, Math.round(pos));
		dateEl.textContent = fmtDate.format(new Date(times[at] * 1000));
		var what = params.moments[times[at]];
		stepEl.textContent = (what ? what + ' · ' : '') + params.labels.step.replace('[0]', at + 1).replace('[1]', n);
		bar.style.width = (n > 1 ? pos / (n - 1) * 100 : 100) + '%';
		range.value = pos;
		slide();
	}

	// The rows follow their places on their own clock: each frame a row covers a part of the way that
	// is left, so it glides however often the places change - one step every 150 milliseconds or a
	// drag of the slider - instead of piling transitions up on top of each other.
	var sliding = false;
	var slideLast = 0;
	function slide()
	{
		if (!sliding)
		{
			sliding = true;
			slideLast = 0;
			requestAnimationFrame(slideFrame);
		}
	}
	function slideFrame(t)
	{
		var dt = slideLast ? Math.min(100, t - slideLast) : 16;
		slideLast = t;
		var k = 1 - Math.exp(-dt / 110);
		var moving = false;
		for (var i = 0; i < count; ++i)
		{
			var row = rows[i];
			var d = row.target - row.y;
			if (d == 0 && !row.snap)
			{
				continue;
			}
			if (row.snap || Math.abs(d) < 0.5)
			{
				row.y = row.target;
				row.snap = false;
			}
			else
			{
				row.y += d * k;
				moving = true;
			}
			row.li.style.transform = 'translateY(' + row.y.toFixed(1) + 'px)';
		}
		if (moving)
		{
			requestAnimationFrame(slideFrame);
		}
		else
		{
			sliding = false;
		}
	}

	// The playback: a game every so many milliseconds, faster when there are many of them, so that a
	// season does not take longer to watch than a tournament of a weekend by much - five minutes at
	// most, and three quarters of a second a step at least.
	var stepMs = Math.max(720, Math.min(5400, 300000 / Math.max(1, n)));
	var pos = 0;
	var playing = false;
	var last = 0;
	var playIcon = playBtn.innerHTML;
	var pauseIcon = '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5h4v14H7zM13 5h4v14h-4z" fill="currentColor"/></svg>';

	function setPlaying(on)
	{
		playing = on;
		playBtn.innerHTML = on ? pauseIcon : playIcon;
		playBtn.setAttribute('aria-label', on ? params.labels.pause : params.labels.play);
		if (on)
		{
			last = 0;
			requestAnimationFrame(tick);
		}
	}

	function tick(t)
	{
		if (!playing)
		{
			return;
		}
		if (last)
		{
			pos += (t - last) / stepMs * speed;
		}
		last = t;
		if (pos >= n - 1)
		{
			pos = n - 1;
			draw(pos);
			setPlaying(false);
			return;
		}
		draw(pos);
		requestAnimationFrame(tick);
	}

	// The speed: a multiple of the pace above, the one chosen last kept in this browser.
	var speed = 1;
	try
	{
		speed = parseFloat(localStorage.getItem('raceSpeed')) || 1;
	}
	catch (e)
	{
	}
	function showSpeed()
	{
		var buttons = speedBox.querySelectorAll('button');
		for (var b = 0; b < buttons.length; ++b)
		{
			buttons[b].setAttribute('aria-pressed', parseFloat(buttons[b].getAttribute('data-speed')) == speed ? 'true' : 'false');
		}
	}
	speedBox.addEventListener('click', function(e)
	{
		var b = e.target.closest('button');
		if (!b)
		{
			return;
		}
		speed = parseFloat(b.getAttribute('data-speed')) || 1;
		try
		{
			localStorage.setItem('raceSpeed', speed);
		}
		catch (e2)
		{
		}
		showSpeed();
	});
	showSpeed();

	// Back to the start of the race, stopped there: Play runs it again from the first game.
	resetBtn.addEventListener('click', function()
	{
		setPlaying(false);
		pos = 0;
		draw(pos);
	});
	playBtn.addEventListener('click', function()
	{
		if (playing)
		{
			setPlaying(false);
			return;
		}
		if (pos >= n - 1)
		{
			pos = 0;
		}
		setPlaying(true);
	});
	range.addEventListener('input', function()
	{
		setPlaying(false);
		pos = parseFloat(range.value);
		draw(pos);
	});
	window.addEventListener('resize', function()
	{
		measure();
		draw(pos);
	});

	measure();
	var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	if (still || n < 2)
	{
		pos = n - 1; // the end of the race, where it stands now
		draw(pos);
	}
	else
	{
		draw(pos);
		setPlaying(true);
	}
}
