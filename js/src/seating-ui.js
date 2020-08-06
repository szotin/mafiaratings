var seatingUi = new function()
{
	function _setupView()
	{
		var html = '<p><table width="100%" class="bordered dark">'
		html += '<tr><td width="260" class="darker">' + seatingLabel.playersCount + ':</td><td><input type="number" style="width: 50px;" id="players" value="' + seating.playersCount + '" step="1" max="500" min="10" onchange="seatingUi.setupChanged()"></td></tr>'; 
		html += '<tr><td class="darker">' + seatingLabel.tablesCount + ':</td><td><input type="number" style="width: 50px;" id="tables" value="' + seating.tablesCount + '" step="1" max="50" min="1"></td></tr>'; 
		html += '<tr><td class="darker">' + seatingLabel.gpp + ':</td><td><select id="gpp" value="' + seating.gpp + '"></td></tr>'; 
		html += '</table></p>';
		
		html += '<p><button onclick="seatingUi.generate()">' + seatingLabel.generate + '</button></p>';
		return html;
	}
	
	function _tableView()
	{
		var html = ''; 
		for (var i = 0; i < seating.tablesCount; ++i)
		{
			html += '<h3>' + seatingLabel.table + ' ' + (i+1) + '</h3>';
			html += '<p><table class="bordered dark" width="100%"><tr class="darker"><td></td>';
			for (var j = 1; j < 11; ++j)
			{
				html += '<td width="32" align="center"><b>' + j + '</b></td>';
			}
			html += '</tr>';
			for (var r = 0; r < seating.rounds.length; ++r)
			{
				var round = seating.rounds[r];
				if (round.length <= i)
				{
					continue;
				}
				var table = round[i];
				
				html += '<tr><td><b>' + seatingLabel.round + ' ' + (r+1) + '</b></td>';
				for (var j = 0; j < 10; ++j)
				{
					html += '<td width="32" align="center">' + (table[j] + 1) + '</b></td>';
				}
				html += '<tr>';
			}
			html += '</table></p>';
		}
		return html;
	}
	
	function _roundView()
	{
		var html = '';
		for (var r = 0; r < seating.rounds.length; ++r)
		{
			var round = seating.rounds[r];
			html += '<h3>' + seatingLabel.round + ' ' + (r+1) + '</h3>';
			html += '<p><table class="bordered dark" width="100%"><tr class="darker"><td></td>';
			for (var j = 1; j < 11; ++j)
			{
				html += '<td width="32" align="center"><b>' + j + '</b></td>';
			}
			html += '<tr>';
			for (var t = 0; t < round.length; ++t)
			{
				var table = round[t];
				html += '<tr><td><b>' + seatingLabel.table + ' ' + (t+1) + '</b></td>';
				for (var j = 0; j < 10; ++j)
				{
					html += '<td align="center">' + (table[j] + 1) + '</td>';
				}
				html += '<tr>';
			}
			
			var skipping = seating.playersCount - round.length * 10;
			if (skipping > 0)
			{
				html += '<tr><td><b>' + seatingLabel.skipping + '</b></td>';
				for (var p = 0; p < seating.playersCount; ++p)
				{
					var t;
					for (t = 0; t < round.length; ++t)
					{
						if (round[t].includes(p))
						{
							break;
						}
					}
					if (t >= round.length)
					{
						html += '<td align="center">' + (p + 1) + '</td>';
					}
				}
				html += '<td colspan="' + (10 - skipping) + '"></td></tr>';
			}
			html += '</table></p>';
		}
		return html;
	}
	
	function _playerView()
	{
		var html = ''; 
		for (var p = 0; p < seating.playersCount; ++p)
		{
			html += '<h3>' + seatingLabel.player + ' ' + (p+1) + '</h3>';
			var columnCount = 0;
			html += '<p><table class="bordered dark" width="100%"><tr>';
			for (var r = 0; r < seating.rounds.length; ++r)
			{
				if (columnCount++ >= 10)
				{
					columnCount = 1;
					html += '</tr><tr>';
				}
				
				var round = seating.rounds[r];
				var t,n;
				for (t = 0; t < round.length; ++t)
				{
					var table = round[t];
					for (n = 0; n < 10; ++n)
					{
						if (table[n] == p)
						{
							break;
						}
					}
					if (n < 10)
					{
						break;
					}
				}
				
				html += '<td width="10%" valign="top"' + (t < round.length ? '' : ' class="darker"') + '><table class="transp" width="100%"><tr><td width="50"><b>' + seatingLabel.round + ':</b></td><td><b>' + (r + 1) + '</b></td></tr>';
				if (t < round.length)
				{
					html += '<tr><td>' + seatingLabel.table + ':</td><td>' + (t + 1) + '</tr><tr><td>' + seatingLabel.number + ':</td><td>' + (n + 1) + '</td></tr>';
				}
				html += '</table></td>';
			}
			while (columnCount++ < 10)
			{
				html += '<td width="10%"></td>';
			}
			html += '</tr></table></p>';
		}
		return html;
	}
	
	function _numberStatsView()
	{
		// init
		var players = [];
		for (var p = 0; p < seating.playersCount; ++p)
		{
			players.push([0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
		}
		
		// calculate
		for (var r = 0; r < seating.rounds.length; ++r)
		{
			var round = seating.rounds[r];
			for (var t = 0; t < round.length; ++t)
			{
				var table = round[t];
				for (var n = 0; n < 10; ++n)
				{
					++players[table[n]][n];
				}
			}
		}
		
		// output
		var html = '<p><table class="bordered dark" width="100%"><tr class="darker"><td></td>';
		for (var n = 1; n < 11; ++n)
		{
			html += '<td width="50" align="center"><b>' + n + '</b></td>';
		}
		html += '</tr>';
		for (var p = 0; p < seating.playersCount; ++p)
		{
			html += '<tr><td>' + seatingLabel.player + ' ' + (p+1) + '</td>';
			var numbers = players[p];
			for (var n = 0; n < 10; ++n)
			{
				html += '<td align="center">' + (numbers[n] > 0 ? numbers[n] : '') + '</td>';
			}
			html += '</tr>'
		}
		html += '</table>';
		return html;
	}
	
	function _tableStatsView()
	{
		// init
		var players = [];
		for (var p = 0; p < seating.playersCount; ++p)
		{
			var tables = [];
			for (var t = 0; t < seating.tablesCount; ++t)
			{
				tables.push(0);
			}
			players.push(tables);
		}
		
		// calculate
		for (var r = 0; r < seating.rounds.length; ++r)
		{
			var round = seating.rounds[r];
			for (var t = 0; t < round.length; ++t)
			{
				var table = round[t];
				for (var n = 0; n < 10; ++n)
				{
					++players[table[n]][t];
				}
			}
		}
		
		// output
		var html = '<p><table class="bordered dark" width="100%"><tr class="darker"><td></td>';
		for (var t = 0; t < seating.tablesCount; ++t)
		{
			html += '<td width="60" align="center"><b>' + seatingLabel.table + " " + (t+1) + '</b></td>';
		}
		html += '</tr>';
		for (var p = 0; p < seating.playersCount; ++p)
		{
			html += '<tr><td>' + seatingLabel.player + ' ' + (p+1) + '</td>';
			var tables = players[p];
			for (var t = 0; t < tables.length; ++t)
			{
				html += '<td align="center">' + (tables[t] > 0 ? tables[t] : '') + '</td>';
			}
			html += '</tr>'
		}
		html += '</table>';
		return html;
	}
	
	function _showDistr(freqs)
	{
		var len = freqs.length;
		while (freqs[len - 1] == 0)
		{
			if (--len == 0)
			{
				return "0";
			}
		}
		var beg = 0;
		while (freqs[beg] == 0)
		{
			++beg;
		}
		
		var html = '<p><table class="bordered dark"><tr class="darker" align="center">';
		for (var f = beg; f < len; ++f)
		{
			html += '<td width="20">' + f + '</td>';
		}
		html += '</tr><tr align="center">'
		for (var f = beg; f < len; ++f)
		{
			html += '<td>' + freqs[f] + '</td>';
		}
		html += '</tr></table></p>';
		return html;
	}
	
	function _pvpStatsView()
	{
		// init
		var players = [];
		var freqs = [];
		var freqSum = [];
		for (var p1 = 0; p1 < seating.playersCount; ++p1)
		{
			var player = [];
			for (var p2 = 0; p2 < seating.playersCount; ++p2)
			{
				player.push(0);
			}
			players.push(player);
			
			var freq = [];
			for (var f = 0; f <= seating.gpp; ++f)
			{
				freq.push(0);
			}
			freqs.push(freq);
		}
		
		for (var f = 0; f <= seating.gpp; ++f)
		{
			freqSum.push(0);
		}
		
		// calculate
		for (var r = 0; r < seating.rounds.length; ++r)
		{
			var round = seating.rounds[r];
			for (var t = 0; t < round.length; ++t)
			{
				var table = round[t];
				for (var n1 = 0; n1 < 10; ++n1)
				{
					var p = players[table[n1]];
					for (var n2 = 0; n2 < 10; ++n2)
					{
						++p[table[n2]];
					}
				}
			}
		}
		
		for (var p1 = 0; p1 < seating.playersCount; ++p1)
		{
			var player = players[p1];
			var freq = freqs[p1];
			for (var p2 = 0; p2 < seating.playersCount; ++p2)
			{
				if (p1 != p2)
				{
					var c = player[p2];
					++freq[c];
					++freqSum[c];
				}
			}
		}
		
		while (freqSum[freqSum.length-1] == 0)
		{
			freqSum.pop();
		}
		
		for (var f = 0; f < freqSum.length; ++f)
		{
			freqSum[f] /= 2; // Because we increase value twice for each pair. Once for p1-p2 and once for p2-p1.
		}
		
		for (var p1 = 0; p1 < seating.playersCount; ++p1)
		{
			freqs[p1] = freqs[p1].slice(0, freqSum.length);
		}
		
		// output
		var html = 
			'<table class="transp" width="100%"><tr><td>"<h3>' + 
			seatingLabel.totalFreqs + 
			'</h3></td><td align="right"><button onclick="seatingUi.optimizeDistr()">' + 
			seatingLabel.optimize + 
			'</button></td></tr></table>' + 
			_showDistr(freqSum);
		
		for (var p1 = 0; p1 < seating.playersCount; ++p1)
		{
			html += '<h3>' + seatingLabel.player + ' ' + (p1+1) + '</h3>' + _showDistr(freqs[p1]);
			
			var player = players[p1];
			var columnCount = 0;
			html += '<p><table class="bordered dark" width="100%"><tr>';
			for (var p2 = 0; p2 < seating.playersCount; ++p2)
			{
				if (columnCount++ >= 10)
				{
					columnCount = 1;
					html += '</tr><tr>';
				}
				
				html += '<td width="10%" align="center"'  + (p1 != p2 ? '' : ' class="darker"') + '>';
				if (p1 != p2)
				{
					html += seatingLabel.withPlayer + ' ' + (p2 + 1) + '<p>' + player[p2] + '</p>';
				}
				html += '</td>';
			}
			while (columnCount++ < 10)
			{
				html += '<td width="10%"></td>';
			}
			html += '</tr></table></p>';
		}
		return html;
	}
	
	function _showTab(tab)
	{
		var html = '';
		var initControls = false;
		switch (tab)
		{
			case 0:
				html = _setupView();
				initControls = true;
				break;
			case 1:
				html = _tableView();
				break;
			case 2:
				html = _roundView();
				break;
			case 3:
				html = _playerView();
				break;
			case 4:
				html = _tableStatsView();
				break;
			case 5:
				html = _numberStatsView();
				break;
			case 6:
				html = _pvpStatsView();
				break;
		}
		$('#content').html(html);
		if (initControls)
		{
			seatingUi.setupChanged();
		}
	}
	
	function _activateTab(e, ui)
	{
		_showTab(ui.newTab.index());
	}
	
	function _setEnables(enable)
	{
		if (typeof enable == "undefined")
		{
			enable = (seating.rounds != null);
		}
		
		if (enable)
		{
			$("#tabs").tabs("option", "disabled", false);
		}
		else
		{
			$("#tabs").tabs("option", "disabled", [1, 2, 3, 4, 5, 6]);
		}
	}
	
	this.show = function()
	{
		var html = '<div id="tabs"><ul>';
		html += '<li><a href="#content">' +  seatingLabel.setupView + '</a></li>';
		html += '<li><a href="#content">' +  seatingLabel.tableView + '</a></li>';
		html += '<li><a href="#content">' +  seatingLabel.roundView + '</a></li>';
		html += '<li><a href="#content">' +  seatingLabel.playerView + '</a></li>';
		html += '<li><a href="#content">' +  seatingLabel.tableStatsView + '</a></li>';
		html += '<li><a href="#content">' +  seatingLabel.numberStatsView + '</a></li>';
		html += '<li><a href="#content">' +  seatingLabel.pvpStatsView + '</a></li>';
		html += '</ul>';
		html += '<div id="content"></div>';
		html += '</div>';
		$('#seating').html(html);
		$("#tabs").tabs({ activate: _activateTab });
		_showTab(0);
		_setEnables();
	}
	
	this.setupChanged = function()
	{
		var playersCount = $("#players").val();
		var tablesCount = $("#tables").val();
		var gppSelect = $("#gpp");
		$("#tables").attr("max", Math.floor(playersCount / 10));
		
		gppSelect.empty();
		for (var i = 1; i <= 100; ++i)
		{
			if ((playersCount * i) % 10 == 0)
			{
				gppSelect.append('<option value="' + i + '"' + (i == seating.gpp ? ' selected' : '') + '>' + i + '</option>');
			}
		}
	}
	
	this.generate = function()
	{
		var playersCount = $("#players").val();
		var tablesCount = $("#tables").val();
		var gpp = $("#gpp").val();
		_setEnables(false);
		setTimeout(function() 
		{	
			seating.generate(playersCount, tablesCount, gpp);
			_setEnables();
		});
	}
	
	this.optimizeDistr = function()
	{
		dlg.custom('<div id="progress"></div>', seatingLabel.optimizing, 400, 
		{
			stop: { id:"dlg-stop", text: seatingLabel.stop, click: function() { dlg.close();  } }
		}, function() {seating.stop();} );
		
		_setEnables(false);
		seating.optimizeDistr
		(
			function(distr)
			{
				$("#progress").html(_showDistr(distr));
				//console.log(distr);
			},
			function(complete)
			{
				if (complete)
				{
					dlg.close();
				}
				_setEnables();
				_showTab(6);
			}
		);
	}
}