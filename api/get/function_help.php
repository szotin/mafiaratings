<?php

require_once '../../include/api.php';
require_once '../../include/scoring.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		global $_scoring_functions, $_lang;
		
		$functionNum = (int)get_required_param('function');
		if ($functionNum < 0 || $functionNum >= count($_scoring_functions))
		{
			throw new Exc('Invalid function number');
		}
		$lang = (int)get_optional_param('lang', $_lang);
		
		$_SESSION['current_function'] = $functionNum;
		$helps = include '../../include/languages/' . get_lang_code($lang) . '/function_help.php';
		if ($functionNum < count($helps) && !is_null($helps[$functionNum]))
		{
			$this->response['help'] = $helps[$functionNum];
		}
		else
		{
			$this->response['help'] = get_label('No help available for the function [0].', $_scoring_functions[$functionNum]->name());
		}
	}
	
	protected function get_help()
	{
		global $_scoring_functions;
		
		$function_list = '<ol start="0">';
		foreach ($_scoring_functions as $function)
		{
			$function_list .= '<li>' . $function->name() . '</li>';
		}
		$function_list .= '</ol>';
		
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('function', 'Function number.</i> For example: <a href="function_help.php?function=2">/api/get/function_help.php?function=2</a> returns help for function ' . $_scoring_functions[2]->name() . '. The functions are:<br>' . $function_list, '-');
		$help->request_param('lang', 'Language id for returned help. For example: <a href="function_help.php?function=3&lang=2">/api/get/function_help.php?function=3&lang=2</a> returns help in Russian.' . valid_langs_help(), 'default language for the logged in account is used. If not logged in the system tries to guess the language by ip address.');
		$help->response_param('help', 'Html help for the requested function.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Club Advertisements', CURRENT_VERSION);

?>