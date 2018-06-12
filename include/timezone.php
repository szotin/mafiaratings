<?php

require_once __DIR__ . '/session.php';

function show_timezone_input($timezone)
{
	$pos = strpos($timezone, '/');
	if ($pos === false)
	{
		$the_region = '';
		$the_zone = $timezone;
	}
	else
	{
		$the_region = substr($timezone, 0, $pos);
		$the_zone = substr($timezone, $pos + 1);
	}
	
	$zones = array();
	$tzones = DateTimeZone::listIdentifiers();
	foreach ($tzones as $tzone)
	{
		$pos = strpos($tzone, '/');
		if ($pos === false)
		{
			continue;
		}
		$region = substr($tzone, 0, $pos);
		$zone = substr($tzone, $pos + 1);
		if (!isset($zones[$region]))
		{
			$zones[$region] = array();
		}
		$zones[$region][] = $zone;
	}
	
	echo '<select id="tz-region" onChange="tzRegionChange()">';
	foreach ($zones as $region => $list)
	{
		show_option($region, $the_region, $region);
	}
	echo '</select> <select id="tz-zone">';
	if (isset($zones[$the_region]))
	{
		$list = $zones[$the_region];
		foreach ($list as $zone)
		{
			show_option($zone, $the_zone, $zone);
		}
	}
	echo '</select>';
	
	$delim1 = '';
	echo "<script>\nvar zones = {";
	foreach ($zones as $region => $list)
	{
		echo $delim1 . '"' . $region . '": [';
		$delim2 = '';
		foreach ($list as $zone)
		{
			echo $delim2 . '"' . $zone . '"';
			$delim2 = ', ';
		}
		echo ']';
		$delim1 = ', ';
	}
	echo '};';
?>

	function tzRegionChange()
	{
		var zsel = $("#tz-zone");
		var z = zones[$("#tz-region").val()];
		zsel.empty();
		for (var i = 0; i < z.length; ++i)
		{
			zsel.append('<option value="' + z[i] + '">' + z[i] + '</option>');
		}
	}
	
	function getTimezone()
	{
		return $("#tz-region").val() + '/' + $("#tz-zone").val();
	}
	
	function setTimezone(zone)
	{
		var pos = zone.indexOf('/');
		if (pos >= 0)
		{
			$("#tz-region").val(zone.substring(0, pos));
			tzRegionChange();
			$("#tz-zone").val(zone.substring(pos + 1));
		}
	}
	</script>
<?php	
}

?>