<?php

require_once 'include/general_page_base.php';
require_once 'include/image.php';

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		echo '<div id="progr"></div>';
		
		echo '<p><input type="submit" class="btn long" value="Lock the site" onclick="mr.lockSite(true)"></p>';
		echo '<p>';
		echo '<input type="submit" class="btn long" value="Rebuild address icons" onclick="rebuildAddrIcons()">';
		echo '<input type="submit" class="btn long" value="Rebuild user icons" onclick="rebuildUserIcons()">';
		echo '<input type="submit" class="btn long" value="Rebuild club icons" onclick="rebuildClubIcons()">';
		echo '<input type="submit" class="btn long" value="Rebuild album icons" onclick="rebuildAlbumIcons()">';
		echo '<input type="submit" class="btn long" value="Rebuild photo icons" onclick="rebuildPhotoIcons()">';
		echo '</p>';
	
		echo '<p><input type="submit" class="btn long" value="Rebuild stats" onclick="rebuildStats()"></p>';
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
		
		function startRepairing()
		{
			$("#loading").show();
			repairLog = '';
			count = 0;
			$("#progr").progressbar("option", "value", 0);
		}
		
		function addrIconsNext(id)
		{
			json.post("repair_ops.php", { addr_icons: "", last_id: id }, function(data) { updateProgress(data, addrIconsNext); });
		}

		function rebuildAddrIcons()
		{
			startRepairing();
			json.post("repair_ops.php", { addr_icons: "" }, function (data)
			{
				$("#progr").progressbar("option", "max", data.count);
				updateProgress(data, addrIconsNext);
			});
		}
		
		function userIconsNext(id)
		{
			json.post("repair_ops.php", { user_icons: "", last_id: id }, function(data) { updateProgress(data, userIconsNext); });
		}

		function rebuildUserIcons()
		{
			startRepairing();
			json.post("repair_ops.php", { user_icons: "" }, function (data)
			{
				$("#progr").progressbar("option", "max", data.count);
				updateProgress(data, userIconsNext);
			});
		}
		
		function clubIconsNext(id)
		{
			json.post("repair_ops.php", { club_icons: "", last_id: id }, function(data) { updateProgress(data, clubIconsNext); });
		}

		function rebuildClubIcons()
		{
			startRepairing();
			json.post("repair_ops.php", { club_icons: "" }, function (data)
			{
				$("#progr").progressbar("option", "max", data.count);
				updateProgress(data, clubIconsNext);
			});
		}
		
		function albumIconsNext(id)
		{
			json.post("repair_ops.php", { album_icons: "", last_id: id }, function(data) { updateProgress(data, albumIconsNext); });
		}

		function rebuildAlbumIcons()
		{
			startRepairing();
			json.post("repair_ops.php", { album_icons: "" }, function (data)
			{
				$("#progr").progressbar("option", "max", data.count);
				updateProgress(data, albumIconsNext);
			});
		}
		
		function photoIconsNext(id)
		{
			json.post("repair_ops.php", { photo_icons: "", last_id: id }, function(data) { updateProgress(data, photoIconsNext); });
		}

		function rebuildPhotoIcons()
		{
			startRepairing();
			json.post("repair_ops.php", { photo_icons: "" }, function (data)
			{
				$("#progr").progressbar("option", "max", data.count);
				updateProgress(data, photoIconsNext);
			});
		}
		
		function statsNext(id)
		{
			json.post("repair_ops.php", { stats: "", last_id: id }, function(data) { updateProgress(data, statsNext); });
		}

		function rebuildStats()
		{
			startRepairing();
			json.post("repair_ops.php", { stats: "" }, function (data)
			{
				$("#progr").progressbar("option", "max", data.count);
				updateProgress(data, statsNext);
			});
		}
<?php
	}
}

$page = new Page();
$page->set_ccc(CCCS_NO);
$page->run('Repairs', U_PERM_ADMIN);

?>

