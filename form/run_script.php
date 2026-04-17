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
	echo '<tr><td>Number of runs:</td><td><input id="form-runs" type="number" min="1" step="1" value="1" style="width: 55px;"> <input id="form-runs-infinite" type="checkbox" onchange="runsInfiniteChange(this)"><label for="form-runs-infinite">' . get_label('infinite') . '</label></td></tr>';
	echo '<tr><td colspan="2"><input id="form-loop" type="checkbox"> ' . get_label('use all allocated time') . '</td></tr>';
	
	echo '</table>';

?>
	<script>
	var logLevel = 'info';
	function logLevelChange(radio)
	{
		logLevel = radio.value;
	}

	function runsInfiniteChange(checkbox)
	{
		let input = $("#form-runs");
		if (checkbox.checked)
		{
			input.data("saved", input.val()).val("").prop("disabled", true);
		}
		else
		{
			input.prop("disabled", false).val(input.data("saved") || 1);
		}
	}
	
	function commit(onSuccess)
	{
		let task = <?php echo $task ? '"'.$task.'"' : 'null'; ?>;
		let runs = $("#form-runs-infinite").is(":checked") ? 0 : $("#form-runs").val();
		let url = "<?php echo $script; ?>" + ".php?log_level=" + logLevel + "&time=" + $("#form-time").val() + "&runs=" + runs + "&loop=" + ($("#form-loop").is(":checked") ? 1 : 0);
		if (task != null)
		{
			url += "&task=" + task;
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