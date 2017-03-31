<?php

require_once 'include/general_page_base.php';
require_once 'include/image.php';

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		echo '<div id="progr"></div>';
		echo '<p>';
		echo '<input type="submit" class="btn long" value="Rebuild" onclick="rebuildGameFlags()">';
		echo '</p>';
	}
	
	protected function js_on_load()
	{
?>
		$("#progr").progressbar({ value: 0 });
<?php
	}
	
	protected function js()
	{
?>
		var count;
		var repairLog = '';
		var repairWaiter = new function()
		{
			this.start = function() { return true; }
			this.success = function() {}
			this.error = function(message) { repairLog += 'Error: ' + message + '<br>'; }
			this.info = function(message, title, onClose)
			{
				if (repairLog.length < 4 || repairLog.indexOf('<br>', repairLog.length - 4) == -1)
					repairLog += '<br>';
				repairLog += message;
				onClose();
			}
			this.connected = function() {}
		}
		http.waiter(repairWaiter);
		
		function updateProgress(data, next)
		{
			if (data.recs > 0)
			{
				count += data.recs;
				next(data.last_id);
				$('#update').html('<p>' + count + '</p>');
				$("#progr").progressbar("option", "value", count);
			}
			else
			{
				$("#progr").progressbar("option", "max", 100);
				$("#progr").progressbar("option", "value", 100);
				$("#loading").hide();
				dlg.info(repairLog, 'Complete');
			}
		}
		
		function gameFlagsNext(id)
		{
			console.log("next");
			json.post("rebuild_game_flags_ops.php", { rebuild: "", last_id: id }, function(data) { console.log(data.count); updateProgress(data, gameFlagsNext); });
		}

		function rebuildGameFlags()
		{
			console.log("rebuildGameFlags");
			$("#loading").show();
			repairLog = '';
			count = 0;
			$("#progr").progressbar("option", "value", 0);
			
			json.post("rebuild_game_flags_ops.php", { rebuild: "" }, function (data)
			{
				console.log(data.count);
				$("#progr").progressbar("option", "max", data.count);
				updateProgress(data, gameFlagsNext);
			});
		}
		
<?php
	}
}

$page = new Page();
$page->set_ccc(CCCS_NO);
$page->run('Rebuild game flags', U_PERM_ADMIN);

?>

