<?php
// if(isset($GLOBALS))
// {
	// echo '<pre>';
	// print_r($GLOBALS);
	// echo '</pre>';
// }

//$parsed_url = parse_url('https://www.youtube.com/?v=1nPMX_AXCNU&feature=youtu.be&t=4297');
//$parsed_url = parse_url('https://www.youtube.com?v=1nPMX_AXCNU&feature=youtu.be&t=4297');
//$parsed_url = parse_url('https://youtu.be/1nPMX_AXCNU');
//$parsed_url = parse_url('https://youtu.be/1nPMX_AXCNU?t=4297');
$parsed_url = parse_url('1nPMX_AXCNU');
echo '<pre>';
print_r($parsed_url);
echo '</pre>';

if (isset($parsed_url['query']))
{
	parse_str($parsed_url['query'], $query);
	echo '<br>------<pre>';
	print_r($query);
	echo '</pre>';
}

if (isset($parsed_url['path']))
{
	echo '<br>------<pre>';
	print_r(basename($parsed_url['path']));
	echo '</pre>';
}


?>