<?php

define('VERSION', 10);

$files = array(
	array('MafiaRatings.exe', 10),
	array('Launcher.exe', 10),
	array('ru/MafiaRatings.resources.dll', 10),
	array('ru/Launcher.resources.dll', 10)
);

echo VERSION;
if (isset($_REQUEST['v']))
{
	$v = $_REQUEST['v'];
	foreach ($files as $value)
	{
		list($name, $version) = $value;
		if ($v < $version)
		{
			echo "\n" . $name . "\t" . filesize($name);
		}
	}
}

?>