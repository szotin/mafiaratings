<?php

define('REDIRECT_ON_LOGIN', true);
require_once 'include/general_page_base.php';

class Page extends GeneralPageBase
{
	private $role;

	protected function prepare()
	{
		parent::prepare();
		$this->role = 0;
		if (isset($_REQUEST['role']))
		{
			$this->role = $_REQUEST['role'];
		}
	}
	
	protected function show_body()
	{
		global $_lang_code;
		
		switch ($this->role)
		{
			case 1:
				include_once("include/languages/".$_lang_code."/tactics-1.php");
				break;
			case 2:
				include_once("include/languages/".$_lang_code."/tactics-2.php");
				break;
			case 3:
				include_once("include/languages/".$_lang_code."/tactics-3.php");
				break;
			case 3:
			default:
				include_once("include/languages/".$_lang_code."/tactics-0.php");
				break;
		}
		echo '<hr>';
		echo get_label('Photos are kindly provided by our partner in Moscow - <a href="http://www.mafiapro.ru" target="_blank">Mafia Pro Club</a>.');
	}

	protected function show_filter_fields()
	{
		echo '<form name="roleForm" method="get"><select name="role" onchange="document.roleForm.submit()">';
		show_option(0, $this->role, get_label('How a Sheriff should play'));
		show_option(1, $this->role, get_label('How a civilian should play'));
		show_option(2, $this->role, get_label('How a mafiosi should play'));
		show_option(3, $this->role, get_label('How a don should play'));
		echo '</select></form>';
	}
}

$page = new Page();
$page->set_ccc(CCCS_NO);
$page->run(get_label('Game tactics'));

?>