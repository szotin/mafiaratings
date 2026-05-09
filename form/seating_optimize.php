<?php

require_once '../include/session.php';
require_once '../include/security.php';

initiate_session();

try
{
	dialog_title(get_label('Optimize seating'));
	check_permissions(PERMISSION_USER);

	$url = 'seating_optimization.php?time=120&loop=1';
	$task = isset($_GET['task']) ? $_GET['task'] : null;
	if ($task)
	{
		$url .= '&task=' . $task;
	}
	$hash = isset($_GET['hash']) ? $_GET['hash'] : null;
	if ($hash)
	{
		$url .= '&hash=' . $hash;
	}

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="220">' . get_label('Optimization time (minutes)') . ':</td>';
	echo '<td><input type="number" id="form-opt-minutes" value="10" min="2" max="120" step="2" style="width:80px"></td></tr>';
	echo '</table>';

?>
	<script>
	function commit(onSuccess)
	{
		var minutes = parseInt($('#form-opt-minutes').val(), 10);
		if (isNaN(minutes) || minutes < 2) minutes = 2;
		if (minutes > 120) minutes = 120;
		var runs = Math.round(minutes / 2);
		window.open('<?php echo $url; ?>&runs=' + runs);
		onSuccess({});
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
