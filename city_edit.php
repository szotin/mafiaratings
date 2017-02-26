<?php

require_once 'include/session.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/timezone.php';

initiate_session();

try
{
	if ($_profile == NULL || !$_profile->is_admin())
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('city')));
	}
	$id = $_REQUEST['id'];
	
	list($country_id, $country_name, $name_en, $name_ru, $timezone, $flags, $near_id) = Db::record(
		get_label('city'), 
		'SELECT i.country_id, o.name_' . $_lang_code . ', i.name_en, i.name_ru, i.timezone, i.flags, i.near_id FROM cities i JOIN countries o ON o.id = i.country_id WHERE i.id = ?',
		$id);
		
	if (($flags & CITY_FLAG_NOT_CONFIRMED) != 0)
	{
		dialog_title(get_label('Confirm city'));
	}
	else
	{
		dialog_title(get_label('Edit city'));
	}
		
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="200">'.get_label('City name in English').':</td><td><input id="form-name_en" value="' . htmlspecialchars($name_en, ENT_QUOTES) . '"></td></tr>';
	echo '<tr><td>'.get_label('City name in Russian').':</td><td><input id="form-name_ru" value="' . htmlspecialchars($name_ru, ENT_QUOTES) . '"></td></tr>';
	
	echo '<tr><td>'.get_label('Country').':</td><td>';
	show_country_input('form-country', $country_name);
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('Time zone') . ':</td><td>';
	show_timezone_input($timezone);
	echo '</td></tr>';
	
	$query = new DbQuery('SELECT i.id, i.name_' . $_lang_code . ', i.timezone, o.id, o.name_' . $_lang_code . ' FROM cities i JOIN countries o ON o.id = i.country_id WHERE i.near_id IS NULL AND i.id <> ? ORDER BY i.name_' . $_lang_code, $id);
	echo '<tr><td>' . get_label('Is near bigger city') . ':</td><td>';
	echo '<select id="form-near" onChange="nearChange()"><option value="-1"></option>';
	while ($row = $query->next())
	{
		list ($cid, $cname, $ctimezone, $ccid, $ccname) = $row;
		echo '<option value="' . $cid . ';' . $ctimezone . ';' . $ccid . ';' . $ccname . '"';
		if ($cid == $near_id)
		{
			echo ' selected';
		}
		echo '>' . $cname . '</option>';
	}
	echo '<select></td></tr>';
	
	if (($flags & CITY_FLAG_NOT_CONFIRMED) != 0)
	{
		echo '<tr><td colspan="2" align="right"><input type="checkbox" id="form-confirm" checked> ' . get_label('confirm') . '</td></tr>';
	}
	echo '</table>';
	
?>
	<script>
	function parseNearVal(val)
	{
		var result = {};
		var beg = 0;
		var end = val.indexOf(';', beg);
		if (end >= 0)
		{
			result['id'] = val.substring(beg, end);
			beg = end + 1;
			end = val.indexOf(';', beg);
			result['tzone'] = val.substring(beg, end);
			beg = end + 1;
			end = val.indexOf(';', beg);
			result['cid'] = val.substring(beg, end);
			beg = end + 1;
			result['cname'] = val.substring(beg);
		}
		else
		{
			result['id'] = -1;
		}
		return result;
	}
	
	function nearChange()
	{
		var data = parseNearVal($("#form-near").val());
		if (data.id > 0)
		{
			setTimezone(data.tzone);
			$("#form-country").val(data.cname);
		}
	}
	
	function commit(onSuccess)
	{
		var data = parseNearVal($("#form-near").val());
		json.post("location_ops.php",
		{
			id: <?php echo $id; ?>,
			name_en: $("#form-name_en").val(),
			name_ru: $("#form-name_ru").val(),
			country: $("#form-country").val(),
			timezone: getTimezone(),
			confirm: ($('#form-confirm').attr('checked') ? 1 : 0),
			near: data.id,
			edit_city: ""
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