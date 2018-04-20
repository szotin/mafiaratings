<?php

require_once 'include/session.php';
require_once 'include/scoring.php';

initiate_session();

try
{
	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('scoring system')));
	}
	$scoring = new ScoringSystem((int)$_REQUEST['id']);
	
	dialog_title($scoring->name);
	$scoring->show_rules(false);
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>