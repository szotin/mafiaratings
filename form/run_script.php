<?php

require_once '../include/session.php';
require_once '../include/security.php';

initiate_session();

try
{
	check_permissions(PERMISSION_ADMIN);

	if (!isset($_REQUEST['script']))
	{
		throw new Exc('Unknown script');
	}
	$script = $_REQUEST['script'];
	
	$task = null;
	if (isset($_REQUEST['task']))
	{
		$task = $_REQUEST['task'];
	}
	
	$title = 'Run maitenance script '.$script.'.';
	if ($task)
	{
		$title .= ' Task '.$task.'.';
	}
	dialog_title($title);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="120">Log level:</td><td>';
	echo '<input type="radio" id="form-log-none" name="log-level" value="none" onchange="logLevelChange(this)"><label for="form-log-none">No log</label><br>';
	echo '<input type="radio" id="form-log-error" name="log-level" value="error" onchange="logLevelChange(this)"><label for="form-log-error">Log errors</label><br>';
	echo '<input type="radio" id="form-log-important" name="log-level" value="important" onchange="logLevelChange(this)"><label for="form-log-important">Log important messages</label><br>';
	echo '<input type="radio" id="form-log-info" name="log-level" value="info" onchange="logLevelChange(this)" checked><label for="form-log-info">Log info messages</label><br>';
	echo '<input type="radio" id="form-log-debug" name="log-level" value="debug" onchange="logLevelChange(this)"><label for="form-log-debug">Log debug messages</label><br>';
	echo '</td></tr>';
	
	echo '<tr><td>Time limit:</td><td><input id="form-time" type="number" min="30" style="width: 45px;" step="30" value="180"></td></tr>';
	echo '<tr><td colspan="2"><input id="form-once" type="checkbox"> run once</td></tr>';
	
	echo '</table>';

?>
	<script>
	var logLevel = 'info';
	function logLevelChange(radio)
	{
		logLevel = radio.value;
	}	
	
	function commit(onSuccess)
	{
		let task = <?php echo $task ? '"'.$task.'"' : 'null'; ?>;
		let url = "<?php echo $script; ?>" + ".php?log_level=" + logLevel + "&time=" + $("#form-time").val();
		if (task != null)
		{
			url += "&task=" + task;
		}
		if ($("#form-once").attr("checked"))
		{
			url += "&run_once";
		}
		window.location.assign(url);
	}
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>