<?php

require_once 'include/general_page_base.php';

class Page extends PageBase
{
	protected function show_body()
	{
		echo '<p><input type="submit" class="btn long" value="Calculate" onclick="calculate()"></p><div id="content"></div>';
	}
	
	protected function js()
	{
?>
		var obj = {};
		var array = [];
		function getRole(player)
		{
			var role;
			if (player.role == "civ")
			{
				if (typeof player.checked_by_srf == "number" && player.checked_by_srf == 0)
				{
					role = 3;
				}
				else
				{
					role = 0;
				}
			}
			else if (player.role == "srf")
			{
				role = 2;
			}
			else if (typeof player.checked_by_srf == "number" && player.checked_by_srf == 0)
			{
				role = 4;
			}
			else
			{
				role = 1;
			}
			return role;
		}
		
		function proceedGame(game, num)
		{
			var voting = [-1, -1, -1, -1, -1, -1, -1, -1, -1, -1];
			var votersCount = 0;
			for (var i = 0; i < game.players.length; ++i)
			{
				var player = game.players[i];
				if (typeof player.voting === "object")
				{
					if (typeof player.voting.round_1 === "number")
					{
						voting[i] = player.voting.round_1;
						++votersCount;
					}
					else if (typeof player.voting.round_1 === "object" && typeof player.voting.round_1[0] === "number")
					{
						voting[i] = player.voting.round_1[0];
						++votersCount;
					}
				}
			}
			
			if (votersCount == 9)
			{
				var nominants = {};
				for (var i = 0; i < game.players.length; ++i)
				{
					var n = voting[i];
					if (n < 0)
					{
						continue;
					}
					
					var player = game.players[i];
					var key = 'p' + n;
					var nom = nominants[key];
					if (typeof nom  === "undefined")
					{
						var role = getRole(game.players[n]);
						nominants[key] = nom = { "civ":0, "maf":0, "shf":0, "red":0, "blk":0, "nom":role };
					}
					
					switch (getRole(player))
					{
						case 0:
							++nom.civ;
							break;
						case 1:
							++nom.maf;
							break;
						case 2:
							++nom.shf;
							break;
						case 3:
							++nom.red;
							break;
						case 4:
							++nom.blk;
							break;
					}
				}
				
				for (var prop in nominants) 
				{
					if (!nominants.hasOwnProperty(prop))
					{
						continue;
					}
					
					var nom = nominants[prop];
					var key = "_" + nom.civ + nom.maf + nom.shf + nom.red + nom.blk;
					var o = obj[key];
					if (typeof o  === "undefined")
					{
						obj[key] = o = { "civ":nom.civ, "maf":nom.maf, "shf":nom.shf, "red":nom.red, "blk":nom.blk, "to_civ":0, "to_maf":0, "to_shf":0, "to_red":0, "to_blk":0 };
					}
					
					switch (nom.nom)
					{
						case 0:
							++o.to_civ;
							break;
						case 1:
							++o.to_maf;
							break;
						case 2:
							++o.to_shf;
							break;
						case 3:
							++o.to_red;
							break;
						case 4:
							++o.to_blk;
							break;
					}
				}
			}
		}
		
		function complete()
		{
			var array = [];
			for (var prop in obj) 
			{
				if (obj.hasOwnProperty(prop))
				{
					array.push(obj[prop]);
				}
			}
			
			array.sort(function(a, b)
			{
				var asum = a.civ + a.maf + a.shf + a.red + a.blk;
				var bsum = b.civ + b.maf + b.shf + b.red + b.blk;
				if (asum != bsum)
				{
					return asum - bsum;
				}
				else if (a.civ != b.civ)
				{
					return b.civ - a.civ;
				}
				else if (a.maf != b.maf)
				{
					return b.maf - a.maf;
				}
				else if (a.shf != b.shf)
				{
					return b.shf - a.shf;
				}
				else if (a.red != b.red)
				{
					return b.red - a.red;
				}
				return b.blk - a.blk;
			});
			
			var html = '<table class="bordered light" width="100%">';
			html += '<tr class="th-long darker"><td width="10%" align="center">civ</td><td width="10%" align="center">maf</td><td width="10%" align="center">sheriff</td><td width="10%" align="center">red</td><td width="10%" align="center">black</td><td width="10%" align="center">to civ</td><td width="10%" align="center">to maf</td><td width="10%" align="center">to sheriff</td><td width="10%" align="center">to red</td><td width="10%" align="center">to black</td></tr>';
			for (var i = 0; i < array.length; ++i)
			{
				var line = array[i];
				html += '<tr class="light"><td>' + line.civ + '</td><td>' + line.maf + '</td><td>' + line.shf + '</td><td>' + line.red + '</td><td>' + line.blk + '</td><td>' + line.to_civ + '</td><td>' + line.to_maf + '</td><td>' + line.to_shf + '</td><td>' + line.to_red + '</td><td>' + line.to_blk + '</td></tr>';
			}
			html += "</table>";
			return html;
		}

		//////////////////////////////////////////////////////////////////
		var requestsToGo = 0;
		var requestsComplete = 0;
		function next(page)
		{
			json.post("ws_games.php?page_size=20&page=" + page, {}, function (data)
			{
				for (var i = 0; i < data.games.length; ++i)
				{
					proceedGame(data.games[i], page * 20 + i);
				}
				++requestsComplete;
				if (requestsComplete >= requestsToGo)
				{
					$('#content').html(complete());
					requestsToGo = requestsComplete = 0;
				}
			});
		}

		function calculate()
		{
			json.post("ws_games.php?count", {}, function (data)
			{
				requestsToGo = Math.ceil(data.count / 20);
				for (var page = 0; page < requestsToGo; ++page)
				{
					next(page);
				}
			});
		}
<?php
	}
	
}

$page = new Page();
$page->run('Custom games stats', PERM_ALL);

?>

