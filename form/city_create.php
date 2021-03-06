<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/timezone.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('city')));

	if ($_profile == NULL || !$_profile->is_admin())
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="200">'.get_label('City name in English').':</td><td><input id="form-name_en"></td></tr>';
	echo '<tr><td>'.get_label('City name in Russian').':</td><td><input id="form-name_ru"></td></tr>';
	
	echo '<tr><td>'.get_label('Country').':</td><td>';
	show_country_input('form-country', COUNTRY_DETECT);
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('Time zone') . ':</td><td>';
	show_timezone_input(get_timezone());
	echo '</td></tr>';
	
	$query = new DbQuery('SELECT i.id, i.name_' . $_lang_code . ', i.timezone, o.id, o.name_' . $_lang_code . ' FROM cities i JOIN countries o ON o.id = i.country_id WHERE i.area_id = i.id ORDER BY i.name_' . $_lang_code);
	echo '<tr><td>' . get_label('Is near bigger city') . ':</td><td>';
	echo '<select id="form-area" onChange="areaChange()"><option value="-1"></option>';
	while ($row = $query->next())
	{
		list ($area_id, $area_name, $area_timezone, $area_country_id, $area_country_name) = $row;
		echo '<option value="' . $area_id . ';' . $area_timezone . ';' . $area_country_id . ';' . $area_country_name . '">' . $area_name . '</option>';
	}
	echo '<select></td></tr>';
	
	echo '<tr><td colspan="2" align="right"><input type="checkbox" id="form-confirm" checked> ' . get_label('confirm') . '</td></tr>';
	echo '</table>';
	
?>
	<script>
	function parseAreaVal(val)
	{
		var result = {};
		var beg = 0;
		var end = val.indexOf(';', beg);
		if (end >= 0)
		{
			result['id'] = val.substring(beg, end);
			beg = end + 1;
			end = val.indexOf(';', beg);
			result['timezone'] = val.substring(beg, end);
			beg = end + 1;
			end = val.indexOf(';', beg);
			result['country_id'] = val.substring(beg, end);
			beg = end + 1;
			result['country_name'] = val.substring(beg);
		}
		else
		{
			result['id'] = -1;
		}
		return result;
	}
	
	function areaChange()
	{
		var data = parseAreaVal($("#form-area").val());
		if (data.id > 0)
		{
			setTimezone(data.timezone);
			$("#form-country").val(data.country_name);
		}
	}
	
	function commit(onSuccess)
	{
		var data = parseAreaVal($("#form-area").val());
		json.post("api/ops/city.php",
		{
			op: 'create'
			, name_en: $("#form-name_en").val()
			, name_ru: $("#form-name_ru").val()
			, country: $("#form-country").val()
			, timezone: getTimezone()
			, area_id: data.id
			, confirm: ($('#form-confirm').attr('checked') ? 1 : 0)
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
	echo '<error=' . $e->getMessage() . '>';
}

?>