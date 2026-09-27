<?php

require_once '../include/session.php';
require_once '../include/scoring.php';

initiate_session();

try
{
	dialog_title('Functions for scoring system');

	// Any set of functions can be listed here - the caller names them and the help itself is
	// looked up by name in include/languages/<lang>/function_help.php, which knows nothing about
	// where a function is used. Without the parameter the list is the tournament scoring one.
	if (isset($_REQUEST['functions']) && !empty($_REQUEST['functions']))
	{
		$names = explode(',', $_REQUEST['functions']);
	}
	else
	{
		$names = array();
		foreach (get_scoring_functions() as $f)
		{
			$names[] = $f->id();
		}
	}

	// The function the dialog was last opened on, unless it belongs to another set.
	$current_function = isset($_SESSION['current_function']) ? $_SESSION['current_function'] : '';
	if (!in_array($current_function, $names))
	{
		$current_function = count($names) ? $names[0] : '';
	}

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td>Function: <select id="form-functions" onchange="functionChanged()">';
	foreach ($names as $name)
	{
		show_option($name, $current_function, $name);
	}
	echo '</td></tr>';
	echo '<tr><td><div id="form-help"></div></td></tr>';
	echo '</table>';
?>
	<script>
		function functionChanged()
		{
			json.get("api/get/function_help.php?function=" + $("#form-functions").val(), function(obj)
			{
				$("#form-help").html(obj.help);
			});
		}
		functionChanged();
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