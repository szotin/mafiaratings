<?php

require_once 'include/localization.php';
require_once 'include/db.php';
require_once 'include/constants.php';

function send_error($e)
{
	Exc::log($e, true);
	echo json_encode(array('error' => $e->getMessage()));
}

$_lang_code = 'ru';
if (isset($_REQUEST['lang']))
{
	$_lang_code = $_REQUEST['lang'];
}

require_once 'include/languages/' . $_lang_code . '/labels.php';
$_default_date_translations = include('include/languages/' . $_lang_code . '/date.php');

$_xml = (isset($_REQUEST['f']) && $_REQUEST['f'] == 'xml');

$club_id = -1;
if (isset($_REQUEST['club']))
{
	$club_id = $_REQUEST['club'];
}

?>