<?php

require_once 'include/localization.php';

function request_param($input, &$offset)
{
    $next = strpos($input, '#', $offset);
	if ($next === false)
	{
		return NULL;
	}
    $str = substr($input, $offset, $next - $offset);
    $offset = $next + 1;
    return $str;
}

$lang = LANG_ENGLISH;
if (isset($_REQUEST['data']))
{
	$input = $_REQUEST['data'];
	$offset = 0;
	$code = request_param($input, $offset);
	$lang = request_param($input, $offset);
}	

require_once 'include/languages/' . get_lang_code($lang) . '/labels.php';
echo '#1#';
echo get_label('Windows standalone client is not supported any more. Please use web page https://mafiaratings.com/game.php instead. Sorry for the inconvinience.');
echo '#';
?>