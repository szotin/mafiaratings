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
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		// do xxx
		// ...
	}
	
	function xxx_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, '');
		$help->request_param('', '');
		$help->response_param('', '');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Xxx Operations', CURRENT_VERSION);

?>