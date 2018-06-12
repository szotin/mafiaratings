<?php

require_once '../../include/api.php';
require_once '../../include/user_location.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// xxx
	//-------------------------------------------------------------------------------------------------------
	function xxx_op()
	{
	}
	
	function xxx_op_help()
	{
		$help = new ApiHelp('');
		$help->request_param('', '');
		$help->response_param('', '');
		return $help;
	}
	
	function xxx_op_permissions()
	{
		return API_PERM_FLAG_MANAGER;
	}
}

$page = new ApiPage();
$page->run('Xxx Operations', CURRENT_VERSION);

?>