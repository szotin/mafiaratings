<?php

require_once 'include/branding.php';

define('VERSION', 10);

$files = array(
	array(PRODUCT_TERM . '.exe', 10),
	array('Launcher.exe', 10),
	array('ru/' . PRODUCT_TERM . '.resources.dll', 10),
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