<?php

require_once '../include/session.php';
require_once '../include/scoring.php';

initiate_session();

try
{
	dialog_title('Functions for scoring system');
	if (!isset($_SESSION['current_function']))
	{
		$_SESSION['current_function'] = 0;
	}
	$current_function = $_SESSION['current_function'];
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td>Function: <select id="form-functions" onchange="functionChanged()">';
	for ($i = 0; $i < count($_scoring_functions); ++$i)
	{
		show_option($i, $current_function, $_scoring_functions[$i]->name());
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