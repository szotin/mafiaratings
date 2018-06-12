<?php

require_once '../../include/api.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
	}
	
	protected function get_help()
	{
		$help = new ApiHelp();
		$help->request_param('', '', '-');
		$help->response_param('', '');
		return $help;
	}
}

$page = new ApiPage();
$page->run('', CURRENT_VERSION);

?>