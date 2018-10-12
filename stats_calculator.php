<?php

require_once 'include/general_page_base.php';

class Page extends PageBase
{
	private $id;
	private $name;
	private $description;
	private $code;
	private $owner_id;
	
	protected function prepare()
	{
		global $_profile;
		
		parent::prepare();
		$this->id = 0;
		if (isset($_REQUEST['id']))
		{
			$this->id = (int)$_REQUEST['id'];
		}
		
		if ($this->id > 0)
		{
			list($this->name, $this->description, $this->code, $this->owner_id) = Db::record('stats code', 'SELECT name, description, code, owner_id FROM stats_calculators WHERE id = ? ORDER BY name', $this->id);
		}
		else
		{
			$this->name = 'New stats calculator';
			$this->description = '';
			$this->code = "var mafiaWins;\n\nfunction reset()\n{\n\tmafiaWins = 0;\n}\n\nfunction proceedGame(game, num)\n{\n\tif(game.winner == 'maf')\n\t{\n\t\t++mafiaWins;\n\t}\n}\n\nfunction complete()\n{\n\treturn 'Mafia wins: ' + mafiaWins;\n}";
			$this->owner_id = $_profile->user_id;
		}
	}
	
	protected function show_body()
	{
		global $_profile;
		
		echo '<table width="100%" class="bordered light">';
		
		echo '<tr><td colspan="2">';
		echo '<div id="count"></div><p><input type="submit" class="btn long" value="Calculate" onclick="calculate()"></p>';
		echo '</td></tr>';
		
		echo '<tr class="darker">';
		if ($_profile->user_id == $this->owner_id)
		{
			echo '<td width="64">';
			echo '<button class="icon" onclick="editCalculator(' . $this->id . ')" title="' . get_label('Edit calculator [0]', $this->name) . '"><img src="images/edit.png" border="0"></button>';
			if ($this->id > 0)
			{
				echo '<button class="icon" onclick="deleteCalculator(' . $this->id . ')" title="' . get_label('Delete calculator [0]', $this->name) . '"><img src="images/delete.png" border="0"></button>';
			}
			echo '</td><td>';
		}
		else
		{
			echo '<td colspan="2">';
		}
		echo '<select id="calculator" onchange="refresh()">';
		show_option(0, $this->id, get_label('New stats calculator'));
		$query = new DbQuery('SELECT id, name FROM stats_calculators WHERE owner_id = ? OR published = TRUE ORDER BY name', $_profile->user_id);
		while ($row = $query->next())
		{
			list($id, $name) = $row;
			show_option($id, $this->id, $name);
		}
		echo '</select>';
		echo '</td></tr>';
		
		if (!empty($this->description))
		{
			echo '<tr><td colspan="2"><pre>';
			echo '<pre>';
			echo $this->description;
			echo '</pre></td></tr>';
		}
		
		echo '<tr class="darker"><td colspan="2">' . get_label('Results') . ':</td></tr><tr><td colspan="2">';
		echo '<div id="content">...</div>';
		echo '</td></tr>';
		
		echo '<tr class="darker"><td colspan="2">' . get_label('Code') . ':</td></tr><tr><td colspan="2"><pre>';
		echo htmlspecialchars($this->code, ENT_QUOTES);
		echo '</pre></td></tr></table>';
	}
	
	protected function js()
	{
		echo $this->code;
?>

		var requestsToGo = 0;
		var requestsComplete = 0;
		function next(page)
		{
			json.post("api/get/games.php?page_size=20&page=" + page, {}, function (data)
			{
				for (var i = 0; i < data.games.length; ++i)
				{
					proceedGame(data.games[i], page * 20 + i);
				}
				++requestsComplete;
				$('#count').html('Page: ' + requestsComplete + ' out of ' + requestsToGo);
				if (requestsComplete >= requestsToGo)
				{
					$('#content').html(complete());
					requestsToGo = requestsComplete = 0;
				}
			});
		}

		function calculate()
		{
			var errorMessage = null;
			try
			{
				reset();
				json.post("api/get/games.php?count", {}, function (data)
				{
					requestsToGo = Math.ceil(data.count / 20);
					requestsComplete = 0;
					for (var page = 0; page < requestsToGo; ++page)
					{
						next(page);
					}
				});
			}
			catch (err)
			{
				dlg.error(err.message);
			}
		}
		
		function editCalculator(id)
		{
			dlg.form("stats_calculator_edit.php?id=" + id, function(data)
			{
				refr("stats_calculator.php?id=" + data.id);
			});
		}
		
		function deleteCalculator(id)
		{
			dlg.yesNo("<?php echo  get_label('Are you sure you want to delete calculator [0]', $this->name); ?>", null, null, function()
			{
				json.post("api/ops/stats_calculator.php", { op: "delete", id: id }, function()
				{
					refr("stats_calculator.php");
				});
			});
		}
		
		function refresh()
		{
			refr("stats_calculator.php?id=" + $("#calculator").val());
		}
<?php
	}
	
}

$page = new Page();
$page->run(get_label('Stats calculator'), PERM_USER);

?>

