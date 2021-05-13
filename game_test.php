<?php

require_once 'include/general_page_base.php';
require_once 'include/game.php';

define('COLUMN_COUNT', 5);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));
define('LEGACY_SUPPORTED_SINSE', 0);

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_lang_code, $_profile;
		echo '<table class="transp" width="100%"><tr>';
		echo '<td width="110"><button onclick="loadGame()">' . get_label('Existing game') . '</button></td>';
		echo '<td width="110"><button onclick="editJson()">' . get_label('Custom game') . '</button></td>';

		echo '<td align="right">';
		
		echo '<table class="transp">';
		echo '<tr>';
		echo '<td width="200"><input type="checkbox" id="show-game" onclick="refresh()" checked> ' . get_label('show game json') . '</td>';
		echo '<td width="200"><input type="checkbox" id="show-voting" onclick="refresh()"> ' . get_label('show voting json') . '</td>';
		echo '</tr><tr>';
		echo '<td width="200"><input type="checkbox" id="show-original" onclick="refresh()"> ' . get_label('show original game record') . '</td>';
		echo '<td width="200"><input type="checkbox" id="show-fixed" onclick="refresh()"> ' . get_label('show fixed game version') . '</td>';
		echo '</tr><tr>';
		echo '<td width="200"><input type="checkbox" id="show-actions" onclick="refresh()"> ' . get_label('show game actions') . '</td>';
		echo '<td width="200"></td>';
		echo '</tr>';
		echo '</table></td>';

		echo '</tr></table>';
		
		echo '<div id="content"></div>';
	}
	
	protected function js()
	{
?>
		var lastRequest = "";
		var lastParams = {};
		var lastGame = "";
		var lastJson = "";

		function refresh()
		{
			if (lastRequest != "")
			{
				lastParams.show = "p";
				if ($("#show-game").attr("checked"))
				{
					lastParams.show += "g";
				}
				if ($("#show-voting").attr("checked"))
				{
					lastParams.show += "v";
				}
				if ($("#show-original").attr("checked"))
				{
					lastParams.show += "o";
				}
				if ($("#show-fixed").attr("checked"))
				{
					lastParams.show += "f";
				}
				if ($("#show-actions").attr("checked"))
				{
					lastParams.show += "a";
				}
				
				if (typeof lastParams.json != "undefined")
				{
					delete lastParams.json;
				}
				http.post(lastRequest, lastParams, function(response)
				{
					$("#content").html(response);
					lastParams.json = true;
					http.post(lastRequest, lastParams, function(response)
					{
						if (response.length > 0 && response[0] == '{')
						{
							lastJson = response;
						}
					});
				});
			}
		}

		function loadGame()
		{
			dlg.okCancel('Game #: <input type="number" style="width: 60px;" step="1" id="game-id" value="' + lastGame + '">', "Enter game id", 400, function()
			{
				lastGame = $("#game-id").val();
				lastRequest = 'game_test_content.php';
				lastParams = { game_id: lastGame };
				refresh();
			});
		}
		
		function editJson()
		{
			dlg.okCancel('<textarea id="game-json" cols="107" rows="48">' + lastJson + '</textarea>', "Enter game id", 800, function()
			{
				lastParams = { game: $("#game-json").val() };
				refresh();
			});
		}
<?php
	}
}

$page = new Page();
$page->set_ccc(CCCS_NO);
$page->run(get_label('Game test'));

?>