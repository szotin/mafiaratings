<?php

require_once '../include/session.php';

initiate_session();

try
{
	if ($_profile == NULL || !$_profile->is_admin())
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('currency')));
	}
	$id = $_REQUEST['id'];
	
	list($name_id, $pattern) = 
		Db::record(get_label('currency'), 'SELECT name_id, pattern FROM currencies WHERE id = ?', $id);
		
	echo '<table class="dialog_form" width="100%">';
	
	echo '<tr><td width="200">'.get_label('Currency name').':</td><td>';
	Names::show_control(new Names($name_id, get_label('currency name')));
	echo '</td></tr>';
	
	echo '<tr><td>'.get_label('Display pattern').'</td><td><input id="form-pattern" value="'.$pattern.'" size="50"></td></tr>';
	echo '</table>';
	
?>
	<script>
	function commit(onSuccess)
	{
		var request =
		{
			op: 'change'
			, currency_id: <?php echo $id; ?>
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