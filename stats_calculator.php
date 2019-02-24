<?php

require_once 'include/general_page_base.php';
require_once 'include/ccc_filter.php';

class Page extends PageBase
{
	private $id;
	private $name;
	private $description;
	private $code;
	private $owner_id;
	
	private $page_size;
	private $chair;
	private $ccc_filter;
	
	protected function prepare()
	{
		global $_profile;
		
		parent::prepare();
		
		check_permissions(PERMISSION_USER);
		
		$this->id = 0;
		if (isset($_REQUEST['id']))
		{
			$this->id = (int)$_REQUEST['id'];
		}
		
		$this->page_size = 16;
		if (isset($_REQUEST['page_size']))
		{
			$this->page_size = (int)$_REQUEST['page_size'];
		}
		
		$this->chair = isset($_REQUEST['chair']);
		
		$filter_str = CCCF_CLUB . CCCF_ALL;
		if (isset($_REQUEST['filter']))
		{
			$filter_str = $_REQUEST['filter'];
		}
		$this->ccc_filter = new CCCFilter('filter', $filter_str, CCCF_NO_MY_CLUBS);
		
		if ($this->id > 0)
		{
			list($this->name, $this->description, $this->code, $this->owner_id, $this->owner_name, $this->owner_flags) = Db::record(get_label('stats calculator'), 'SELECT s.name, s.description, s.code, s.owner_id, u.name, u.flags FROM stats_calculators s JOIN users u ON u.id = s.owner_id WHERE s.id = ?', $this->id);
		}
		else
		{
			$query = new DbQuery('SELECT s.id, s.name, s.description, s.code, s.owner_id, u.name, u.flags FROM stats_calculators s JOIN users u ON u.id = s.owner_id WHERE s.owner_id = ? OR s.published = TRUE ORDER BY s.name LIMIT 1', $_profile->user_id);
			if ($row = $query->next())
			{
				list($this->id, $this->name, $this->description, $this->code, $this->owner_id, $this->owner_name, $this->owner_flags) = $row;
			}
		}
	}
	
	protected function show_body()
	{
		global $_profile;
		
		echo '<p><table width="100%" class="bordered light">';
		
		echo '<tr><td colspan="2">';
		echo '<table width="100%" class="transp"><tr>';
		echo '<td width="200">' . get_label('Page size') . ': <select id="page_size">';
		show_option(8, $this->page_size, 8);
		show_option(16, $this->page_size, 16);
		show_option(32, $this->page_size, 32);
		show_option(48, $this->page_size, 48);
		show_option(64, $this->page_size, 64);
		show_option(128, $this->page_size, 128);
		show_option(256, $this->page_size, 256);
		show_option(512, $this->page_size, 512);
		show_option(1024, $this->page_size, 1024);
		echo '</select></td>';
		
		echo '<td width="240">' . get_label('Filter') . ': ';
		$this->ccc_filter->show('filterSelect', get_label('Filter games by club/city/country.'));
		echo '</td>';
		
		echo '<td><input type="checkbox" id="chair"';
		if ($this->chair)
		{
			echo ' checked';
		}
		echo '> ' . get_label('include games with empty chair') . '</td>';
		
		echo '</tr></table>';
		
		echo '<p><input type="submit" class="btn long" value="' . get_label('Calculate') . '" onclick="calculate()"></p>';
		echo '</td></tr>';
		
		echo '<tr class="darker">';
		
		echo '<td width="84">';
		echo '<button class="icon" onclick="editCalculator(0)" title="' . get_label('Create new calculator') . '"><img src="images/create.png" border="0"></button>';
		if ($this->id > 0 && ($_profile->user_id == $this->owner_id || $_profile->is_admin()))
		{
			echo '<button class="icon" onclick="editCalculator(' . $this->id . ')" title="' . get_label('Edit calculator \'[0]\'.', $this->name) . '"><img src="images/edit.png" border="0"></button>';
			echo '<button class="icon" onclick="deleteCalculator(' . $this->id . ')" title="' . get_label('Delete calculator \'[0]\'.', $this->name) . '"><img src="images/delete.png" border="0"></button>';
		}
		else
		{
			echo '<button class="icon" onclick="viewCalculator(' . $this->id . ')" title="' . get_label('View the code of calculator \'[0]\'.', $this->name) . '"><img src="images/details.png" border="0"></button>';
		}
		echo '</td><td>';

		echo '<select id="calculator" onchange="refresh()">';
		$query = new DbQuery('SELECT id, name FROM stats_calculators WHERE owner_id = ? OR published = TRUE ORDER BY name', $_profile->user_id);
		while ($row = $query->next())
		{
			list($id, $name) = $row;
			show_option($id, $this->id, $name);
		}
		echo '</select>';
		echo '</td></tr>';
		
		$descr = prepare_message($this->description);
		echo '<tr><td colspan="2"><table class="transp" width="100%"><tr><td>';
		echo $descr;
		echo '</td><td width="50">';
		show_user_pic($this->owner_id, $this->owner_name, $this->owner_flags, ICONS_DIR);
		echo '</td></tr></table></td></tr>';
		
		echo '</table></p>';
		
		echo '<h3>' . get_label('Results') . '</h3>';
		echo '<p><table width="100%" class="bordered light"><tr><td>';
		echo '<div id="results">...</div>';
		echo '</td></tr></table></p>';
		
		echo '<script>';
		echo $this->code;
		echo '</script>';
	}
	
