<?php

require_once '../include/session.php';
require_once '../include/country.php';

initiate_session();

try
{
	if ($_profile == NULL || !$_profile->is_admin())
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('country')));
	}
	$id = $_REQUEST['id'];
	
	list($name_id, $code, $flags) = 
		Db::record(get_label('country'), 'SELECT name_id, code, flags FROM countries WHERE id = ?', $id);
		
	if (($flags & COUNTRY_FLAG_NOT_CONFIRMED) != 0)
	{
		dialog_title(get_label('Confirm country'));
	}
	else
	{
		dialog_title(get_label('Edit country'));
	}
		
	echo '<table class="dialog_form" width="100%">';
	
	echo '<tr><td width="200">'.get_label('Country name').':</td><td>';
	Names::show_control(new Names($name_id, get_label('country name')));
	echo '</td></tr>';
	
	echo '<tr><td>'.get_label('Country code').':</td><td><input id="form-code" value="' . htmlspecialchars($code, ENT_QUOTES) . '"></td></tr>';
	
	if (($flags & COUNTRY_FLAG_NOT_CONFIRMED) != 0)
	{
		echo '<tr><td colspan="2" align="right"><input type="checkbox" id="form-confirm" checked> ' . get_label('confirm') . '</td></tr>';
	}
	echo '</table>';
	
?>
	<script>
	function commit(onSuccess)
	{
		var request =
		{
			op: 'change'
			, country_id: <?php echo $id; ?>
			, code: $("#form-code").val()
			, confirm: ($('#form-confirm').attr('checked') ? 1 : 0)
		};
		nameControl.fillRequest(request);
		json.post("api/ops/country.php", request, onSuccess);
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