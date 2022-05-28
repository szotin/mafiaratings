<?php

require_once 'include/general_page_base.php';
require_once 'include/image.php';

class Page extends GeneralPageBase
{
	private function show_error_logs()
	{
		$all_logs = array(
			'',
			'form/',
			'api/ops/',
			'api/control/',
			'api/get/',
			'include/',
			'include/languages/',
			'include/languages/en/',
			'include/languages/ru/',
		);
		
		$error_logs = array();
		foreach ($all_logs as $dir)
		{
			$src_filename = $dir . 'error_log';
			$dst_filename = $dir . 'error.log';
			if (file_exists($src_filename))
			{
				rename($src_filename, $dst_filename);
				$error_logs[] = $dir;
			}
			else if (file_exists($dst_filename))
			{
				$error_logs[] = $dir;
			}
		}
		
		if (count($error_logs) > 0)
		{
			echo '<table class="bordered light" width="100%"><tr class="dark"><td colspan="2">There are some errors:</td></tr>';
			foreach ($error_logs as $dir)
			{
				echo '<tr><td width="24"><button class="icon" onclick="deleteLog(\'' . $dir . '\')" title="Delete ' . $dir . 'error.log"><img src="images/delete.png" border="0"></button></td>';
				echo '<td><a href="' . $dir . 'error.log">' . $dir . 'error.log</a></td></tr>';
			}
			echo '</table>';
		}
	}
	
	protected function show_body()
	{
		echo '<div id="progr"></div>';
		
		check_permissions(PERMISSION_ADMIN);
		if ($this->_locked)
		{
			echo '<p align="center"><input type="submit" class="btn long" value="Unlock the site" onclick="mr.lockSite(false)"></p>';
		}
		else
		{
			echo '<p align="center"><input type="submit" class="btn long" value="Lock the site" onclick="mr.lockSite(true)"></p>';
		}
			
		echo '<h3>' . 'Rebuild assets' . '</h3>';
		echo '<p>';
		echo '<input type="submit" class="btn long" value="Rebuild address icons" onclick="rebuildAddrIcons()"> ';
		echo '<input type="submit" class="btn long" value="Rebuild user icons" onclick="rebuildUserIcons()"> ';
		echo '<input type="submit" class="btn long" value="Rebuild club icons" onclick="rebuildClubIcons()"> ';
		echo '<input type="submit" class="btn long" value="Rebuild album icons" onclick="rebuildAlbumIcons()"> ';
		echo '<input type="submit" class="btn long" value="Rebuild photo icons" onclick="rebuildPhotoIcons()"> ';
		echo '</p>';
	
		echo '<h3>' . 'Rebuild games statistics' . '</h3>';
		echo '<p>';
		echo '<input type="submit" class="btn long" value="Rebuild stats" onclick="rebuildStats()"> ';
		echo '</p>';
		
		echo '<h3>' . 'Rebuild ratings' . '</h3>';
		echo '<p>';
		echo '<input type="submit" class="btn long" value="For the last month" onclick="rebuildRatings(31)"> ';
		echo '<input type="submit" class="btn long" value="For the last 3 month" onclick="rebuildRatings(91)"> ';
		echo '<input type="submit" class="btn long" value="For the last year" onclick="rebuildRatings(365)"> ';
		echo '<input type="submit" class="btn long" value="All of them" onclick="rebuildRatings(0)"> ';
		echo '</p>';
		
		if (file_exists("rebuild_ratings.log"))
		{
			echo '<p><table class="transp" width="100%">';
			echo '<tr><td width="40"><button class="icon" onclick="deleteRatingsLog()" title="Delete rebuild ratings log"><img src="images/delete.png" border="0"></button></td>';
			echo '<td><a href="rebuild_ratings.log">View rebuild raitings log</a></td></tr>';
			echo '</table></p>';
		}
		
		echo '<h3>' . 'Rebuild Snapshots' . '</h3>';
		echo '<p>';
		echo '<input type="submit" class="btn long" value="For the last month" onclick="rebuildSnapshots(31)"> ';
		echo '<input type="submit" class="btn long" value="For the last 3 month" onclick="rebuildRatings(91)"> ';
		echo '<input type="submit" class="btn long" value="For the last year" onclick="rebuildSnapshots(365)"> ';
		echo '<input type="submit" class="btn long" value="All of them" onclick="rebuildSnapshots(0)"> ';
		echo '</p>';
		
		$this->show_error_logs();
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
				if (repairLog.length == 0)
				{
					repairLog = 'Success!!!';
				}
				dlg.info(repairLog, 'Complete', null, refr);
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
			json.post("api/ops/repair.php", { op: 'addr_icons', last_id: id }, function(data) { updateProgress(data, addrIconsNext); });
		}