	protected function js()
	{
?>
		var requestsToGo = 0;
		var requestsComplete = 0;
		
		var gamesUrlBase;
		var filterCode = '';
		var emptyChair = true;
		var pageSize = 16;
		
		function next(page)
		{
			json.post(gamesUrlBase + "&page=" + page, {}, function (data)
			{
				for (var i = 0; i < data.games.length; ++i)
				{
					var game = data.games[i];
					var proceed = true;
					if (!emptyChair)
					{
						for (var j = 0; j < game.players.length; ++j)
						{
							var player = game.players[j];
							if (typeof player.user_id !== "number")
							{
								proceed = false;
								break;
							}
						}					
					}
					if (proceed)
					{
						proceedGame(game, page * page_size + i);
					}
				}
				++requestsComplete;
				$('#results').html('Proceeding page: ' + (requestsComplete + 1) + ' out of ' + requestsToGo);
				if (requestsComplete >= requestsToGo)
				{
					$('#results').html(complete());
					requestsToGo = requestsComplete = 0;
				}
			});
		}
		
		function calculate()
		{
			var errorMessage = null;
			try
			{
				$('#results').html('Obtaining number of games...');
				
				pageSize = $('#page_size').val();
				gamesUrlBase = "api/get/games.php?page_size=" + pageSize;
				emptyChair = $("#chair").prop('checked');
				if (filterCode.length > 0)
				{
					switch (filterCode[0])
					{
						case 'L':
							gamesUrlBase += '&club=' + filterCode.substring(1);
							break;
						case 'I':
							gamesUrlBase += '&city=' + filterCode.substring(1);
							break;
						case 'O':
							gamesUrlBase += '&country=' + filterCode.substring(1);
							break;
					}
				}
				
				reset();
				
				json.post(gamesUrlBase + "&count", {}, function (data)
				{
					requestsToGo = Math.ceil(data.count / pageSize);
					if (requestsToGo > 0)
					{
						$('#results').html('Proceeding page: 1 out of ' + requestsToGo);
						requestsComplete = 0;
						for (var page = 0; page < requestsToGo; ++page)
						{
							next(page, gamesUrlBase);
						}
					}
					else
					{
						$('#results').html('No games found');
					}
				});
			}
			catch (err)
			{
				$('#results').html(err.message);
			}
		}
		
		function editCalculator(id)
		{
			dlg.form("stats_calculator_edit.php?id=" + id, function(data)
			{
				refresh(data.id);
			});
		}
		
		function viewCalculator(id)
		{
			dlg.form("stats_calculator_view.php?id=" + id, function()
			{
				calculate();
			});
		}
		
		function deleteCalculator(id)
		{
			dlg.yesNo("<?php echo  get_label('Are you sure you want to delete calculator [0]', $this->name); ?>", null, null, function()
			{
				json.post("api/ops/stats_calculator.php", { op: "delete", id: id }, function()
				{
					refresh(0);
				});
			});
		}
		
		function refresh(id)
		{
			var url = "stats_calculator.php";
			var sep = "?"
			if (typeof id == "undefined")
			{
				id = $("#calculator").val();
			}
			if (id > 0)
			{
				url += sep + "id=" + id;
				sep = "&";
			}
			
			pageSize = $('#page_size').val();
			if (pageSize != 16)
			{
				url += sep + "page_size" + pageSize;
				sep = "&";
			}
			
			if ($("#chair").prop('checked'))
			{
				url += sep + "chair";
				sep = "&";
			}
			
			if (filterCode.length > 0)
			{
				url += sep + "filter=" + filterCode;
			}
			goTo(url);
		}
		
		function filterSelect(code)
		{
			filterCode = code;
		}
	
<?php
	}
}

$page = new Page();
$page->run(get_label('Stats calculator'));

?>