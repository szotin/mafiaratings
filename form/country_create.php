<?php

require_once '../include/session.php';
require_once '../include/country.php';
require_once '../include/names.php';

initiate_session();

try
{
	dialog_title(get_label('Create country'));

	if ($_profile == NULL || !$_profile->is_admin())
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	echo '<table class="dialog_form" width="100%">';
	
	echo '<tr><td width="200">'.get_label('Country name').':</td><td>';
	Names::show_control();
	echo '</td></tr>';
	
	echo '<tr><td>'.get_label('Two letter country code').':</td><td><input id="form-code" maxlength="2" size="2"></td></tr>';
	
	echo '<tr><td>'.get_label('National currency').':</td><td>';
	$query = new DbQuery('SELECT c.id, n.name FROM currencies c JOIN names n ON n.id = c.name_id AND (n.langs & ?) <> 0 ORDER BY n.name', $_lang);
	echo '<select id="form-currency" onChange="currencyChanged()">';
	show_option(0, 0, get_label('Create new'));
	while ($row = $query->next())
	{
		list($currency_id, $currency_name) = $row;
		show_option($currency_id, -1, $currency_name);
	}
	echo '</select><span id="form-create-currency"><table class="transp" width="100%">';
	echo '<tr><td width="150">' . get_label('Name') . ':</td><td>';
	Names::show_control(NULL, 'form-currency-name', $var_name = 'currencyNameControl');
	echo '</td></tr>';
	echo '<tr><td>'.get_label('Display pattern').':</td><td><input id="form-currency-pattern" value="" size="50"></td></tr>';
	echo '</table></span></td></tr>';
	
	echo '<tr><td colspan="2" align="right"><input type="checkbox" id="form-confirm" checked> ' . get_label('confirm') . '</td></tr>';
	echo '</table>';
	
?>
	<script>
	function currencyChanged()
	{
		if ($('#form-currency').val() > 0)
		{
			$('#form-create-currency').hide();
		}
		else
		{
			$('#form-create-currency').show();
		}
	}
	
	function commit(onSuccess)
	{
		var request =
		{
			op: 'create'
			, code: $("#form-code").val()
			, confirm: ($('#form-confirm').attr('checked') ? 1 : 0)
		};
		nameControl.fillRequest(request);
		if ($('#form-currency').val() > 0)
		{
			request['currency_id'] = $('#form-currency').val();
		}
		else
		{
			currencyNameControl.fillRequest(request, 'currency_name');
			request['currency_pattern'] = $('#form-currency-pattern').val();
		}
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