		function rebuildAddrIcons()
		{
			startRepairing();
			json.post("api/ops/repair.php", { op: 'addr_icons' }, function (data)
			{
				$("#progr").progressbar("option", "max", data.count);
				updateProgress(data, addrIconsNext);
			});
		}
		
		function userIconsNext(id)
		{
			json.post("api/ops/repair.php", { op: 'user_icons', last_id: id }, function(data) { updateProgress(data, userIconsNext); });
		}

		function rebuildUserIcons()
		{
			startRepairing();
			json.post("api/ops/repair.php", { op: 'user_icons' }, function (data)
			{
				$("#progr").progressbar("option", "max", data.count);
				updateProgress(data, userIconsNext);
			});
		}
		
		function clubIconsNext(id)
		{
			json.post("api/ops/repair.php", { op: 'club_icons', last_id: id }, function(data) { updateProgress(data, clubIconsNext); });
		}

		function rebuildClubIcons()
		{
			startRepairing();
			json.post("api/ops/repair.php", { op: 'club_icons' }, function (data)
			{
				$("#progr").progressbar("option", "max", data.count);
				updateProgress(data, clubIconsNext);
			});
		}
		
		function albumIconsNext(id)
		{
			json.post("api/ops/repair.php", { op: 'album_icons', last_id: id }, function(data) { updateProgress(data, albumIconsNext); });
		}

		function rebuildAlbumIcons()
		{
			startRepairing();
			json.post("api/ops/repair.php", { op: 'album_icons' }, function (data)
			{
				$("#progr").progressbar("option", "max", data.count);
				updateProgress(data, albumIconsNext);
			});
		}
		
		function photoIconsNext(id)
		{
			json.post("api/ops/repair.php", { op: 'photo_icons', last_id: id }, function(data) { updateProgress(data, photoIconsNext); });
		}

		function rebuildPhotoIcons()
		{
			startRepairing();
			json.post("api/ops/repair.php", { op: 'photo_icons' }, function (data)
			{
				$("#progr").progressbar("option", "max", data.count);
				updateProgress(data, photoIconsNext);
			});
		}
		
		function statsNext(id)
		{
			json.post("api/ops/repair.php", { op: 'stats', last_id: id }, function(data) { updateProgress(data, statsNext); });
		}

		function rebuildStats()
		{
			startRepairing();
			json.post("api/ops/repair.php", { op: 'stats' }, function (data)
			{
				$("#progr").progressbar("option", "max", data.count);
				updateProgress(data, statsNext);
			});
		}
		
		function deleteLog(dir)
		{
			dlg.yesNo("Are you sure you want to delete " + dir + "error.log?", null, null, function()
			{
				json.post("api/ops/repair.php", { op: 'delete_error_log', dir: dir }, refr);
			});
		}
		
		function deleteRatingsLog()
		{
			dlg.yesNo("Are you sure you want to delete rebuild_ratings.log?", null, null, function()
			{
				json.post("api/ops/repair.php", { op: 'delete_rebuild_ratings_log' }, refr);
			});
		}
		
		function rebuildRatings(days)
		{
			json.post("api/ops/repair.php", { op: 'rebuild_ratings', days: days }, function(data)
			{
				if (days > 0)
					dlg.info('Ratings for the last ' + days + ' days are scheduled to rebuild successfuly', 'Done', null, function() {});
				else
					dlg.info('Ratings are scheduled to rebuild successfuly', 'Done', null, function() {});
			});
		}
		
		function rebuildSnapshots(days)
		{
			json.post("api/ops/repair.php", { op: 'rebuild_snapshots', days: days }, function(data)
			{
				if (days > 0)
					dlg.info('Snapshots for the last ' + days + ' days are scheduled to rebuild successfuly', 'Done', null, function() {});
				else
					dlg.info('Snapshots are scheduled to rebuild successfuly', 'Done', null, function() {});
			});
		}
<?php
	}
}

$page = new Page();
$page->run('Repairs');

?>

