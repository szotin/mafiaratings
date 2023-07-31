<?php

require_once '../include/session.php';
require_once '../include/names.php';

initiate_session();

try
{
	dialog_title(get_label('Create currency'));

	if ($_profile == NULL || !$_profile->is_admin())
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	echo '<table class="dialog_form" width="100%">';
	
	echo '<tr><td width="200">'.get_label('Currency name').':</td><td>';
	Names::show_control();
	echo '</td></tr>';
	
	echo '<tr><td>'.get_label('Display pattern').'</td><td><input id="form-pattern" value="#" size="50"></td></tr>';
	echo '</table>';
	
?>
	<script>
	function commit(onSuccess)
	{
		var request =
		{
			op: 'create'
			, pattern: $("#form-pattern").val()
		};
		nameControl.fillRequest(request);
		
		json.post("api/ops/currency.php", request, onSuccess);
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