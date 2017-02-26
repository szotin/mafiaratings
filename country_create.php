<?php

require_once 'include/session.php';
require_once 'include/country.php';

initiate_session();

try
{
	dialog_title(get_label('Create country'));

	if ($_profile == NULL || !$_profile->is_admin())
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="200">'.get_label('Country name in English').':</td><td><input id="form-name_en"></td></tr>';
	echo '<tr><td>'.get_label('Country name in Russian').':</td><td><input id="form-name_ru"></td></tr>';
	echo '<tr><td>'.get_label('Country code').':</td><td><input id="form-code"></td></tr>';
	
	echo '<tr><td colspan="2" align="right"><input type="checkbox" id="form-confirm" checked> ' . get_label('confirm') . '</td></tr>';
	echo '</table>';
	
?>
	<script>
	function commit(onSuccess)
	{
		json.post("location_ops.php",
			{
				name_en: $("#form-name_en").val(),
				name_ru: $("#form-name_ru").val(),
				code: $("#form-code").val(),
				confirm: ($('#form-confirm').attr('checked') ? 1 : 0),
				new_country: ""
			},
			onSuccess);
	}
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo $e->getMessage();
}

?>