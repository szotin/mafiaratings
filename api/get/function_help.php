<?php

require_once '../../include/api.php';
require_once '../../include/scoring.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		global $_lang;
	
		$this->functions = get_scoring_functions();
		$functionId = get_required_param('function');
		$lang = (int)get_optional_param('lang', $_lang);
		
		$_SESSION['current_function'] = $functionId;
		$this->helps = include '../../include/languages/' . get_lang_code($lang) . '/function_help.php';
		if (array_key_exists($functionId, $this->helps))
		{
			$this->response['help'] = $this->helps[$functionId];
		}
		else
		{
			$this->response['help'] = get_label('No help available for the function [0].', $functionId);
		}
	}
	
	protected function get_help()
	{
		$function_list = '<ol start="0">';
		foreach ($this->helps as $functionId => $help)
		{
			$function_list .= '<li>' . $functionId . '</li>';
		}
		$function_list .= '</ol>';
		
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('function', 'Function number.</i> For example: <a href="function_help.php?function=max">/api/get/function_help.php?function=max</a> returns help for function max. The functions are:<br>' . $function_list, '-');
		$help->request_param('lang', 'Language id for returned help. For example: <a href="function_help.php?function=3&lang=2">/api/get/function_help.php?function=3&lang=2</a> returns help in Russian.' . valid_langs_help(), 'default language for the logged in account is used. If not logged in the system tries to guess the language by ip address.');
		$help->response_param('help', 'Html help for the requested function.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Club Advertisements', CURRENT_VERSION);

?>